<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreWhatsappSettings;
use App\Helpers\CustomHelper;
use App\Http\Requests\StoreWhatsappProfile;
use App\Models\Addon;
use App\Models\workspace;
use App\Models\Setting;
use App\Models\Template;
use App\Services\WhatsApp\MessageSendingService;
use App\Services\WhatsApp\TemplateManagementService;
use App\Services\WhatsApp\BusinessProfileService;
use App\Services\WhatsApp\WhatsAppHealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AdminWhatsAppSettingsController extends BaseController
{
    public function __construct(
        private MessageSendingService $messageService,
        private TemplateManagementService $templateService,
        private BusinessProfileService $businessService,
        private WhatsAppHealthService $healthService
    ) {
        // Constructor injection - WhatsApp services now injected
    }

    /**
     * Display WhatsApp settings page
     */
    public function viewWhatsappSettings(Request $request)
    {
        $settings = Setting::whereIn('key', ['is_embedded_signup_active', 'whatsapp_client_id', 'whatsapp_config_id'])
            ->pluck('value', 'key');

        $data = [
            'embeddedSignupActive' => CustomHelper::isModuleEnabled('Embedded Signup'),
            'graphAPIVersion' => config('graph.api_version'),
            'appId' => $settings->get('whatsapp_client_id', null),
            'configId' => $settings->get('whatsapp_config_id', null),
            'settings' => workspace::where('id', session()->get('current_workspace'))->first(),
            'modules' => Addon::get(),
            'title' => __('Settings'),
        ];

        return Inertia::render('User/Settings/Whatsapp', $data);
    }

    /**
     * Store WhatsApp settings
     */
    public function storeWhatsappSettings(StoreWhatsappSettings $request)
    {
        $embeddedSignupActive = Setting::where('key', 'is_embedded_signup_active')->value('value');
        $setWebhookUrl = $embeddedSignupActive == 1 ? true : false;

        return $this->saveWhatsappSettings(
            $request->access_token,
            $request->app_id,
            $request->phone_number_id,
            $request->waba_id,
            $setWebhookUrl
        );
    }

    /**
     * Update WhatsApp token
     */
    public function updateToken(Request $request)
    {
        if ($response = $this->abortIfDemo()) {
            return $response;
        }

        $workspaceId = session()->get('current_workspace');
        $accessToken = $request->input('access_token');

        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Access token is required'
            ], 422);
        }

        try {
            $workspace = workspace::findOrFail($workspaceId);
            $metadata = $workspace->metadata ? json_decode($workspace->metadata, true) : [];

            if (!isset($metadata['whatsapp'])) {
                $metadata['whatsapp'] = [];
            }

            $metadata['whatsapp']['access_token'] = $accessToken;
            $metadata['whatsapp']['updated_at'] = now()->toISOString();

            $workspace->update(['metadata' => json_encode($metadata)]);

            return response()->json([
                'success' => true,
                'message' => 'WhatsApp token updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update WhatsApp token', [
                'error' => $e->getMessage(),
                'workspace_id' => $workspaceId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update token: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh WhatsApp data from API
     */
    public function refreshWhatsappData()
    {
        if ($response = $this->abortIfDemo()) {
            return $response;
        }

        $workspaceId = session()->get('current_workspace');
        $workspace = workspace::where('id', $workspaceId)->first();
        $config = $workspace && $workspace->metadata ? json_decode($workspace->metadata, true) : [];

        if (isset($config['whatsapp']['access_token'])) {
            try {
                // Use injected health service to check WhatsApp status
                $healthResponse = $this->healthService->checkHealth();

                if ($healthResponse->success) {
                    // Update last refresh time
                    $config['whatsapp']['last_refresh'] = now()->toISOString();
                    $workspace->update(['metadata' => json_encode($config)]);

                    return response()->json([
                        'success' => true,
                        'message' => 'WhatsApp data refreshed successfully',
                        'data' => $healthResponse->data
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to refresh WhatsApp data: ' . ($healthResponse->message ?? 'Unknown error')
                    ], 500);
                }

            } catch (\Exception $e) {
                Log::error('Failed to refresh WhatsApp data', [
                    'error' => $e->getMessage(),
                    'workspace_id' => $workspaceId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to refresh WhatsApp data: ' . $e->getMessage()
                ], 500);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'WhatsApp integration not found'
        ], 404);
    }

    /**
     * Update WhatsApp business profile
     */
    public function whatsappBusinessProfileUpdate(StoreWhatsappProfile $request)
    {
        if ($response = $this->abortIfDemo()) {
            return $response;
        }

        $workspaceId = session()->get('current_workspace');
        $workspace = workspace::where('id', $workspaceId)->first();
        $config = $workspace && $workspace->metadata ? json_decode($workspace->metadata, true) : [];

        if (isset($config['whatsapp'])) {
            // Use injected service
            $response = $this->businessService->updateBusinessProfile($request->validated());

            if ($response->success === true) {
                return back()->with(
                    'status', [
                        'type' => 'success',
                        'message' => __('Your whatsapp business profile has been changed successfully!')
                    ]
                );
            } else {
                return back()->with(
                    'status', [
                        'type' => 'error',
                        'message' => __('Something went wrong! Your business profile could not be updated!')
                    ]
                );
            }
        }

        return back()->with(
            'status', [
                'type' => 'error',
                'message' => __('Setup your whatsapp integration first!')
            ]
        );
    }

    /**
     * Delete WhatsApp integration
     */
    public function deleteWhatsappIntegration(Request $request)
    {
        if ($response = $this->abortIfDemo()) {
            return $response;
        }

        $workspaceId = session()->get('current_workspace');
        $embeddedSignupActive = Setting::where('key', 'is_embedded_signup_active')->value('value');

        try {
            $workspace = workspace::findOrFail($workspaceId);
            $metadata = $workspace->metadata ? json_decode($workspace->metadata, true) : [];

            if (isset($metadata['whatsapp'])) {
                // If using embedded signup, unsubscribe from webhooks
                if ($embeddedSignupActive == 1 && isset($metadata['whatsapp']['access_token'])) {
                    try {
                        $unsubscribe = $this->healthService->unSubscribeToWaba();
                        Log::info('WhatsApp webhook unsubscription result', [
                            'workspace_id' => $workspaceId,
                            'result' => $unsubscribe
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('Failed to unsubscribe from WhatsApp webhooks', [
                            'workspace_id' => $workspaceId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // Remove WhatsApp configuration
                unset($metadata['whatsapp']);
                $workspace->update(['metadata' => json_encode($metadata)]);

                return back()->with(
                    'status', [
                        'type' => 'success',
                        'message' => __('Your whatsapp integration has been deleted successfully!')
                    ]
                );
            }

            return back()->with(
                'status', [
                    'type' => 'error',
                    'message' => __('No WhatsApp integration found to delete!')
                ]
            );

        } catch (\Exception $e) {
            Log::error('Failed to delete WhatsApp integration', [
                'error' => $e->getMessage(),
                'workspace_id' => $workspaceId
            ]);

            return back()->with(
                'status', [
                    'type' => 'error',
                    'message' => __('Something went wrong while deleting WhatsApp integration!')
                ]
            );
        }
    }

    /**
     * Save WhatsApp settings - moved from original controller
     */
    private function saveWhatsappSettings($accessToken, $appId, $phoneNumberId, $wabaId, $setWebhookUrl = false)
    {
        if ($response = $this->abortIfDemo()) {
            return $response;
        }

        $workspaceId = session()->get('current_workspace');
        $apiVersion = config('graph.api_version');

        try {
            // Use injected business service for validation
            $phoneNumberResponse = $this->businessService->getPhoneNumberId($accessToken, $wabaId);

            if (!$phoneNumberResponse->success) {
                $errorMessage = 'Unknown error occurred';
                if (isset($phoneNumberResponse->error)) {
                    $errorMessage = $phoneNumberResponse->error;
                }

                return back()->with(
                    'status', [
                        'type' => 'error',
                        'message' => $errorMessage
                    ]
                );
            }

            // Validate phone number ID
            if (isset($phoneNumberResponse->id) && $phoneNumberResponse->id != $phoneNumberId) {
                return back()->with(
                    'status', [
                        'type' => 'error',
                        'message' => 'Phone number ID does not match your WABA account. Please use the correct phone number ID.'
                    ]
                );
            }

            $workspace = workspace::findOrFail($workspaceId);
            $metadata = $workspace->metadata ? json_decode($workspace->metadata, true) : [];

            if (!isset($metadata['whatsapp'])) {
                $metadata['whatsapp'] = [];
            }

            $metadata['whatsapp'] = array_merge($metadata['whatsapp'], [
                'access_token' => $accessToken,
                'api_version' => $apiVersion,
                'app_id' => $appId,
                'phone_number_id' => $phoneNumberId,
                'waba_id' => $wabaId,
                'connected_at' => now()->toISOString(),
                'webhook_configured' => $setWebhookUrl,
            ]);

            $workspace->update(['metadata' => json_encode($metadata)]);

            return back()->with(
                'status', [
                    'type' => 'success',
                    'message' => __('Your whatsapp integration has been saved successfully!')
                ]
            );

        } catch (\Exception $e) {
            Log::error('Failed to save WhatsApp settings', [
                'error' => $e->getMessage(),
                'workspace_id' => $workspaceId,
                'waba_id' => $wabaId
            ]);

            return back()->with(
                'status', [
                    'type' => 'error',
                    'message' => __('Something went wrong while saving WhatsApp integration!')
                ]
            );
        }
    }

    /**
     * Check if in demo mode and abort if so
     */
    private function abortIfDemo()
    {
        if (config('app.demo_mode')) {
            return back()->with(
                'status', [
                    'type' => 'error',
                    'message' => __('This feature is not available in demo mode!')
                ]
            );
        }
        return null;
    }

    /**
     * Get WhatsApp settings status
     */
    public function getWhatsAppStatus(Request $request)
    {
        $workspaceId = session()->get('current_workspace');

        try {
            $workspace = workspace::findOrFail($workspaceId);
            $metadata = $workspace->metadata ? json_decode($workspace->metadata, true) : [];

            $whatsappConfig = $metadata['whatsapp'] ?? null;
            $isConnected = !empty($whatsappConfig['access_token']) &&
                           !empty($whatsappConfig['phone_number_id']) &&
                           !empty($whatsappConfig['waba_id']);

            $status = [
                'connected' => $isConnected,
                'configured' => !empty($whatsappConfig),
                'last_connected' => $whatsappConfig['connected_at'] ?? null,
                'phone_number_id' => $whatsappConfig['phone_number_id'] ?? null,
                'waba_id' => $whatsappConfig['waba_id'] ?? null,
                'api_version' => $whatsappConfig['api_version'] ?? null,
                'webhook_configured' => $whatsappConfig['webhook_configured'] ?? false,
            ];

            // Check health if connected
            if ($isConnected) {
                try {
                    $healthResponse = $this->healthService->checkHealth();
                    $status['health'] = $healthResponse->success ? 'healthy' : 'unhealthy';
                    $status['health_details'] = $healthResponse->data ?? [];
                } catch (\Exception $e) {
                    $status['health'] = 'error';
                    $status['health_error'] = $e->getMessage();
                }
            }

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get WhatsApp status: ' . $e->getMessage()
            ], 500);
        }
    }
}