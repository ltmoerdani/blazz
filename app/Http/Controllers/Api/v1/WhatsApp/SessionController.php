<?php

namespace App\Http\Controllers\Api\v1\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SessionController extends Controller
{
    /**
     * Get session status
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