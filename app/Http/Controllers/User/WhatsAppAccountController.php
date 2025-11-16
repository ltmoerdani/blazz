<?php

namespace App\Http\Controllers\User;

use App\Events\WhatsAppQRGeneratedEvent;
use App\Events\WhatsAppAccountStatusChangedEvent;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppAccount;
use App\Services\WhatsApp\AccountService;
use App\Services\WhatsApp\AccountStatusService;
use App\Services\ProviderSelector;
use App\Services\Adapters\WebJSAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WhatsAppAccountController extends Controller
{
    protected $accountService;
    protected $accountStatusService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $workspaceId = session('current_workspace');
            $this->accountService = new AccountService($workspaceId);
            $this->accountStatusService = new AccountStatusService($workspaceId);
            return $next($request);
        });
    }

    /**
     * Display WhatsApp accounts for the current workspace
     */
    public function index(Request $request)
    {
        $workspaceId = session('current_workspace');
        $perPage = $request->get('per_page', 15);
        $filters = $request->only(['status', 'provider_type', 'search']);

        // Get accounts using service
        $accounts = $this->accountService->list($perPage, $filters);

        // Transform for frontend
        $transformedAccounts = $accounts->getCollection()->map(function ($account) {
            return [
                'id' => $account->id,
                'uuid' => $account->uuid,
                'session_id' => $account->session_id,
                'phone_number' => $account->phone_number,
                'display_name' => $account->display_name,
                'provider_type' => $account->provider_type,
                'status' => $account->status,
                'is_primary' => $account->is_primary,
                'is_active' => $account->is_active ?? true,
                'last_activity_at' => $account->last_activity_at,
                'connected_at' => $account->connected_at,
                'disconnected_at' => $account->disconnected_at,
                'formatted_phone_number' => $this->accountService->formatPhoneNumber($account->phone_number),
                'created_at' => $account->created_at,
            ];
        });

        // Get statistics
        $statistics = $this->accountService->getStatistics();

        $settings = \App\Models\Setting::whereIn('key', ['is_embedded_signup_active', 'whatsapp_client_id', 'whatsapp_config_id'])
            ->pluck('value', 'key');

        return inertia('User/Settings/WhatsAppAccounts', [
            'accounts' => $transformedAccounts,
            'pagination' => [
                'current_page' => $accounts->currentPage(),
                'last_page' => $accounts->lastPage(),
                'per_page' => $accounts->perPage(),
                'total' => $accounts->total(),
                'from' => $accounts->firstItem(),
                'to' => $accounts->lastItem(),
            ],
            'statistics' => $statistics,
            'canAddAccount' => $this->accountService->canAddSession(),
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
        // Validate request
        $validator = Validator::make($request->all(), [
            'phone_number' => 'nullable|string',  // Phone number is not known yet for QR scanning
            'display_name' => 'nullable|string|max:255',
            'provider_type' => 'required|in:webjs,meta',
            'is_primary' => 'boolean',
            'webhook_url' => 'nullable|url',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Create account with session initialization using service
        $result = $this->accountService->createWithSession($request->validated());

        if (!$result->success) {
            return response()->json([
                'success' => false,
                'message' => $result->message
            ], $result->message === 'You have reached the maximum number of WhatsApp accounts for your plan.' ? 403 : 500);
        }

        return response()->json([
            'success' => true,
            'message' => $result->message,
            'session' => [
                'id' => $result->data->id,
                'uuid' => $result->data->uuid,
                'session_id' => $result->data->session_id,
                'phone_number' => $result->data->phone_number,
                'display_name' => $result->data->display_name,
                'provider_type' => $result->data->provider_type,
                'status' => $result->data->status,
                'is_primary' => $result->data->is_primary,
                'is_active' => $result->data->is_active ?? true,
                'last_activity_at' => $result->data->last_activity_at,
                'connected_at' => $result->data->connected_at,
                'disconnected_at' => $result->data->disconnected_at,
                'formatted_phone_number' => $this->accountService->formatPhoneNumber($result->data->phone_number),
                'created_at' => $result->data->created_at,
            ],
            // QR code will be sent via webhook/websocket event
            'qr_code' => null,
        ]);
    }

    /**
     * Show a specific session
     */
    public function show(string $uuid)
    {
        $account = $this->accountService->getByUuid($uuid);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'session' => [
                'id' => $account->id,
                'uuid' => $account->uuid,
                'session_id' => $account->session_id,
                'phone_number' => $account->phone_number,
                'display_name' => $account->display_name,
                'provider_type' => $account->provider_type,
                'status' => $account->status,
                'is_primary' => $account->is_primary,
                'is_active' => $account->is_active ?? true,
                'last_activity_at' => $account->last_activity_at,
                'connected_at' => $account->connected_at,
                'disconnected_at' => $account->disconnected_at,
                'formatted_phone_number' => $this->accountService->formatPhoneNumber($account->phone_number),
                'metadata' => $account->metadata,
                'created_at' => $account->created_at,
            ]
        ]);
    }

    /**
     * Set a session as primary
     */
    public function setPrimary(string $uuid)
    {
        $result = $this->accountStatusService->setPrimary($uuid);

        if (!$result->success) {
            return response()->json([
                'success' => false,
                'message' => $result->message
            ], $result->message === 'Account not found' ? 404 : 500);
        }

        // Broadcast status change
        broadcast(new WhatsAppAccountStatusChangedEvent(
            $result->data->session_id,
            $result->data->status,
            $result->data->workspace_id,
            $result->data->phone_number,
            [
                'action' => 'set_primary',
                'timestamp' => now()->toISOString()
            ]
        ));

        return response()->json([
            'success' => true,
            'message' => 'Session set as primary successfully',
            'session' => $result->data
        ]);
    }

    /**
     * Disconnect a session
     */
    public function disconnect(string $uuid)
    {
        $result = $this->accountStatusService->disconnect($uuid);

        if ($result->success) {
            // Broadcast status change event
            broadcast(new WhatsAppAccountStatusChangedEvent(
                $result->data->session_id,
                'disconnected',
                session('current_workspace'),
                $result->data->phone_number,
                [
                    'action' => 'disconnect',
                    'uuid' => $result->data->uuid,
                    'timestamp' => now()->toISOString()
                ]
            ));

            return response()->json([
                'success' => true,
                'message' => $result->message
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result->message
        ], 400);
    }

    /**
     * Delete a session
     */
    public function destroy(string $uuid)
    {
        // Delete account with cleanup using service
        $result = $this->accountService->deleteWithCleanup($uuid);

        if (!$result->success) {
            return response()->json([
                'success' => false,
                'message' => $result->message
            ], $result->message === 'Account not found' ? 404 : 500);
        }

        return response()->json([
            'success' => true,
            'message' => $result->message
        ]);
    }

    /**
     * Reconnect a disconnected session
     */
    public function reconnect(string $uuid)
    {
        $result = $this->accountStatusService->reconnect($uuid);

        return response()->json([
            'success' => $result->success,
            'message' => $result->message,
            'qr_code' => $result->success && isset($result->data->qr_code) ? $result->data->qr_code : null,
        ], $result->success ? 200 : 400);
    }

    /**
     * Regenerate QR code for a session
     */
    public function regenerateQR(string $uuid)
    {
        $result = $this->accountStatusService->regenerateQR($uuid);

        if ($result->success) {
            // Fire QR generated event
            event(new WhatsAppQRGeneratedEvent(
                $result->data->qr_code,
                300, // 5 minutes in seconds
                session('current_workspace'),
                $result->data->session_id
            ));

            return response()->json([
                'success' => true,
                'message' => $result->message,
                'qr_code' => $result->data->qr_code,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result->message
        ], 400);
    }

    /**
     * Get session statistics
     */
    public function statistics(string $uuid)
    {
        $result = $this->accountStatusService->healthCheck($uuid);

        if ($result->success) {
            return response()->json([
                'success' => true,
                'statistics' => $result->data
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result->message
        ], 400);
    }
}
