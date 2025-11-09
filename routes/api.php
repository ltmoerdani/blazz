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

    // Session Restoration Endpoints (for auto-reconnect feature)
    Route::get('/sessions/active', [App\Http\Controllers\Api\WhatsAppWebJSController::class, 'getActiveSessions']);
    Route::post('/sessions/{sessionId}/mark-disconnected', [App\Http\Controllers\Api\WhatsAppWebJSController::class, 'markDisconnected']);

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
    Route::post('/send', [App\Http\Controllers\Api\v1\ApiController::class, 'sendMessage']);
    Route::post('/send/template', [App\Http\Controllers\Api\v1\ApiController::class, 'sendTemplateMessage']);
    Route::post('/send/media', [App\Http\Controllers\Api\v1\ApiController::class, 'sendMediaMessage']);
    Route::post('/campaigns', [App\Http\Controllers\Api\v1\ApiController::class, 'storeCampaign']);
    
    Route::get('/contacts', [App\Http\Controllers\Api\v1\ApiController::class, 'listContacts']);
    Route::post('/contacts', [App\Http\Controllers\Api\v1\ApiController::class, 'storeContact']);
    Route::put('/contacts/{uuid}', [App\Http\Controllers\Api\v1\ApiController::class, 'storeContact']);
    Route::delete('/contacts/{uuid}', [App\Http\Controllers\Api\v1\ApiController::class, 'destroyContact']);

    Route::get('/contact-groups', [App\Http\Controllers\Api\v1\ApiController::class, 'listContactGroups']);
    Route::post('/contact-groups', [App\Http\Controllers\Api\v1\ApiController::class, 'storeContactGroup']);
    Route::put('/contact-groups/{uuid}', [App\Http\Controllers\Api\v1\ApiController::class, 'storeContactGroup']);
    Route::delete('/contact-groups/{uuid}', [App\Http\Controllers\Api\v1\ApiController::class, 'destroyContactGroup']);

    Route::get('/canned-replies', [App\Http\Controllers\Api\v1\ApiController::class, 'listCannedReplies']);
    Route::post('/canned-replies', [App\Http\Controllers\Api\v1\ApiController::class, 'storeCannedReply']);
    Route::put('/canned-replies/{uuid}', [App\Http\Controllers\Api\v1\ApiController::class, 'storeCannedReply']);
    Route::delete('/canned-replies/{uuid}', [App\Http\Controllers\Api\v1\ApiController::class, 'destroyCannedReply']);

    Route::get('/templates', [App\Http\Controllers\Api\v1\ApiController::class, 'listTemplates']);

    // NEW: Specialized API Controllers (Week 3 Implementation)
    Route::prefix('v2')->group(function () {
        // WhatsApp API Routes
        Route::prefix('whatsapp')->group(function () {
            Route::post('/send', [App\Http\Controllers\Api\v1\WhatsAppApiController::class, 'sendMessage']);
            Route::post('/send/template', [App\Http\Controllers\Api\v1\TemplateApiController::class, 'sendTemplateMessage']);
            Route::post('/send/media', [App\Http\Controllers\Api\v1\WhatsAppApiController::class, 'sendMediaMessage']);
            Route::get('/templates', [App\Http\Controllers\Api\v1\TemplateApiController::class, 'listTemplates']);
        });

        // Contact API Routes
        Route::prefix('contacts')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\v1\ContactApiController::class, 'listContacts']);
            Route::post('/', [App\Http\Controllers\Api\v1\ContactApiController::class, 'storeContact']);
            Route::put('/{uuid}', [App\Http\Controllers\Api\v1\ContactApiController::class, 'storeContact']);
            Route::delete('/{uuid}', [App\Http\Controllers\Api\v1\ContactApiController::class, 'destroyContact']);
        });

        // WhatsApp Session Management Routes (NEW)
        Route::prefix('whatsapp/sessions')->group(function () {
            Route::get('/{sessionId}/status', [App\Http\Controllers\Api\v1\WhatsApp\SessionController::class, 'getSessionStatus']);
            Route::get('/active', [App\Http\Controllers\Api\v1\WhatsApp\SessionController::class, 'getActiveSessions']);
            Route::post('/{sessionId}/mark-disconnected', [App\Http\Controllers\Api\v1\WhatsApp\SessionController::class, 'markDisconnected']);
        });

        // WhatsApp Webhook Routes (NEW)
        Route::prefix('whatsapp')->middleware(['whatsapp.hmac'])->group(function () {
            Route::post('/webhooks/v2', [App\Http\Controllers\Api\v1\WhatsApp\WebhookController::class, 'webhook']);
        });
    });
});
