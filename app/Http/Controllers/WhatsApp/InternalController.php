<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InternalController extends Controller
{
    /**
     * Handle session migration updates from Node.js instances.
     * This endpoint is used internally when sessions are migrated between instances.
     */
    public function sessionMigrated(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'target_instance_index' => 'required|integer|min:0',
            'target_instance_url' => 'required|url',
        ]);

        $sessionId = $request->session_id;
        $targetInstanceIndex = $request->target_instance_index;
        $targetInstanceUrl = $request->target_instance_url;

        Log::info("Session migration update received", [
            'session_id' => $sessionId,
            'target_instance_index' => $targetInstanceIndex,
            'target_instance_url' => $targetInstanceUrl
        ]);

        try {
            $account = WhatsAppAccount::where('session_id', $sessionId)->first();

            if (!$account) {
                Log::warning("Session {$sessionId} not found for migration update");
                return response()->json(['error' => 'Session not found'], 404);
            }

            // Update the instance assignment
            $account->assignToInstance($targetInstanceIndex, $targetInstanceUrl);

            Log::info("Session {$sessionId} assigned to Instance {$targetInstanceIndex}");

            return response()->json([
                'success' => true,
                'session_id' => $sessionId,
                'assigned_instance_index' => $targetInstanceIndex,
                'assigned_instance_url' => $targetInstanceUrl,
                'migration_count' => $account->instance_migration_count,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to update session migration", [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to update session assignment',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle session disconnection updates from Node.js instances.
     * This endpoint is used when instances detect session disconnections.
     */
    public function sessionDisconnected(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'reason' => 'required|string',
            'details' => 'nullable|string',
        ]);

        $sessionId = $request->session_id;
        $reason = $request->reason;
        $details = $request->details;

        Log::info("Session disconnection update received", [
            'session_id' => $sessionId,
            'reason' => $reason,
            'details' => $details
        ]);

        try {
            $account = WhatsAppAccount::where('session_id', $sessionId)->first();

            if (!$account) {
                Log::warning("Session {$sessionId} not found for disconnection update");
                return response()->json(['error' => 'Session not found'], 404);
            }

            // Mark as disconnected
            $account->markDisconnected($reason, $details);

            Log::info("Session {$sessionId} marked as disconnected: {$reason}");

            return response()->json([
                'success' => true,
                'session_id' => $sessionId,
                'status' => 'disconnected',
                'disconnected_at' => $account->disconnected_at->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to update session disconnection", [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to update session disconnection',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update session activity timestamp.
     * Called by instances to keep session activity fresh.
     */
    public function updateActivity(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        $sessionId = $request->session_id;

        try {
            $account = WhatsAppAccount::where('session_id', $sessionId)->first();

            if (!$account) {
                return response()->json(['error' => 'Session not found'], 404);
            }

            $account->update([
                'last_activity_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'session_id' => $sessionId,
                'last_activity_at' => $account->last_activity_at->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to update session activity", [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to update activity',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get session assignment information.
     * Used by instances to verify session assignments.
     */
    public function getSessionAssignment(string $sessionId)
    {
        try {
            $account = WhatsAppAccount::where('session_id', $sessionId)->first();

            if (!$account) {
                return response()->json(['error' => 'Session not found'], 404);
            }

            return response()->json([
                'success' => true,
                'session_id' => $sessionId,
                'assigned_instance_index' => $account->assigned_instance_index,
                'assigned_instance_url' => $account->assigned_instance_url,
                'previous_instance_index' => $account->previous_instance_index,
                'migration_count' => $account->instance_migration_count,
                'last_migration_at' => $account->last_instance_migration_at?->toISOString(),
                'workspace_id' => $account->workspace_id,
                'status' => $account->status,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to get session assignment", [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to get session assignment',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify internal API token for security.
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $token = $request->header('X-Internal-Token') ?? $request->input('token');
            $expectedToken = config('whatsapp.internal_token', 'secret-internal-token');

            if (!$token || !hash_equals($expectedToken, $token)) {
                Log::warning('Unauthorized internal API access attempt', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'url' => $request->fullUrl()
                ]);

                return response()->json(['error' => 'Unauthorized'], 401);
            }

            return $next($request);
        });
    }
}