<?php

namespace App\Services\WhatsApp;

use App\Events\WhatsAppQRGeneratedEvent;
use App\Events\WhatsAppAccountStatusChangedEvent;
use App\Models\WhatsAppAccount;
use App\Services\ProviderSelector;
use App\Services\Adapters\WebJSAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WhatsAppAccountService
{
    private $providerSelector;
    private $workspaceId;

    public function __construct(ProviderSelector $providerSelector, $workspaceId = null)
    {
        $this->providerSelector = $providerSelector;
        $this->workspaceId = $workspaceId ?: account('current_workspace');
    }

    /**
     * Get all WhatsApp accounts for the current workspace
     */
    public function getWorkspaceSessions()
    {
        return WhatsAppAccount::forWorkspace($this->workspaceId)
            ->orderBy('is_primary', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($account) {
                return $this->formatSessionData($account);
            });
    }

    /**
     * Create a new WhatsApp account
     */
    public function createSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'account_name' => 'nullable|string|max:255',
            'provider_type' => 'required|string|in:webjs,meta',
            'is_primary' => 'boolean'
        ]);

        if ($validator->fails()) {
            return (object) [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ];
        }

        $validated = $validator->validated();

        // Check if account with this phone number already exists
        $existingSession = WhatsAppAccount::where('workspace_id', $this->workspaceId)
            ->where('phone_number', $validated['phone_number'])
            ->first();

        if ($existingSession) {
            return (object) [
                'success' => false,
                'message' => 'Session with this phone number already exists'
            ];
        }

        try {
            $account = WhatsAppAccount::create([
                'uuid' => Str::uuid()->toString(),
                'workspace_id' => $this->workspaceId,
                'account_name' => $validated['account_name'] ?? $validated['phone_number'],
                'phone_number' => $validated['phone_number'],
                'provider_type' => $validated['provider_type'],
                'is_primary' => $validated['is_primary'] ?? false,
                'status' => 'disconnected',
                'is_active' => false,
            ]);

            // If setting as primary, unset other primary accounts
            if ($account->is_primary) {
                WhatsAppAccount::where('workspace_id', $this->workspaceId)
                    ->where('id', '!=', $account->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            return (object) [
                'success' => true,
                'message' => 'Session created successfully',
                'data' => $this->formatSessionData($account)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create WhatsApp account', [
                'error' => $e->getMessage(),
                'workspace_id' => $this->workspaceId,
                'phone_number' => $validated['phone_number']
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to create account: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get account details by UUID
     */
    public function getSession(string $uuid)
    {
        $account = WhatsAppAccount::forWorkspace($this->workspaceId)
            ->where('uuid', $uuid)
            ->first();

        if (!$account) {
            return (object) [
                'success' => false,
                'message' => 'Session not found'
            ];
        }

        return (object) [
            'success' => true,
            'data' => $this->formatSessionData($account)
        ];
    }

    /**
     * Set account as primary
     */
    public function setPrimary(string $uuid)
    {
        $account = WhatsAppAccount::forWorkspace($this->workspaceId)
            ->where('uuid', $uuid)
            ->first();

        if (!$account) {
            return (object) [
                'success' => false,
                'message' => 'Session not found'
            ];
        }

        try {
            // Unset other primary accounts
            WhatsAppAccount::where('workspace_id', $this->workspaceId)
                ->where('id', '!=', $account->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);

            // Set this account as primary
            $account->update(['is_primary' => true]);

            return (object) [
                'success' => true,
                'message' => 'Session set as primary successfully',
                'data' => $this->formatSessionData($account)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to set primary account', [
                'error' => $e->getMessage(),
                'account_uuid' => $uuid,
                'workspace_id' => $this->workspaceId
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to set primary account: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Disconnect account
     */
    public function disconnectSession(string $uuid)
    {
        $account = WhatsAppAccount::forWorkspace($this->workspaceId)
            ->where('uuid', $uuid)
            ->first();

        if (!$account) {
            return (object) [
                'success' => false,
                'message' => 'Session not found'
            ];
        }

        try {
            // Get provider adapter
            try {
                $provider = $this->providerSelector->selectProvider($this->workspaceId, $account->provider_type);
            } catch (\Exception $e) {
                $provider = null;
            }

            if ($provider && $account->provider_type === 'webjs') {
                // For WebJS adapter, create new instance with account
                $webjsAdapter = new WebJSAdapter($this->workspaceId, $account);
                $result = $webjsAdapter->disconnectSession();

                if (!$result['success']) {
                    Log::warning('Provider disconnection failed', [
                        'account_uuid' => $uuid,
                        'provider_type' => $account->provider_type,
                        'error' => $result['error'] ?? 'Unknown error'
                    ]);
                }
            }

            // Update account status
            $account->update([
                'status' => 'disconnected',
                'is_active' => false,
                'account_id' => null,
                'last_activity_at' => now()
            ]);

            // Fire status changed event
            event(new WhatsAppAccountStatusChangedEvent(
                $account->account_id,
                'disconnected',
                $this->workspaceId,
                $account->phone_number,
                [
                    'action' => 'account_disconnected',
                    'uuid' => $account->uuid,
                    'timestamp' => now()->toISOString()
                ]
            ));

            return (object) [
                'success' => true,
                'message' => 'Session disconnected successfully',
                'data' => $this->formatSessionData($account)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to disconnect account', [
                'error' => $e->getMessage(),
                'account_uuid' => $uuid,
                'workspace_id' => $this->workspaceId
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to disconnect account: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete account
     */
    public function deleteSession(string $uuid)
    {
        $account = WhatsAppAccount::forWorkspace($this->workspaceId)
            ->where('uuid', $uuid)
            ->first();

        if (!$account) {
            return (object) [
                'success' => false,
                'message' => 'Session not found'
            ];
        }

        try {
            // Disconnect from provider first
            if ($account->account_id) {
                $this->disconnectSession($uuid);
            }

            // Delete the account
            $account->delete();

            return (object) [
                'success' => true,
                'message' => 'Session deleted successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to delete account', [
                'error' => $e->getMessage(),
                'account_uuid' => $uuid,
                'workspace_id' => $this->workspaceId
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to delete account: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Reconnect account
     */
    public function reconnectSession(string $uuid)
    {
        $account = WhatsAppAccount::forWorkspace($this->workspaceId)
            ->where('uuid', $uuid)
            ->first();

        if (!$account) {
            return (object) [
                'success' => false,
                'message' => 'Session not found'
            ];
        }

        try {
            // Reset account status
            $account->update([
                'status' => 'connecting',
                'is_active' => false,
                'account_id' => null,
                'qr_code' => null
            ]);

            // Fire status changed event
            event(new WhatsAppAccountStatusChangedEvent(
                $account->account_id,
                'connecting',
                $this->workspaceId,
                $account->phone_number,
                [
                    'action' => 'account_reconnecting',
                    'uuid' => $account->uuid,
                    'timestamp' => now()->toISOString()
                ]
            ));

            return (object) [
                'success' => true,
                'message' => 'Session reconnection initiated',
                'data' => $this->formatSessionData($account)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to reconnect account', [
                'error' => $e->getMessage(),
                'account_uuid' => $uuid,
                'workspace_id' => $this->workspaceId
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to reconnect account: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Regenerate QR code for account
     */
    public function regenerateQR(string $uuid)
    {
        $account = WhatsAppAccount::forWorkspace($this->workspaceId)
            ->where('uuid', $uuid)
            ->first();

        if (!$account) {
            return (object) [
                'success' => false,
                'message' => 'Session not found'
            ];
        }

        try {
            // Get provider adapter
            try {
                $provider = $this->providerSelector->selectProvider($this->workspaceId, $account->provider_type);
            } catch (\Exception $e) {
                $provider = null;
            }

            if (!$provider && $account->provider_type === 'webjs') {
                // For WebJS adapter, create new instance with account
                $webjsAdapter = new WebJSAdapter($this->workspaceId, $account);
                $result = $webjsAdapter->regenerateQR();

                if (!$result['success']) {
                    return (object) [
                        'success' => false,
                        'message' => 'Failed to generate QR code: ' . ($result['error'] ?? 'Unknown error')
                    ];
                }

                // Update account with new QR code
                $account->update([
                    'qr_code' => $result['qr_code'],
                    'qr_expires_at' => now()->addMinutes(5),
                    'status' => 'qr_generated'
                ]);

                // Fire QR generated event
                event(new WhatsAppQRGeneratedEvent(
                    $result['qr_code'],
                    300, // 5 minutes in seconds
                    $this->workspaceId,
                    $account->account_id
                ));

                return (object) [
                    'success' => true,
                    'message' => 'QR code regenerated successfully',
                    'data' => [
                        'qr_code' => $result['qr_code'],
                        'qr_expires_at' => $account->qr_expires_at
                    ]
                ];
            } elseif (!$provider) {
                return (object) [
                    'success' => false,
                    'message' => 'Provider not available'
                ];
            }

            return (object) [
                'success' => false,
                'message' => 'QR generation not supported for this provider type'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to regenerate QR code', [
                'error' => $e->getMessage(),
                'account_uuid' => $uuid,
                'workspace_id' => $this->workspaceId
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to regenerate QR code: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get account statistics
     */
    public function getSessionStatistics(string $uuid)
    {
        $account = WhatsAppAccount::forWorkspace($this->workspaceId)
            ->where('uuid', $uuid)
            ->first();

        if (!$account) {
            return (object) [
                'success' => false,
                'message' => 'Session not found'
            ];
        }

        try {
            // Calculate statistics
            $now = now();
            $stats = [
                'account_id' => $account->id,
                'uuid' => $account->uuid,
                'phone_number' => $account->phone_number,
                'status' => $account->status,
                'is_active' => $account->is_active,
                'is_primary' => $account->is_primary,
                'provider_type' => $account->provider_type,
                'created_at' => $account->created_at,
                'last_connected_at' => $account->last_connected_at,
                'last_activity_at' => $account->last_activity_at,
                'health_score' => $account->health_score ?? 0,
                'uptime_percentage' => $this->calculateUptimePercentage($account),
                'days_active' => $account->created_at->diffInDays($now),
                'total_messages' => $this->getTotalMessages($account),
                'last_24h_messages' => $this->getMessagesInPeriod($account, 24),
                'last_7d_messages' => $this->getMessagesInPeriod($account, 168), // 7 days
            ];

            return (object) [
                'success' => true,
                'data' => $stats
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get account statistics', [
                'error' => $e->getMessage(),
                'account_uuid' => $uuid,
                'workspace_id' => $this->workspaceId
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Format account data for API response
     */
    private function formatSessionData($account)
    {
        return [
            'id' => $account->id,
            'uuid' => $account->uuid,
            'account_id' => $account->account_id,
            'phone_number' => $account->phone_number,
            'account_name' => $account->account_name,
            'provider_type' => $account->provider_type,
            'status' => $account->status,
            'is_primary' => $account->is_primary,
            'is_active' => $account->is_active,
            'qr_code' => $account->qr_code,
            'qr_expires_at' => $account->qr_expires_at,
            'last_activity_at' => $account->last_activity_at,
            'last_connected_at' => $account->last_connected_at,
            'health_score' => $account->health_score,
            'formatted_phone_number' => $account->formatted_phone_number,
            'created_at' => $account->created_at,
        ];
    }

    /**
     * Calculate account uptime percentage
     */
    private function calculateUptimePercentage($account)
    {
        if (!$account->last_connected_at || !$account->created_at) {
            return 0;
        }

        $totalTime = $account->created_at->diffInSeconds(now());
        $activeTime = $account->last_connected_at->diffInSeconds(now());

        return $totalTime > 0 ? ($activeTime / $totalTime) * 100 : 0;
    }

    /**
     * Get total message count for account
     */
    private function getTotalMessages($account)
    {
        // This would typically query the messages table
        // Implementation depends on your message storage structure
        return 0; // Placeholder
    }

    /**
     * Get message count in specific period (hours)
     */
    private function getMessagesInPeriod($account, $hours)
    {
        // This would typically query the messages table for the period
        // Implementation depends on your message storage structure
        return 0; // Placeholder
    }
}