<?php

namespace App\Http\Controllers\Api\v1\WhatsApp;

use App\Events\WhatsAppQRGeneratedEvent;
use App\Events\WhatsAppAccountStatusChangedEvent;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppAccount;
use App\Services\ContactProvisioningService;
use App\Services\MediaService;
use App\Services\ProviderSelector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
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

            case 'message_sent':
                $this->handleMessageSent($data);
                break;

            case 'message_status_updated':
                $this->handleMessageStatusUpdated($data);
                break;

            case 'message_delivered':
                $this->handleMessageDelivered($data);
                break;

            case 'message_read':
                $this->handleMessageRead($data);
                break;

            case 'typing_indicator':
                $this->handleTypingIndicator($data);
                break;

            case 'chat_state_updated':
                $this->handleChatStateUpdated($data);
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
        $session = WhatsAppAccount::where('session_id', $sessionId)
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

        $session = WhatsAppAccount::where('session_id', $sessionId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($session) {
            $session->update([
                'status' => 'authenticated',
                'last_activity_at' => now(),
            ]);

            // Broadcast status change
            broadcast(new WhatsAppAccountStatusChangedEvent(
                $sessionId,
                'authenticated',
                $workspaceId,
                $session->phone_number,
                [
                    'id' => $session->id,
                    'uuid' => $session->uuid,
                    'phone_number' => $session->phone_number,
                    'formatted_phone_number' => $session->formatted_phone_number,
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

        $session = WhatsAppAccount::where('session_id', $sessionId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($session) {
            $session->update([
                'status' => 'connected',
                'phone_number' => $phoneNumber,
                'last_connected_at' => now(),
                'last_activity_at' => now(),
            ]);

            // Refresh model to get updated accessor values
            $session->refresh();

            // Broadcast status change with complete data
            broadcast(new WhatsAppAccountStatusChangedEvent(
                $sessionId,
                'connected',
                $workspaceId,
                $phoneNumber,
                [
                    'id' => $session->id,
                    'uuid' => $session->uuid,
                    'phone_number' => $phoneNumber,
                    'formatted_phone_number' => $session->formatted_phone_number,
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

        $session = WhatsAppAccount::where('session_id', $sessionId)
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
            broadcast(new WhatsAppAccountStatusChangedEvent(
                $sessionId,
                'disconnected',
                $workspaceId,
                $session->phone_number,
                [
                    'id' => $session->id,
                    'uuid' => $session->uuid,
                    'phone_number' => $session->phone_number,
                    'formatted_phone_number' => $session->formatted_phone_number,
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
            $session = WhatsAppAccount::where('session_id', $sessionId)
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
            ]);

            if (!$contact) {
                Log::error('Failed to create or find contact', [
                    'phone_number' => $phoneNumber,
                    'workspace_id' => $workspaceId
                ]);
                return;
            }

            // Create chat record for received message
            $chat = \App\Models\Chat::create([
                'workspace_id' => $workspaceId,
                'contact_id' => $contact->id,
                'whatsapp_account_id' => $session->id,
                'whatsapp_message_id' => $message['id'] ?? null,
                'wam_id' => $message['id'] ?? null,
                'type' => 'inbound',
                'status' => 'received',
                'message_status' => 'delivered',
                'ack_level' => 2, // Delivered
                'provider_type' => 'webjs',
                'chat_type' => $isGroup ? 'group' : 'private',
                'is_read' => false,
                'created_at' => now(),
                'delivered_at' => now(),
                'metadata' => json_encode([
                    'body' => $message['body'] ?? '',
                    'type' => $message['type'] ?? 'text',
                    'has_media' => isset($message['has_media']) ? $message['has_media'] : false,
                    'media_type' => $message['type'] ?? 'text',
                    'from' => $message['from'] ?? null,
                    'to' => $message['to'] ?? null,
                    'timestamp' => $message['timestamp'] ?? null,
                ]),
            ]);

            // Create ChatLog entry for UI display
            \App\Models\ChatLog::create([
                'contact_id' => $contact->id,
                'entity_type' => 'chat',
                'entity_id' => $chat->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update contact's latest_chat_created_at and last_message_at for frontend chat list
            $contact->update([
                'latest_chat_created_at' => now(),
                'last_message_at' => now(),
                'last_activity' => now(),
                'unread_messages' => DB::raw('unread_messages + 1'),
            ]);

            // Log message received successfully
            Log::info('WhatsApp message processed successfully', [
                'contact_id' => $contact->id,
                'message_id' => $message['id'] ?? null,
                'message_type' => $message['type'] ?? 'unknown',
                'workspace_id' => $workspaceId,
                'session_id' => $sessionId,
                'chat_saved' => true
            ]);

            // ✅ REALTIME FIX: Broadcast NewChatEvent to frontend for instant message display
            $chatData = [[
                'type' => 'chat',
                'value' => [
                    'id' => $chat->id,
                    'wam_id' => $chat->wam_id,
                    'message' => $message['body'] ?? '',
                    'type' => 'inbound',
                    'message_status' => 'delivered',
                    'created_at' => $chat->created_at->toISOString(),
                    'from_me' => false,
                    'metadata' => $chat->metadata,
                    'contact_id' => $contact->id,
                    'whatsapp_message_id' => $chat->whatsapp_message_id,
                ]
            ]];

            // Broadcast to workspace channel for chat list updates
            event(new \App\Events\NewChatEvent($chatData, $workspaceId));

            Log::info('✅ NewChatEvent broadcasted to workspace', [
                'workspace_id' => $workspaceId,
                'contact_id' => $contact->id,
                'chat_id' => $chat->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Error handling WhatsApp message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data_keys' => array_keys($data)
            ]);
        }
    }

    /**
     * Handle message sent event (for real-time UI updates)
     */
    private function handleMessageSent(array $data): void
    {
        try {
            $workspaceId = $data['workspace_id'];
            $sessionId = $data['session_id'];
            $messageData = $data['message'];

            Log::info('Message sent via WebJS', [
                'workspace_id' => $workspaceId,
                'session_id' => $sessionId,
                'message_id' => $messageData['id'] ?? null,
                'to' => $messageData['to'] ?? null,
            ]);

            // Find or create the chat entry in database with WhatsApp message ID
            $session = WhatsAppAccount::where('session_id', $sessionId)
                ->where('workspace_id', $workspaceId)
                ->first();

            if (!$session) {
                Log::error('Session not found for message sent', [
                    'session_id' => $sessionId,
                    'workspace_id' => $workspaceId
                ]);
                return;
            }

            // Extract phone number and find/create contact
            $to = $messageData['to'];
            $phoneNumber = str_replace(['@c.us', '@g.us'], '', $to);

            $provisioningService = new ContactProvisioningService();
            $contact = $provisioningService->getOrCreateContact(
                $phoneNumber,
                $phoneNumber, // Use phone number as name for sent messages
                $workspaceId,
                'webjs',
                $session->id
            );

            if ($contact) {
                // Check if chat already exists (to prevent duplicates from MessageService)
                $existingChat = \App\Models\Chat::where('whatsapp_message_id', $messageData['id'])
                    ->orWhere('wam_id', $messageData['id'])
                    ->first();

                if ($existingChat) {
                    Log::info('Chat record already exists, skipping duplicate creation', [
                        'contact_id' => $contact->id,
                        'message_id' => $messageData['id'],
                        'existing_chat_id' => $existingChat->id,
                    ]);
                } else {
                    // Create chat record with real-time messaging fields
                    $chat = \App\Models\Chat::create([
                        'workspace_id' => $workspaceId,
                        'contact_id' => $contact->id,
                        'whatsapp_account_id' => $session->id,
                        'whatsapp_message_id' => $messageData['id'],
                        'wam_id' => $messageData['id'], // Keep for compatibility
                        'type' => 'outbound',
                        'status' => 'sent',
                        'message_status' => 'pending', // Real-time status
                        'ack_level' => 1, // Pending status
                        'metadata' => json_encode([
                            'body' => $messageData['body'] ?? '',
                            'type' => $messageData['type'] ?? 'text',
                            'has_media' => $messageData['has_media'] ?? false,
                            'from_me' => true,
                        ]),
                        'created_at' => date('Y-m-d H:i:s', $messageData['timestamp'] ?? time()),
                    ]);

                    // Create ChatLog entry for UI display
                    \App\Models\ChatLog::create([
                        'contact_id' => $contact->id,
                        'entity_type' => 'chat',
                        'entity_id' => $chat->id,
                        'created_at' => date('Y-m-d H:i:s', $messageData['timestamp'] ?? time()),
                        'updated_at' => date('Y-m-d H:i:s', $messageData['timestamp'] ?? time()),
                    ]);

                    Log::info('Chat record created for sent message', [
                        'contact_id' => $contact->id,
                        'message_id' => $messageData['id'],
                    ]);

                    // ✅ REALTIME FIX: Broadcast NewChatEvent for outbound messages too
                    $chatData = [[
                        'type' => 'chat',
                        'value' => [
                            'id' => $chat->id,
                            'wam_id' => $chat->wam_id,
                            'message' => $messageData['body'] ?? '',
                            'type' => 'outbound',
                            'message_status' => 'pending',
                            'created_at' => $chat->created_at,
                            'from_me' => true,
                            'metadata' => $chat->metadata,
                            'contact_id' => $contact->id,
                            'whatsapp_message_id' => $chat->whatsapp_message_id,
                        ]
                    ]];

                    // Broadcast to workspace channel
                    event(new \App\Events\NewChatEvent($chatData, $workspaceId));

                    Log::info('✅ NewChatEvent broadcasted for sent message', [
                        'workspace_id' => $workspaceId,
                        'contact_id' => $contact->id,
                        'chat_id' => $chat->id,
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('Error handling message sent', [
                'error' => $e->getMessage(),
                'data_keys' => array_keys($data)
            ]);
        }
    }

    /**
     * Handle message status updated event (for ✓ ✓✓ ✓✓✓ tracking)
     */
    private function handleMessageStatusUpdated(array $data): void
    {
        try {
            $workspaceId = $data['workspace_id'];
            $sessionId = $data['session_id'];
            $messageId = $data['message_id'];
            $status = $data['status'];
            $ackLevel = $data['ack_level'] ?? null;

            Log::info('Message status updated via WebJS', [
                'workspace_id' => $workspaceId,
                'session_id' => $sessionId,
                'message_id' => $messageId,
                'status' => $status,
                'ack_level' => $ackLevel,
            ]);

            // Dispatch job for fast database update and real-time broadcasting
            dispatch(new \App\Jobs\UpdateMessageStatusJob(
                messageId: $messageId,
                status: $status,
                recipientId: null, // Will be determined from chat
                ackLevel: $ackLevel,
                eventType: 'message_status_updated'
            ));

        } catch (\Exception $e) {
            Log::error('Error handling message status updated', [
                'error' => $e->getMessage(),
                'data_keys' => array_keys($data)
            ]);
        }
    }

    /**
     * Handle message delivered event
     */
    private function handleMessageDelivered(array $data): void
    {
        try {
            $workspaceId = $data['workspace_id'];
            $sessionId = $data['session_id'];
            $messageId = $data['message_id'];

            Log::info('Message delivered via WebJS', [
                'workspace_id' => $workspaceId,
                'session_id' => $sessionId,
                'message_id' => $messageId,
            ]);

            // Dispatch job with specific event type
            dispatch(new \App\Jobs\UpdateMessageStatusJob(
                messageId: $messageId,
                status: 'delivered',
                recipientId: null, // Will be determined from chat
                ackLevel: 3,
                eventType: 'message_delivered'
            ));

        } catch (\Exception $e) {
            Log::error('Error handling message delivered', [
                'error' => $e->getMessage(),
                'data_keys' => array_keys($data)
            ]);
        }
    }

    /**
     * Handle message read event
     */
    private function handleMessageRead(array $data): void
    {
        try {
            $workspaceId = $data['workspace_id'];
            $sessionId = $data['session_id'];
            $messageId = $data['message_id'];

            Log::info('Message read via WebJS', [
                'workspace_id' => $workspaceId,
                'session_id' => $sessionId,
                'message_id' => $messageId,
            ]);

            // Dispatch job with specific event type
            dispatch(new \App\Jobs\UpdateMessageStatusJob(
                messageId: $messageId,
                status: 'read',
                recipientId: null, // Will be determined from chat
                ackLevel: 4,
                eventType: 'message_read'
            ));

        } catch (\Exception $e) {
            Log::error('Error handling message read', [
                'error' => $e->getMessage(),
                'data_keys' => array_keys($data)
            ]);
        }
    }

    /**
     * Handle typing indicator event
     */
    private function handleTypingIndicator(array $data): void
    {
        try {
            $workspaceId = $data['workspace_id'];
            $sessionId = $data['session_id'];
            $contactId = $data['contact_id'];
            $contactName = $data['contact_name'] ?? '';
            $isTyping = $data['is_typing'];

            Log::info('Typing indicator via WebJS', [
                'workspace_id' => $workspaceId,
                'session_id' => $sessionId,
                'contact_id' => $contactId,
                'is_typing' => $isTyping,
            ]);

            // Find contact in database
            $contact = \App\Models\Contact::where('phone', $contactId)
                ->where('workspace_id', $workspaceId)
                ->first();

            if ($contact) {
                // Update contact typing status
                $contact->update([
                    'typing_status' => $isTyping ? 'typing' : 'idle',
                    'last_activity' => now(),
                ]);

                // Broadcast typing indicator event to frontend
                \App\Events\TypingIndicator::dispatch(
                    $contact,
                    \Illuminate\Support\Facades\Auth::id() ?? 1, // Fallback user ID
                    $isTyping,
                    null
                );

                Log::info('Typing indicator broadcasted', [
                    'contact_id' => $contact->id,
                    'is_typing' => $isTyping,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error handling typing indicator', [
                'error' => $e->getMessage(),
                'data_keys' => array_keys($data)
            ]);
        }
    }

    /**
     * Handle chat state updated event (presence changes)
     */
    private function handleChatStateUpdated(array $data): void
    {
        try {
            $workspaceId = $data['workspace_id'];
            $sessionId = $data['session_id'];
            $chatId = $data['chat_id'];
            $chatState = $data['chat_state'];
            $lastSeen = $data['last_seen'] ?? null;

            Log::info('Chat state updated via WebJS', [
                'workspace_id' => $workspaceId,
                'session_id' => $sessionId,
                'chat_id' => $chatId,
                'chat_state' => $chatState,
                'last_seen' => $lastSeen,
            ]);

            // Extract phone number from chat ID
            $phoneNumber = str_replace(['@c.us', '@g.us'], '', $chatId);

            // Find contact and update online status
            $contact = \App\Models\Contact::where('phone', $phoneNumber)
                ->where('workspace_id', $workspaceId)
                ->first();

            if ($contact) {
                $contact->update([
                    'is_online' => $chatState === 'online' || $chatState === 'composing',
                    'last_activity' => $lastSeen ? now()->setTimestamp($lastSeen / 1000) : now(),
                ]);

                Log::info('Contact presence updated', [
                    'contact_id' => $contact->id,
                    'is_online' => $contact->is_online,
                    'chat_state' => $chatState,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error handling chat state updated', [
                'error' => $e->getMessage(),
                'data_keys' => array_keys($data)
            ]);
        }
    }

    /**
     * Validate HMAC signature for security
     */
    private function validateHmacSignature(Request $request): void
    {
        $signature = $request->header('X-Hub-Signature-256');
        $payload = $request->getContent();

        if (!$signature || !$payload) {
            Log::warning('Missing HMAC signature or payload');
            return;
        }

        // Extract the hash from the signature header
        $signatureParts = explode('=', $signature);
        if (count($signatureParts) !== 2) {
            Log::warning('Invalid HMAC signature format');
            return;
        }

        $expectedHash = hash_hmac('sha256', $payload, config('services.whatsapp.webhook_secret', 'default-secret'));

        if (!hash_equals($expectedHash, $signatureParts[1])) {
            Log::warning('HMAC signature validation failed', [
                'expected' => $expectedHash,
                'received' => $signatureParts[1]
            ]);
        }
    }
}