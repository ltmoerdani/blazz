<?php

namespace App\Services\Adapters;

use App\Contracts\WhatsAppAdapterInterface;
use App\Models\Contact;
use App\Models\WhatsAppSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebJSAdapter implements WhatsAppAdapterInterface
{
    // Constants for repeated strings
    private const SESSION_NOT_AVAILABLE = 'WebJS session not available';
    private const PROVIDER_TYPE = 'webjs';

    private ?WhatsAppSession $session;
    private int $workspaceId;
    private string $nodeServiceUrl;
    private WebJSMessageSender $messageSender;
    private WebJSHealthChecker $healthChecker;
    private WebJSUtility $utility;

    public function __construct(int $workspaceId, ?WhatsAppSession $session = null)
    {
        $this->workspaceId = $workspaceId;
        $this->session = $session;
        $this->nodeServiceUrl = config('whatsapp.node_service_url', 'http://localhost:3000');

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
    public function getSession(): ?WhatsAppSession
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
     */
    public function initializeSession(): array
    {
        try {
            $response = Http::timeout(30)->post("{$this->nodeServiceUrl}/api/sessions", [
                'workspace_id' => $this->workspaceId,
                'session_id' => $this->session->session_id,
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
                    'message' => 'Session initialized successfully',
                    'qr_code' => $data['qr_code'] ?? null,
                ];
            }

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
