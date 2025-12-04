<?php

namespace App\Services\WhatsApp;

use App\Models\Setting;
use App\Models\Workspace;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BusinessProfileService
{
    const BEARER_PREFIX = 'Bearer ';

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

    public function getBusinessProfile()
    {
        $responseObject = new \stdClass();

        try {
            $response = Http::withHeaders([
                'Authorization' => self::BEARER_PREFIX . $this->accessToken
            ])->get("https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/whatsapp_business_profile", [
                'fields' => 'about,address,description,email,profile_picture_url,websites,vertical',
            ])->throw()->json();

            if (isset($response['data']['error'])) {
                $responseObject->success = false;
                $responseObject->data = new \stdClass();
                $responseObject->data->error = new \stdClass();
                $responseObject->data->error->code = $response['data']['error']['code'];
                $responseObject->data->error->message = $response['data']['error']['message'];
            } else {
                $responseObject->success = true;
                $responseObject->data = new \stdClass();
                $responseObject->data = (object) $response['data'][0];
            }
        } catch (\Exception $e) {
            $responseObject->success = false;
            $responseObject->data = new \stdClass();
            $responseObject->data->error = new \stdClass();
            $responseObject->data->error->message = $e->getMessage();
        }

        return $responseObject;
    }

    public function updateBusinessProfile(Request $request)
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/whatsapp_business_profile";

        $headers = $this->setHeaders();

        $requestData['messaging_product'] = 'whatsapp';
        $requestData['about'] = $request->about;
        $requestData['address'] = $request->address;
        $requestData['description'] = $request->description;
        $requestData['vertical'] = $request->industry;
        $requestData['email'] = $request->email;

        $profile_picture_url = null;

        if($request->hasFile('profile_picture_url')){
            $storage = Setting::where('key', 'storage_system')->first()->value;
            $fileContent = $request->file('profile_picture_url');

            if($storage === 'local'){
                $file = Storage::disk('local')->put('public', $fileContent);
                $mediaFilePath = $file;
                $profile_picture_url = rtrim(config('app.url'), '/') . '/media/' . ltrim($mediaFilePath, '/');
            } elseif($storage === 'aws') {
                $file = $request->file('profile_picture_url');
                $uploadedFile = $file->store('uploads/media/sent/' . $this->workspaceId, 's3');
                /** @var \Illuminate\Filesystem\FilesystemAdapter $s3Disk */
                $s3Disk = Storage::disk('s3');
                $mediaFilePath = $s3Disk->url($uploadedFile);
                $profile_picture_url = $mediaFilePath;
            }

            // Upload to WhatsApp servers
            $fileUploadResponse = $this->initiateResumableUploadSession($request->file('profile_picture_url'));

            if($fileUploadResponse->success){
                $requestData['profile_picture_handle'] = $fileUploadResponse->data->h;
            }
        }

        if ($request->has('websites')) {
            $requestData['websites'] = $request->websites;
        }

        $responseObject = $this->sendHttpRequest('POST', $url, $requestData, $headers);

        if($responseObject->success === true){
            $workspaceConfig = workspace::where('id', $this->workspaceId)->first();
            $metadataArray = $workspaceConfig->metadata ? json_decode($workspaceConfig->metadata, true) : [];

            $metadataArray['whatsapp']['business_profile']['about'] = $request->about;
            $metadataArray['whatsapp']['business_profile']['address'] = $request->address;
            $metadataArray['whatsapp']['business_profile']['description'] = $request->description;
            $metadataArray['whatsapp']['business_profile']['industry'] = $request->industry;
            $metadataArray['whatsapp']['business_profile']['email'] = $request->email;
            if($profile_picture_url != null){
                $metadataArray['whatsapp']['business_profile']['profile_picture_url'] = $profile_picture_url;
            }
            if ($request->has('websites')) {
                $metadataArray['whatsapp']['business_profile']['websites'] = $request->websites;
            }

            $updatedMetadataJson = json_encode($metadataArray);

            $workspaceConfig->metadata = $updatedMetadataJson;
            $workspaceConfig->save();
        }

        return $responseObject;
    }

    public function deRegisterPhone()
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/deregister";

        $headers = $this->setHeaders();

        $responseObject = $this->sendHttpRequest('POST', $url, null, $headers);

        if($responseObject->success === true){
            $workspaceConfig = workspace::where('id', $this->workspaceId)->first();
            $metadataArray = $workspaceConfig->metadata ? json_decode($workspaceConfig->metadata, true) : [];

            // Clear phone registration metadata
            if(isset($metadataArray['whatsapp']['phone_number'])){
                unset($metadataArray['whatsapp']['phone_number']);
            }

            $updatedMetadataJson = json_encode($metadataArray);

            $workspaceConfig->metadata = $updatedMetadataJson;
            $workspaceConfig->save();
        }

        return $responseObject;
    }

    public function getPhoneNumberId()
    {
        $responseObject = new \stdClass();

        try {
            $response = Http::withHeaders([
                'Authorization' => self::BEARER_PREFIX . $this->accessToken
            ])->get("https://graph.facebook.com/{$this->apiVersion}/me", [
                'fields' => 'phone_numbers',
            ])->throw()->json();

            if (isset($response['phone_numbers']) && !empty($response['phone_numbers'])) {
                $responseObject->success = true;
                $responseObject->data = new \stdClass();
                $responseObject->data->phone_number_id = $response['phone_numbers'][0]['phone_number_id'];
                $responseObject->data->display_phone_number = $response['phone_numbers'][0]['display_phone_number'];
            } else {
                $responseObject->success = false;
                $responseObject->error = 'No phone numbers found';
            }
        } catch (\Exception $e) {
            $responseObject->success = false;
            $responseObject->error = $e->getMessage();
        }

        return $responseObject;
    }

    public function getPhoneNumberStatus()
    {
        $responseObject = new \stdClass();

        try {
            $response = Http::withHeaders([
                'Authorization' => self::BEARER_PREFIX . $this->accessToken
            ])->get("https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}")->throw()->json();

            if (isset($response['error'])) {
                $responseObject->success = false;
                $responseObject->error = $response['error']['message'];
            } else {
                $responseObject->success = true;
                $responseObject->data = new \stdClass();
                $responseObject->data->name_display = $response['name_display'] ?? null;
                $responseObject->data->name_status = $response['name_status'] ?? null;
                $responseObject->data->quality_rating = $response['quality_rating'] ?? null;
                $responseObject->data->status = $response['status'] ?? null;
                $responseObject->data->code_verification_status = $response['code_verification_status'] ?? null;
                $responseObject->data->display_phone_number = $response['display_phone_number'] ?? null;
                $responseObject->data->new_name_status = $response['new_name_status'] ?? null;
                $responseObject->data->is_official_business_account = $response['is_official_business_account'] ?? false;
                $responseObject->data->is_pin_enabled = $response['is_pin_enabled'] ?? false;
                $responseObject->data->is_captcha_enabled = $response['is_captcha_enabled'] ?? false;
                $responseObject->data->capabilities = $response['capabilities'] ?? [];
            }
        } catch (\Exception $e) {
            $responseObject->success = false;
            $responseObject->error = $e->getMessage();
        }

        return $responseObject;
    }

    public function getAccountReviewStatus()
    {
        $responseObject = new \stdClass();

        try {
            $response = Http::withHeaders([
                'Authorization' => self::BEARER_PREFIX . $this->accessToken
            ])->get("https://graph.facebook.com/{$this->apiVersion}/{$this->wabaId}")->throw()->json();

            if (isset($response['error'])) {
                $responseObject->success = false;
                $responseObject->error = $response['error']['message'];
            } else {
                $responseObject->success = true;
                $responseObject->data = new \stdClass();
                $responseObject->data->account_review_status = $response['account_review_status'] ?? null;
                $responseObject->data->name_status = $response['name_status'] ?? null;
                $responseObject->data->quality_rating = $response['quality_rating'] ?? null;
                $responseObject->data->status = $response['status'] ?? null;
                $responseObject->data->message_template_namespace = $response['message_template_namespace'] ?? null;
                $responseObject->data->webhook_url = $response['webhook_url'] ?? null;
            }
        } catch (\Exception $e) {
            $responseObject->success = false;
            $responseObject->error = $e->getMessage();
        }

        return $responseObject;
    }

    public function updateWebhookUrl($webhookUrl, $verifyToken = null)
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->wabaId}";

        $headers = $this->setHeaders();

        $requestData = [
            'webhook_url' => $webhookUrl,
        ];

        if ($verifyToken) {
            $requestData['verify_token'] = $verifyToken;
        }

        $responseObject = $this->sendHttpRequest('PATCH', $url, $requestData, $headers);

        if($responseObject->success === true){
            $workspaceConfig = workspace::where('id', $this->workspaceId)->first();
            $metadataArray = $workspaceConfig->metadata ? json_decode($workspaceConfig->metadata, true) : [];

            $metadataArray['whatsapp']['webhook_url'] = $webhookUrl;
            if ($verifyToken) {
                $metadataArray['whatsapp']['webhook_verify_token'] = $verifyToken;
            }

            $updatedMetadataJson = json_encode($metadataArray);

            $workspaceConfig->metadata = $updatedMetadataJson;
            $workspaceConfig->save();
        }

        return $responseObject;
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
            $defaultHeaders = $this->setHeaders();
            $finalHeaders = array_merge($defaultHeaders, $headers);

            $response = Http::withHeaders($finalHeaders)->asJson()->send($method, $url, $data);

            $responseObject = new \stdClass();
            $responseObject->success = $response->successful();
            $responseObject->status = $response->status();
            $responseObject->data = $response->json();

            if ($response->failed()) {
                $responseObject->error = $response->body();
                Log::error('Business Profile API Error: ' . $response->body());
            }

            return $responseObject;
        } catch (ConnectException $e) {
            Log::error('Business Profile API Connection Error: ' . $e->getMessage());
            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->error = 'Connection failed';
            return $responseObject;
        } catch (GuzzleException $e) {
            Log::error('Business Profile API Error: ' . $e->getMessage());
            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->error = 'Request failed';
            return $responseObject;
        }
    }
}