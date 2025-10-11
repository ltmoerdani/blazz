<?php

namespace App\Services\WhatsApp\Adapters;

use App\Models\Contact;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Adapter wrapper for existing Meta (WhatsApp Cloud API) logic.
 * Keeps compatibility with legacy WhatsappService behavior.
 */
class MetaAPIAdapter implements WhatsAppAdapterInterface
{
    private string $accessToken;
    private string $apiVersion;
    private string $phoneNumberId;

    public function __construct(string $accessToken, string $apiVersion, string $phoneNumberId)
    {
        $this->accessToken = $accessToken;
        $this->apiVersion = $apiVersion;
        $this->phoneNumberId = $phoneNumberId;
    }

    public function sendMessage($contactUuId, $messageContent, $userId = null, array $options = [])
    {
        $contact = Contact::where('uuid', $contactUuId)->firstOrFail();
        $url = sprintf('https://graph.facebook.com/%s/%s/messages', $this->apiVersion, $this->phoneNumberId);

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $contact->phone,
            'type' => 'text',
            'text' => ['body' => $messageContent],
        ];

        // Map simple interactive options if provided
        if (!empty($options['type']) && $options['type'] !== 'text') {
            // Extend mapping as needed for interactive types
            Log::warning('MetaAPIAdapter: non-text types fallback to text');
        }

        $response = Http::withToken($this->accessToken)
            ->acceptJson()
            ->post($url, $payload);

        if ($response->failed()) {
            Log::error('MetaAPIAdapter: sendMessage failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $response->json();
    }

    public function syncTemplates()
    {
        // Intentionally not implemented for Meta API wrapper.
        // Template management remains handled by existing legacy service paths.
        throw new \BadMethodCallException('MetaAPIAdapter::syncTemplates not implemented');
    }

    public function createTemplate($request)
    {
        // See note above; use legacy implementation until adapter extended.
        throw new \BadMethodCallException('MetaAPIAdapter::createTemplate not implemented');
    }

    public function updateTemplate($request, $uuid)
    {
        // See note above; use legacy implementation until adapter extended.
        throw new \BadMethodCallException('MetaAPIAdapter::updateTemplate not implemented');
    }

    public function deleteTemplate($uuid)
    {
        // See note above; use legacy implementation until adapter extended.
        throw new \BadMethodCallException('MetaAPIAdapter::deleteTemplate not implemented');
    }
}
