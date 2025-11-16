<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppAccount;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * WhatsApp Account Service
 *
 * Extracted business logic from WhatsAppAccountController
 * Handles CRUD operations and lifecycle management for WhatsApp accounts
 *
 * TASK-SERVICE-1: Extract business logic from WhatsAppAccountController
 */
class AccountService
{
    protected $workspaceId;
    protected $logger;

    public function __construct($workspaceId)
    {
        $this->workspaceId = $workspaceId;
        $this->logger = Log::channel('whatsapp');
    }

    /**
     * Generate unique session ID
     *
     * @return string
     */
    protected function generateSessionId()
    {
        return 'wa_' . Str::random(16) . '_' . time();
    }

    /**
     * List WhatsApp accounts with pagination
     *
     * @param int $perPage
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function list($perPage = 15, $filters = [])
    {
        $query = WhatsAppAccount::where('workspace_id', $this->workspaceId)
            ->with(['workspace:id,name']);

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['provider_type'])) {
            $query->where('provider_type', $filters['provider_type']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('phone_number', 'like', "%{$search}%")
                  ->orWhere('display_name', 'like', "%{$search}%")
                  ->orWhere('session_id', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('is_primary', 'desc')
                   ->orderBy('created_at', 'desc')
                   ->paginate($perPage);
    }

    /**
     * Create new WhatsApp account
     *
     * @param array $data
     * @return object
     */
    public function create(array $data)
    {
        try {
            DB::beginTransaction();

            // Generate unique session ID
            $sessionId = $this->generateSessionId();

            $account = WhatsAppAccount::create([
                'uuid' => Str::uuid(),
                'workspace_id' => $this->workspaceId,
                'session_id' => $sessionId,
                'phone_number' => $data['phone_number'],
                'display_name' => $data['display_name'] ?? $data['phone_number'],
                'provider_type' => $data['provider_type'] ?? 'webjs',
                'status' => $data['status'] ?? 'disconnected',
                'is_primary' => $data['is_primary'] ?? false,
                'settings' => $data['settings'] ?? [],
                'webhook_url' => $data['webhook_url'] ?? null,
                'last_activity_at' => now(),
                'connected_at' => null,
                'disconnected_at' => null,
            ]);

            // If this is set as primary, unset other primary accounts
            if ($data['is_primary'] ?? false) {
                WhatsAppAccount::where('workspace_id', $this->workspaceId)
                    ->where('id', '!=', $account->id)
                    ->update(['is_primary' => false]);
            }

            DB::commit();

            // Clear account cache after successful creation
            $this->clearAccountCache();

            $this->logger->info('WhatsApp account created', [
                'workspace_id' => $this->workspaceId,
                'account_id' => $account->id,
                'session_id' => $sessionId,
                'phone_number' => $data['phone_number'],
                'provider_type' => $account->provider_type,
            ]);

            return (object) [
                'success' => true,
                'data' => $account,
                'message' => 'Account created successfully',
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            $this->logger->error('Failed to create WhatsApp account', [
                'workspace_id' => $this->workspaceId,
                'phone_number' => $data['phone_number'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to create account: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update existing WhatsApp account
     *
     * @param string $uuid
     * @param array $data
     * @return object
     */
    public function update($uuid, array $data)
    {
        try {
            $account = WhatsAppAccount::where('uuid', $uuid)
                ->where('workspace_id', $this->workspaceId)
                ->firstOrFail();

            DB::beginTransaction();

            // Update account fields
            $updateData = [
                'display_name' => $data['display_name'] ?? $account->display_name,
                'webhook_url' => $data['webhook_url'] ?? $account->webhook_url,
                'settings' => $data['settings'] ?? $account->settings,
                'updated_at' => now(),
            ];

            // Handle primary status change
            if (isset($data['is_primary']) && $data['is_primary'] !== $account->is_primary) {
                if ($data['is_primary']) {
                    // Set all other accounts to non-primary
                    WhatsAppAccount::where('workspace_id', $this->workspaceId)
                        ->where('id', '!=', $account->id)
                        ->update(['is_primary' => false]);
                    $updateData['is_primary'] = true;
                } else {
                    $updateData['is_primary'] = false;
                }
            }

            $account->update($updateData);

            DB::commit();

            // Clear account cache after successful update
            $this->clearAccountCache();

            $this->logger->info('WhatsApp account updated', [
                'workspace_id' => $this->workspaceId,
                'account_id' => $account->id,
                'updated_fields' => array_keys($updateData),
            ]);

            // Reload with relationships
            $account->load(['workspace:id,name']);

            return (object) [
                'success' => true,
                'data' => $account,
                'message' => 'Account updated successfully',
            ];

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return (object) [
                'success' => false,
                'message' => 'Account not found',
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            $this->logger->error('Failed to update WhatsApp account', [
                'workspace_id' => $this->workspaceId,
                'account_uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to update account: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete WhatsApp account
     *
     * @param string $uuid
     * @return object
     */
    public function delete($uuid)
    {
        try {
            $account = WhatsAppAccount::where('uuid', $uuid)
                ->where('workspace_id', $this->workspaceId)
                ->firstOrFail();

            DB::beginTransaction();

            // Check if account is connected
            if ($account->status === 'connected') {
                return (object) [
                    'success' => false,
                    'message' => 'Cannot delete connected account. Please disconnect first.',
                ];
            }

            $accountId = $account->id;
            $phoneNumber = $account->phone_number;

            // Delete related data (chats, messages, etc.)
            $account->chats()->delete();

            // Delete the account
            $account->delete();

            DB::commit();

            $this->logger->info('WhatsApp account deleted', [
                'workspace_id' => $this->workspaceId,
                'account_id' => $accountId,
                'phone_number' => $phoneNumber,
            ]);

            return (object) [
                'success' => true,
                'message' => 'Account deleted successfully',
            ];

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return (object) [
                'success' => false,
                'message' => 'Account not found',
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            $this->logger->error('Failed to delete WhatsApp account', [
                'workspace_id' => $this->workspaceId,
                'account_uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to delete account: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get account by UUID
     *
     * @param string $uuid
     * @return WhatsAppAccount|null
     */
    public function getByUuid($uuid)
    {
        return WhatsAppAccount::where('uuid', $uuid)
            ->where('workspace_id', $this->workspaceId)
            ->with(['workspace:id,name'])
            ->first();
    }

    /**
     * Get account by session ID
     *
     * @param string $sessionId
     * @return WhatsAppAccount|null
     */
    public function getBySessionId($sessionId)
    {
        return WhatsAppAccount::where('session_id', $sessionId)
            ->where('workspace_id', $this->workspaceId)
            ->with(['workspace:id,name'])
            ->first();
    }

    /**
     * Get primary account for workspace (cached)
     *
     * @return WhatsAppAccount|null
     */
    public function getPrimary()
    {
        $cacheKey = "workspace:{$this->workspaceId}:primary_account";

        return Cache::remember($cacheKey, 600, function () {
            return WhatsAppAccount::where('workspace_id', $this->workspaceId)
                ->where('is_primary', true)
                ->with(['workspace:id,name'])
                ->first();
        });
    }

    /**
     * Get active accounts (cached)
     *
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActive($limit = null)
    {
        $cacheKey = "workspace:{$this->workspaceId}:active_accounts:" . ($limit ?? 'all');

        return Cache::remember($cacheKey, 300, function () use ($limit) {
            $query = WhatsAppAccount::where('workspace_id', $this->workspaceId)
                ->whereIn('status', ['connected', 'ready'])
                ->with(['workspace:id,name'])
                ->orderBy('last_activity_at', 'desc');

            if ($limit) {
                $query->limit($limit);
            }

            return $query->get();
        });
    }

    /**
     * Get account statistics
     *
     * @return array
     */
    public function getStatistics()
    {
        $stats = WhatsAppAccount::where('workspace_id', $this->workspaceId)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "connected" THEN 1 ELSE 0 END) as connected,
                SUM(CASE WHEN status = "disconnected" THEN 1 ELSE 0 END) as disconnected,
                SUM(CASE WHEN status = "qr_scanning" THEN 1 ELSE 0 END) as qr_scanning,
                SUM(CASE WHEN is_primary = 1 THEN 1 ELSE 0 END) as primary_count,
                SUM(CASE WHEN provider_type = "webjs" THEN 1 ELSE 0 END) as webjs_count,
                SUM(CASE WHEN provider_type = "meta" THEN 1 ELSE 0 END) as meta_count
            ')
            ->first();

        return [
            'total' => (int) $stats->total,
            'connected' => (int) $stats->connected,
            'disconnected' => (int) $stats->disconnected,
            'qr_scanning' => (int) $stats->qr_scanning,
            'primary_count' => (int) $stats->primary_count,
            'by_provider' => [
                'webjs' => (int) $stats->webjs_count,
                'meta' => (int) $stats->meta_count,
            ],
            'connection_rate' => $stats->total > 0 ? round(($stats->connected / $stats->total) * 100, 2) : 0,
        ];
    }

    /**
     * Update account status
     *
     * @param string $uuid
     * @param string $status
     * @param array $metadata
     * @return object
     */
    public function updateStatus($uuid, $status, $metadata = [])
    {
        try {
            $account = WhatsAppAccount::where('uuid', $uuid)
                ->where('workspace_id', $this->workspaceId)
                ->firstOrFail();

            $updateData = [
                'status' => $status,
                'last_activity_at' => now(),
            ];

            // Update timestamp based on status
            switch ($status) {
                case 'connected':
                case 'ready':
                    $updateData['connected_at'] = now();
                    $updateData['disconnected_at'] = null;
                    break;
                case 'disconnected':
                    $updateData['disconnected_at'] = now();
                    break;
            }

            // Add metadata if provided
            if (!empty($metadata)) {
                $updateData['metadata'] = array_merge($account->metadata ?? [], $metadata);
            }

            $account->update($updateData);

            $this->logger->info('WhatsApp account status updated', [
                'workspace_id' => $this->workspaceId,
                'account_id' => $account->id,
                'old_status' => $account->getOriginal('status'),
                'new_status' => $status,
            ]);

            return (object) [
                'success' => true,
                'data' => $account,
                'message' => 'Status updated successfully',
            ];

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return (object) [
                'success' => false,
                'message' => 'Account not found',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to update account status', [
                'workspace_id' => $this->workspaceId,
                'account_uuid' => $uuid,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create WhatsApp account with Node.js session initialization
     *
     * @param array $data
     * @return object
     */
    public function createWithSession($data)
    {
        try {
            // Check session limits first
            if (!$this->canAddSession()) {
                return (object) [
                    'success' => false,
                    'message' => 'You have reached the maximum number of WhatsApp accounts for your plan.',
                ];
            }

            // Create account - handle QR scanning where phone_number is not known yet
            $accountData = array_merge([
                'phone_number' => 'Unknown', // Will be updated after QR scan
                'display_name' => 'Connecting...',
                'status' => 'qr_scanning', // Set status for QR scanning
            ], $data);

            $createResult = $this->create($accountData);
            if (!$createResult->success) {
                return $createResult;
            }

            // Initialize session with Node.js service
            $whatsappClient = new WhatsAppServiceClient();
            $initResult = $whatsappClient->createSession(
                $this->workspaceId,
                $createResult->data->uuid,
                [
                    'phone_number' => $data['phone_number'],
                    'provider_type' => $data['provider_type'] ?? 'webjs',
                ]
            );

            if (!$initResult['success']) {
                // Clean up failed session
                $createResult->data->delete();

                $this->logger->error('Failed to initialize WhatsApp session', [
                    'workspace_id' => $this->workspaceId,
                    'account_uuid' => $createResult->data->uuid,
                    'error' => $initResult['error'] ?? 'Unknown error',
                ]);

                return (object) [
                    'success' => false,
                    'message' => $initResult['error'] ?? 'Failed to initialize session',
                ];
            }

            $this->logger->info('WhatsApp session created and initialized', [
                'workspace_id' => $this->workspaceId,
                'account_uuid' => $createResult->data->uuid,
                'phone_number' => $data['phone_number'],
            ]);

            return (object) [
                'success' => true,
                'data' => $createResult->data,
                'message' => 'WhatsApp session created successfully',
                'nodejs_result' => $initResult,
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to create WhatsApp session with initialization', [
                'workspace_id' => $this->workspaceId,
                'phone_number' => $data['phone_number'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to create WhatsApp session: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete WhatsApp account with Node.js cleanup
     *
     * @param string $uuid
     * @return object
     */
    public function deleteWithCleanup($uuid)
    {
        try {
            $account = WhatsAppAccount::where('uuid', $uuid)
                ->where('workspace_id', $this->workspaceId)
                ->first();

            if (!$account) {
                return (object) [
                    'success' => false,
                    'message' => 'Account not found',
                ];
            }

            // Check if account is connected - cannot delete connected accounts
            if ($account->status === 'connected') {
                return (object) [
                    'success' => false,
                    'message' => 'Cannot delete connected account. Please disconnect first.',
                ];
            }

            DB::beginTransaction();

            // Cleanup Node.js session if exists
            if (in_array($account->status, ['qr_scanning', 'disconnected'])) {
                $whatsappClient = new WhatsAppServiceClient();
                $cleanupResult = $whatsappClient->disconnectSession(
                    $this->workspaceId,
                    $account->uuid
                );

                // Log cleanup result but don't fail if Node.js session doesn't exist
                if (!$cleanupResult['success']) {
                    $this->logger->warning('Failed to cleanup Node.js session during delete', [
                        'workspace_id' => $this->workspaceId,
                        'account_uuid' => $uuid,
                        'error' => $cleanupResult['error'] ?? 'Unknown error',
                    ]);
                }
            }

            $accountId = $account->id;
            $phoneNumber = $account->phone_number;

            // Delete related data (chats, messages, etc.)
            $account->chats()->delete();

            // Delete the account
            $account->delete();

            DB::commit();

            $this->logger->info('WhatsApp account deleted with cleanup', [
                'workspace_id' => $this->workspaceId,
                'account_id' => $accountId,
                'phone_number' => $phoneNumber,
            ]);

            return (object) [
                'success' => true,
                'message' => 'Account deleted successfully',
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            $this->logger->error('Failed to delete WhatsApp account', [
                'workspace_id' => $this->workspaceId,
                'account_uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to delete account: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check if workspace can add more sessions
     *
     * @return bool
     */
    public function canAddSession()
    {
        // Get current account count
        $currentCount = WhatsAppAccount::where('workspace_id', $this->workspaceId)
            ->count();

        // For now, use a reasonable limit. This should be based on subscription plan
        $maxSessions = 10; // This should come from workspace/plan settings

        return $currentCount < $maxSessions;
    }

    /**
     * Format phone number to international format
     *
     * @param string $phone
     * @return string
     */
    public function formatPhoneNumber($phone)
    {
        if (!$phone) {
            return $phone;
        }

        // Remove any non-digit characters except +
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // Format as international number
        if (strlen($phone) >= 10) {
            return '+' . ltrim($phone, '+');
        }

        return $phone;
    }

    /**
     * Get accounts needing reconnection
     *
     * @param int $hoursThreshold
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAccountsNeedingReconnection($hoursThreshold = 24)
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
     * Clear workspace account caches
     *
     * @return void
     */
    protected function clearAccountCache(): void
    {
        $patterns = [
            "workspace:{$this->workspaceId}:active_accounts:*",
            "workspace:{$this->workspaceId}:primary_account",
            "workspace:{$this->workspaceId}:account_statistics",
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                // Handle wildcard cache clearing
                $keys = Cache::getStore()->getPrefix() . $pattern;
                foreach (Cache::getRedis()->keys($keys) as $key) {
                    Cache::forget(str_replace(Cache::getStore()->getPrefix(), '', $key));
                }
            } else {
                Cache::forget($pattern);
            }
        }
    }

    /**
     * Get workspace account statistics (cached)
     *
     * @return array
     */
    public function getAccountStatistics()
    {
        $cacheKey = "workspace:{$this->workspaceId}:account_statistics";

        return Cache::remember($cacheKey, 120, function () {
            $accounts = WhatsAppAccount::where('workspace_id', $this->workspaceId)->get();

            return [
                'total' => $accounts->count(),
                'connected' => $accounts->where('status', 'connected')->count(),
                'disconnected' => $accounts->where('status', 'disconnected')->count(),
                'qr_scanning' => $accounts->where('status', 'qr_scanning')->count(),
                'primary_set' => $accounts->where('is_primary', true)->count(),
                'last_updated' => now()->toISOString(),
            ];
        });
    }
}