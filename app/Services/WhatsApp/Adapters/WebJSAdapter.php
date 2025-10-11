<?php

namespace App\Services\WhatsApp\Adapters;

use App\Models\Contact;
use App\Models\Workspace;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class WebJSAdapter implements WhatsAppAdapterInterface
{
    private Workspace $workspace;
    private Client $httpClient;
    private string $nodeServiceUrl;

    public function __construct(Workspace $workspace)
    {
        $this->workspace = $workspace;
        $this->nodeServiceUrl = rtrim(config('services.whatsapp_node.url', 'http://127.0.0.1:3000'), '/');
        $this->httpClient = new Client([
            'base_uri' => $this->nodeServiceUrl,
            'timeout' => (int) config('services.whatsapp_node.timeout', 30),
        ]);
    }

    public function sendMessage($contactUuId, $messageContent, $userId = null, array $options = [])
    {
        $contact = Contact::where('uuid', $contactUuId)->firstOrFail();
        $phone = $contact->phone ?? $contact->phone_number ?? $contact->phoneNumber ?? null;
        if (!$phone) {
            throw new \InvalidArgumentException('Contact phone number not found');
        }

        $meta = $this->workspace->metadata ? json_decode($this->workspace->metadata, true) : [];
        $sessionId = $meta['whatsapp']['webjs_session_id'] ?? ($meta['whatsapp']['webjs']['session_id'] ?? null);
        if (!$sessionId) {
            throw new \App\Exceptions\WhatsAppSessionUnavailableException("No Web JS session found for workspace {$this->workspace->id}");
        }

        $payload = [
            'session_id' => $sessionId,
            'workspace_id' => $this->workspace->id,
            'phone_number' => $phone,
            'message' => $messageContent,
            'user_id' => $userId,
        ];

        // Optional options
        if (!empty($options)) {
            $payload['options'] = $options;
        }

        try {
            $timestamp = time();
            $jsonBody = json_encode($payload);
            $signature = hash_hmac('sha256', $jsonBody . $timestamp, (string) config('services.whatsapp_node.hmac_secret'));

            $res = $this->httpClient->post('/api/messages/send', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Workspace-ID' => (string) $this->workspace->id,
                    'X-API-Token' => (string) config('services.whatsapp_node.api_token'),
                    'X-HMAC-Signature' => $signature,
                    'X-Timestamp' => (string) $timestamp,
                ],
                'body' => $jsonBody,
            ]);

            $result = json_decode($res->getBody()->getContents(), true);
            return (object) [
                'success' => true,
                'data' => (object) [
                    'provider' => 'webjs',
                    'message_id' => $result['message_id'] ?? null,
                ],
                'message' => 'Message sent via WebJS',
            ];
        } catch (\Throwable $e) {
            Log::error('WebJSAdapter sendMessage failed', [
                'workspace_id' => $this->workspace->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function syncTemplates()
    {
    throw new \App\Exceptions\NotSupportedByProviderException('Template sync not supported for WebJS provider');
    }

    public function createTemplate($request)
    {
    throw new \App\Exceptions\NotSupportedByProviderException('Template creation not supported for WebJS provider');
    }

    public function updateTemplate($request, $uuid)
    {
    throw new \App\Exceptions\NotSupportedByProviderException('Template update not supported for WebJS provider');
    }

    public function deleteTemplate($uuid)
    {
    throw new \App\Exceptions\NotSupportedByProviderException('Template deletion not supported for WebJS provider');
    }
}
