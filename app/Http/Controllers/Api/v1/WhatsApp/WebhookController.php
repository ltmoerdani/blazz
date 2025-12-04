<?php

namespace App\Http\Controllers\Api\v1\WhatsApp;

use App\Events\WhatsAppQRGeneratedEvent;
use App\Events\WhatsAppAccountStatusChangedEvent;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsAppWebhookJob;
use App\Jobs\HandleMobileActivityJob;
use App\Models\ChatMedia;
use App\Models\Contact;
use App\Models\WhatsAppAccount;
use App\Services\ChatService;
use App\Services\ContactProvisioningService;
use App\Services\Media\MediaStorageService;
use App\Services\MediaService;
use App\Services\ProviderSelector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle webhook from Node.js service
     * OPTIMIZED: Return instantly, process async
     * 
     * CRITICAL FIX: session_ready now processes INLINE for immediate phone number update
     * Root Cause: Queue delay caused phone_number to remain NULL in frontend polling
     * Solution: Process synchronously to ensure database update before webhook response
     */
    public function webhook(Request $request)
    {
        // HMAC validation handled by middleware (VerifyWhatsAppHmac)
        
        $event = $request->input('event');
        $data = $request->input('data');

        // CRITICAL DEBUG: Log FULL request payload to diagnose event mismatch
        Log::info('WhatsApp WebJS webhook received', [
            'event' => $event,
            'event_type' => gettype($event),
            'event_length' => strlen($event ?? ''),
            'event_hex' => bin2hex($event ?? ''),
            'workspace_id' => $data['workspace_id'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
            'full_payload' => $request->all() // FULL request for debugging
        ]);

        // CRITICAL FIX: Process session_ready INLINE (synchronously)
        // Reason: Phone number must be saved to DB before frontend polls status
        if ($event === 'session_ready') {
            try {
                $this->handleSessionReady($data);
                Log::info('✅ session_ready processed inline successfully', [
                    'session_id' => $data['session_id'] ?? null,
                    'phone_number' => $data['phone_number'] ?? null
                ]);
            } catch (\Exception $e) {
                Log::error('❌ session_ready inline processing failed', [
                    'error' => $e->getMessage(),
                    'session_id' => $data['session_id'] ?? null
                ]);
            }
            return response()->json(['status' => 'processed_inline']);
        }

        // Queue non-critical events for async processing
        if (in_array($event, ['qr_code_generated', 'session_authenticated', 'session_disconnected', 'session_error'])) {
            ProcessWhatsAppWebhookJob::dispatch($event, $data)->onQueue('whatsapp-urgent');
            return response()->json(['status' => 'queued']);
        }

        // Process real-time events inline (messages need instant processing)
        switch ($event) {
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

            case 'session_error':
                $this->handleSessionError($data);
                break;

            case 'mobile_activity_detected':
                return $this->handleMobileActivityDetected($request);

            default:
                Log::warning('Unknown WhatsApp WebJS event', ['event' => $event]);
                break;
        }

        return response()->json(['status' => 'received']);
    }





    /**
     * Handle session ready event
     * CRITICAL FIX: Process inline with duplicate cleanup and proper error handling
     * 
     * This method:
     * 1. Cleans up any duplicate/stuck sessions before update
     * 2. Validates phone number is not null/undefined
     * 3. Updates database synchronously
     * 4. Broadcasts status change event
     */
    private function handleSessionReady(array $data): void
    {
        $workspaceId = $data['workspace_id'];
        $sessionId = $data['session_id'];
        $phoneNumber = $data['phone_number'] ?? null;

        Log::info('🔵 Processing session_ready webhook', [
            'workspace_id' => $workspaceId,
            'session_id' => $sessionId,
            'phone_number' => $phoneNumber,
            'extraction_method' => $data['extraction_method'] ?? 'unknown'
        ]);

        // Validate phone number
        if (!$phoneNumber || $phoneNumber === 'null' || $phoneNumber === 'undefined') {
            Log::error('❌ Invalid phone number in session_ready', [
                'workspace_id' => $workspaceId,
                'session_id' => $sessionId,
                'phone_number' => $phoneNumber
            ]);
            return;
        }

        // Find session
        $session = WhatsAppAccount::where('session_id', $sessionId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if (!$session) {
            Log::error('❌ Session not found in database', [
                'session_id' => $sessionId,
                'workspace_id' => $workspaceId
            ]);
            return;
        }

        // CRITICAL FIX: Clean up any duplicate/stuck sessions before update
        // This prevents unique constraint violations
        // BUGFIX: Set phone_number NULL + soft delete to avoid unique constraint (phone_number, workspace_id, status)
        try {
            $duplicatesDeleted = DB::table('whatsapp_accounts')
                ->where('workspace_id', $workspaceId)
                ->where('phone_number', $phoneNumber)
                ->where('id', '!=', $session->id)
                // Clean up ALL conflicting statuses (including connected and already soft-deleted)
                ->whereIn('status', ['qr_scanning', 'authenticated', 'disconnected', 'connected', 'error', 'failed'])
                ->update([
                    'status' => 'failed',
                    'phone_number' => null, // CRITICAL: Set NULL to bypass unique constraint (phone_number, workspace_id, status)
                    'deleted_at' => now(),
                    'updated_at' => now()
                ]);

            if ($duplicatesDeleted > 0) {
                Log::info('🧹 Cleaned up duplicate sessions', [
                    'workspace_id' => $workspaceId,
                    'phone_number' => $phoneNumber,
                    'deleted_count' => $duplicatesDeleted
                ]);
            }
        } catch (\Exception $e) {
            Log::error('⚠️ Duplicate cleanup failed (non-fatal)', [
                'error' => $e->getMessage()
            ]);
        }

        // Check if this should be the primary number (first connected account in workspace)
        $hasPrimaryAccount = WhatsAppAccount::where('workspace_id', $workspaceId)
            ->where('is_primary', true)
            ->where('status', 'connected')
            ->where('id', '!=', $session->id)
            ->exists();

        $isPrimary = !$hasPrimaryAccount;

        // ✅ PREVENTION FIX: Extract instance URL from request header for auto-sync
        $requestInstanceUrl = request()->header('X-Instance-Url');
        
        // Update session with phone number AND assigned_instance_url
        try {
            $updateData = [
                'status' => 'connected',
                'phone_number' => $phoneNumber,
                'is_primary' => $isPrimary, // Auto-set as primary if first connected account
                'last_connected_at' => now(),
                'last_activity_at' => now(),
                'metadata' => array_merge($session->metadata ?? [], [
                    'extraction_method' => $data['extraction_method'] ?? 'unknown',
                    'platform' => $data['platform'] ?? 'unknown',
                    'connected_timestamp' => now()->toISOString(),
                    'auto_set_primary' => $isPrimary // Track if auto-set as primary
                ])
            ];
            
            // ✅ AUTO-SYNC: Update assigned_instance_url if provided in request
            if ($requestInstanceUrl) {
                $updateData['assigned_instance_url'] = $requestInstanceUrl;
                Log::info('🔄 Auto-syncing assigned_instance_url from webhook header', [
                    'session_id' => $sessionId,
                    'old_url' => $session->assigned_instance_url,
                    'new_url' => $requestInstanceUrl
                ]);
            }
            
            $session->update($updateData);

            Log::info('✅ Session updated successfully', [
                'session_id' => $sessionId,
                'phone_number' => $phoneNumber,
                'status' => 'connected',
                'is_primary' => $isPrimary,
                'auto_set_primary' => $isPrimary
            ]);

            // Broadcast status change event
            $session->refresh();
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
                    'is_primary' => $session->is_primary,
                    'timestamp' => now()->toISOString()
                ]
            ));

            Log::info('📤 Status change event broadcasted', [
                'session_id' => $sessionId,
                'phone_number' => $phoneNumber,
                'is_primary' => $session->is_primary
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to update session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Handle session error event
     * Process errors from Node.js service (e.g., phone extraction failures)
     */
    private function handleSessionError(array $data): void
    {
        $workspaceId = $data['workspace_id'];
        $sessionId = $data['session_id'];
        $error = $data['error'] ?? 'unknown_error';
        $message = $data['message'] ?? 'Unknown error occurred';

        Log::error('❌ Session error received from Node.js', [
            'workspace_id' => $workspaceId,
            'session_id' => $sessionId,
            'error' => $error,
            'message' => $message
        ]);

        // Find and update session
        $session = WhatsAppAccount::where('session_id', $sessionId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($session) {
            $session->update([
                'status' => 'error',
                'last_activity_at' => now(),
                'metadata' => array_merge($session->metadata ?? [], [
                    'last_error' => $error,
                    'error_message' => $message,
                    'error_timestamp' => now()->toISOString()
                ])
            ]);

            // Broadcast error event
            broadcast(new WhatsAppAccountStatusChangedEvent(
                $sessionId,
                'error',
                $workspaceId,
                $session->phone_number,
                [
                    'id' => $session->id,
                    'uuid' => $session->uuid,
                    'error' => $error,
                    'message' => $message,
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

            // Get contact name: group messages should use group_name, private chats use notifyName or phone number
            if ($isGroup) {
                $contactName = $message['group_name'] ?? 'Group ' . substr($phoneNumber, -4);
            } else {
                $contactName = $message['notifyName'] ?? $phoneNumber;
            }

            Log::debug('🔍 DEBUG: Contact Name Calculation', [
                'is_group' => $isGroup,
                'group_name_raw' => $message['group_name'] ?? 'MISSING',
                'sender_name_raw' => $message['sender_name'] ?? 'MISSING',
                'notify_name_raw' => $message['notifyName'] ?? 'MISSING',
                'phone_number' => $phoneNumber,
                'calculated_name' => $contactName
            ]);

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
                $session->id,
                $isGroup,
                $isGroup ? [
                    'group_id' => $message['group_id'] ?? null,
                    'participants' => $message['participants'] ?? []
                ] : []
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
                    'has_media' => isset($message['media']) || (isset($message['has_media']) && $message['has_media']),
                    'media_type' => $message['type'] ?? 'text',
                    'from' => $message['from'] ?? null,
                    'to' => $message['to'] ?? null,
                    'timestamp' => $message['timestamp'] ?? null,
                    // For group messages, include sender information
                    'sender_phone' => $isGroup ? ($message['sender_phone'] ?? null) : null,
                    'sender_name' => $isGroup ? ($message['sender_name'] ?? 'Unknown') : null,
                ]),
            ]);

            // ==========================================
            // PROCESS MEDIA FROM WHATSAPP WEBJS
            // Media is sent as base64 from Node.js service
            // ==========================================
            $chatMedia = null;
            if (!empty($message['media']) && !empty($message['media']['data'])) {
                try {
                    $chatMedia = $this->processWebJsMedia($message['media'], $workspaceId, $chat->id, $contact->id);
                    
                    if ($chatMedia) {
                        // Update chat with media_id
                        $chat->update(['media_id' => $chatMedia->id]);
                        
                        Log::info('📎 Media processed and saved from WebJS', [
                            'chat_id' => $chat->id,
                            'media_id' => $chatMedia->id,
                            'media_type' => $chatMedia->type,
                            'size' => $chatMedia->size,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('❌ Failed to process WebJS media', [
                        'error' => $e->getMessage(),
                        'message_id' => $message['id'] ?? null,
                        'workspace_id' => $workspaceId,
                    ]);
                    // Continue without media - don't fail the whole message
                }
            }

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

            // ✅ REALTIME FIX: Broadcast new message event
            // Load relationships needed for broadcast
            $chat->load(['contact', 'media', 'user']);
            
            // Build message data structure
            // Extract message body from metadata
            $messageBody = is_string($chat->metadata) 
                ? (json_decode($chat->metadata, true)['body'] ?? '') 
                : ($chat->metadata['body'] ?? '');
            
            $messageData = [
                'id' => $chat->id,
                'wam_id' => $chat->wam_id,
                'contact_id' => $chat->contact_id,
                'contact' => [
                    'id' => $chat->contact->id,
                    'name' => $chat->contact->name,
                    'phone' => $chat->contact->phone,
                    'avatar' => $chat->contact->avatar ?? null,
                    'unread_messages' => $chat->contact->unread_messages ?? 0,
                ],
                'message' => $messageBody,  // For compatibility
                'body' => $messageBody,     // For ChatThread component
                'type' => $chat->type,
                'message_status' => $chat->message_status,
                'from_me' => $chat->type === 'outbound',
                'created_at' => is_string($chat->created_at) ? $chat->created_at : $chat->created_at?->toISOString(),
                'metadata' => $chat->metadata,
            ];
            
            // Broadcast event (use workspaceId from context, not from relationship)
            event(new \App\Events\NewChatEvent($messageData, $workspaceId, $chat->contact_id));
            
            Log::info('📤 NewChatEvent broadcasted', [
                'workspace_id' => $workspaceId,
                'contact_id' => $chat->contact_id,
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
     * 
     * CRITICAL FIX (Issue #3616): WhatsApp changed behavior
     * - Messages from mobile NO LONGER trigger 'message' event with fromMe=true
     * - MUST use 'message_create' event for self-sent messages
     * - This handler processes messages from BOTH web and mobile
     */
    private function handleMessageSent(array $data): void
    {
        try {
            $workspaceId = $data['workspace_id'];
            $sessionId = $data['session_id'];
            $messageData = $data['message'];
            $source = $data['source'] ?? 'unknown';

            Log::info('🔵 Message sent via WebJS (from ' . $source . ')', [
                'workspace_id' => $workspaceId,
                'session_id' => $sessionId,
                'message_id' => $messageData['id'] ?? null,
                'from' => $messageData['from'] ?? null,  // CRITICAL: Use 'from' not 'to'
                'to' => $messageData['to'] ?? null,
                'chat_type' => $messageData['chat_type'] ?? 'private',
                'source' => $source,
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
            // CRITICAL FIX: For self-sent messages from mobile, 'from' contains the CHAT ID
            // SessionManager already sets messageData['from'] to chat.id._serialized
            $chatId = $messageData['from'] ?? $messageData['to'];  // Fallback to 'to' for backward compatibility
            $phoneNumber = str_replace(['@c.us', '@g.us'], '', $chatId);
            
            // Determine if this is a group message
            $isGroup = $messageData['chat_type'] === 'group' || strpos($chatId, '@g.us') !== false;
            
            Log::info('📞 Extracting contact info', [
                'chat_id' => $chatId,
                'phone_number' => $phoneNumber,
                'is_group' => $isGroup,
                'chat_type' => $messageData['chat_type'] ?? 'unknown',
            ]);

            $provisioningService = new ContactProvisioningService();
            
            // Try to find existing contact first (to avoid duplicates)
            $contact = Contact::where('workspace_id', $workspaceId)
                ->where('phone', $phoneNumber)
                ->first();
            
            // If not found, create it
            if (!$contact) {
                $contact = $provisioningService->getOrCreateContact(
                    $phoneNumber,
                    $phoneNumber, // Use phone number as name for sent messages
                    $workspaceId,
                    'webjs',
                    $session->id,
                    $isGroup,  // CRITICAL: Pass isGroup parameter
                    $isGroup ? ['group_id' => $phoneNumber] : []
                );
            }

            if ($contact) {
                // Check if chat already exists (to prevent duplicates from MessageService)
                $existingChat = \App\Models\Chat::where('whatsapp_message_id', $messageData['id'])
                    ->orWhere('wam_id', $messageData['id'])
                    ->first();

                if ($existingChat) {
                    // ✅ CRITICAL FIX: Don't skip! Always broadcast for real-time sync
                    // This ensures messages from mobile always appear in chat list
                    Log::info('Chat record exists - broadcasting for real-time update', [
                        'contact_id' => $contact->id,
                        'message_id' => $messageData['id'],
                        'existing_chat_id' => $existingChat->id,
                        'source' => $data['source'] ?? 'unknown'
                    ]);
                    
                    // Update contact timestamps to ensure chat appears at top of list
                    $contact->update([
                        'latest_chat_created_at' => now(),
                        'last_message_at' => now(),
                        'last_activity' => now(),
                    ]);
                    
                    // Load relationships for broadcasting
                    $existingChat->load(['contact', 'media', 'user']);
                    
                    // Extract message body from metadata
                    $messageBody = is_string($existingChat->metadata) 
                        ? (json_decode($existingChat->metadata, true)['body'] ?? '') 
                        : ($existingChat->metadata['body'] ?? '');
                    
                    // Build message data for broadcast - MUST match inbound format for consistency!
                    // Parse metadata to add device source info
                    $metadataArray = is_string($existingChat->metadata) 
                        ? json_decode($existingChat->metadata, true) 
                        : $existingChat->metadata;
                    
                    // Add device source indicator
                    if (!isset($metadataArray['device_source'])) {
                        $metadataArray['device_source'] = $source === 'message_create_event' ? 'mobile' : 'web';
                    }
                    
                    $chatData = [
                        'id' => $existingChat->id,
                        'wam_id' => $existingChat->wam_id,
                        'contact_id' => $existingChat->contact_id,
                        
                        // Contact nested object - MATCH INBOUND FORMAT
                        'contact' => [
                            'id' => $existingChat->contact->id,
                            'name' => $existingChat->contact->name,  // ✅ Use 'name' like inbound
                            'phone' => $existingChat->contact->phone,
                            'avatar' => $existingChat->contact->avatar ?? null,  // ✅ Use 'avatar' like inbound
                            'unread_messages' => 0,  // Outbound doesn't increase unread
                        ],
                        
                        // Message content and metadata - MATCH INBOUND FORMAT
                        'message' => $messageBody,  // Primary field (like inbound)
                        'body' => $messageBody,     // Secondary field (like inbound)
                        'type' => 'outbound',
                        'message_status' => $existingChat->message_status ?? 'sent',
                        'from_me' => true,
                        'device_source' => $metadataArray['device_source'],  // 🆕 Add device source
                        'created_at' => is_string($existingChat->created_at) ? $existingChat->created_at : $existingChat->created_at?->toISOString(),
                        'metadata' => $metadataArray,
                    ];
                    
                    // ✅ BROADCAST EVENT - This makes chat appear in list
                    event(new \App\Events\NewChatEvent($chatData, $workspaceId, $existingChat->contact_id));
                    
                    Log::info('✅ NewChatEvent broadcasted for existing chat (self-sent from mobile)', [
                        'workspace_id' => $workspaceId,
                        'contact_id' => $existingChat->contact_id,
                        'chat_id' => $existingChat->id,
                    ]);
                    
                    return;  // Exit after broadcasting
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
                            'device_source' => $source === 'message_create_event' ? 'mobile' : 'web',  // 🆕 Track device origin
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
                    
                    // Update contact timestamps to ensure chat appears at top of list
                    $contact->update([
                        'latest_chat_created_at' => now(),
                        'last_message_at' => now(),
                        'last_activity' => now(),
                    ]);

                    Log::info('Chat record created for sent message', [
                        'contact_id' => $contact->id,
                        'message_id' => $messageData['id'],
                    ]);

                    // ✅ REALTIME FIX: Broadcast NewChatEvent for outbound messages too
                    // MUST match inbound format for consistency!
                    $metadataForBroadcast = is_string($chat->metadata) ? json_decode($chat->metadata, true) : $chat->metadata;
                    
                    $chatData = [
                        'id' => $chat->id,
                        'wam_id' => $chat->wam_id,
                        'contact_id' => $contact->id,
                        
                        // Contact nested object - MATCH INBOUND FORMAT
                        'contact' => [
                            'id' => $contact->id,
                            'name' => $contact->name,  // ✅ Use 'name' like inbound
                            'phone' => $contact->phone,
                            'avatar' => $contact->avatar ?? null,  // ✅ Use 'avatar' like inbound
                            'unread_messages' => 0,
                        ],
                        
                        // Message content - MATCH INBOUND FORMAT
                        'message' => $messageData['body'] ?? '',  // Primary field
                        'body' => $messageData['body'] ?? '',     // Secondary field
                        'type' => 'outbound',
                        'message_status' => 'pending',
                        'from_me' => true,
                        'device_source' => $metadataForBroadcast['device_source'] ?? ($source === 'message_create_event' ? 'mobile' : 'web'),  // 🆕 Device source
                        'created_at' => is_string($chat->created_at) ? $chat->created_at : $chat->created_at?->toISOString(),
                        'metadata' => $metadataForBroadcast,
                    ];

                    // Broadcast to workspace channel
                    event(new \App\Events\NewChatEvent($chatData, $workspaceId, $contact->id));

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
     * Handle mobile activity detected webhook
     */
    protected function handleMobileActivityDetected(Request $request): \Illuminate\Http\JsonResponse
    {
        $sessionId = $request->input('session_id');
        $data = $request->input('data', []);

        $workspaceId = $data['workspace_id'] ?? null;
        $deviceType = $data['device_type'] ?? 'unknown';

        if (!$workspaceId || !$sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'Missing required fields: workspace_id, session_id',
            ], 422);
        }

        // Dispatch job for async processing
        HandleMobileActivityJob::dispatch(
            (int) $workspaceId,
            $sessionId,
            $deviceType
        );

        Log::info('Mobile activity webhook queued', [
            'workspace_id' => $workspaceId,
            'session_id' => $sessionId,
            'device_type' => $deviceType,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mobile activity queued for processing',
        ]);
    }

    // ==========================================
    // MEDIA PROCESSING HELPERS
    // ==========================================

    /**
     * Process media from WhatsApp WebJS webhook
     * 
     * Media is received as base64-encoded data from Node.js service.
     * This method stores the media to S3 and creates a ChatMedia record.
     * 
     * @param array $mediaData Media data from webhook ['data' => base64, 'mimetype' => string, 'filename' => string]
     * @param int $workspaceId Workspace ID
     * @param int $chatId Chat record ID
     * @param int $contactId Contact ID
     * @return ChatMedia|null Created media record or null on failure
     */
    protected function processWebJsMedia(array $mediaData, int $workspaceId, int $chatId, int $contactId): ?ChatMedia
    {
        // Validate media data
        if (empty($mediaData['data'])) {
            Log::warning('[WebhookController] Empty media data received', [
                'workspace_id' => $workspaceId,
                'chat_id' => $chatId,
            ]);
            return null;
        }

        $base64Data = $mediaData['data'];
        $mimeType = $mediaData['mimetype'] ?? 'application/octet-stream';
        $filename = $mediaData['filename'] ?? 'media_' . time();

        // Log media info
        Log::info('📎 Processing WebJS media', [
            'workspace_id' => $workspaceId,
            'chat_id' => $chatId,
            'mime_type' => $mimeType,
            'filename' => $filename,
            'base64_length' => strlen($base64Data),
        ]);

        try {
            /** @var MediaStorageService $mediaService */
            $mediaService = app(MediaStorageService::class);

            // Use the uploadForChat method which handles base64
            $chatMedia = $mediaService->uploadForChat(
                $base64Data,
                $workspaceId,
                [
                    'direction' => MediaStorageService::DIRECTION_RECEIVED,
                    'mime_type' => $mimeType,
                    'filename' => $filename,
                    'contact_id' => $contactId,
                ]
            );

            // Store media category in metadata for quick reference
            $category = $this->getMediaCategory($mimeType);
            $currentMetadata = $chatMedia->metadata ?? [];
            $currentMetadata['category'] = $category;
            $currentMetadata['chat_id'] = $chatId;
            $currentMetadata['source'] = 'webjs_inbound';
            $chatMedia->update(['metadata' => $currentMetadata]);

            Log::info('✅ WebJS media stored successfully', [
                'media_id' => $chatMedia->id,
                'path' => $chatMedia->original_path,
                'category' => $category,
                'size' => $chatMedia->size,
            ]);

            return $chatMedia;

        } catch (\Exception $e) {
            Log::error('❌ Failed to store WebJS media', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'workspace_id' => $workspaceId,
                'chat_id' => $chatId,
            ]);
            throw $e;
        }
    }

    /**
     * Get media category from MIME type
     * 
     * @param string $mimeType MIME type
     * @return string Category: image, video, audio, document
     */
    protected function getMediaCategory(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }
        return 'document';
    }
}