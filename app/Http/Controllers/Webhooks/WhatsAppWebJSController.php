<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Workspace as WorkspaceModel;
use App\Models\Contact;
use App\Models\Chat;
use App\Models\ChatLog;
use App\Events\NewChatEvent;
use App\Events\WhatsAppQRGenerated;
use App\Events\WhatsAppSessionStatusChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WhatsAppWebJSController extends Controller
{
    /**
     * Handle incoming webhook from Node.js WhatsApp Web JS service
     *
     * Event Types:
     * - message.received: Incoming message from customer
     * - message.sent: Message sent confirmation
     * - message.failed: Message failed to send
     * - session.qr: QR code generated
     * - session.ready: Session connected
     * - session.disconnected: Session disconnected
     */
    public function handle(Request $request)
    {
        $eventType = $request->input('event');
        $workspaceId = $request->attributes->get('workspace_id');

        Log::info("WhatsApp Web JS webhook received", [
            'event_type' => $eventType,
            'workspace_id' => $workspaceId,
        ]);

        try {
            return match ($eventType) {
                'message.received' => $this->handleMessageReceived($request, $workspaceId),
                'message.sent' => $this->handleMessageSent($request, $workspaceId),
                'message.failed' => $this->handleMessageFailed($request, $workspaceId),
                'session.qr' => $this->handleSessionQR($request, $workspaceId),
                'session.ready' => $this->handleSessionReady($request, $workspaceId),
                'session.disconnected' => $this->handleSessionDisconnected($request, $workspaceId),
                default => $this->handleUnknownEvent($eventType),
            };
        } catch (\Exception $e) {
            Log::error("Webhook processing failed", [
                'event_type' => $eventType,
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Process incoming message from customer
     */
    private function handleMessageReceived(Request $request, int $workspaceId)
    {
        $data = $request->input('data');
        $messageId = $data['message_id'];
        $phoneNumber = $data['from'];
        $messageBody = $data['body'];
        $timestamp = $data['timestamp'];

        // Check for duplicate message (deduplication)
        $existing = Chat::where('external_id', $messageId)->first();
        if ($existing) {
            Log::info("Duplicate message ignored", ['message_id' => $messageId]);
            return response()->json(['status' => 'duplicate_ignored'], 200);
        }

        DB::beginTransaction();
        try {
            // Find or create contact
            $contact = Contact::firstOrCreate(
                [
                    'phone' => $phoneNumber,
                    'workspace_id' => $workspaceId,
                ],
                [
                    'uuid' => \Illuminate\Support\Str::uuid(),
                    'name' => $data['contact_name'] ?? $phoneNumber,
                ]
            );

            // Create chat record
            $chat = Chat::create([
                'workspace_id' => $workspaceId,
                'contact_id' => $contact->id,
                'external_id' => $messageId,
                'type' => 'inbound',
                'metadata' => json_encode([
                    'text' => ['body' => $messageBody],
                    'type' => 'text',
                ]),
                'status' => 'received',
                'created_at' => date('Y-m-d H:i:s', $timestamp),
            ]);

            // Create chat log entry
            $chatlogId = ChatLog::insertGetId([
                'contact_id' => $contact->id,
                'entity_type' => 'chat',
                'entity_id' => $chat->id,
                'created_at' => now()
            ]);

            DB::commit();

            // Broadcast event for real-time UI update
            $chatLogArray = ChatLog::where('id', $chatlogId)->where('deleted_at', null)->first();
            $chatArray = [[
                'type' => 'chat',
                'value' => $chatLogArray->relatedEntities
            ]];

            event(new NewChatEvent($chatArray, $workspaceId));

            Log::info("Message received processed", [
                'message_id' => $messageId,
                'contact_id' => $contact->id,
                'chat_id' => $chat->id,
            ]);

            return response()->json(['status' => 'success', 'chat_id' => $chat->id], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Process message sent confirmation
     */
    private function handleMessageSent(Request $request, int $workspaceId)
    {
        $data = $request->input('data');
        $messageId = $data['message_id'];

        // Update chat status
        $chat = Chat::where('external_id', $messageId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($chat) {
            $chat->status = 'sent';
            $chat->save();

            Log::info("Message status updated to sent", ['message_id' => $messageId]);
        }

        return response()->json(['status' => 'success'], 200);
    }

    /**
     * Process message failed notification
     */
    private function handleMessageFailed(Request $request, int $workspaceId)
    {
        $data = $request->input('data');
        $messageId = $data['message_id'];
        $errorMessage = $data['error'] ?? 'Unknown error';

        // Update chat status
        $chat = Chat::where('external_id', $messageId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($chat) {
            $chat->status = 'failed';
            $chat->save();

            Log::error("Message failed", [
                'message_id' => $messageId,
                'error' => $errorMessage,
            ]);
        }

        return response()->json(['status' => 'success'], 200);
    }

    /**
     * Process QR code generation event
     */
    private function handleSessionQR(Request $request, int $workspaceId)
    {
        $data = $request->input('data');
        $qrCode = $data['qr_code'];
        $sessionId = $data['session_id'];

        // Broadcast QR code to frontend via broadcasting
        event(new WhatsAppQRGenerated($workspaceId, $qrCode, $sessionId));

        Log::info("QR code generated", ['workspace_id' => $workspaceId]);

        return response()->json(['status' => 'success'], 200);
    }

    /**
     * Process session ready event (connected)
     */
    private function handleSessionReady(Request $request, int $workspaceId)
    {
        $data = $request->input('data');
        $sessionId = $data['session_id'];
        $phoneNumber = $data['phone_number'];

        // Update workspace metadata
        $workspace = WorkspaceModel::find($workspaceId);
        if ($workspace) {
            $metadata = json_decode($workspace->metadata, true) ?: [];

            if (!isset($metadata['whatsapp'])) {
                $metadata['whatsapp'] = [];
            }

            $metadata['whatsapp']['webjs_status'] = 'connected';
            $metadata['whatsapp']['webjs_session_id'] = $sessionId;
            $metadata['whatsapp']['webjs_phone_number'] = $phoneNumber;
            $metadata['whatsapp']['webjs_connected_at'] = now()->toIso8601String();

            $workspace->metadata = json_encode($metadata);
            $workspace->save();
        }

        // Broadcast status update to frontend
        event(new WhatsAppSessionStatusChanged($workspaceId, 'connected', $sessionId));

        Log::info("Session connected", [
            'workspace_id' => $workspaceId,
            'session_id' => $sessionId,
            'phone_number' => $phoneNumber,
        ]);

        return response()->json(['status' => 'success'], 200);
    }

    /**
     * Process session disconnected event
     */
    private function handleSessionDisconnected(Request $request, int $workspaceId)
    {
        $data = $request->input('data');
        $sessionId = $data['session_id'];
        $reason = $data['reason'] ?? 'Unknown';

        // Update workspace metadata
        $workspace = WorkspaceModel::find($workspaceId);
        if ($workspace) {
            $metadata = json_decode($workspace->metadata, true) ?: [];

            if (!isset($metadata['whatsapp'])) {
                $metadata['whatsapp'] = [];
            }

            $metadata['whatsapp']['webjs_status'] = 'disconnected';
            $metadata['whatsapp']['webjs_disconnected_at'] = now()->toIso8601String();
            $metadata['whatsapp']['webjs_disconnect_reason'] = $reason;

            $workspace->metadata = json_encode($metadata);
            $workspace->save();
        }

        // Broadcast status update to frontend
        event(new WhatsAppSessionStatusChanged($workspaceId, 'disconnected', $sessionId));

        Log::warning("Session disconnected", [
            'workspace_id' => $workspaceId,
            'session_id' => $sessionId,
            'reason' => $reason,
        ]);

        return response()->json(['status' => 'success'], 200);
    }

    /**
     * Handle unknown event types
     */
    private function handleUnknownEvent(string $eventType)
    {
        Log::warning("Unknown webhook event type", ['event' => $eventType]);
        return response()->json(['error' => 'Unknown event type'], 400);
    }
}
