<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\v1\WhatsApp\WebhookController;
use App\Http\Controllers\Api\v1\WhatsApp\SessionController;
use Illuminate\Http\Request;

/**
 * @deprecated Use App\Http\Controllers\Api\v1\WhatsApp\WebhookController for webhook operations
 * @deprecated Use App\Http\Controllers\Api\v1\WhatsApp\SessionController for session operations
 */
class WhatsAppWebJSController extends Controller
{
    private WebhookController $webhookController;
    private SessionController $sessionController;

    public function __construct()
    {
        $this->webhookController = new WebhookController();
        $this->sessionController = new SessionController();
    }

    /**
     * Handle webhook from Node.js service
     * @deprecated Use WebhookController::webhook() instead
     */
    public function webhook(Request $request)
    {
        return $this->webhookController->webhook($request);
    }

    /**
     * Get session status
     * @deprecated Use SessionController::getSessionStatus() instead
     */
    public function getSessionStatus(Request $request, string $sessionId)
    {
        return $this->sessionController->getSessionStatus($request, $sessionId);
    }

    /**
     * Get all active sessions for restoration
     * @deprecated Use SessionController::getActiveSessions() instead
     */
    public function getActiveSessions(Request $request)
    {
        return $this->sessionController->getActiveSessions($request);
    }

    /**
     * Mark session as disconnected
     * @deprecated Use SessionController::markDisconnected() instead
     */
    public function markDisconnected(Request $request, $sessionId)
    {
        return $this->sessionController->markDisconnected($request, $sessionId);
    }
}