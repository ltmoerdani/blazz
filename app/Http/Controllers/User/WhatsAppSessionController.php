<?php

namespace App\Http\Controllers\User;

use App\Events\WhatsAppQRGeneratedEvent;
use App\Events\WhatsAppSessionStatusChangedEvent;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppSession;
use App\Services\ProviderSelector;
use App\Services\Adapters\WebJSAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WhatsAppSessionController extends Controller
{
    /**
     * Display WhatsApp sessions for the current workspace
     */
    public function index()
    {
        $workspaceId = session('current_workspace');

        $sessions = WhatsAppSession::forWorkspace($workspaceId)
            ->orderBy('is_primary', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'uuid' => $session->uuid,
                    'session_id' => $session->session_id,
                    'phone_number' => $session->phone_number,
                    'provider_type' => $session->provider_type,
                    'status' => $session->status,
                    'is_primary' => $session->is_primary,
                    'is_active' => $session->is_active,
                    'last_activity_at' => $session->last_activity_at,
                    'last_connected_at' => $session->last_connected_at,
                    'health_score' => $session->health_score,
                    'formatted_phone_number' => $session->formatted_phone_number,
                    'created_at' => $session->created_at,
                ];
            });

        $settings = \App\Models\Setting::whereIn('key', ['is_embedded_signup_active', 'whatsapp_client_id', 'whatsapp_config_id'])
            ->pluck('value', 'key');

        return inertia('User/Settings/WhatsAppSessions', [
            'sessions' => $sessions,
            'canAddSession' => $this->canAddSession($workspaceId),
            'modules' => \App\Models\Addon::get(),
            'embeddedSignupActive' => \App\Helpers\CustomHelper::isModuleEnabled('Embedded Signup'),
            'graphAPIVersion' => config('graph.api_version'),
            'appId' => $settings->get('whatsapp_client_id', null),
            'configId' => $settings->get('whatsapp_config_id', null),
            'settings' => \App\Models\workspace::where('id', $workspaceId)->first(),
            'title' => __('Settings'),
        ]);
    }

    /**
     * Create a new WhatsApp session
     */
    public function store(Request $request)
    {
        $workspaceId = session('current_workspace');
        $response = null;

        // Validate request and check session limits
        $validator = Validator::make($request->all(), [
            'provider_type' => 'required|in:webjs,meta',
            'is_primary' => 'boolean',
        ]);

        if ($validator->fails()) {
            $response = response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        } elseif (!$this->canAddSession($workspaceId)) {
            $response = response()->json([
                'success' => false,
                'message' => 'You have reached the maximum number of WhatsApp sessions for your plan.'
            ], 403);
        } else {
            try {
                $session = WhatsAppSession::create([
                    'uuid' => Str::uuid()->toString(),
                    'workspace_id' => $workspaceId,
                    'session_id' => 'webjs_' . $workspaceId . '_' . time() . '_' . Str::random(8),
                    'provider_type' => $request->input('provider_type', 'webjs'),
                    'status' => 'qr_scanning',
                    'is_primary' => $request->boolean('is_primary', false),
                    'is_active' => true,
                    'created_by' => Auth::id(),
                    'metadata' => [
                        'created_via' => 'frontend',
                        'creation_timestamp' => now()->toISOString(),
                    ]
                ]);

                // If this is the first session, make it primary
                if (WhatsAppSession::forWorkspace($workspaceId)->count() === 1) {
                    $session->update(['is_primary' => true]);
                }

                // Initialize session with Node.js service
                $adapter = new WebJSAdapter($workspaceId, $session);
                $result = $adapter->initializeSession();

                if (!$result['success']) {
                    // Clean up failed session
                    $session->delete();
                    $response = response()->json([
                        'success' => false,
                        'message' => $result['error'] ?? 'Failed to initialize session'
                    ], 500);
                } else {
                    $response = response()->json([
                        'success' => true,
                        'message' => 'WhatsApp session created successfully. QR code will be sent via websocket.',
                        'session' => $session,
                        // QR code will be sent via webhook/websocket event
                        'qr_code' => null,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to create WhatsApp session', [
                    'workspace_id' => $workspaceId,
                    'error' => $e->getMessage(),
                ]);

                $response = response()->json([
                    'success' => false,
                    'message' => 'Failed to create WhatsApp session: ' . $e->getMessage()
                ], 500);
            }
        }

        return $response;
    }

    /**
     * Show a specific session
     */
    public function show(string $uuid)
    {
        $workspaceId = session('current_workspace');

        $session = WhatsAppSession::where('uuid', $uuid)
            ->where('workspace_id', $workspaceId)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'session' => [
                'id' => $session->id,
                'uuid' => $session->uuid,
                'session_id' => $session->session_id,
                'phone_number' => $session->phone_number,
                'provider_type' => $session->provider_type,
                'status' => $session->status,
                'is_primary' => $session->is_primary,
                'is_active' => $session->is_active,
                'last_activity_at' => $session->last_activity_at,
                'last_connected_at' => $session->last_connected_at,
                'health_score' => $session->health_score,
                'formatted_phone_number' => $session->formatted_phone_number,
                'metadata' => $session->metadata,
                'created_at' => $session->created_at,
            ]
        ]);
    }

    /**
     * Set a session as primary
     */
    public function setPrimary(string $uuid)
    {
        $workspaceId = session('current_workspace');

        $session = WhatsAppSession::where('uuid', $uuid)
            ->where('workspace_id', $workspaceId)
            ->firstOrFail();

        // Remove primary flag from all other sessions in workspace
        WhatsAppSession::forWorkspace($workspaceId)
            ->where('id', '!=', $session->id)
            ->update(['is_primary' => false]);

        // Set this session as primary
        $session->update(['is_primary' => true]);

        // Broadcast status change
        broadcast(new WhatsAppSessionStatusChangedEvent(
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

        $session = WhatsAppSession::where('uuid', $uuid)
            ->where('workspace_id', $workspaceId)
            ->firstOrFail();

        try {
            $adapter = new WebJSAdapter($workspaceId, $session);
            $result = $adapter->disconnectSession();

            if ($result['success']) {
                $session->update([
                    'status' => 'disconnected',
                    'last_activity_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Session disconnected successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'] ?? 'Failed to disconnect session'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to disconnect WhatsApp session', [
                'workspace_id' => $workspaceId,
                'session_id' => $session->session_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to disconnect session: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a session
     */
    public function destroy(string $uuid)
    {
        $workspaceId = session('current_workspace');

        $session = WhatsAppSession::where('uuid', $uuid)
            ->where('workspace_id', $workspaceId)
            ->firstOrFail();

        try {
            // Disconnect first if connected
            if ($session->status === 'connected') {
                $adapter = new WebJSAdapter($workspaceId, $session);
                $adapter->disconnectSession();
            }

            // Delete session
            $session->delete();

            return response()->json([
                'success' => true,
                'message' => 'Session deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete WhatsApp session', [
                'workspace_id' => $workspaceId,
                'session_id' => $session->session_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete session: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reconnect a disconnected session
     */
    public function reconnect(string $uuid)
    {
        $workspaceId = session('current_workspace');
        $response = null;

        $session = WhatsAppSession::where('uuid', $uuid)
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
        $response = null;

        $session = WhatsAppSession::where('uuid', $uuid)
            ->where('workspace_id', $workspaceId)
            ->firstOrFail();

        if (!in_array($session->status, ['qr_scanning', 'disconnected'])) {
            $response = response()->json([
                'success' => false,
                'message' => 'Cannot regenerate QR for this session status'
            ], 400);
        } else {
            try {
                $adapter = new WebJSAdapter($workspaceId, $session);
                $result = $adapter->regenerateQR();

                if (!$result['success']) {
                    $response = response()->json([
                        'success' => false,
                        'message' => $result['error'] ?? 'Failed to regenerate QR code'
                    ], 500);
                } else {
                    $response = response()->json([
                        'success' => true,
                        'message' => 'QR code regenerated successfully',
                        'qr_code' => $result['qr_code'] ?? null,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to regenerate QR code', [
                    'workspace_id' => $workspaceId,
                    'session_id' => $session->session_id,
                    'error' => $e->getMessage(),
                ]);

                $response = response()->json([
                    'success' => false,
                    'message' => 'Failed to regenerate QR code: ' . $e->getMessage()
                ], 500);
            }
        }

        return $response;
    }

    /**
     * Get session statistics
     */
    public function statistics(string $uuid)
    {
        $workspaceId = session('current_workspace');

        $session = WhatsAppSession::where('uuid', $uuid)
            ->where('workspace_id', $workspaceId)
            ->firstOrFail();

        $stats = [
            'messages_sent' => $session->chats()->where('type', 'outbound')->count(),
            'messages_received' => $session->chats()->where('type', 'inbound')->count(),
            'chats_count' => $session->chats()->distinct('contact_id')->count(),
            'campaigns_sent' => $session->campaignLogs()->count(),
            'contacts_count' => $session->contacts()->count(),
            'last_activity' => $session->last_activity_at,
            'uptime_percentage' => $this->calculateUptime($session),
        ];

        return response()->json([
            'success' => true,
            'statistics' => $stats
        ]);
    }

    /**
     * Check if user can add more sessions based on plan limits
     */
    private function canAddSession(int $workspaceId): bool
    {
        $currentCount = WhatsAppSession::forWorkspace($workspaceId)->count();

        // Get plan limits from subscription_plans table or workspace settings
        $workspace = \App\Models\Workspace::find($workspaceId);
        if ($workspace && $workspace->subscription) {
            $maxSessions = $workspace->subscription->plan->whatsapp_sessions_limit ?? 5;
        } else {
            // Fallback to workspace settings or default
            $maxSessions = $workspace->settings()->where('key', 'whatsapp_sessions_limit')->first()?->value ?? 5;
        }

        return $currentCount < $maxSessions;
    }

    /**
     * Calculate session uptime percentage
     */
    private function calculateUptime(WhatsAppSession $session): float
    {
        if (!$session->last_connected_at) {
            return 0.0;
        }

        $uptime = now()->diffInMinutes($session->last_connected_at);
        $totalTime = now()->diffInMinutes($session->created_at);

        if ($totalTime === 0) {
            return 100.0;
        }

        return min(100.0, ($uptime / $totalTime) * 100);
    }
}
