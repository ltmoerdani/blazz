<?php

namespace App\Services\Adapters;

use App\Contracts\WhatsAppAdapterInterface;
use App\Models\Contact;
use App\Models\WhatsAppSession;

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
}
