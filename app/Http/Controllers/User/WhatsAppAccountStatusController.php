<?php

namespace App\Http\Controllers\User;

use App\Events\WhatsAppQRGeneratedEvent;
use App\Events\WhatsAppAccountStatusChangedEvent;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppAccount;
use App\Services\ProviderSelector;
use App\Services\Adapters\WebJSAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WhatsAppAccountStatusController extends Controller
{
    /**
     * Set a session as primary
     */
    public function setPrimary(string $uuid)
    {
        $workspaceId = session('current_workspace');

        $session = WhatsAppAccount::where('uuid', $uuid)
            ->where('workspace_id', $workspaceId)
            ->firstOrFail();

        // Remove primary flag from all other accounts in workspace
        WhatsAppAccount::forWorkspace($workspaceId)
            ->where('id', '!=', $session->id)
            ->update(['is_primary' => false]);

        // Set this session as primary
        $session->update(['is_primary' => true]);

        // Broadcast status change
        broadcast(new WhatsAppAccountStatusChangedEvent(
            $session->session_id,
            $session->status,
            $workspaceId,
            $session->phone_number,
            [
                'action' => 'set_primary',
                'timestamp' => now()->toISOString()
            ]
        ));

        return response()->json([
            'success' => true,
            'message' => 'Session set as primary successfully'
        ]);
    }

    /**
     * Disconnect a session
     */
    public function disconnect(string $uuid)
    {
        $workspaceId = session('current_workspace');

        $session = WhatsAppAccount::where('uuid', $uuid)
            ->where('workspace_id', $workspaceId)
            ->firstOrFail();

        try {
            // If session is qr_scanning (not yet connected), just update status
            if ($session->status === 'qr_scanning') {
                $session->update([
                    'status' => 'disconnected',
                    'is_active' => false,
                    'qr_code' => null,
                    'qr_expires_at' => null,
                    'last_activity_at' => now()
                ]);

                // Fire status changed event
                event(new WhatsAppAccountStatusChangedEvent(
                    $session->session_id,
                    'disconnected',
                    $workspaceId,
                    $session->phone_number,
                    [
                        'action' => 'disconnect',
                        'uuid' => $session->uuid,
                        'timestamp' => now()->toISOString()
                    ]
                ));

                return response()->json([
                    'success' => true,
                    'message' => 'Session disconnected successfully'
                ]);
            }

            // Get provider adapter
            $providerSelector = app(ProviderSelector::class);
            try {
                $provider = $providerSelector->selectProvider($workspaceId, $session->provider_type);
            } catch (\Exception $e) {
                $provider = null;
            }

            if (!$provider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Provider not available for session type: ' . $session->provider_type
                ], 400);
            }

            // Disconnect from provider
            if ($session->provider_type === 'webjs') {
                $webjsAdapter = new WebJSAdapter($workspaceId, $session);
                $disconnectResult = $webjsAdapter->disconnectSession();

                if (!$disconnectResult['success']) {
                    Log::error('Provider disconnection failed', [
                        'session_uuid' => $uuid,
                        'provider_type' => $session->provider_type,
                        'error' => $disconnectResult['error'] ?? 'Unknown error'
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to disconnect from provider: ' . ($disconnectResult['error'] ?? 'Unknown error')
                    ], 500);
                }
            }

            // Update session status
            $session->update([
                'status' => 'disconnected',
                'is_active' => false,
                'qr_code' => null,
                'qr_expires_at' => null,
                'last_activity_at' => now()
            ]);

            // Fire status changed event
            event(new WhatsAppAccountStatusChangedEvent(
                $session->session_id,
                'disconnected',
                $workspaceId,
                $session->phone_number,
                [
                    'action' => 'disconnect',
                    'uuid' => $session->uuid,
                    'timestamp' => now()->toISOString()
                ]
            ));

            return response()->json([
                'success' => true,
                'message' => 'Session disconnected successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to disconnect WhatsApp session', [
                'error' => $e->getMessage(),
                'session_uuid' => $uuid,
                'workspace_id' => $workspaceId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to disconnect session: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reconnect a session
     */
    public function reconnect(string $uuid)
    {
        $workspaceId = session('current_workspace');
        $response = null;

        $session = WhatsAppAccount::where('uuid', $uuid)
            ->where('workspace_id', $workspaceId)
            ->firstOrFail();

        if ($session->status === 'connected') {
            $response = response()->json([
                'success' => false,
                'message' => 'Session is already connected'
            ], 400);
        } else {
            try {
                $adapter = new WebJSAdapter($workspaceId, $session);
                $result = $adapter->reconnectSession();

                if (!$result['success']) {
                    $response = response()->json([
                        'success' => false,
                        'message' => $result['error'] ?? 'Failed to reconnect session'
                    ], 500);
                } else {
                    $response = response()->json([
                        'success' => true,
                        'message' => 'Reconnection initiated. Please scan QR code.',
                        'qr_code' => $result['qr_code'] ?? null,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to reconnect WhatsApp session', [
                    'workspace_id' => $workspaceId,
                    'session_id' => $session->session_id,
                    'error' => $e->getMessage(),
                ]);

                $response = response()->json([
                    'success' => false,
                    'message' => 'Failed to reconnect session: ' . $e->getMessage()
                ], 500);
            }
        }

        return $response;
    }

    /**
     * Regenerate QR code for a session
     */
    public function regenerateQR(string $uuid)
    {
        $workspaceId = session('current_workspace');

        $session = WhatsAppAccount::where('uuid', $uuid)
            ->where('workspace_id', $workspaceId)
            ->firstOrFail();

        try {
            // Get provider adapter
            $providerSelector = app(ProviderSelector::class);
            try {
                $provider = $providerSelector->selectProvider($workspaceId, $session->provider_type);
            } catch (\Exception $e) {
                $provider = null;
            }

            if (!$provider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Provider not available for session type: ' . $session->provider_type
                ], 400);
            }

            // Generate new QR code
            if ($session->provider_type === 'webjs') {
                $webjsAdapter = new WebJSAdapter($workspaceId, $session);
                $qrResult = $webjsAdapter->regenerateQR();

                if (!$qrResult['success']) {
                    Log::error('Failed to generate QR code', [
                        'session_uuid' => $uuid,
                        'provider_type' => $session->provider_type,
                        'error' => $qrResult['error'] ?? 'Unknown error'
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to generate QR code: ' . ($qrResult['error'] ?? 'Unknown error')
                    ], 500);
                }

                // Update session with new QR code
                $session->update([
                    'qr_code' => $qrResult['qr_code'],
                    'qr_expires_at' => now()->addMinutes(5),
                    'status' => 'qr_generated',
                    'last_activity_at' => now()
                ]);

                // Fire QR generated event
                event(new WhatsAppQRGeneratedEvent(
                    $qrResult['qr_code'],
                    300, // 5 minutes in seconds
                    $workspaceId,
                    $session->session_id
                ));

                return response()->json([
                    'success' => true,
                    'message' => 'QR code regenerated successfully',
                    'qr_code' => $qrResult['qr_code'],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'QR generation not supported for this provider type'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Failed to regenerate QR code', [
                'error' => $e->getMessage(),
                'session_uuid' => $uuid,
                'workspace_id' => $workspaceId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to regenerate QR code: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get session statistics and health information
     */
    public function statistics(string $uuid)
    {
        $workspaceId = session('current_workspace');

        try {
            $session = WhatsAppAccount::where('uuid', $uuid)
                ->where('workspace_id', $workspaceId)
                ->firstOrFail();

            // Get provider adapter for additional stats
            $providerSelector = app(ProviderSelector::class);
            try {
                $provider = $providerSelector->selectProvider($workspaceId, $session->provider_type);
            } catch (\Exception $e) {
                $provider = null;
            }

            $statistics = [
                'session_id' => $session->id,
                'uuid' => $session->uuid,
                'phone_number' => $session->phone_number,
                'provider_type' => $session->provider_type,
                'status' => $session->status,
                'is_primary' => $session->is_primary,
                'is_active' => $session->is_active,
                'created_at' => $session->created_at,
                'last_connected_at' => $session->last_connected_at,
                'last_activity_at' => $session->last_activity_at,
                'health_score' => $session->health_score ?? 0,
                'qr_expires_at' => $session->qr_expires_at,
                'metadata' => $session->metadata,
            ];

            // Calculate uptime and activity metrics
            $now = now();
            if ($session->created_at) {
                $statistics['days_active'] = $session->created_at->diffInDays($now);
                $statistics['hours_since_last_activity'] = $session->last_activity_at
                    ? $session->last_activity_at->diffInHours($now)
                    : null;
            }

            // Get provider-specific statistics
            if ($provider) {
                try {
                    $providerStats = $provider->getHealthInfo();
                    if ($providerStats) {
                        $statistics['provider_stats'] = $providerStats;
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to get provider statistics', [
                        'session_uuid' => $uuid,
                        'provider_type' => $session->provider_type,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Get message statistics (placeholder - implement based on your message storage)
            $statistics['message_stats'] = [
                'total_messages' => $this->getTotalMessageCount($session),
                'messages_24h' => $this->getMessageCountInPeriod($session, 24),
                'messages_7d' => $this->getMessageCountInPeriod($session, 168), // 7 days
                'last_message_at' => $this->getLastMessageTime($session),
            ];

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get WhatsApp session statistics', [
                'error' => $e->getMessage(),
                'session_uuid' => $uuid,
                'workspace_id' => $workspaceId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get session statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get total message count for session
     */
    private function getTotalMessageCount($session)
    {
        // Implement based on your message storage structure
        // This is a placeholder - adjust according to your database schema
        try {
            return \App\Models\Chat::where('workspace_id', $session->workspace_id)
                ->whereHas('contact', function ($query) use ($session) {
                    $query->where('phone_number', $session->phone_number);
                })
                ->count();
        } catch (\Exception $e) {
            Log::warning('Failed to get total message count', [
                'session_id' => $session->id,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get message count in specific period (hours)
     */
    private function getMessageCountInPeriod($session, $hours)
    {
        // Implement based on your message storage structure
        try {
            $cutoffTime = now()->subHours($hours);
            return \App\Models\Chat::where('workspace_id', $session->workspace_id)
                ->whereHas('contact', function ($query) use ($session) {
                    $query->where('phone_number', $session->phone_number);
                })
                ->where('created_at', '>=', $cutoffTime)
                ->count();
        } catch (\Exception $e) {
            Log::warning('Failed to get message count in period', [
                'session_id' => $session->id,
                'hours' => $hours,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get last message time for session
     */
    private function getLastMessageTime($session)
    {
        try {
            $lastMessage = \App\Models\Chat::where('workspace_id', $session->workspace_id)
                ->whereHas('contact', function ($query) use ($session) {
                    $query->where('phone_number', $session->phone_number);
                })
                ->latest()
                ->first();

            return $lastMessage ? $lastMessage->created_at : null;
        } catch (\Exception $e) {
            Log::warning('Failed to get last message time', [
                'session_id' => $session->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}