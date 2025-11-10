<?php

namespace App\Services\WhatsApp;

use App\Events\WhatsAppQRGeneratedEvent;
use App\Events\WhatsAppSessionStatusChangedEvent;
use App\Models\WhatsAppSession;
use App\Services\ProviderSelector;
use App\Services\Adapters\WebJSAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WhatsAppSessionService
{
    private $providerSelector;
    private $workspaceId;

    public function __construct(ProviderSelector $providerSelector, $workspaceId = null)
    {
        $this->providerSelector = $providerSelector;
        $this->workspaceId = $workspaceId ?: session('current_workspace');
    }

    /**
     * Get all WhatsApp sessions for the current workspace
     */
    public function getWorkspaceSessions()
    {
        return WhatsAppSession::forWorkspace($this->workspaceId)
            ->orderBy('is_primary', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($session) {
                return $this->formatSessionData($session);
            });
    }

    /**
     * Create a new WhatsApp session
     */
    public function createSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
            'session_name' => 'nullable|string|max:255',
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

        // Check if session with this phone number already exists
        $existingSession = WhatsAppSession::where('workspace_id', $this->workspaceId)
            ->where('phone_number', $validated['phone_number'])
            ->first();

        if ($existingSession) {
            return (object) [
                'success' => false,
                'message' => 'Session with this phone number already exists'
            ];
        }

        try {
            $session = WhatsAppSession::create([
                'uuid' => Str::uuid()->toString(),
                'workspace_id' => $this->workspaceId,
                'session_name' => $validated['session_name'] ?? $validated['phone_number'],
                'phone_number' => $validated['phone_number'],
                'provider_type' => $validated['provider_type'],
                'is_primary' => $validated['is_primary'] ?? false,
                'status' => 'disconnected',
                'is_active' => false,
            ]);

            // If setting as primary, unset other primary sessions
            if ($session->is_primary) {
                WhatsAppSession::where('workspace_id', $this->workspaceId)
                    ->where('id', '!=', $session->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            return (object) [
                'success' => true,
                'message' => 'Session created successfully',
                'data' => $this->formatSessionData($session)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create WhatsApp session', [
                'error' => $e->getMessage(),
                'workspace_id' => $this->workspaceId,
                'phone_number' => $validated['phone_number']
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to create session: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get session details by UUID
     */
    public function getSession(string $uuid)
    {
        $session = WhatsAppSession::forWorkspace($this->workspaceId)
            ->where('uuid', $uuid)
            ->first();

        if (!$session) {
            return (object) [
                'success' => false,
                'message' => 'Session not found'
            ];
        }

        return (object) [
            'success' => true,
            'data' => $this->formatSessionData($session)
        ];
    }

    /**
     * Set session as primary
     */
    public function setPrimary(string $uuid)
    {
        $session = WhatsAppSession::forWorkspace($this->workspaceId)
            ->where('uuid', $uuid)
            ->first();

        if (!$session) {
            return (object) [
                'success' => false,
                'message' => 'Session not found'
            ];
        }

        try {
            // Unset other primary sessions
            WhatsAppSession::where('workspace_id', $this->workspaceId)
                ->where('id', '!=', $session->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);

            // Set this session as primary
            $session->update(['is_primary' => true]);

            return (object) [
                'success' => true,
                'message' => 'Session set as primary successfully',
                'data' => $this->formatSessionData($session)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to set primary session', [
                'error' => $e->getMessage(),
                'session_uuid' => $uuid,
                'workspace_id' => $this->workspaceId
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to set primary session: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Disconnect session
     */
    public function disconnectSession(string $uuid)
    {
        $session = WhatsAppSession::forWorkspace($this->workspaceId)
            ->where('uuid', $uuid)
            ->first();

        if (!$session) {
            return (object) [
                'success' => false,
                'message' => 'Session not found'
            ];
        }

        try {
            // Get provider adapter
            $provider = $this->providerSelector->getProvider($session->provider_type);

            if ($provider) {
                // Disconnect from provider
                $result = $provider->disconnect($session->session_id);

                if (!$result->success) {
                    Log::warning('Provider disconnection failed', [
                        'session_uuid' => $uuid,
                        'provider_type' => $session->provider_type,
                        'error' => $result->message ?? 'Unknown error'
                    ]);
                }
            }

            // Update session status
            $session->update([
                'status' => 'disconnected',
                'is_active' => false,
                'session_id' => null,
                'last_activity_at' => now()
            ]);

            // Fire status changed event
            event(new WhatsAppSessionStatusChangedEvent($session, 'disconnected'));

            return (object) [
                'success' => true,
                'message' => 'Session disconnected successfully',
                'data' => $this->formatSessionData($session)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to disconnect session', [
                'error' => $e->getMessage(),
                'session_uuid' => $uuid,
                'workspace_id' => $this->workspaceId
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to disconnect session: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete session
     */
    public function deleteSession(string $uuid)
    {
        $session = WhatsAppSession::forWorkspace($this->workspaceId)
            ->where('uuid', $uuid)
            ->first();

        if (!$session) {
            return (object) [
                'success' => false,
                'message' => 'Session not found'
            ];
        }

        try {
            // Disconnect from provider first
            if ($session->session_id) {
                $this->disconnectSession($uuid);
            }

            // Delete the session
            $session->delete();

            return (object) [
                'success' => true,
                'message' => 'Session deleted successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to delete session', [
                'error' => $e->getMessage(),
                'session_uuid' => $uuid,
                'workspace_id' => $this->workspaceId
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to delete session: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Reconnect session
     */
    public function reconnectSession(string $uuid)
    {
        $session = WhatsAppSession::forWorkspace($this->workspaceId)
            ->where('uuid', $uuid)
            ->first();

        if (!$session) {
            return (object) [
                'success' => false,
                'message' => 'Session not found'
            ];
        }

        try {
            // Reset session status
            $session->update([
                'status' => 'connecting',
                'is_active' => false,
                'session_id' => null,
                'qr_code' => null
            ]);

            // Fire status changed event
            event(new WhatsAppSessionStatusChangedEvent($session, 'connecting'));

            return (object) [
                'success' => true,
                'message' => 'Session reconnection initiated',
                'data' => $this->formatSessionData($session)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to reconnect session', [
                'error' => $e->getMessage(),
                'session_uuid' => $uuid,
                'workspace_id' => $this->workspaceId
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to reconnect session: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Regenerate QR code for session
     */
    public function regenerateQR(string $uuid)
    {
        $session = WhatsAppSession::forWorkspace($this->workspaceId)
            ->where('uuid', $uuid)
            ->first();

        if (!$session) {
            return (object) [
                'success' => false,
                'message' => 'Session not found'
            ];
        }

        try {
            // Get provider adapter
            $provider = $this->providerSelector->getProvider($session->provider_type);

            if (!$provider) {
                return (object) [
                    'success' => false,
                    'message' => 'Provider not available'
                ];
            }

            // Generate new QR code
            $result = $provider->generateQR($session);

            if (!$result->success) {
                return (object) [
                    'success' => false,
                    'message' => 'Failed to generate QR code: ' . ($result->message ?? 'Unknown error')
                ];
            }

            // Update session with new QR code
            $session->update([
                'qr_code' => $result->qr_code,
                'qr_expires_at' => now()->addMinutes(5),
                'status' => 'qr_generated'
            ]);

            // Fire QR generated event
            event(new WhatsAppQRGeneratedEvent($session, $result->qr_code));

            return (object) [
                'success' => true,
                'message' => 'QR code regenerated successfully',
                'data' => [
                    'qr_code' => $result->qr_code,
                    'qr_expires_at' => $session->qr_expires_at
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Failed to regenerate QR code', [
                'error' => $e->getMessage(),
                'session_uuid' => $uuid,
                'workspace_id' => $this->workspaceId
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to regenerate QR code: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get session statistics
     */
    public function getSessionStatistics(string $uuid)
    {
        $session = WhatsAppSession::forWorkspace($this->workspaceId)
            ->where('uuid', $uuid)
            ->first();

        if (!$session) {
            return (object) [
                'success' => false,
                'message' => 'Session not found'
            ];
        }

        try {
            // Calculate statistics
            $now = now();
            $stats = [
                'session_id' => $session->id,
                'uuid' => $session->uuid,
                'phone_number' => $session->phone_number,
                'status' => $session->status,
                'is_active' => $session->is_active,
                'is_primary' => $session->is_primary,
                'provider_type' => $session->provider_type,
                'created_at' => $session->created_at,
                'last_connected_at' => $session->last_connected_at,
                'last_activity_at' => $session->last_activity_at,
                'health_score' => $session->health_score ?? 0,
                'uptime_percentage' => $this->calculateUptimePercentage($session),
                'days_active' => $session->created_at->diffInDays($now),
                'total_messages' => $this->getTotalMessages($session),
                'last_24h_messages' => $this->getMessagesInPeriod($session, 24),
                'last_7d_messages' => $this->getMessagesInPeriod($session, 168), // 7 days
            ];

            return (object) [
                'success' => true,
                'data' => $stats
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get session statistics', [
                'error' => $e->getMessage(),
                'session_uuid' => $uuid,
                'workspace_id' => $this->workspaceId
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Format session data for API response
     */
    private function formatSessionData($session)
    {
        return [
            'id' => $session->id,
            'uuid' => $session->uuid,
            'session_id' => $session->session_id,
            'phone_number' => $session->phone_number,
            'session_name' => $session->session_name,
            'provider_type' => $session->provider_type,
            'status' => $session->status,
            'is_primary' => $session->is_primary,
            'is_active' => $session->is_active,
            'qr_code' => $session->qr_code,
            'qr_expires_at' => $session->qr_expires_at,
            'last_activity_at' => $session->last_activity_at,
            'last_connected_at' => $session->last_connected_at,
            'health_score' => $session->health_score,
            'formatted_phone_number' => $session->formatted_phone_number,
            'created_at' => $session->created_at,
        ];
    }

    /**
     * Calculate session uptime percentage
     */
    private function calculateUptimePercentage($session)
    {
        if (!$session->last_connected_at || !$session->created_at) {
            return 0;
        }

        $totalTime = $session->created_at->diffInSeconds(now());
        $activeTime = $session->last_connected_at->diffInSeconds(now());

        return $totalTime > 0 ? ($activeTime / $totalTime) * 100 : 0;
    }

    /**
     * Get total message count for session
     */
    private function getTotalMessages($session)
    {
        // This would typically query the messages table
        // Implementation depends on your message storage structure
        return 0; // Placeholder
    }

    /**
     * Get message count in specific period (hours)
     */
    private function getMessagesInPeriod($session, $hours)
    {
        // This would typically query the messages table for the period
        // Implementation depends on your message storage structure
        return 0; // Placeholder
    }
}