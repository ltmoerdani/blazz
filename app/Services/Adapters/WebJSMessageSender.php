<?php

namespace App\Services\Adapters;

use App\Models\Contact;
use App\Models\WhatsAppSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebJSMessageSender
{
    private WhatsAppSession $session;
    private int $workspaceId;
    private string $nodeServiceUrl;

    public function __construct(WhatsAppSession $session, int $workspaceId, string $nodeServiceUrl)
    {
        $this->session = $session;
        $this->workspaceId = $workspaceId;
        $this->nodeServiceUrl = $nodeServiceUrl;
    }

    /**
     * Send a text message via WebJS
     */
    public function sendMessage(Contact $contact, string $message, ?int $userId = null): array
    {
        return $this->sendMessageToNodeJS($contact, $message, 'text', $userId);
    }

    /**
     * Send a media message via WebJS
     */
    public function sendMedia(Contact $contact, string $mediaType, string $mediaUrl, string $caption = '', ?int $userId = null): array
    {
        return $this->sendMediaToNodeJS($contact, $mediaType, $mediaUrl, $caption, $userId);
    }

    /**
     * Send a template message via WebJS
     */
    public function sendTemplate(Contact $contact, array $templateData, ?int $userId = null, ?int $campaignId = null): array
    {
        return $this->sendTemplateToNodeJS($contact, $templateData, $userId, $campaignId);
    }

    /**
     * Send message to Node.js service
     */
    private function sendMessageToNodeJS(Contact $contact, string $message, string $type, ?int $userId = null): array
    {
        try {
            $response = Http::timeout(30)->post("{$this->nodeServiceUrl}/api/messages/send", [
                'session_id' => $this->session->session_id,
                'workspace_id' => $this->workspaceId,
                'recipient_phone' => $contact->phone,
                'message' => $message,
                'type' => $type,
                'user_id' => $userId,
            ]);

            if ($response->successful()) {
                return $this->handleSuccessfulResponse($response);
            }

            return [
                'success' => false,
                'error' => $response->body(),
                'provider' => 'webjs'
            ];
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e, $contact->id, 'message sending');
        }
    }

    /**
     * Send media message to Node.js service
     */
    private function sendMediaToNodeJS(Contact $contact, string $mediaType, string $mediaUrl, string $caption, ?int $userId): array
    {
        try {
            $response = Http::timeout(60)->post("{$this->nodeServiceUrl}/api/messages/send", [
                'session_id' => $this->session->session_id,
                'workspace_id' => $this->workspaceId,
                'recipient_phone' => $contact->phone,
                'message' => $mediaUrl,
                'type' => $mediaType,
                'caption' => $caption,
                'user_id' => $userId,
            ]);

            if ($response->successful()) {
                return $this->handleSuccessfulMediaResponse($response);
            }

            return [
                'success' => false,
                'error' => $response->body(),
                'provider' => 'webjs'
            ];
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e, $contact->id, 'media sending');
        }
    }

    /**
     * Send template message to Node.js service
     */
    private function sendTemplateToNodeJS(Contact $contact, array $templateData, ?int $userId, ?int $campaignId): array
    {
        try {
            $message = $this->formatTemplateForWebJS($templateData);

            $response = Http::timeout(30)->post("{$this->nodeServiceUrl}/api/messages/send", [
                'session_id' => $this->session->session_id,
                'workspace_id' => $this->workspaceId,
                'recipient_phone' => $contact->phone,
                'message' => $message,
                'type' => 'text',
                'user_id' => $userId,
                'campaign_id' => $campaignId,
            ]);

            if ($response->successful()) {
                return $this->handleSuccessfulTemplateResponse($response);
            }

            return [
                'success' => false,
                'error' => $response->body(),
                'provider' => 'webjs'
            ];
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e, $contact->id, 'template sending');
        }
    }

    /**
     * Handle successful API response
     */
    private function handleSuccessfulResponse($response): array
    {
        $data = $response->json();

        // Update session statistics
        $this->session->update([
            'last_activity_at' => now(),
            'metadata' => array_merge($this->session->metadata ?? [], [
                'last_message_sent' => now()->toISOString()
            ])
        ]);

        return [
            'success' => true,
            'data' => $data,
            'provider' => 'webjs'
        ];
    }

    /**
     * Handle successful media API response
     */
    private function handleSuccessfulMediaResponse($response): array
    {
        $data = $response->json();

        // Update session statistics
        $this->session->update([
            'last_activity_at' => now(),
            'metadata' => array_merge($this->session->metadata ?? [], [
                'last_media_sent' => now()->toISOString()
            ])
        ]);

        return [
            'success' => true,
            'data' => $data,
            'provider' => 'webjs'
        ];
    }

    /**
     * Handle successful template API response
     */
    private function handleSuccessfulTemplateResponse($response): array
    {
        $data = $response->json();

        // Update session statistics
        $this->session->update([
            'last_activity_at' => now(),
            'metadata' => array_merge($this->session->metadata ?? [], [
                'last_template_sent' => now()->toISOString()
            ])
        ]);

        return [
            'success' => true,
            'data' => $data,
            'provider' => 'webjs'
        ];
    }

    /**
     * Handle error response
     */
    private function handleErrorResponse(\Exception $e, int $contactId, string $operation): array
    {
        Log::error("WebJS {$operation} failed", [
            'workspace_id' => $this->workspaceId,
            'session_id' => $this->session->session_id,
            'contact_id' => $contactId,
            'error' => $e->getMessage()
        ]);

        return [
            'success' => false,
            'error' => $e->getMessage(),
            'provider' => 'webjs'
        ];
    }

    /**
     * Format template data for WebJS (simplified text format)
     */
    private function formatTemplateForWebJS(array $templateData): string
    {
        // For WebJS, we send templates as formatted text
        // This is a simplified implementation - could be enhanced based on needs

        if (isset($templateData['name'])) {
            // Template name provided - this would need template lookup
            return "Template: {$templateData['name']}";
        }

        if (isset($templateData['text'])) {
            return $templateData['text'];
        }

        return 'Template message';
    }
}
