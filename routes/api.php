<?php

use App\Http\Middleware\AuthenticateBearerToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/translations/{locale}', function ($locale) {
    if (Str::startsWith($locale, 'php_')) {
        return response()->json(['error' => 'Invalid locale'], 400);
    }

    $path = base_path("lang/{$locale}.json");
    
    if (!File::exists($path)) {
        return response()->json(['error' => 'Translation file not found'], 404);
    }

    return response()->json(json_decode(File::get($path), true));
});

// WhatsApp WebJS Integration Routes (HMAC secured, no Bearer Token needed)
Route::prefix('whatsapp')->middleware(['whatsapp.hmac'])->group(function () {
    // Webhook for Node.js service callbacks (HMAC secured)
    Route::post('/webhooks/webjs', [App\Http\Controllers\Api\WhatsAppWebJSController::class, 'webhook']);

    // Session management for Node.js service (HMAC secured)
    Route::get('/sessions/{sessionId}/status', [App\Http\Controllers\Api\WhatsAppWebJSController::class, 'getSessionStatus']);

    // Chat Sync Endpoints (HMAC secured + rate limited)
    Route::post('/chats/sync', [App\Http\Controllers\API\WhatsAppSyncController::class, 'syncBatch'])
        ->middleware('whatsapp.throttle');
    Route::get('/sessions/{sessionId}/sync-status', [App\Http\Controllers\API\WhatsAppSyncController::class, 'getSyncStatus']);

    // Broadcasting events (for testing) - requires HMAC
    Route::post('/broadcast', function (Request $request) {
        $event = $request->input('event');
        $data = $request->input('data');

        switch ($event) {
            case 'qr-code-generated':
                broadcast(new \App\Events\WhatsAppQRGeneratedEvent(
                    $data['qr_code'],
                    $data['expires_in'] ?? 300,
                    $data['workspace_id'],
                    $data['session_id']
                ));
                break;

            case 'session-status-changed':
                broadcast(new \App\Events\WhatsAppSessionStatusChangedEvent(
                    $data['session_id'],
                    $data['status'],
                    $data['workspace_id'],
                    $data['phone_number'] ?? null,
                    $data['metadata'] ?? []
                ));
                break;

            default:
                return response()->json(['error' => 'Unknown event'], 400);
        }

        return response()->json(['status' => 'broadcasted']);
    });
});

Route::middleware([AuthenticateBearerToken::class])->group(function () {
    Route::post('/send', [App\Http\Controllers\ApiController::class, 'sendMessage']);
    Route::post('/send/template', [App\Http\Controllers\ApiController::class, 'sendTemplateMessage']);
    Route::post('/send/media', [App\Http\Controllers\ApiController::class, 'sendMediaMessage']);
    Route::post('/campaigns', [App\Http\Controllers\ApiController::class, 'storeCampaign']);
    
    Route::get('/contacts', [App\Http\Controllers\ApiController::class, 'listContacts']);
    Route::post('/contacts', [App\Http\Controllers\ApiController::class, 'storeContact']);
    Route::put('/contacts/{uuid}', [App\Http\Controllers\ApiController::class, 'storeContact']);
    Route::delete('/contacts/{uuid}', [App\Http\Controllers\ApiController::class, 'destroyContact']);

    Route::get('/contact-groups', [App\Http\Controllers\ApiController::class, 'listContactGroups']);
    Route::post('/contact-groups', [App\Http\Controllers\ApiController::class, 'storeContactGroup']);
    Route::put('/contact-groups/{uuid}', [App\Http\Controllers\ApiController::class, 'storeContactGroup']);
    Route::delete('/contact-groups/{uuid}', [App\Http\Controllers\ApiController::class, 'destroyContactGroup']);

    Route::get('/canned-replies', [App\Http\Controllers\ApiController::class, 'listCannedReplies']);
    Route::post('/canned-replies', [App\Http\Controllers\ApiController::class, 'storeCannedReply']);
    Route::put('/canned-replies/{uuid}', [App\Http\Controllers\ApiController::class, 'storeCannedReply']);
    Route::delete('/canned-replies/{uuid}', [App\Http\Controllers\ApiController::class, 'destroyCannedReply']);

    Route::get('/templates', [App\Http\Controllers\ApiController::class, 'listTemplates']);
});
