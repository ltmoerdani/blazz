<?php

namespace App\Services\WhatsApp;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MediaProcessingService
{
    private $accessToken;
    private $apiVersion;
    private $workspaceId;
    private $appId;
    private $phoneNumberId;
    private $wabaId;

    public function __construct($accessToken, $apiVersion, $appId, $phoneNumberId, $wabaId, $workspaceId)
    {
        $this->accessToken = $accessToken;
        $this->apiVersion = $apiVersion;
        $this->appId = $appId;
        $this->phoneNumberId = $phoneNumberId;
        $this->wabaId = $wabaId;
        $this->workspaceId = $workspaceId;
    }

    public function getMedia($mediaId)
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$mediaId}";
        $headers = $this->setHeaders();

        return $this->sendHttpRequest('GET', $url, null, $headers);
    }

    public function viewMedia($mediaId)
    {
        $response = $this->getMedia($mediaId);

        if($response->success && isset($response->data->url)) {
            $mediaUrl = $response->data->url;
            $mediaData = file_get_contents($mediaUrl);
            $mimeType = $this->getContentTypeFromUrl($mediaUrl);
            $fileName = $mediaId . '.' . $this->getFileExtensionFromMimeType($mimeType);

            return [
                'data' => $mediaData,
                'mime_type' => $mimeType,
                'file_name' => $fileName,
                'url' => $mediaUrl
            ];
        }

        return $response;
    }

    public function getContentTypeFromUrl($url) {
        try {
            // Make a HEAD request to fetch headers only
            $client = new Client();
            $response = $client->head($url);

            // Get the Content-Type header
            return $response->getHeaderLine('Content-Type');
        } catch (\Exception $e) {
            // Fallback to making a full GET request if HEAD fails
            $headers = get_headers($url, 1);
            return $headers['Content-Type'] ?? 'application/octet-stream';
        }
    }

    public function formatMediaResponse($wamId, $mediaType, $contentType, $transcription = null){
        $response = [
            "id" => $wamId,
            "media_type" => $mediaType,
            "mime_type" => $contentType,
            "transcription" => $transcription
        ];

        return $response;
    }

    public function getMediaSizeInBytesFromUrl($url) {
        $imageContent = file_get_contents($url);

        return strlen($imageContent);
    }

    public function initiateResumableUploadSession($file)
    {
        $sessionResponse = $this->createResumableUploadSession($file);

        if (!$sessionResponse->success) {
            return $sessionResponse;
        }

        $uploadUrl = $sessionResponse->data->upload_url;
        $sessionId = $sessionResponse->data->id;

        // Read file in chunks and upload
        $fileHandle = fopen($file->getPathname(), 'r');
        $chunkSize = 1024 * 1024; // 1MB chunks
        $offset = 0;

        while (!feof($fileHandle)) {
            $chunk = fread($fileHandle, $chunkSize);
            $chunkLength = strlen($chunk);

            $headers = [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Range' => "bytes $offset-" . ($offset + $chunkLength - 1) . "/$file->getSize()",
                'Content-Length' => $chunkLength,
            ];

            try {
                $response = Http::withHeaders($headers)->asJson()->put($uploadUrl, $chunk);

                if ($response->failed()) {
                    $responseObject = new \stdClass();
                    $responseObject->success = false;
                    $responseObject->error = 'Upload chunk failed';
                    return $responseObject;
                }
            } catch (\Exception $e) {
                $responseObject = new \stdClass();
                $responseObject->success = false;
                $responseObject->error = 'Upload chunk exception: ' . $e->getMessage();
                return $responseObject;
            }

            $offset += $chunkLength;
        }

        fclose($fileHandle);

        // Finalize upload
        $finalizeResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ])->post($uploadUrl, [
            'file_type' => $file->getMimeType(),
        ]);

        $responseObject = new \stdClass();
        $responseObject->success = $finalizeResponse->successful();
        $responseObject->status = $finalizeResponse->status();
        $responseObject->data = $finalizeResponse->json();

        if ($finalizeResponse->failed()) {
            $responseObject->error = $finalizeResponse->body();
        }

        return $responseObject;
    }

    public function createResumableUploadSession($file)
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

    public function storeMedia($file, $path = 'whatsapp/media')
    {
        try {
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs($path, $fileName, 'public');

            if ($filePath) {
                $responseObject = new \stdClass();
                $responseObject->success = true;
                $responseObject->data = new \stdClass();
                $responseObject->data->file_name = $fileName;
                $responseObject->data->file_path = $filePath;
                $responseObject->data->file_size = $file->getSize();
                $responseObject->data->mime_type = $file->getMimeType();
                $responseObject->data->url = Storage::url($filePath);

                return $responseObject;
            }

            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->error = 'File storage failed';
            return $responseObject;

        } catch (\Exception $e) {
            Log::error('Media storage error: ' . $e->getMessage());
            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->error = 'Storage exception: ' . $e->getMessage();
            return $responseObject;
        }
    }

    public function deleteMedia($filePath)
    {
        try {
            if (Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);

                $responseObject = new \stdClass();
                $responseObject->success = true;
                $responseObject->data = new \stdClass();
                $responseObject->data->message = 'File deleted successfully';

                return $responseObject;
            }

            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->error = 'File not found';
            return $responseObject;

        } catch (\Exception $e) {
            Log::error('Media deletion error: ' . $e->getMessage());
            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->error = 'Deletion failed';
            return $responseObject;
        }
    }

    public function optimizeMedia($file, $maxWidth = 1920, $maxHeight = 1080, $quality = 85)
    {
        try {
            $mimeType = $file->getMimeType();

            // Only optimize images
            if (strpos($mimeType, 'image/') !== 0) {
                return $file;
            }

            // For now, return the original file without optimization
            // TODO: Implement image optimization using GD library or install Intervention Image
            Log::info('Media optimization skipped - Intervention Image not installed');
            return $file;

        } catch (\Exception $e) {
            Log::error('Media optimization error: ' . $e->getMessage());
            return $file;
        }
    }

    private function getFileExtensionFromMimeType($mimeType)
    {
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'audio/mpeg' => 'mp3',
            'audio/amr' => 'amr',
            'audio/ogg' => 'ogg',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ];

        return $extensions[$mimeType] ?? 'bin';
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
                Log::error('Media API Error: ' . $response->body());
            }

            return $responseObject;
        } catch (ConnectException $e) {
            Log::error('Media API Connection Error: ' . $e->getMessage());
            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->error = 'Connection failed';
            return $responseObject;
        } catch (GuzzleException $e) {
            Log::error('Media API Error: ' . $e->getMessage());
            $responseObject = new \stdClass();
            $responseObject->success = false;
            $responseObject->error = 'Request failed';
            return $responseObject;
        }
    }
}