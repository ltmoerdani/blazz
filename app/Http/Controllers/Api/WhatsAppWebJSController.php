<?php

namespace App\Http\Controllers\Api;

use App\Events\WhatsAppQRGeneratedEvent;
use App\Events\WhatsAppSessionStatusChangedEvent;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppSession;
use App\Services\ContactProvisioningService; // NEW: For contact creation
use App\Services\ProviderSelector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WhatsAppWebJSController extends Controller
{
    /**
     * Handle webhook from Node.js service
     */
    public function webhook(Request $request)
    {
        // Validate HMAC signature for security
        $this->validateHmacSignature($request);

        $event = $request->input('event');
        $data = $request->input('data');

        Log::info('WhatsApp WebJS webhook received', [
            'event' => $event,
            'workspace_id' => $data['workspace_id'] ?? null,
            'session_id' => $data['session_id'] ?? null,
        ]);

        switch ($event) {
            case 'qr_code_generated':
                $this->handleQRCodeGenerated($data);
                break;

            case 'session_authenticated':
                $this->handleSessionAuthenticated($data);
                break;

            case 'session_ready':
                $this->handleSessionReady($data);
                break;

            case 'session_disconnected':
                $this->handleSessionDisconnected($data);
                break;

            case 'message_received':
                $this->handleMessageReceived($data);
                break;

            default:
                Log::warning('Unknown WhatsApp WebJS event', ['event' => $event]);
                break;
        }

        return response()->json(['status' => 'received']);
    }

    /**
     * Handle QR code generated event
     */
    private function handleQRCodeGenerated(array $data): void
    {
        $workspaceId = $data['workspace_id'];
        $sessionId = $data['session_id'];
        $qrCode = $data['qr_code'];

        // Update session in database
        $session = WhatsAppSession::where('session_id', $sessionId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($session) {
            $session->update([
                'status' => 'qr_scanning',
                'qr_code' => $qrCode,
                'last_activity_at' => now(),
            ]);

            // Broadcast QR code to frontend
            Log::info('Broadcasting WhatsAppQRGeneratedEvent', [
                'workspace_id' => $workspaceId,
                'session_id' => $sessionId,
                'qr_code_length' => strlen($qrCode)
            ]);
            broadcast(new WhatsAppQRGeneratedEvent($qrCode, 300, $workspaceId, $sessionId));
            Log::info('WhatsAppQRGeneratedEvent broadcasted');
        }
    }

    /**
     * Handle session authenticated event
     */
    private function handleSessionAuthenticated(array $data): void
    {
        $workspaceId = $data['workspace_id'];
        $sessionId = $data['session_id'];

        $session = WhatsAppSession::where('session_id', $sessionId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($session) {
            $session->update([
                'status' => 'authenticated',
                'last_activity_at' => now(),
            ]);

            // Broadcast status change
            broadcast(new WhatsAppSessionStatusChangedEvent(
                $sessionId,
                'authenticated',
                $workspaceId,
                null,
                [
                    'uuid' => $session->uuid,
                    'timestamp' => now()->toISOString()
                ]
            ));
        }
    }

    /**
     * Handle session ready event
     */
    private function handleSessionReady(array $data): void
    {
        $workspaceId = $data['workspace_id'];
        $sessionId = $data['session_id'];
        $phoneNumber = $data['phone_number'] ?? null;

        $session = WhatsAppSession::where('session_id', $sessionId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($session) {
            $session->update([
                'status' => 'connected',
                'phone_number' => $phoneNumber,
                'last_connected_at' => now(),
                'last_activity_at' => now(),
            ]);

            // Broadcast status change
            broadcast(new WhatsAppSessionStatusChangedEvent(
                $sessionId,
                'connected',
                $workspaceId,
                $phoneNumber,
                [
                    'uuid' => $session->uuid,
                    'timestamp' => now()->toISOString()
                ]
            ));
        }
    }

    /**
     * Handle session disconnected event
     */
    private function handleSessionDisconnected(array $data): void
    {
        $workspaceId = $data['workspace_id'];
        $sessionId = $data['session_id'];
        $reason = $data['reason'] ?? 'unknown';

        $session = WhatsAppSession::where('session_id', $sessionId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($session) {
            $session->update([
                'status' => 'disconnected',
                'last_activity_at' => now(),
                'metadata' => array_merge($session->metadata ?? [], [
                    'last_disconnect_reason' => $reason,
                    'disconnect_timestamp' => now()->toISOString(),
                ])
            ]);

            // Broadcast status change
            broadcast(new WhatsAppSessionStatusChangedEvent(
                $sessionId,
                'disconnected',
                $workspaceId,
                $session->phone_number,
                [
                    'uuid' => $session->uuid,
                    'reason' => $reason,
                    'timestamp' => now()->toISOString()
                ]
            ));
        }
    }

    /**
     * Handle message received event
     * IMPLEMENTED: Week 1-3 Integration (BUGFIX)
     */
    private function handleMessageReceived(array $data): void
    {
        try {
            $workspaceId = $data['workspace_id'];
            $sessionId = $data['session_id'];
            $message = $data['message'];

            Log::info('WhatsApp message received via WebJS', [
                'workspace_id' => $workspaceId,
                'session_id' => $sessionId,
                'message_id' => $message['id'] ?? null,
                'from' => $message['from'] ?? null,
            ]);

            // Skip status updates (status@broadcast messages)
            if (isset($message['from']) && strpos($message['from'], 'status@broadcast') !== false) {
                Log::debug('Skipping WhatsApp status update message');
                return;
            }

            // Get session from database
            $session = WhatsAppSession::where('session_id', $sessionId)
                ->where('workspace_id', $workspaceId)
                ->first();

            Log::debug('🔍 DEBUG: Session lookup result', [
                'session_found' => $session ? 'YES' : 'NO',
                'session_id' => $sessionId,
                'workspace_id' => $workspaceId
            ]);

            if (!$session) {
                Log::error('WhatsApp session not found', [
                    'session_id' => $sessionId,
                    'workspace_id' => $workspaceId
                ]);
                return;
            }

            // Extract phone number from WhatsApp ID (format: 6282146291472@c.us)
            $from = $message['from'];
            $phoneNumber = str_replace(['@c.us', '@g.us'], '', $from);

            // Determine if this is a group or private chat
            $isGroup = strpos($from, '@g.us') !== false;
            $chatType = $isGroup ? 'group' : 'private';

            Log::debug('Processing WhatsApp message', [
                'phone_number' => $phoneNumber,
                'chat_type' => $chatType,
                'is_group' => $isGroup,
                'message_type' => $message['type'] ?? 'unknown',
                'has_body' => isset($message['body']),
                'body_preview' => isset($message['body']) ? substr($message['body'], 0, 50) : 'N/A'
            ]);

            // Use ProviderSelector to get appropriate provider
            Log::debug('🔍 DEBUG: About to select provider', [
                'workspace_id' => $workspaceId,
                'preferred_provider' => 'webjs'
            ]);

            $providerSelector = new ProviderSelector();
            $provider = $providerSelector->selectProvider($workspaceId, 'webjs');

            Log::debug('🔍 DEBUG: Provider selected', [
                'provider_type' => $provider ? get_class($provider) : 'NULL'
            ]);

            // Provision contact using ContactProvisioningService
            $provisioningService = new ContactProvisioningService();

            // Get contact name: group messages have sender_name, private chats use phone number
            $contactName = $isGroup
                ? ($message['sender_name'] ?? $phoneNumber)
                : ($message['notifyName'] ?? $phoneNumber);

            Log::debug('🔍 DEBUG: About to provision contact', [
                'phone_number' => $phoneNumber,
                'contact_name' => $contactName,
                'workspace_id' => $workspaceId,
                'session_id' => $session->id
            ]);

            $contact = $provisioningService->getOrCreateContact(
                $phoneNumber,
                $contactName,
                $workspaceId,
                'webjs',
                $session->id
            );

            Log::debug('🔍 DEBUG: Contact provision result', [
                'contact_created' => $contact ? 'YES' : 'NO',
                'contact_id' => $contact->id ?? null,
                'contact_phone' => $contact->phone ?? null
            ]);

            if (!$contact) {
                Log::error('Failed to provision contact', [
                    'phone_number' => $phoneNumber,
                    'workspace_id' => $workspaceId
                ]);
                return;
            }

            Log::info('Contact provisioned successfully', [
                'contact_id' => $contact->id,
                'phone' => $contact->phone,
                'session_id' => $session->id
            ]);

            // Process the message using provider's processIncomingWebhook
            // This will create chat entry, handle media, trigger events, etc.

            // Map WhatsApp Web.js message type to Meta API type
            $messageType = $message['type'] ?? 'chat';
            $metaApiType = ($messageType === 'chat') ? 'text' : $messageType;

            $webhookData = [
                'object' => 'whatsapp_business_account',
                'entry' => [
                    [
                        'changes' => [
                            [
                                'value' => [
                                    'messaging_product' => 'whatsapp',
                                    'metadata' => [
                                        'display_phone_number' => $session->phone_number,
                                        'phone_number_id' => 'webjs_' . $session->id,
                                    ],
                                    'messages' => [
                                        [
                                            'id' => $message['id'],
                                            'from' => $phoneNumber,
                                            'timestamp' => $message['timestamp'] ?? time(),
                                            'type' => $metaApiType,
                                            'text' => isset($message['body']) ? [
                                                'body' => $message['body']
                                            ] : null,
                                            // Add other message types as needed
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            Log::debug('Converted to Meta API format', [
                'original_type' => $messageType,
                'meta_api_type' => $metaApiType,
                'phone_number' => $phoneNumber
            ]);

            // Convert to Meta API format and process
            $request = new Request();
            $request->merge($webhookData);

            Log::debug('🔍 DEBUG: About to create chat and message', [
                'contact_id' => $contact->id,
                'phone' => $contact->phone
            ]);

            // Check if chat already exists for this message
            $chat = \App\Models\Chat::where('wam_id', $message['id'])
                ->where('workspace_id', $workspaceId)
                ->first();

            if (!$chat) {
                // First handle ticket assignment (creates ticket if needed)
                (new \App\Services\ChatService($workspaceId))->handleTicketAssignment($contact->id);

                // Create new chat record (same pattern as Meta API webhook)
                $chat = new \App\Models\Chat();
                $chat->workspace_id = $workspaceId;
                $chat->wam_id = $message['id'];
                $chat->contact_id = $contact->id;
                $chat->type = 'inbound';
                $chat->status = 'delivered';
                $chat->metadata = json_encode($message);
                $chat->provider_type = 'webjs';
                $chat->chat_type = $isGroup ? 'group' : 'private';
                $chat->save();

                Log::debug('🔍 DEBUG: Chat created', [
                    'chat_id' => $chat->id,
                    'wam_id' => $chat->wam_id
                ]);

                // Reload chat with relationships
                $chat = \App\Models\Chat::with('contact', 'media')->where('id', $chat->id)->first();

                // Create chat log entry
                $chatLog = new \App\Models\ChatLog();
                $chatLog->contact_id = $contact->id;
                $chatLog->entity_type = 'chat';
                $chatLog->entity_id = $chat->id;
                $chatLog->created_at = now();
                $chatLog->save();
                $chatLogId = $chatLog->id;

                // Prepare chat array for event (same format as Meta API)
                $chatLog = \App\Models\ChatLog::where('id', $chatLogId)
                    ->where('deleted_at', null)
                    ->first();

                $chatArray = array([
                    'type' => 'chat',
                    'value' => $chatLog->relatedEntities
                ]);

                // Broadcast event for real-time UI update
                event(new \App\Events\NewChatEvent($chatArray, $workspaceId));

                Log::info('✅ WhatsApp WebJS message processed successfully', [
                    'contact_id' => $contact->id,
                    'chat_id' => $chat->id,
                    'message_id' => $message['id'],
                    'provider_type' => 'webjs'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error processing WhatsApp WebJS message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data
            ]);
        }
    }

    /**
     * Validate HMAC signature for webhook security
     */
    private function validateHmacSignature(Request $request): void
    {
        $signature = $request->header('X-HMAC-Signature');
        $timestamp = $request->header('X-Timestamp');
        $payload = $request->getContent();

        if (!$signature || !$timestamp) {
            Log::warning('WhatsApp WebJS webhook missing security headers');
            abort(401, 'Missing security headers');
        }

        // Validate timestamp (prevent replay attacks)
        $now = time();
        $requestTime = (int) $timestamp;
        if (abs($now - $requestTime) > 300) { // 5 minutes
            Log::warning('WhatsApp WebJS webhook timestamp expired');
            abort(401, 'Request expired');
        }

        // Verify HMAC signature
        $expectedSignature = hash_hmac('sha256', $timestamp . $payload, config('whatsapp.node_api_secret'));

        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('WhatsApp WebJS webhook invalid signature');
            abort(401, 'Invalid signature');
        }
    }

    /**
     * Get session status for Node.js service
     */
    public function getSessionStatus(Request $request, string $sessionId)
    {
        $workspaceId = $request->input('workspace_id');

        $session = WhatsAppSession::where('session_id', $sessionId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if (!$session) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        return response()->json([
            'session_id' => $session->session_id,
            'status' => $session->status,
            'phone_number' => $session->phone_number,
            'is_active' => $session->is_active,
            'last_activity_at' => $session->last_activity_at,
            'health_score' => $session->health_score,
        ]);
    }

    /**
     * Get all active sessions for restoration
     * Called by Node.js service on startup
     */
    public function getActiveSessions(Request $request)
    {
        try {
            // Get all sessions that should be active (connected or authenticated)
            $sessions = WhatsAppSession::whereIn('status', ['connected', 'authenticated'])
                ->where('is_active', true)
                ->select('id', 'session_id', 'workspace_id', 'phone_number', 'status', 'provider_type')
                ->get()
                ->map(function ($session) {
                    return [
                        'id' => $session->id,
                        'session_id' => $session->session_id,
                        'workspace_id' => $session->workspace_id,
                        'phone_number' => $session->phone_number,
                        'status' => $session->status,
                        'provider_type' => $session->provider_type
                    ];
                });

            Log::info('Active sessions requested by Node.js', [
                'count' => $sessions->count()
            ]);

            return response()->json([
                'success' => true,
                'sessions' => $sessions,
                'count' => $sessions->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get active sessions', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark session as disconnected
     * Called by Node.js when session restoration fails
     */
    public function markDisconnected(Request $request, $sessionId)
    {
        try {
            $workspaceId = $request->input('workspace_id');
            $reason = $request->input('reason', 'Unknown');

            $session = WhatsAppSession::where('session_id', $sessionId)
                ->where('workspace_id', $workspaceId)
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'error' => 'Session not found'
                ], 404);
            }

            $session->update([
                'status' => 'disconnected',
                'last_activity_at' => now(),
                'metadata' => array_merge($session->metadata ?? [], [
                    'last_disconnect_reason' => $reason,
                    'last_disconnect_at' => now()->toISOString()
                ])
            ]);

            Log::info('Session marked as disconnected', [
                'session_id' => $sessionId,
                'workspace_id' => $workspaceId,
                'reason' => $reason
            ]);

            // Broadcast status change
            broadcast(new WhatsAppSessionStatusChangedEvent(
                $sessionId,
                'disconnected',
                $workspaceId,
                $session->phone_number,
                [
                    'reason' => $reason,
                    'timestamp' => now()->toISOString()
                ]
            ));

            return response()->json([
                'success' => true,
                'message' => 'Session marked as disconnected'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to mark session as disconnected', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
