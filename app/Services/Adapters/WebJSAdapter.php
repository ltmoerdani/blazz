<?php

namespace App\Services\Adapters;

use App\Contracts\WhatsAppAdapterInterface;
use App\Models\Contact;
use App\Models\WhatsAppAccount;
use App\Services\WhatsApp\InstanceRouter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebJSAdapter implements WhatsAppAdapterInterface
{
    // Constants for repeated strings
    private const SESSION_NOT_AVAILABLE = 'WebJS session not available';
    private const PROVIDER_TYPE = 'webjs';

    private ?WhatsAppAccount $session;
    private int $workspaceId;
    private string $nodeServiceUrl;
    private WebJSMessageSender $messageSender;
    private WebJSHealthChecker $healthChecker;
    private WebJSUtility $utility;
    private InstanceRouter $router;

    public function __construct(int $workspaceId, ?WhatsAppAccount $session = null)
    {
        $this->workspaceId = $workspaceId;
        $this->session = $session;
        $this->router = app(InstanceRouter::class);
        
        // MULTI-INSTANCE: Use router to get correct instance URL
        // If session already assigned, use that. Otherwise, route based on workspace.
        if ($this->session && $this->session->assigned_instance_url) {
            $this->nodeServiceUrl = $this->session->assigned_instance_url;
        } else {
            $this->nodeServiceUrl = $this->router->getInstanceForWorkspace($workspaceId);
        }

        // Initialize helper classes
        if ($this->session) {
            $this->messageSender = new WebJSMessageSender($this->session, $this->workspaceId, $this->nodeServiceUrl);
            $this->healthChecker = new WebJSHealthChecker($this->session, $this->workspaceId, $this->nodeServiceUrl);
            $this->utility = new WebJSUtility($this->session, $this->workspaceId, $this->nodeServiceUrl);
        }
    }

    /**
     * Check if WebJS service is available and ready
     */
    public function isAvailable(): bool
    {
        return $this->session && $this->healthChecker->isAvailable();
    }

    /**
     * Send a text message via WebJS
     */
    public function sendMessage(Contact $contact, string $message, ?int $userId = null): array
    {
        if (!$this->session) {
            return [
                'success' => false,
                'error' => self::SESSION_NOT_AVAILABLE,
                'provider' => self::PROVIDER_TYPE
            ];
        }

        return $this->messageSender->sendMessage($contact, $message, $userId);
    }

    /**
     * Send a media message via WebJS
     */
    public function sendMedia(Contact $contact, string $mediaType, string $mediaUrl, string $caption = '', ?int $userId = null): array
    {
        if (!$this->session) {
            return [
                'success' => false,
                'error' => self::SESSION_NOT_AVAILABLE,
                'provider' => self::PROVIDER_TYPE
            ];
        }

        return $this->messageSender->sendMedia($contact, $mediaType, $mediaUrl, $caption, $userId);
    }

    /**
     * Send a template message via WebJS
     */
    public function sendTemplate(Contact $contact, array $templateData, ?int $userId = null, ?int $campaignId = null): array
    {
        if (!$this->session) {
            return [
                'success' => false,
                'error' => self::SESSION_NOT_AVAILABLE,
                'provider' => self::PROVIDER_TYPE
            ];
        }

        return $this->messageSender->sendTemplate($contact, $templateData, $userId, $campaignId);
    }

    /**
     * Get the session associated with this adapter
     */
    public function getSession(): ?WhatsAppAccount
    {
        return $this->session;
    }

    /**
     * Get provider type
     */
    public function getProviderType(): string
    {
        return self::PROVIDER_TYPE;
    }

    /**
     * Get provider health information
     */
    public function getHealthInfo(): array
    {
        if (!$this->session) {
            return [
                'status' => 'no_session',
                'provider' => self::PROVIDER_TYPE,
                'health_score' => 0
            ];
        }

        return $this->healthChecker->getHealthInfo();
    }

    /**
     * Mark a message as read via WebJS
     */
    public function markAsRead(string $messageId): bool
    {
        if (!$this->session) {
            return false;
        }

        return $this->utility->markAsRead($messageId);
    }

    /**
     * Get message status via WebJS
     */
    public function getMessageStatus(string $messageId): ?string
    {
        if (!$this->session) {
            return null;
        }

        return $this->utility->getMessageStatus($messageId);
    }

    /**
     * Initialize a new session with Node.js service
     *
     * MULTI-INSTANCE ARCHITECTURE:
     * Uses InstanceRouter to determine correct instance based on workspace_id.
     * This ensures workspace-based sharding and proper load distribution.
     *
     * Note: QR code will be sent via webhook event (qr_code_generated),
     * not in the response. Frontend should listen to Echo/Reverb events.
     */
    public function initializeSession(): array
    {
        try {
            // MULTI-INSTANCE: Route to correct instance based on workspace
            $instanceIndex = $this->router->getInstanceIndex($this->workspaceId);
            $targetInstanceUrl = $this->router->getInstanceUrl($instanceIndex);

            Log::info("Initializing session on Instance {$instanceIndex}", [
                'workspace_id' => $this->workspaceId,
                'session_id' => $this->session->session_id,
                'target_instance' => $targetInstanceUrl,
            ]);

            // Increased timeout to 60 seconds for puppeteer initialization
            $response = Http::timeout(60)->post("{$targetInstanceUrl}/api/sessions", [
                'workspace_id' => $this->workspaceId,
                'account_id' => $this->session->id,           // INTEGER ID from database
                'session_id' => $this->session->session_id,   // STRING session identifier
                'api_key' => config('whatsapp.node_api_key'),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Update session status AND instance assignment
                $this->session->update([
                    'status' => $data['status'] ?? 'qr_scanning',
                    'last_activity_at' => now(),
                ]);

                // Assign to instance in database
                $this->session->assignToInstance($instanceIndex, $targetInstanceUrl);

                Log::info("Session initialized successfully on Instance {$instanceIndex}", [
                    'workspace_id' => $this->workspaceId,
                    'session_id' => $this->session->session_id,
                ]);

                return [
                    'success' => true,
                    'message' => 'Session initialized successfully. QR code will be sent via websocket.',
                    'session_id' => $data['session_id'] ?? $this->session->session_id,
                    'status' => $data['status'] ?? 'qr_scanning',
                    // QR code will be sent via webhook event, not here
                    'qr_code' => null,
                ];
            }

            Log::error('Node.js service returned error', [
                'workspace_id' => $this->workspaceId,
                'session_id' => $this->session->session_id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'Node.js service returned error: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Session initialization failed', [
                'workspace_id' => $this->workspaceId,
                'session_id' => $this->session->session_id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to initialize session: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Disconnect a session
     */
    public function disconnectSession(): array
    {
        try {
            $response = Http::timeout(30)->delete("{$this->nodeServiceUrl}/api/sessions/{$this->session->session_id}", [
                'workspace_id' => $this->workspaceId,
                'api_key' => config('whatsapp.node_api_key'),
            ]);

            if ($response->successful()) {
                // Update session status
                $this->session->update([
                    'status' => 'disconnected',
                    'last_activity_at' => now(),
                ]);

                return [
                    'success' => true,
                    'message' => 'Session disconnected successfully',
                ];
            }

            return [
                'success' => false,
                'error' => 'Node.js service returned error: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Session disconnect failed', [
                'workspace_id' => $this->workspaceId,
                'session_id' => $this->session->session_id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to disconnect session: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Reconnect a disconnected session
     */
    public function reconnectSession(): array
    {
        try {
            $response = Http::timeout(30)->post("{$this->nodeServiceUrl}/api/sessions/{$this->session->session_id}/reconnect", [
                'workspace_id' => $this->workspaceId,
                'api_key' => config('whatsapp.node_api_key'),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Update session status
                $this->session->update([
                    'status' => 'qr_scanning',
                    'last_activity_at' => now(),
                ]);

                return [
                    'success' => true,
                    'message' => 'Reconnection initiated successfully',
                    'qr_code' => $data['qr_code'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => 'Node.js service returned error: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Session reconnect failed', [
                'workspace_id' => $this->workspaceId,
                'session_id' => $this->session->session_id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to reconnect session: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Regenerate QR code for a session
     */
    public function regenerateQR(): array
    {
        try {
            $response = Http::timeout(30)->post("{$this->nodeServiceUrl}/api/sessions/{$this->session->session_id}/regenerate-qr", [
                'workspace_id' => $this->workspaceId,
                'api_key' => config('whatsapp.node_api_key'),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'message' => 'QR code regenerated successfully',
                    'qr_code' => $data['qr_code'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => 'Node.js service returned error: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('QR regeneration failed', [
                'workspace_id' => $this->workspaceId,
                'session_id' => $this->session->session_id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to regenerate QR code: ' . $e->getMessage(),
            ];
        }
    }
}
