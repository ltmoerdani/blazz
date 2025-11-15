<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\WhatsAppChatSyncJob;
use App\Models\WhatsAppAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * WhatsApp Sync Controller
 *
 * Handles chat synchronization requests from Node.js WhatsApp Web.js service.
 *
 * Security:
 * - HMAC signature validation (via middleware)
 * - Workspace isolation
 * - Rate limiting (60 req/min per session)
 *
 * @package App\Http\Controllers\API
 */
class WhatsAppSyncController extends Controller
{
    /**
     * Sync batch of chats
     *
     * Receives batch of chats from Node.js and queues for processing.
     *
     * Strategy:
     * - Accept batch (max 50 chats per request)
     * - Validate session ownership
     * - Dispatch to queue (async processing)
     * - Return 202 Accepted immediately
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *     path="/api/whatsapp/chats/sync",
     *     summary="Sync batch of WhatsApp chats",
     *     tags={"WhatsApp Sync"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"session_id", "workspace_id", "chats"},
     *             @OA\Property(property="session_id", type="integer", example=1),
     *             @OA\Property(property="workspace_id", type="integer", example=1),
     *             @OA\Property(
     *                 property="chats",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="chat_type", type="string", enum={"private", "group"}),
     *                     @OA\Property(property="contact_phone", type="string", example="+6281234567890"),
     *                     @OA\Property(property="message_body", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=202, description="Batch queued for processing"),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Session not found")
     * )
     */
    public function syncBatch(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'whatsapp_account_id' => 'required|integer|exists:whatsapp_accounts,id',
            'workspace_id' => 'required|integer|exists:workspaces,id',
            'chats' => 'required|array|min:1|max:50',
            'chats.*.chat_type' => 'required|in:private,group',
            'chats.*.provider_type' => 'required|in:webjs,meta',
            'chats.*.whatsapp_account_id' => 'required|integer|exists:whatsapp_accounts,id',
            'chats.*.contact_phone' => 'required_if:chats.*.chat_type,private|nullable|string',
            'chats.*.contact_name' => 'nullable|string|max:255',
            'chats.*.group_jid' => 'required_if:chats.*.chat_type,group|nullable|string',
            'chats.*.group_name' => 'required_if:chats.*.chat_type,group|nullable|string',
            'chats.*.message_body' => 'nullable|string|max:65536',
            'chats.*.timestamp' => 'nullable|integer',
            'chats.*.message_status' => 'required|in:pending,sent,delivered,read,failed',
            'chats.*.sent_at' => 'nullable|date',
            'chats.*.delivered_at' => 'nullable|date',
            'chats.*.read_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            Log::channel('whatsapp')->warning('Sync batch validation failed', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->only(['whatsapp_account_id', 'workspace_id']),
            ]);

            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        $validated = $validator->validated();

        // Verify account belongs to workspace
        $account = WhatsAppAccount::where('id', $validated['whatsapp_account_id'])
            ->where('workspace_id', $validated['workspace_id'])
            ->first();

        if (!$account) {
            Log::channel('whatsapp')->warning('Account ownership validation failed', [
                'whatsapp_account_id' => $validated['whatsapp_account_id'],
                'workspace_id' => $validated['workspace_id'],
            ]);

            return response()->json([
                'message' => 'WhatsApp account not found or access denied',
            ], 404);
        }

        // Check account status
        if ($account->status !== 'connected' && $account->status !== 'authenticated') {
            Log::channel('whatsapp')->warning('Sync rejected - account not connected', [
                'whatsapp_account_id' => $account->id,
                'status' => $account->status,
            ]);

            return response()->json([
                'message' => 'WhatsApp account is not active',
                'status' => $account->status,
            ], 400);
        }

        // Dispatch to queue
        try {
            WhatsAppChatSyncJob::dispatch(
                $validated['whatsapp_account_id'],
                $validated['workspace_id'],
                $validated['chats']
            );

            Log::channel('whatsapp')->info('Sync batch queued', [
                'whatsapp_account_id' => $validated['whatsapp_account_id'],
                'workspace_id' => $validated['workspace_id'],
                'batch_size' => count($validated['chats']),
            ]);

            return response()->json([
                'message' => 'Batch queued for processing',
                'status' => 'queued',
                'batch_size' => count($validated['chats']),
                'whatsapp_account_id' => $validated['whatsapp_account_id'],
            ], 202);

        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Failed to queue sync batch', [
                'whatsapp_account_id' => $validated['whatsapp_account_id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to queue batch',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sync status for session
     *
     * Returns current sync progress and statistics.
     *
     * @param Request $request
     * @param int $sessionId
     * @return \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *     path="/api/whatsapp/sessions/{sessionId}/sync-status",
     *     summary="Get sync status",
     *     tags={"WhatsApp Sync"},
     *     @OA\Parameter(
     *         name="sessionId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Sync status"),
     *     @OA\Response(response=404, description="Session not found")
     * )
     */
    public function getSyncStatus(Request $request, int $sessionId)
    {
        $session = WhatsAppAccount::find($sessionId);

        if (!$session) {
            return response()->json([
                'message' => 'Session not found',
            ], 404);
        }

        $metadata = $session->metadata ?? [];

        return response()->json([
            'session_id' => $session->id,
            'sync_status' => $metadata['sync_status'] ?? 'pending',
            'total_synced' => $metadata['total_synced'] ?? 0,
            'last_sync_at' => $metadata['last_sync_at'] ?? null,
            'sync_errors' => $metadata['sync_errors'] ?? 0,
            'last_error' => $metadata['last_error'] ?? null,
        ], 200);
    }
}
