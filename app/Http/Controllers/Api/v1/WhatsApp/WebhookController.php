<?php

namespace App\Http\Controllers\Api\v1\WhatsApp;

use App\Events\WhatsAppQRGeneratedEvent;
use App\Events\WhatsAppSessionStatusChangedEvent;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppSession;
use App\Services\ContactProvisioningService;
use App\Services\MediaService;
use App\Services\ProviderSelector;
use Illuminate\Http\Request;
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
            ]);

            if (!$contact) {
                Log::error('Failed to create or find contact', [
                    'phone_number' => $phoneNumber,
                    'workspace_id' => $workspaceId
                ]);
                return;
            }

            // Log message received successfully
            Log::info('WhatsApp message processed successfully', [
                'contact_id' => $contact->id,
                'message_id' => $message['id'] ?? null,
                'message_type' => $message['type'] ?? 'unknown',
                'workspace_id' => $workspaceId,
                'session_id' => $sessionId
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