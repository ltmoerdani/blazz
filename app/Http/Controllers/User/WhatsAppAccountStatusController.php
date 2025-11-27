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

class WhatsAppAccountStatusController extends Controller
{
    protected $accountService;
    protected $accountStatusService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $workspaceId = $this->getWorkspaceId();
            $this->accountService = new AccountService($workspaceId);
            $this->accountStatusService = new AccountStatusService($workspaceId);
            return $next($request);
        });
    }

    /**
     * Set a session as primary
     */
    public function setPrimary(string $uuid)
    {
        $result = $this->accountStatusService->setPrimary($uuid);

        if ($result->success) {
            // Broadcast status change
            broadcast(new WhatsAppAccountStatusChangedEvent(
                $result->data->session_id,
                $result->data->status,
                $this->getWorkspaceId(),
                $result->data->phone_number,
                [
                    'action' => 'set_primary',
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
     * Disconnect a session
     */
    public function disconnect(string $uuid)
    {
        $result = $this->accountStatusService->disconnect($uuid);

        if ($result->success) {
            // Fire status changed event
            event(new WhatsAppAccountStatusChangedEvent(
                $result->data->session_id,
                'disconnected',
                $this->getWorkspaceId(),
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
     * Reconnect a session
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
                $this->getWorkspaceId(),
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
     * Get session statistics and health information
     */
    public function statistics(string $uuid)
    {
        $result = $this->accountStatusService->healthCheck($uuid);

        if ($result->success) {
            return response()->json([
                'success' => true,
                'data' => $result->data
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result->message
        ], 400);
    }
}