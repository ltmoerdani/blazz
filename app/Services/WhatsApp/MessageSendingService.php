<?php

namespace App\Services\WhatsApp;

use App\Events\NewChatEvent;
use App\Helpers\WebhookHelper;
use App\Models\Chat;
use App\Models\ChatLog;
use App\Models\ChatMedia;
use App\Models\Contact;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MessageSendingService
{
    // Constants for repeated string literals
    const INTERACTIVE_BUTTONS = 'interactive buttons';
    const INTERACTIVE_CTA_URL = 'interactive call to action url';
    const INTERACTIVE_LIST = 'interactive list';

    private $accessToken;
    private $apiVersion;
    private $phoneNumberId;
    private $workspaceId;
    private $wabaId;
    private $appId;

    public function __construct($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId)
    {
        $this->accessToken = $accessToken;
        $this->apiVersion = $apiVersion;
        $this->appId = $appId;
        $this->phoneNumberId = $phoneNumberId;
        $this->wabaId = $wabaId;
        $this->workspaceId = $workspaceId;
    }

    /**
     * This function sends a text message via a POST request to the specified phone number using Facebook's messaging API.
     *
     * @param string $phoneNumber The phone number of the recipient.
     * @param string $messageContent The content of the message to be sent.
     * @return mixed Returns the response from the HTTP request.
     */
    public function sendMessage($contactUuId, $messageContent, $userId = null, $type="text", $buttons = [], $header = [], $footer = null, $buttonLabel = null)
    {
        $contact = Contact::where('uuid', $contactUuId)->first();
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $headers = $this->setHeaders();

        $requestData['messaging_product'] = 'whatsapp';
        $requestData['recipient_type'] = 'individual';
        $requestData['to'] = $contact->phone;
        if($type == "text"){
            $requestData['type'] = 'text';
            $requestData['text']['preview_url'] = true; //If you have added url either http or https a preview will be displayed
            $requestData['text']['body'] = clean($messageContent);
        } elseif($type == self::INTERACTIVE_BUTTONS || $type == self::INTERACTIVE_CTA_URL || $type == self::INTERACTIVE_LIST){
            $requestData['type'] = 'interactive';

            if($type == self::INTERACTIVE_BUTTONS){
                $requestData['interactive']['type'] = 'button';
            } elseif($type == self::INTERACTIVE_CTA_URL){
                $requestData['interactive']['type'] = 'cta_url';
            } elseif($type == self::INTERACTIVE_LIST){
                $requestData['interactive']['type'] = 'list';
            }

            if($type == self::INTERACTIVE_BUTTONS){
                foreach($buttons as $button){
                    $requestData['interactive']['action']['buttons'][] = [
                        'type' => 'reply',
                        'reply' => [
                            'id' => $button['id'],
                            'title' => $button['title'],
                        ],
                    ];
                }
            } elseif($type == self::INTERACTIVE_CTA_URL){
                $requestData['interactive']['action']['name'] = "cta_url";
                $requestData['interactive']['action']['parameters'] = $buttons;
            } elseif($type == self::INTERACTIVE_LIST){
                $requestData['interactive']['action']['sections'] = $buttons;
                $requestData['interactive']['action']['button'] = $buttonLabel;
            }

            if (!empty($header)) {
                $requestData['interactive']['header'] = $header;
            }

            $requestData['interactive']['body']['text'] = clean($messageContent);

            if ($footer != null) {
                $requestData['interactive']['footer'] = [
                    'text' => clean($footer),
                ];
            }
        }

        $responseObject = $this->sendHttpRequest('POST', $url, $requestData, $headers);

        if($responseObject->success === true){
            $response['text']['body'] = clean($messageContent);
            $response['type'] = 'text';

            $chat = Chat::create([
                'workspace_id' => $contact->Workspace_id,
                'wam_id' => $responseObject->data->messages[0]->id,
                'contact_id' => $contact->id,
                'type' => 'outbound',
                'user_id' => $userId,
                'metadata' => json_encode($response),
                'status' => 'delivered',
            ]);

            $chat = Chat::with('contact','media')->where('id', $chat->id)->first();
            $responseObject->data->chat = $chat;

            $chatlogId = ChatLog::insertGetId([
                'contact_id' => $contact->id,
                'entity_type' => 'chat',
                'entity_id' => $chat->id,
                'created_at' => now()
            ]);

            $chatLogArray = ChatLog::where('id', $chatlogId)->where('deleted_at', null)->first();
            $chatArray = array([
                'type' => 'chat',
                'value' => $chatLogArray->relatedEntities
            ]);

            event(new NewChatEvent($chatArray, $contact->Workspace_id));
        }

        // Trigger webhook
        WebhookHelper::triggerWebhookEvent('message.sent', [
            'data' => $responseObject,
        ], $contact->Workspace_id);

        return $responseObject;
    }

    public function reactToMessage($phoneNumber, $wamId, $emoji)
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $headers = $this->setHeaders();

        $requestData['messaging_product'] = 'whatsapp';
        $requestData['recipient_type'] = 'individual';
        $requestData['to'] = $phoneNumber;
        $requestData['type'] = 'reaction';
        $requestData['reaction']['message_id'] = $wamId;
        $requestData['reaction']['emoji'] = $emoji;

        return $this->sendHttpRequest('POST', $url, $requestData, $headers);
    }

    public function sendLocation($phoneNumber, $location)
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $headers = $this->setHeaders();

        $requestData['messaging_product'] = 'whatsapp';
        $requestData['recipient_type'] = 'individual';
        $requestData['to'] = $phoneNumber;
        $requestData['type'] = 'location';
        $requestData['location'] = $location;

        return $this->sendHttpRequest('POST', $url, $requestData, $headers);
    }

    /**
     * This function sends a template message via a POST request to the specified phone number using Facebook's messaging API.
     *
     * @param string $contactUuId The UUID of the contact.
     * @param string $templateContent The content of the template to be sent.
     * @param int|null $userId The ID of the user sending the message.
     * @param int|null $campaignId The ID of the campaign.
     * @param int|null $mediaId The ID of the media.
     * @return mixed Returns the response from the HTTP request.
     */
    public function sendTemplateMessage($contactUuId, $templateContent, $userId = null, $campaignId = null, $mediaId = null)
    {
        $contact = Contact::where('uuid', $contactUuId)->first();
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $headers = $this->setHeaders();

        $requestData['messaging_product'] = 'whatsapp';
        $requestData['recipient_type'] = 'individual';
        $requestData['to'] = $contact->phone;
        $requestData['type'] = 'template';
        $requestData['template'] = $templateContent;

        if($mediaId != null){
            $chatMedia = ChatMedia::find($mediaId);
            if($chatMedia){
                $mediaArray = array(
                    'id' => $chatMedia->uuid,
                    'link' => $chatMedia->url,
                );
                if($chatMedia->caption != null){
                    $mediaArray['caption'] = $chatMedia->caption;
                }
                if($chatMedia->filename != null){
                    $mediaArray['filename'] = $chatMedia->filename;
                }
                $requestData['template']['components'][] = array(
                    'type' => 'header',
                    'parameters' => array(
                        array(
                            'type' => 'image',
                            'image' => $mediaArray,
                        ),
                    ),
                );
            }
        }

        $responseObject = $this->sendHttpRequest('POST', $url, $requestData, $headers);

        if($responseObject->success === true){
            $response['template'] = $templateContent;
            $response['type'] = 'template';

            $chat = Chat::create([
                'workspace_id' => $contact->Workspace_id,
                'wam_id' => $responseObject->data->messages[0]->id,
                'contact_id' => $contact->id,
                'type' => 'outbound',
                'user_id' => $userId,
                'metadata' => json_encode($response),
                'status' => 'delivered',
                'campaign_id' => $campaignId,
            ]);

            $chat = Chat::with('contact','media')->where('id', $chat->id)->first();
            $responseObject->data->chat = $chat;

            $chatlogId = ChatLog::insertGetId([
                'contact_id' => $contact->id,
                'entity_type' => 'chat',
                'entity_id' => $chat->id,
                'created_at' => now()
            ]);

            $chatLogArray = ChatLog::where('id', $chatlogId)->where('deleted_at', null)->first();
            $chatArray = array([
                'type' => 'chat',
                'value' => $chatLogArray->relatedEntities
            ]);

            event(new NewChatEvent($chatArray, $contact->Workspace_id));
        }

        // Trigger webhook
        WebhookHelper::triggerWebhookEvent('message.sent', [
            'data' => $responseObject,
        ], $contact->Workspace_id);

        return $responseObject;
    }

    public function sendMedia($contactUuId, $mediaType, $mediaFileName, $mediaUrl, $location, $caption = null, $transcription = null)
    {
        $contact = Contact::where('uuid', $contactUuId)->first();
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $headers = $this->setHeaders();

        $requestData['messaging_product'] = 'whatsapp';
        $requestData['recipient_type'] = 'individual';
        $requestData['to'] = $contact->phone;
        $requestData['type'] = $mediaType;

        if($mediaType == 'image') {
            $requestData['image']['link'] = $mediaUrl;
            if($caption != null) {
                $requestData['image']['caption'] = clean($caption);
            }
        } elseif($mediaType == 'document') {
            $requestData['document']['link'] = $mediaUrl;
            if($mediaFileName != null) {
                $requestData['document']['filename'] = $mediaFileName;
            }
        } elseif($mediaType == 'audio') {
            $requestData['audio']['link'] = $mediaUrl;
        } elseif($mediaType == 'video') {
            $requestData['video']['link'] = $mediaUrl;
            if($caption != null) {
                $requestData['video']['caption'] = clean($caption);
            }
        }

        $responseObject = $this->sendHttpRequest('POST', $url, $requestData, $headers);

        if($responseObject->success === true){
            $response['media_url'] = $mediaUrl;
            $response['type'] = $mediaType;
            $response['file_name'] = $mediaFileName;
            $response['caption'] = $caption;

            $chat = Chat::create([
                'workspace_id' => $contact->Workspace_id,
                'wam_id' => $responseObject->data->messages[0]->id,
                'contact_id' => $contact->id,
                'type' => 'outbound',
                'user_id' => Auth::id(),
                'metadata' => json_encode($response),
                'status' => 'delivered',
            ]);

            $chatMedia = ChatMedia::create([
                'chat_id' => $chat->id,
                'workspace_id' => $contact->Workspace_id,
                'url' => $mediaUrl,
                'filename' => $mediaFileName,
                'type' => $mediaType,
                'caption' => $caption,
            ]);

            $chat = Chat::with('contact','media')->where('id', $chat->id)->first();
            $responseObject->data->chat = $chat;

            $chatlogId = ChatLog::insertGetId([
                'contact_id' => $contact->id,
                'entity_type' => 'chat',
                'entity_id' => $chat->id,
                'created_at' => now()
            ]);

            $chatLogArray = ChatLog::where('id', $chatlogId)->where('deleted_at', null)->first();
            $chatArray = array([
                'type' => 'chat',
                'value' => $chatLogArray->relatedEntities
            ]);

            event(new NewChatEvent($chatArray, $contact->Workspace_id));
        }

        // Trigger webhook
        WebhookHelper::triggerWebhookEvent('message.sent', [
            'data' => $responseObject,
        ], $contact->Workspace_id);

        return $responseObject;
    }

    /**
     * Send direct message for hybrid campaigns
     *
     * @param string $contactUuId Contact UUID
     * @param array $messageRequest Message content from job
     * @param int|null $userId User ID
     * @param int|null $campaignId Campaign ID
     * @param WhatsAppAccount|null $session WhatsApp session
     * @return object Response object
     */
    public function sendDirectMessage($contactUuId, $messageRequest, $userId = null, $campaignId = null, $session = null)
    {
        $contact = Contact::where('uuid', $contactUuId)->first();

        if (!$contact) {
            throw new \Exception('Contact not found');
        }

        // Determine API endpoint based on session provider
        if ($session && $session->provider_type === 'webjs') {
            return $this->sendWebJSDirectMessage($contact, $messageRequest, $userId, $campaignId, $session);
        } else {
            return $this->sendMetaApiDirectMessage($contact, $messageRequest, $userId, $campaignId, $session);
        }
    }

    /**
     * Send direct message via WebJS provider
     */
    private function sendWebJSDirectMessage($contact, $messageRequest, $userId = null, $campaignId = null, $session = null)
    {
        try {
            // For WebJS, we use the existing sendMessage method with proper formatting
            $messageType = 'text';
            $buttons = [];
            $header = [];
            $footer = null;

            // Extract message content
            $content = $messageRequest['content'] ?? [];

            // Build header if exists
            if (isset($content['header'])) {
                $headerComponent = $content['header'];

                if ($headerComponent['type'] === 'text') {
                    $header['text'] = $headerComponent['text'];
                } elseif (in_array($headerComponent['type'], ['image', 'document', 'video'])) {
                    // For WebJS, media messages might need special handling
                    $messageType = 'media';
                    return $this->sendMediaMessage($contact, $messageRequest, $userId, $campaignId);
                }
            }

            // Build body text
            $bodyText = $content['body']['text'] ?? '';

            // Build footer
            if (isset($content['footer'])) {
                $footer = $content['footer']['text'] ?? null;
            }

            // Build buttons
            if (isset($content['buttons']) && !empty($content['buttons'])) {
                $messageType = self::INTERACTIVE_BUTTONS;
                foreach ($content['buttons'] as $index => $button) {
                    $buttons[] = [
                        'id' => 'btn_' . $index,
                        'title' => $button['text']
                    ];
                }
            }

            // Send using existing sendMessage method
            $responseObject = $this->sendMessage(
                $contact->uuid,
                $bodyText,
                $userId,
                $messageType,
                $buttons,
                $header,
                $footer
            );

            // Update campaign ID if provided
            if ($responseObject->success === true && $campaignId) {
                $chatId = $responseObject->data->chat->id ?? null;
                if ($chatId) {
                    Chat::where('id', $chatId)->update(['campaign_id' => $campaignId]);
                }
            }

            return $responseObject;

        } catch (\Exception $e) {
            Log::error('WebJS Direct Message Error: ' . $e->getMessage(), [
                'contact_uuid' => $contact->uuid,
                'session_id' => $session->id,
                'campaign_id' => $campaignId
            ]);

            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->error = $e->getMessage();
            return $responseObject;
        }
    }

    /**
     * Send direct message via Meta Business API
     */
    private function sendMetaApiDirectMessage($contact, $messageRequest, $userId = null, $campaignId = null, $session = null)
    {
        try {
            $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";
            $headers = $this->setHeaders();

            $content = $messageRequest['content'] ?? [];

            // Build base request
            $requestData = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $contact->phone,
                'type' => 'template' // Meta API prefers template structure for structured messages
            ];

            // Build template structure for direct message
            $templateData = [
                'name' => 'direct_message_' . uniqid(), // Generate unique template name
                'language' => [
                    'code' => 'en_US'
                ],
                'components' => []
            ];

            // Add header component if exists
            if (isset($content['header'])) {
                $headerComponent = $content['header'];
                $templateHeader = [
                    'type' => 'header',
                    'parameters' => []
                ];

                if ($headerComponent['type'] === 'text') {
                    $templateHeader['parameters'][] = [
                        'type' => 'text',
                        'text' => $headerComponent['text']
                    ];
                } elseif (in_array($headerComponent['type'], ['image', 'document', 'video'])) {
                    $templateHeader['parameters'][] = [
                        'type' => $headerComponent['type'],
                        'media' => $headerComponent['media'] ?? ''
                    ];
                }

                $templateData['components'][] = $templateHeader;
            }

            // Add body component (required)
            $templateData['components'][] = [
                'type' => 'body',
                'parameters' => [
                    [
                        'type' => 'text',
                        'text' => $content['body']['text'] ?? ''
                    ]
                ]
            ];

            // Add footer component if exists
            if (isset($content['footer']) && !empty($content['footer']['text'])) {
                $templateData['components'][] = [
                    'type' => 'footer',
                    'text' => $content['footer']['text']
                ];
            }

            // Add buttons component if exists
            if (isset($content['buttons']) && !empty($content['buttons'])) {
                $buttonComponent = [
                    'type' => 'buttons',
                    'buttons' => []
                ];

                foreach ($content['buttons'] as $button) {
                    $buttonData = [
                        'type' => $button['type'],
                        'text' => $button['text']
                    ];

                    if ($button['type'] === 'url') {
                        $buttonData['url'] = $button['url'];
                    } elseif ($button['type'] === 'phone_number') {
                        $buttonData['phone_number'] = $button['phone_number'];
                    }

                    $buttonComponent['buttons'][] = $buttonData;
                }

                $templateData['components'][] = $buttonComponent;
            }

            $requestData['template'] = $templateData;

            // Send request
            $responseObject = $this->sendHttpRequest('POST', $url, $requestData, $headers);

            // Create chat record if successful
            if ($responseObject->success === true) {
                $response['direct_message'] = $templateData;
                $response['type'] = 'direct_message';

                $chat = Chat::create([
                    'workspace_id' => $contact->workspace_id,
                    'wam_id' => $responseObject->data->messages[0]->id,
                    'contact_id' => $contact->id,
                    'type' => 'outbound',
                    'user_id' => $userId,
                    'metadata' => json_encode($response),
                    'status' => 'delivered',
                    'campaign_id' => $campaignId,
                ]);

                $chat = Chat::with('contact', 'media')->where('id', $chat->id)->first();
                $responseObject->data->chat = $chat;

                // Trigger chat log and events
                $chatlogId = ChatLog::insertGetId([
                    'contact_id' => $contact->id,
                    'entity_type' => 'chat',
                    'entity_id' => $chat->id,
                    'created_at' => now()
                ]);

                $chatLogArray = ChatLog::where('id', $chatlogId)->where('deleted_at', null)->first();
                $chatArray = [
                    'type' => 'chat',
                    'value' => $chatLogArray->relatedEntities
                ];

                event(new NewChatEvent($chatArray, $contact->workspace_id));
            }

            // Trigger webhook
            WebhookHelper::triggerWebhookEvent('message.sent', [
                'data' => $responseObject,
            ], $contact->workspace_id);

            return $responseObject;

        } catch (\Exception $e) {
            Log::error('Meta API Direct Message Error: ' . $e->getMessage(), [
                'contact_uuid' => $contact->uuid,
                'session_id' => $session->id,
                'campaign_id' => $campaignId
            ]);

            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->error = $e->getMessage();
            return $responseObject;
        }
    }

    /**
     * Handle media messages for direct campaigns
     */
    private function sendMediaMessage($contact, $messageRequest, $userId = null, $campaignId = null)
    {
        $content = $messageRequest['content'] ?? [];
        $header = $content['header'] ?? [];

        if (!isset($header['type']) || !isset($header['media'])) {
            throw new \Exception('Invalid media message structure');
        }

        $mediaType = $header['type'];
        $mediaUrl = $header['media'];
        $caption = $content['footer']['text'] ?? null;

        $responseObject = $this->sendMedia(
            $contact->uuid,
            $mediaType,
            null, // filename
            $mediaUrl,
            null, // location
            $caption
        );

        // Update campaign ID if provided
        if ($responseObject->success === true && $campaignId) {
            $chatId = $responseObject->data->chat->id ?? null;
            if ($chatId) {
                Chat::where('id', $chatId)->update(['campaign_id' => $campaignId]);
            }
        }

        return $responseObject;
    }

    private function setHeaders()
    {
        return [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ];
    }

    private function sendHttpRequest($method, $url, $data = [], $headers = [])
    {
        try {
            $response = Http::withHeaders($headers)->asJson()->send($method, $url, $data);

            $responseObject = new \stdClass();
            $responseObject->success = $response->successful();
            $responseObject->status = $response->status();
            $responseObject->data = $response->json();

            if ($response->failed()) {
                $responseObject->error = $response->body();
                Log::error('WhatsApp API Error: ' . $response->body());
            }

            return $responseObject;
        } catch (ConnectException $e) {
            Log::error('WhatsApp API Connection Error: ' . $e->getMessage());
            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->error = 'Connection failed';
            return $responseObject;
        } catch (GuzzleException $e) {
            Log::error('WhatsApp API Error: ' . $e->getMessage());
            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->error = 'Request failed';
            return $responseObject;
        }
    }
}