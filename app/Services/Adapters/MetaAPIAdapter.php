<?php

namespace App\Services\Adapters;

use App\Contracts\WhatsAppAdapterInterface;
use App\Models\Contact;
use App\Models\WhatsAppAccount;
use App\Services\WhatsappService;
use Illuminate\Support\Facades\Log;

class MetaAPIAdapter implements WhatsAppAdapterInterface
{
    // Constants for repeated strings
    private const SERVICE_NOT_INITIALIZED = 'Meta API service not initialized';
    private const PROVIDER_TYPE = 'meta';

    private ?WhatsAppAccount $session;
    private int $workspaceId;
    private ?WhatsappService $whatsappService;

    public function __construct(int $workspaceId, ?WhatsAppAccount $session = null)
    {
        $this->workspaceId = $workspaceId;
        $this->session = $session;
        $this->whatsappService = $this->initializeWhatsappService();
    }

    /**
     * Initialize WhatsappService with Meta API credentials
     */
    private function initializeWhatsappService(): ?WhatsappService
    {
        if (!$this->session || $this->session->provider_type !== 'meta') {
            return null;
        }

        $sessionData = $this->session->session_data;

        if (!$sessionData || !isset($sessionData['access_token'])) {
            Log::warning('Meta API session data missing access token', [
                'session_id' => $this->session->id,
                'workspace_id' => $this->workspaceId
            ]);
            return null;
        }

        return new WhatsappService(
            $sessionData['access_token'],
            $sessionData['api_version'] ?? 'v18.0',
            $sessionData['app_id'] ?? null,
            $sessionData['phone_number_id'] ?? null,
            $sessionData['waba_id'] ?? null,
            $this->workspaceId
        );
    }

    /**
     * Check if Meta API is available and ready
     */
    public function isAvailable(): bool
    {
        if (!$this->whatsappService) {
            return false;
        }

        try {
            // Try to get phone number status as a health check
            $response = $this->whatsappService->getPhoneNumberStatus();

            return $response->success && isset($response->data->status);
        } catch (\Exception $e) {
            Log::warning('Meta API availability check failed', [
                'workspace_id' => $this->workspaceId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send a text message via Meta API
     */
    public function sendMessage(Contact $contact, string $message, ?int $userId = null): array
    {
        if (!$this->whatsappService) {
            return [
                'success' => false,
                'error' => self::SERVICE_NOT_INITIALIZED,
                'provider' => self::PROVIDER_TYPE
            ];
        }

        try {
            $response = $this->whatsappService->sendMessage(
                $contact->uuid,
                $message,
                $userId
            );

            // Update session statistics
            if ($this->session && $response->success) {
                $this->session->update([
                    'last_activity_at' => now(),
                    'metadata' => array_merge($this->session->metadata ?? [], [
                        'last_message_sent' => now()->toISOString()
                    ])
                ]);
            }

            return [
                'success' => $response->success,
                'data' => $response->data ?? null,
                'error' => $response->message ?? null,
                'provider' => self::PROVIDER_TYPE
            ];
        } catch (\Exception $e) {
            Log::error('Meta API message sending failed', [
                'workspace_id' => $this->workspaceId,
                'contact_id' => $contact->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => self::PROVIDER_TYPE
            ];
        }
    }

    /**
     * Send a media message via Meta API
     */
    public function sendMedia(Contact $contact, string $mediaType, string $mediaUrl, string $caption = '', ?int $userId = null): array
    {
        if (!$this->whatsappService) {
            return [
                'success' => false,
                'error' => self::SERVICE_NOT_INITIALIZED,
                'provider' => self::PROVIDER_TYPE
            ];
        }

        try {
            $response = $this->whatsappService->sendMedia(
                $contact->uuid,
                $mediaType,
                basename($mediaUrl), // filename
                $mediaUrl,
                'uploads', // location
                $caption
            );

            // Update session statistics
            if ($this->session && $response->success) {
                $this->session->update([
                    'last_activity_at' => now(),
                    'metadata' => array_merge($this->session->metadata ?? [], [
                        'last_media_sent' => now()->toISOString()
                    ])
                ]);
            }

            return [
                'success' => $response->success,
                'data' => $response->data ?? null,
                'error' => $response->message ?? null,
                'provider' => 'meta'
            ];
        } catch (\Exception $e) {
            Log::error('Meta API media sending failed', [
                'workspace_id' => $this->workspaceId,
                'contact_id' => $contact->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => self::PROVIDER_TYPE
            ];
        }
    }

    /**
     * Send a template message via Meta API
     */
    public function sendTemplate(Contact $contact, array $templateData, ?int $userId = null, ?int $campaignId = null): array
    {
        if (!$this->whatsappService) {
            return [
                'success' => false,
                'error' => self::SERVICE_NOT_INITIALIZED,
                'provider' => self::PROVIDER_TYPE
            ];
        }

        try {
            $response = $this->whatsappService->sendTemplateMessage(
                $contact->uuid,
                $templateData,
                $userId,
                $campaignId
            );

            // Update session statistics
            if ($this->session && $response->success) {
                $this->session->update([
                    'last_activity_at' => now(),
                    'metadata' => array_merge($this->session->metadata ?? [], [
                        'last_template_sent' => now()->toISOString()
                    ])
                ]);
            }

            return [
                'success' => $response->success,
                'data' => $response->data ?? null,
                'error' => $response->message ?? null,
                'provider' => 'meta'
            ];
        } catch (\Exception $e) {
            Log::error('Meta API template sending failed', [
                'workspace_id' => $this->workspaceId,
                'contact_id' => $contact->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => self::PROVIDER_TYPE
            ];
        }
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
        return 'meta';
    }

    /**
     * Get provider health information
     */
    public function getHealthInfo(): array
    {
        if (!$this->session) {
            return [
                'status' => 'no_session',
                'provider' => 'meta',
                'health_score' => 0
            ];
        }

        return [
            'status' => $this->session->status,
            'provider' => 'meta',
            'health_score' => $this->session->health_score,
            'is_available' => $this->isAvailable(),
            'last_activity' => $this->session->last_activity_at,
            'phone_number' => $this->session->phone_number
        ];
    }

    /**
     * Mark a message as read (Meta API doesn't support this directly)
     */
    public function markAsRead(string $messageId): bool
    {
        // Meta API doesn't have a direct mark as read endpoint
        // This would need to be handled via webhooks or other means
        Log::info('Mark as read not directly supported by Meta API', [
            'message_id' => $messageId,
            'provider' => 'meta'
        ]);

        return false;
    }

    /**
     * Get message status (Meta API doesn't provide this directly)
     */
    public function getMessageStatus(string $messageId): ?string
    {
        // Meta API doesn't provide message status lookup
        // Status would need to be tracked via webhooks
        return null;
    }
}
