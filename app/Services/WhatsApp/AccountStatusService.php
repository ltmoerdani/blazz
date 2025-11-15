<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

/**
 * WhatsApp Account Status Service
 *
 * Extracted business logic from WhatsAppAccountStatusController
 * Handles status operations, connection management, and health monitoring
 *
 * TASK-SERVICE-2: Extract business logic from WhatsAppAccountStatusController
 */
class AccountStatusService
{
    protected $workspaceId;
    protected $logger;
    protected $nodeServiceUrl;

    public function __construct($workspaceId)
    {
        $this->workspaceId = $workspaceId;
        $this->logger = Log::channel('whatsapp');
        $this->nodeServiceUrl = config('whatsapp.node_service_url', 'http://localhost:3000');
    }

    /**
     * Set account as primary
     *
     * @param string $uuid
     * @return object
     */
    public function setPrimary($uuid)
    {
        try {
            $account = WhatsAppAccount::where('uuid', $uuid)
                ->where('workspace_id', $this->workspaceId)
                ->firstOrFail();

            // Start database transaction
            DB::beginTransaction();

            // Set all accounts in workspace as non-primary
            WhatsAppAccount::where('workspace_id', $this->workspaceId)
                ->where('id', '!=', $account->id)
                ->update(['is_primary' => false, 'updated_at' => now()]);

            // Set selected account as primary
            $account->update([
                'is_primary' => true,
                'updated_at' => now()
            ]);

            DB::commit();

            $this->logger->info('Primary account set', [
                'workspace_id' => $this->workspaceId,
                'account_id' => $account->id,
                'phone_number' => $account->phone_number,
            ]);

            return (object) [
                'success' => true,
                'data' => $account,
                'message' => 'Primary account updated successfully',
            ];

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return (object) [
                'success' => false,
                'message' => 'Account not found',
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            $this->logger->error('Failed to set primary account', [
                'workspace_id' => $this->workspaceId,
                'account_uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to set primary account: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Disconnect WhatsApp account
     *
     * @param string $uuid
     * @return object
     */
    public function disconnect($uuid)
    {
        try {
            $account = WhatsAppAccount::where('uuid', $uuid)
                ->where('workspace_id', $this->workspaceId)
                ->firstOrFail();

            // Only disconnect if currently connected
            if (!in_array($account->status, ['connected', 'ready'])) {
                return (object) [
                    'success' => false,
                    'message' => 'Account is not connected',
                ];
            }

            // Call Node.js service to disconnect session
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-API-Key' => config('whatsapp.node_api_key'),
                ])
                ->delete("{$this->nodeServiceUrl}/api/sessions/{$account->session_id}", [
                    'workspace_id' => $this->workspaceId,
                    'api_key' => config('whatsapp.node_api_key'),
                ]);

            if (!$response->successful()) {
                throw new \Exception('Node.js service disconnect failed: ' . $response->body());
            }

            // Update account status
            $account->update([
                'status' => 'disconnected',
                'disconnected_at' => now(),
                'last_activity_at' => now(),
                'updated_at' => now(),
            ]);

            $this->logger->info('WhatsApp account disconnected', [
                'workspace_id' => $this->workspaceId,
                'account_id' => $account->id,
                'session_id' => $account->session_id,
            ]);

            return (object) [
                'success' => true,
                'data' => $account,
                'message' => 'Account disconnected successfully',
            ];

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return (object) [
                'success' => false,
                'message' => 'Account not found',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to disconnect WhatsApp account', [
                'workspace_id' => $this->workspaceId,
                'account_uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to disconnect account: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Reconnect WhatsApp account
     *
     * @param string $uuid
     * @return object
     */
    public function reconnect($uuid)
    {
        try {
            $account = WhatsAppAccount::where('uuid', $uuid)
                ->where('workspace_id', $this->workspaceId)
                ->firstOrFail();

            // First disconnect if connected
            if (in_array($account->status, ['connected', 'ready'])) {
                $disconnectResult = $this->disconnect($uuid);
                if (!$disconnectResult->success) {
                    return $disconnectResult;
                }
            }

            // Call Node.js service to reconnect session
            $response = Http::timeout(60)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-API-Key' => config('whatsapp.node_api_key'),
                ])
                ->post("{$this->nodeServiceUrl}/api/sessions/{$account->session_id}/reconnect", [
                    'workspace_id' => $this->workspaceId,
                    'account_id' => $account->id,
                    'api_key' => config('whatsapp.node_api_key'),
                ]);

            if (!$response->successful()) {
                throw new \Exception('Node.js service reconnect failed: ' . $response->body());
            }

            // Update account status to connecting
            $account->update([
                'status' => 'qr_scanning',
                'connected_at' => null,
                'disconnected_at' => now(),
                'last_activity_at' => now(),
                'updated_at' => now(),
            ]);

            $this->logger->info('WhatsApp account reconnected', [
                'workspace_id' => $this->workspaceId,
                'account_id' => $account->id,
                'session_id' => $account->session_id,
            ]);

            return (object) [
                'success' => true,
                'data' => $account,
                'message' => 'Reconnection initiated successfully',
            ];

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return (object) [
                'success' => false,
                'message' => 'Account not found',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to reconnect WhatsApp account', [
                'workspace_id' => $this->workspaceId,
                'account_uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to reconnect account: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Regenerate QR code for account
     *
     * @param string $uuid
     * @return object
     */
    public function regenerateQR($uuid)
    {
        try {
            $account = WhatsAppAccount::where('uuid', $uuid)
                ->where('workspace_id', $this->workspaceId)
                ->firstOrFail();

            // Call Node.js service to regenerate QR
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-API-Key' => config('whatsapp.node_api_key'),
                ])
                ->post("{$this->nodeServiceUrl}/api/sessions/{$account->session_id}/regenerate-qr", [
                    'workspace_id' => $this->workspaceId,
                    'account_id' => $account->id,
                    'api_key' => config('whatsapp.node_api_key'),
                ]);

            if (!$response->successful()) {
                throw new \Exception('Node.js service QR regeneration failed: ' . $response->body());
            }

            $result = $response->json();

            // Update account status
            $account->update([
                'status' => 'qr_scanning',
                'last_activity_at' => now(),
                'updated_at' => now(),
            ]);

            $this->logger->info('QR code regenerated', [
                'workspace_id' => $this->workspaceId,
                'account_id' => $account->id,
                'session_id' => $account->session_id,
            ]);

            return (object) [
                'success' => true,
                'data' => [
                    'qr_code' => $result['qr_code'] ?? null,
                    'expires_in' => $result['expires_in'] ?? 300,
                    'account' => $account,
                ],
                'message' => 'QR code regenerated successfully',
            ];

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return (object) [
                'success' => false,
                'message' => 'Account not found',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to regenerate QR code', [
                'workspace_id' => $this->workspaceId,
                'account_uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to regenerate QR code: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Perform health check on account
     *
     * @param string $uuid
     * @return object
     */
    public function healthCheck($uuid)
    {
        try {
            $account = WhatsAppAccount::where('uuid', $uuid)
                ->where('workspace_id', $this->workspaceId)
                ->firstOrFail();

            // Call Node.js service to check session status
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-API-Key' => config('whatsapp.node_api_key'),
                ])
                ->get("{$this->nodeServiceUrl}/api/sessions/{$account->session_id}/status", [
                    'workspace_id' => $this->workspaceId,
                    'api_key' => config('whatsapp.node_api_key'),
                ]);

            if (!$response->successful()) {
                // Update status to disconnected if service returns error
                $account->update([
                    'status' => 'disconnected',
                    'disconnected_at' => now(),
                    'last_activity_at' => now(),
                    'updated_at' => now(),
                ]);

                return (object) [
                    'success' => false,
                    'data' => [
                        'account' => $account,
                        'node_status' => 'unreachable',
                        'last_check' => now(),
                    ],
                    'message' => 'Node.js service unreachable',
                ];
            }

            $nodeStatus = $response->json();

            // Update account status based on node response
            $this->updateAccountStatusFromNode($account, $nodeStatus);

            $this->logger->debug('Health check completed', [
                'workspace_id' => $this->workspaceId,
                'account_id' => $account->id,
                'node_status' => $nodeStatus['status'] ?? 'unknown',
            ]);

            return (object) [
                'success' => true,
                'data' => [
                    'account' => $account->fresh(),
                    'node_status' => $nodeStatus,
                    'last_check' => now(),
                ],
                'message' => 'Health check completed',
            ];

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return (object) [
                'success' => false,
                'message' => 'Account not found',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Health check failed', [
                'workspace_id' => $this->workspaceId,
                'account_uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Health check failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update account status from Node.js response
     *
     * @param WhatsAppAccount $account
     * @param array $nodeStatus
     * @return void
     */
    protected function updateAccountStatusFromNode($account, $nodeStatus)
    {
        $status = $nodeStatus['status'] ?? 'unknown';
        $lastActivity = $nodeStatus['last_activity'] ?? null;

        $updateData = [
            'last_activity_at' => now(),
            'updated_at' => now(),
        ];

        switch ($status) {
            case 'connected':
            case 'ready':
                $updateData['status'] = 'connected';
                if (!$account->connected_at) {
                    $updateData['connected_at'] = now();
                }
                $updateData['disconnected_at'] = null;
                break;
            case 'disconnected':
                $updateData['status'] = 'disconnected';
                $updateData['disconnected_at'] = now();
                break;
            case 'qr_scanning':
                $updateData['status'] = 'qr_scanning';
                break;
        }

        if ($lastActivity) {
            $updateData['last_activity_at'] = Carbon::parse($lastActivity);
        }

        $account->update($updateData);
    }

    /**
     * Get status summary for all accounts in workspace
     *
     * @return array
     */
    public function getStatusSummary()
    {
        $accounts = WhatsAppAccount::where('workspace_id', $this->workspaceId)
            ->selectRaw('
                status,
                COUNT(*) as count,
                SUM(CASE WHEN is_primary = 1 THEN 1 ELSE 0 END) as primary_count
            ')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $total = WhatsAppAccount::where('workspace_id', $this->workspaceId)->count();

        return [
            'total' => $total,
            'connected' => $accounts->get('connected', (object) ['count' => 0])->count,
            'disconnected' => $accounts->get('disconnected', (object) ['count' => 0])->count,
            'qr_scanning' => $accounts->get('qr_scanning', (object) ['count' => 0])->count,
            'connecting' => $accounts->get('connecting', (object) ['count' => 0])->count,
            'primary_connected' => $accounts->get('connected', (object) ['primary_count' => 0])->primary_count,
            'connection_rate' => $total > 0 ? round(($accounts->get('connected', (object) ['count' => 0])->count / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get accounts with connection issues
     *
     * @param int $hoursThreshold
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAccountsWithIssues($hoursThreshold = 2)
    {
        $threshold = Carbon::now()->subHours($hoursThreshold);

        return WhatsAppAccount::where('workspace_id', $this->workspaceId)
            ->where('status', '!=', 'connected')
            ->where(function($query) use ($threshold) {
                $query->where('last_activity_at', '<', $threshold)
                      ->orWhereNull('last_activity_at');
            })
            ->orderBy('last_activity_at', 'asc')
            ->get();
    }

    /**
     * Batch status update for multiple accounts
     *
     * @param array $accountUuids
     * @param string $status
     * @return object
     */
    public function batchStatusUpdate($accountUuids, $status)
    {
        try {
            DB::beginTransaction();

            $updated = WhatsAppAccount::where('workspace_id', $this->workspaceId)
                ->whereIn('uuid', $accountUuids)
                ->update([
                    'status' => $status,
                    'last_activity_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::commit();

            $this->logger->info('Batch status update completed', [
                'workspace_id' => $this->workspaceId,
                'updated_count' => $updated,
                'new_status' => $status,
            ]);

            return (object) [
                'success' => true,
                'data' => [
                    'updated_count' => $updated,
                    'new_status' => $status,
                ],
                'message' => "Updated {$updated} accounts to {$status}",
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            $this->logger->error('Batch status update failed', [
                'workspace_id' => $this->workspaceId,
                'account_uuids' => $accountUuids,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Batch update failed: ' . $e->getMessage(),
            ];
        }
    }
}