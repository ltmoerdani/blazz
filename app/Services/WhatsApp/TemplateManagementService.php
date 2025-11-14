<?php

namespace App\Services\WhatsApp;

use App\Models\Template;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TemplateManagementService
{
    private $accessToken;
    private $apiVersion;
    private $wabaId;
    private $workspaceId;
    private $appId;
    private $phoneNumberId;

    public function __construct($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId)
    {
        $this->accessToken = $accessToken;
        $this->apiVersion = $apiVersion;
        $this->appId = $appId;
        $this->phoneNumberId = $phoneNumberId;
        $this->wabaId = $wabaId;
        $this->workspaceId = $workspaceId;
    }

    public function createTemplate(Request $request)
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->wabaId}/message_templates";

        $requestData = [
            "name" => $request->name,
            "language" => $request->language,
            "category" => $request->category,
        ];

        if($request->customize_ttl && $request->message_send_ttl_seconds){
            $requestData['message_send_ttl_seconds'] = $request->message_send_ttl_seconds;
        }

        if($request->category != 'AUTHENTICATION'){
            if($request->header['format'] === 'TEXT' && isset($request->header['text'])){
                $headerComponent = [];

                $headerComponent['type'] = "HEADER";
                $headerComponent['format'] = $request->header['format'];
                $headerComponent['text'] = $request->header['text'];

                if (!empty($request->header['example'])) {
                    $headerComponent['example']['header_text'] = $request->header['example'];
                }

                $requestData['components'][] = $headerComponent;
            }


            if(($request->header['format'] === 'IMAGE' || $request->header['format'] === 'VIDEO' || $request->header['format'] === 'DOCUMENT') && isset($request->header['example'])){
                $fileUploadResponse = $this->initiateResumableUploadSession($request->header['example']);

                if(!$fileUploadResponse->success){
                    return $fileUploadResponse;
                }

                $requestData['components'][] = [
                    "type" => "HEADER",
                    "format" => $request->header['format'],
                    "example" => [
                        "header_handle" => [
                            $fileUploadResponse->data->h
                        ]
                    ]
                ];
            }
        }

        if($request->category == 'AUTHENTICATION'){
            $bodyComponent = [];
            $bodyComponent['type'] = "BODY";
            $bodyComponent['add_security_recommendation'] = $request->body['add_security_recommendation'];

            $requestData['components'][] = $bodyComponent;
        } else {
            $bodyComponent = [];

            if($request->body['text'] != null){
                $bodyComponent['type'] = "BODY";
                $bodyComponent['text'] = $request->body['text'];

                if (!empty($request->body['example'])) {
                    $bodyComponent['example']['body_text'][] = $request->body['example'];
                }

                $requestData['components'][] = $bodyComponent;
            }
        }

        if ($request->has('footer')) {
            if($request->category != 'AUTHENTICATION'){
                if(isset($request->footer['text']) &&  $request->footer['text'] != null){
                    $requestData['components'][] = [
                        "type" => "FOOTER",
                        "text" => $request->footer['text']
                    ];
                }
            } else {
                $requestData['components'][] = [
                    "type" => "FOOTER",
                    "code_expiration_minutes" => $request->footer['code_expiration_minutes']
                ];
            }
        }

        if($request->category != 'AUTHENTICATION'){
            if ($request->has('buttons')) {
                if (!isset($requestData['components'])) {
                    $requestData['components'] = [];
                }

                $requestData['components'][] = [
                    'type' => 'BUTTONS',
                    'buttons' => []
                ];

                $quickReplyButtons = [];

                foreach ($request->buttons as $button) {
                    if ($button['type'] === 'QUICK_REPLY') {
                        $quickReplyButtons[] = [
                            'type' => 'reply',
                            'reply' => [
                                'id' => $button['reply']['id'],
                                'title' => $button['reply']['title'],
                            ],
                        ];
                    } else {
                        $requestData['components'][count($requestData['components']) - 1]['buttons'][] = [
                            'type' => strtolower($button['type']),
                            'text' => $button['text'],
                            'url' => $button['url'] ?? null,
                            'phone_number' => $button['phone_number'] ?? null,
                        ];
                    }
                }

                if (!empty($quickReplyButtons)) {
                    $requestData['components'][] = [
                        'type' => 'BUTTONS',
                        'buttons' => $quickReplyButtons,
                    ];
                }
            }
        }

        $responseObject = $this->sendHttpRequest('POST', $url, $requestData);

        if($responseObject->success === true){
            Template::create([
                'uuid' => Str::uuid(),
                'workspace_id' => $this->workspaceId,
                'name' => $request->name,
                'language' => $request->language,
                'category' => $request->category,
                'status' => 'PENDING',
                'components' => json_encode($requestData['components'] ?? []),
            ]);
        }

        return $responseObject;
    }

    public function updateTemplate(Request $request, $uuid)
    {
        $template = Template::where('uuid', $uuid)->first();

        if (!$template) {
            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->error = 'Template not found';
            return $responseObject;
        }

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->wabaId}/message_templates";

        $requestData = [
            "name" => $request->name,
            "language" => $request->language,
            "category" => $request->category,
        ];

        if($request->customize_ttl && $request->message_send_ttl_seconds){
            $requestData['message_send_ttl_seconds'] = $request->message_send_ttl_seconds;
        }

        // Build template components similar to createTemplate
        $components = [];

        // Header component
        if($request->category != 'AUTHENTICATION' && isset($request->header)){
            if($request->header['format'] === 'TEXT' && isset($request->header['text'])){
                $headerComponent = [
                    'type' => "HEADER",
                    'format' => $request->header['format'],
                    'text' => $request->header['text']
                ];

                if (!empty($request->header['example'])) {
                    $headerComponent['example']['header_text'] = $request->header['example'];
                }

                $components[] = $headerComponent;
            }
        }

        // Body component
        if($request->category == 'AUTHENTICATION'){
            $bodyComponent = [
                'type' => "BODY",
                'add_security_recommendation' => $request->body['add_security_recommendation']
            ];
            $components[] = $bodyComponent;
        } elseif(isset($request->body['text']) && $request->body['text'] != null){
            $bodyComponent = [
                'type' => "BODY",
                'text' => $request->body['text']
            ];

            if (!empty($request->body['example'])) {
                $bodyComponent['example']['body_text'][] = $request->body['example'];
            }

            $components[] = $bodyComponent;
        }

        // Footer component
        if ($request->has('footer')) {
            if($request->category != 'AUTHENTICATION'){
                if(isset($request->footer['text']) && $request->footer['text'] != null){
                    $components[] = [
                        "type" => "FOOTER",
                        "text" => $request->footer['text']
                    ];
                }
            } else {
                $components[] = [
                    "type" => "FOOTER",
                    "code_expiration_minutes" => $request->footer['code_expiration_minutes']
                ];
            }
        }

        // Buttons component
        if($request->category != 'AUTHENTICATION' && $request->has('buttons')){
            $buttonsComponent = [
                'type' => 'BUTTONS',
                'buttons' => []
            ];

            $quickReplyButtons = [];

            foreach ($request->buttons as $button) {
                if ($button['type'] === 'QUICK_REPLY') {
                    $quickReplyButtons[] = [
                        'type' => 'reply',
                        'reply' => [
                            'id' => $button['reply']['id'],
                            'title' => $button['reply']['title'],
                        ],
                    ];
                } else {
                    $buttonsComponent['buttons'][] = [
                        'type' => strtolower($button['type']),
                        'text' => $button['text'],
                        'url' => $button['url'] ?? null,
                        'phone_number' => $button['phone_number'] ?? null,
                    ];
                }
            }

            $components[] = $buttonsComponent;

            if (!empty($quickReplyButtons)) {
                $components[] = [
                    'type' => 'BUTTONS',
                    'buttons' => $quickReplyButtons,
                ];
            }
        }

        if (!empty($components)) {
            $requestData['components'] = $components;
        }

        $responseObject = $this->sendHttpRequest('POST', $url, $requestData);

        if($responseObject->success === true){
            $template->update([
                'name' => $request->name,
                'language' => $request->language,
                'category' => $request->category,
                'components' => json_encode($components),
                'status' => 'PENDING'
            ]);
        }

        return $responseObject;
    }

    public function syncTemplates()
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->wabaId}/message_templates";

        $headers = [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ];

        try {
            $response = Http::withHeaders($headers)->get($url);

            if ($response->successful()) {
                $templates = $response->json()['data'] ?? [];

                foreach ($templates as $templateData) {
                    Template::updateOrCreate(
                        [
                            'workspace_id' => $this->workspaceId,
                            'name' => $templateData['name'],
                            'language' => $templateData['language'],
                        ],
                        [
                            'uuid' => Str::uuid(),
                            'status' => $templateData['status'],
                            'category' => $templateData['category'],
                            'components' => json_encode($templateData['components'] ?? []),
                        ]
                    );
                }

                $responseObject = new \stdClass();
                $responseObject->success = true;
                $responseObject->data = $templates;
                return $responseObject;
            }

            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->error = $response->body();
            return $responseObject;

        } catch (\Exception $e) {
            Log::error('Template sync error: ' . $e->getMessage());
            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->error = 'Sync failed';
            return $responseObject;
        }
    }

    public function deleteTemplate($uuid)
    {
        $template = Template::where('uuid', $uuid)->first();

        if (!$template) {
            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->error = 'Template not found';
            return $responseObject;
        }

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->wabaId}/message_templates";

        $requestData = [
            "name" => $template->name,
            "language" => $template->language,
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ];

        try {
            $response = Http::withHeaders($headers)->delete($url, $requestData);

            $responseObject = new \stdClass();
            $responseObject->success = $response->successful();
            $responseObject->status = $response->status();
            $responseObject->data = $response->json();

            if ($response->successful()) {
                $template->delete();
            } else {
                $responseObject->error = $response->body();
                Log::error('Template deletion error: ' . $response->body());
            }

            return $responseObject;

        } catch (\Exception $e) {
            Log::error('Template deletion error: ' . $e->getMessage());
            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->error = 'Deletion failed';
            return $responseObject;
        }
    }

    private function initiateResumableUploadSession($file)
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/uploads";

        $fileLength = $file->getSize();
        $fileType = $file->getMimeType();
        $fileName = $file->getClientOriginalName();

        $headers = [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ];

        $requestData = [
            "file_name" => $fileName,
            "file_length" => $fileLength,
            "file_type" => $fileType,
            "access_token" => $this->accessToken,
        ];

        try {
            $response = Http::withHeaders($headers)->post($url, $requestData);

            $responseObject = new \stdClass();
            $responseObject->success = $response->successful();
            $responseObject->status = $response->status();
            $responseObject->data = $response->json();

            if ($response->failed()) {
                $responseObject->error = $response->body();
                Log::error('Upload session error: ' . $response->body());
            }

            return $responseObject;

        } catch (\Exception $e) {
            Log::error('Upload session error: ' . $e->getMessage());
            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->error = 'Upload session failed';
            return $responseObject;
        }
    }

    private function sendHttpRequest($method, $url, $data = [])
    {
        try {
            $headers = [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ];

            $response = Http::withHeaders($headers)->asJson()->send($method, $url, $data);

            $responseObject = new \stdClass();
            $responseObject->success = $response->successful();
            $responseObject->status = $response->status();
            $responseObject->data = $response->json();

            if ($response->failed()) {
                $responseObject->error = $response->body();
                Log::error('Template API Error: ' . $response->body());
            }

            return $responseObject;
        } catch (ConnectException $e) {
            Log::error('Template API Connection Error: ' . $e->getMessage());
            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->error = 'Connection failed';
            return $responseObject;
        } catch (GuzzleException $e) {
            Log::error('Template API Error: ' . $e->getMessage());
            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->error = 'Request failed';
            return $responseObject;
        }
    }
}