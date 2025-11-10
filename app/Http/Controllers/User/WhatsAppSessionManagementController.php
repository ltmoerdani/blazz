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

class WhatsAppSessionManagementController extends Controller
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
            'workspaceId' => $workspaceId,
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
                        'provider_type' => $request->input('provider_type', 'webjs'),
                        'created_at' => now()->toISOString(),
                        'created_by' => Auth::user()->email,
                    ]
                ]);

                // If setting as primary, unset other primary sessions
                if ($session->is_primary) {
                    WhatsAppSession::where('workspace_id', $workspaceId)
                        ->where('id', '!=', $session->id)
                        ->where('is_primary', true)
                        ->update(['is_primary' => false]);
                }

                // Fire status changed event
                event(new WhatsAppSessionStatusChangedEvent(
                    $session->session_id,
                    'qr_scanning',
                    $workspaceId,
                    $session->phone_number,
                    [
                        'action' => 'session_created',
                        'uuid' => $session->uuid,
                        'timestamp' => now()->toISOString()
                    ]
                ));

                $response = response()->json([
                    'success' => true,
                    'message' => 'WhatsApp session created successfully',
                    'data' => [
                        'uuid' => $session->uuid,
                        'session_id' => $session->session_id,
                        'provider_type' => $session->provider_type,
                        'status' => $session->status,
                        'is_primary' => $session->is_primary,
                        'is_active' => $session->is_active,
                        'created_at' => $session->created_at,
                    ]
                ], 201);

            } catch (\Exception $e) {
                Log::error('Failed to create WhatsApp session', [
                    'error' => $e->getMessage(),
                    'workspace_id' => $workspaceId,
                    'request_data' => $request->all()
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
     * Get session details by UUID
     */
    public function show(string $uuid)
    {
        $workspaceId = session('current_workspace');

        try {
            $session = WhatsAppSession::forWorkspace($workspaceId)
                ->where('uuid', $uuid)
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found'
                ], 404);
            }

            // Get provider adapter for additional session info
            $providerSelector = app(ProviderSelector::class);
            try {
                $provider = $providerSelector->selectProvider($session->workspace_id, $session->provider_type);
            } catch (\Exception $e) {
                $provider = null;
            }

            $sessionData = [
                'id' => $session->id,
                'uuid' => $session->uuid,
                'session_id' => $session->session_id,
                'phone_number' => $session->phone_number,
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
                'metadata' => $session->metadata,
            ];

            // Add provider-specific information
            if ($provider) {
                try {
                    $providerStatus = $provider->getHealthInfo();
                    if ($providerStatus) {
                        $sessionData['provider_status'] = $providerStatus;
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to get provider status', [
                        'session_uuid' => $uuid,
                        'provider_type' => $session->provider_type,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $sessionData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get WhatsApp session details', [
                'error' => $e->getMessage(),
                'session_uuid' => $uuid,
                'workspace_id' => $workspaceId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get session details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete session
     */
    public function destroy(string $uuid)
    {
        $workspaceId = session('current_workspace');

        try {
            $session = WhatsAppSession::forWorkspace($workspaceId)
                ->where('uuid', $uuid)
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found'
                ], 404);
            }

            // Get provider adapter and disconnect from provider first
            $providerSelector = app(ProviderSelector::class);
            try {
                $provider = $providerSelector->selectProvider($session->workspace_id, $session->provider_type);
            } catch (\Exception $e) {
                $provider = null;
            }

            if ($provider && $session->session_id) {
                try {
                    // For WebJS adapter, we need to create a new instance with the session
                    if ($session->provider_type === 'webjs') {
                        $webjsAdapter = new WebJSAdapter($session->workspace_id, $session);
                        $disconnectResult = $webjsAdapter->disconnectSession();
                        if (!$disconnectResult['success']) {
                            Log::warning('Provider disconnection failed during session deletion', [
                                'session_uuid' => $uuid,
                                'provider_type' => $session->provider_type,
                                'error' => $disconnectResult['error'] ?? 'Unknown error'
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Provider disconnection error during session deletion', [
                        'session_uuid' => $uuid,
                        'provider_type' => $session->provider_type,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Fire status changed event before deletion
            event(new WhatsAppSessionStatusChangedEvent(
                $session->session_id,
                'deleted',
                $workspaceId,
                $session->phone_number,
                [
                    'action' => 'session_deleted',
                    'uuid' => $session->uuid,
                    'timestamp' => now()->toISOString()
                ]
            ));

            // Delete the session
            $session->delete();

            return response()->json([
                'success' => true,
                'message' => 'WhatsApp session deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete WhatsApp session', [
                'error' => $e->getMessage(),
                'session_uuid' => $uuid,
                'workspace_id' => $workspaceId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete session: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if workspace can add more sessions
     */
    private function canAddSession($workspaceId)
    {
        $workspace = \App\Models\workspace::find($workspaceId);
        if (!$workspace) {
            return false;
        }

        $currentSessionCount = WhatsAppSession::forWorkspace($workspaceId)
            ->where('is_active', true)
            ->count();

        // Get maximum sessions allowed for the workspace
        $maxSessions = $workspace->max_whatsapp_sessions ?? 1;

        return $currentSessionCount < $maxSessions;
    }
}