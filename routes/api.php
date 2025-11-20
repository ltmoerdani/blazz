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
    Route::post('/webhooks/webjs', [App\Http\Controllers\Api\v1\WhatsApp\WebhookController::class, 'webhook']);

    // Account management for Node.js service (HMAC secured)
    Route::get('/accounts/{accountId}/status', [App\Http\Controllers\Api\v1\WhatsApp\AccountController::class, 'getAccountStatus']);

    // Account Restoration Endpoints (for auto-reconnect feature)
    Route::get('/accounts/active', [App\Http\Controllers\Api\v1\WhatsApp\AccountController::class, 'getActiveAccounts']);
    Route::post('/accounts/{accountId}/mark-disconnected', [App\Http\Controllers\Api\v1\WhatsApp\AccountController::class, 'markDisconnected']);

    // Chat Sync Endpoints (HMAC secured + rate limited)
    Route::post('/chats/sync', [App\Http\Controllers\API\WhatsAppSyncController::class, 'syncBatch'])
        ->middleware('whatsapp.throttle');
    Route::get('/accounts/{accountId}/sync-status', [App\Http\Controllers\API\WhatsAppSyncController::class, 'getSyncStatus']);

    // Session Cleanup Endpoints (Week 2 Optional) - HMAC secured
    Route::get('/accounts-for-cleanup', [App\Http\Controllers\Api\WhatsAppCleanupController::class, 'getAccountsForCleanup']);
    Route::get('/accounts/by-session/{sessionId}', [App\Http\Controllers\Api\WhatsAppCleanupController::class, 'getAccountBySession']);
    Route::patch('/accounts/{id}/status', [App\Http\Controllers\Api\WhatsAppCleanupController::class, 'updateAccountStatus']);
    Route::post('/cleanup-logs', [App\Http\Controllers\Api\WhatsAppCleanupController::class, 'logCleanup']);
    Route::get('/cleanup-stats', [App\Http\Controllers\Api\WhatsAppCleanupController::class, 'getCleanupStats']);

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

            case 'account-status-changed':
                broadcast(new \App\Events\WhatsAppAccountStatusChangedEvent(
                    $data['account_id'],
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
    // WhatsApp messaging routes
    Route::post('/send', [App\Http\Controllers\Api\v1\WhatsAppApiController::class, 'sendMessage']);
    Route::post('/send/template', [App\Http\Controllers\Api\v1\TemplateApiController::class, 'sendTemplateMessage']);
    Route::post('/send/media', [App\Http\Controllers\Api\v1\WhatsAppApiController::class, 'sendMediaMessage']);
    Route::post('/campaigns', [App\Http\Controllers\Api\v1\CampaignApiController::class, 'storeCampaign']);

    // Contact management routes
    Route::get('/contacts', [App\Http\Controllers\Api\v1\ContactApiController::class, 'listContacts']);
    Route::post('/contacts', [App\Http\Controllers\Api\v1\ContactApiController::class, 'storeContact']);
    Route::put('/contacts/{uuid}', [App\Http\Controllers\Api\v1\ContactApiController::class, 'storeContact']);
    Route::delete('/contacts/{uuid}', [App\Http\Controllers\Api\v1\ContactApiController::class, 'destroyContact']);

    Route::get('/contact-groups', [App\Http\Controllers\Api\v1\ContactGroupApiController::class, 'listContactGroups']);
    Route::post('/contact-groups', [App\Http\Controllers\Api\v1\ContactGroupApiController::class, 'storeContactGroup']);
    Route::put('/contact-groups/{uuid}', [App\Http\Controllers\Api\v1\ContactGroupApiController::class, 'storeContactGroup']);
    Route::delete('/contact-groups/{uuid}', [App\Http\Controllers\Api\v1\ContactGroupApiController::class, 'destroyContactGroup']);

    // Contact presence management routes (real-time features)
    Route::prefix('contacts')->group(function () {
        Route::get('/{contactId}/presence', [App\Http\Controllers\Api\v1\ContactPresenceController::class, 'getPresence']);
        Route::put('/{contactId}/typing-status', [App\Http\Controllers\Api\v1\ContactPresenceController::class, 'updateTypingStatus']);
        Route::put('/{contactId}/online-status', [App\Http\Controllers\Api\v1\ContactPresenceController::class, 'updateOnlineStatus']);
        Route::put('/{contactId}/last-message', [App\Http\Controllers\Api\v1\ContactPresenceController::class, 'updateLastMessageTime']);
        Route::get('/workspace/presence', [App\Http\Controllers\Api\v1\ContactPresenceController::class, 'getWorkspaceContactsPresence']);
        Route::post('/workspace/typing', [App\Http\Controllers\Api\v1\ContactPresenceController::class, 'getTypingContacts']);
        Route::post('/bulk/presence', [App\Http\Controllers\Api\v1\ContactPresenceController::class, 'bulkUpdatePresence']);
        Route::post('/cleanup/offline', [App\Http\Controllers\Api\v1\ContactPresenceController::class, 'cleanupOfflineContacts']);
    });

    Route::get('/canned-replies', [App\Http\Controllers\Api\v1\CannedReplyApiController::class, 'listCannedReplies']);
    Route::post('/canned-replies', [App\Http\Controllers\Api\v1\CannedReplyApiController::class, 'storeCannedReply']);
    Route::put('/canned-replies/{uuid}', [App\Http\Controllers\Api\v1\CannedReplyApiController::class, 'storeCannedReply']);
    Route::delete('/canned-replies/{uuid}', [App\Http\Controllers\Api\v1\CannedReplyApiController::class, 'destroyCannedReply']);

    Route::get('/templates', [App\Http\Controllers\Api\v1\TemplateApiController::class, 'listTemplates']);

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

        // WhatsApp Account Management Routes (NEW)
        Route::prefix('whatsapp/accounts')->group(function () {
            Route::get('/{accountId}/status', [App\Http\Controllers\Api\v1\WhatsApp\AccountController::class, 'getAccountStatus']);
            Route::get('/active', [App\Http\Controllers\Api\v1\WhatsApp\AccountController::class, 'getActiveAccounts']);
            Route::post('/{accountId}/mark-disconnected', [App\Http\Controllers\Api\v1\WhatsApp\AccountController::class, 'markDisconnected']);
        });

        // WhatsApp Webhook Routes (NEW)
        Route::prefix('whatsapp')->middleware(['whatsapp.hmac'])->group(function () {
            Route::post('/webhooks/v2', [App\Http\Controllers\Api\v1\WhatsApp\WebhookController::class, 'webhook']);
        });
    });
});
