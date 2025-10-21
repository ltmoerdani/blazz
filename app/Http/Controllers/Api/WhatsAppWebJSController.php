<?php

namespace App\Http\Controllers\Api;

use App\Events\WhatsAppQRGeneratedEvent;
use App\Events\WhatsAppSessionStatusChangedEvent;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppSession;
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
     */
    private function handleMessageReceived(array $data): void
    {
        // This would integrate with existing chat processing
        // For now, just log the event
        Log::info('WhatsApp message received via WebJS', [
            'workspace_id' => $data['workspace_id'],
            'session_id' => $data['session_id'],
            'message_id' => $data['message']['id'] ?? null,
            'from' => $data['message']['from'] ?? null,
        ]);

        // TODO: Integrate with existing ChatService to process incoming messages
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
}
