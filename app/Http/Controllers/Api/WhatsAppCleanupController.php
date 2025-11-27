<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Cleanup API Controller
 * 
 * Provides endpoints for Node.js SessionCleanupService to manage stale sessions.
 * 
 * @author Blazz Platform
 * @date November 20, 2025
 * @follows docs/architecture/07-development-patterns-guidelines.md
 */
class WhatsAppCleanupController extends Controller
{
    /**
     * Get accounts that need cleanup check
     * 
     * Returns accounts that:
     * - Status: disconnected, failed, qr_scanning (old)
     * - Last activity > 24 hours ago
     * 
     * GET /api/whatsapp/accounts-for-cleanup
     */
    public function getAccountsForCleanup(Request $request)
    {
        try {
            $threshold = now()->subDay(); // 24 hours ago

            $accounts = WhatsAppAccount::query()
                ->whereIn('status', ['disconnected', 'failed', 'qr_scanning'])
                ->where(function($query) use ($threshold) {
                    $query->where('last_activity_at', '<', $threshold)
                          ->orWhereNull('last_activity_at');
                })
                ->select([
                    'id',
                    'workspace_id',
                    'session_id',
                    'phone_number',
                    'provider_type',
                    'status',
                    'last_activity_at',
                    'last_connected_at',
                    'health_score',
                    'created_at'
                ])
                ->orderBy('last_activity_at', 'asc')
                ->limit(50) // Safety limit
                ->get();

            return response()->json([
                'success' => true,
                'data' => $accounts,
                'count' => $accounts->count(),
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch accounts for cleanup', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch accounts'
            ], 500);
        }
    }

    /**
     * Get account by session ID
     * 
     * GET /api/whatsapp/accounts/by-session/{sessionId}
     */
    public function getAccountBySession(Request $request, $sessionId)
    {
        try {
            $account = WhatsAppAccount::where('session_id', $sessionId)->first();

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'error' => 'Account not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $account
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch account by session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch account'
            ], 500);
        }
    }

    /**
     * Update account status
     * 
     * PATCH /api/whatsapp/accounts/{id}/status
     * 
     * Body:
     * {
     *   "status": "failed",
     *   "reason": "Cleaned up by automatic cleanup",
     *   "last_cleanup_at": "2025-11-20T..."
     * }
     */
    public function updateAccountStatus(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'status' => 'sometimes|string|in:qr_scanning,authenticated,connected,disconnected,failed',
                'reason' => 'sometimes|string|max:500',
                'last_cleanup_at' => 'sometimes|date',
                'health_score' => 'sometimes|integer|min:0|max:100',
                'last_health_check_at' => 'sometimes|date'
            ]);

            $account = WhatsAppAccount::findOrFail($id);

            $updateData = [];
            
            if (isset($validated['status'])) {
                $updateData['status'] = $validated['status'];
            }

            if (isset($validated['last_cleanup_at'])) {
                $updateData['last_cleanup_at'] = $validated['last_cleanup_at'];
            }

            if (isset($validated['health_score'])) {
                $updateData['health_score'] = $validated['health_score'];
            }

            if (isset($validated['last_health_check_at'])) {
                $updateData['last_health_check_at'] = $validated['last_health_check_at'];
            }

            // Store reason in metadata
            if (isset($validated['reason'])) {
                $metadata = $account->metadata ?? [];
                $metadata['last_status_change'] = [
                    'reason' => $validated['reason'],
                    'changed_at' => now()->toISOString(),
                    'changed_by' => 'cleanup_service'
                ];
                $updateData['metadata'] = $metadata;
            }

            $account->update($updateData);

            Log::info('Account status updated by cleanup service', [
                'account_id' => $id,
                'session_id' => $account->session_id,
                'updates' => $updateData
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Account status updated',
                'data' => $account->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update account status', [
                'account_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to update account status'
            ], 500);
        }
    }

    /**
     * Log cleanup operation
     * 
     * POST /api/whatsapp/cleanup-logs
     * 
     * Body:
     * {
     *   "whatsapp_account_id": 123,
     *   "action": "cleanup",
     *   "status": "success",
     *   "reason": "Stale session inactive for 7 days",
     *   "metadata": {}
     * }
     */
    public function logCleanup(Request $request)
    {
        try {
            $validated = $request->validate([
                'whatsapp_account_id' => 'required|integer|exists:whatsapp_accounts,id',
                'action' => 'required|string|in:cleanup,remove,restore,health_check',
                'status' => 'required|string|in:success,failed,skipped',
                'reason' => 'nullable|string|max:1000',
                'metadata' => 'nullable|array'
            ]);

            $log = DB::table('session_cleanup_logs')->insert([
                'whatsapp_account_id' => $validated['whatsapp_account_id'],
                'action' => $validated['action'],
                'status' => $validated['status'],
                'reason' => $validated['reason'] ?? null,
                'metadata' => isset($validated['metadata']) ? json_encode($validated['metadata']) : null,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cleanup log recorded'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to log cleanup operation', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to log cleanup'
            ], 500);
        }
    }

    /**
     * Get cleanup statistics
     * 
     * GET /api/whatsapp/cleanup-stats
     */
    public function getCleanupStats(Request $request)
    {
        try {
            $stats = [
                // Total cleanups in last 7 days
                'cleanups_last_7_days' => DB::table('session_cleanup_logs')
                    ->where('action', 'cleanup')
                    ->where('status', 'success')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),

                // Last cleanup time
                'last_cleanup_at' => DB::table('session_cleanup_logs')
                    ->where('action', 'cleanup')
                    ->max('created_at'),

                // Stale accounts count (disconnected > 3 days)
                'stale_disconnected_count' => WhatsAppAccount::where('status', 'disconnected')
                    ->where('last_activity_at', '<', now()->subDays(3))
                    ->count(),

                // Failed accounts count (failed > 1 day)
                'stale_failed_count' => WhatsAppAccount::where('status', 'failed')
                    ->where('last_activity_at', '<', now()->subDay())
                    ->count(),

                // Healthy accounts
                'healthy_accounts' => WhatsAppAccount::whereIn('status', ['authenticated', 'connected'])
                    ->where('health_score', '>=', 70)
                    ->count(),

                // Low health accounts
                'low_health_accounts' => WhatsAppAccount::where('health_score', '<', 50)
                    ->count(),

                // Recent logs (last 10)
                'recent_logs' => DB::table('session_cleanup_logs')
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch cleanup stats', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch stats'
            ], 500);
        }
    }
}
