<?php

namespace App\Services;

use App\Models\BillingPayment;
use App\Models\BillingTransaction;
use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Traits\ConsumesExternalServices;
use Carbon\Carbon;
use CurrencyHelper;
use DB;
use Helper;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Storage;

class MediaService
{
    public static function upload($image)
    {
        if(config('settings.use_s3_as_storage',false)){
            $path = $image->storePublicly('uploads/media/send/'.$contact->company_id,'s3');
            $imageUrl = Storage::disk('s3')->url($path);
        } else {
            $path = $image->store(null,'public',);
            $imageUrl = Storage::disk('public')->url($path);
        }

        $name = basename($path);

        return ['name' => $name, 'path' => $imageUrl];
    }

    /**
     * Save base64 media from WhatsApp webhook
     *
     * @param string $base64Data Base64 encoded media data
     * @param string $mimetype Media MIME type
     * @param string $filename Original filename
     * @param int $workspaceId Workspace ID for folder organization
     * @return array ['name' => filename, 'path' => url, 'size' => bytes]
     */
    public static function saveBase64Media($base64Data, $mimetype, $filename, $workspaceId)
    {
        try {
            // Decode base64 data
            $fileData = base64_decode($base64Data);

            // Get file extension from mimetype
            $extension = self::getExtensionFromMimetype($mimetype);

            // Generate unique filename if not provided or invalid
            if (!$filename || !pathinfo($filename, PATHINFO_EXTENSION)) {
                $filename = uniqid('media_') . '.' . $extension;
            }

            // Sanitize filename
            $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);

            // Define storage path
            $directory = 'uploads/media/whatsapp/' . $workspaceId . '/' . date('Y/m');

            if (config('settings.use_s3_as_storage', false)) {
                // Store to S3
                $path = $directory . '/' . $filename;
                Storage::disk('s3')->put($path, $fileData, 'public');
                $fileUrl = Storage::disk('s3')->url($path);
            } else {
                // Store to local public disk
                $path = $directory . '/' . $filename;
                Storage::disk('public')->put($path, $fileData);
                $fileUrl = Storage::disk('public')->url($path);
            }

            return [
                'name' => $filename,
                'path' => $fileUrl,
                'size' => strlen($fileData),
                'type' => $mimetype
            ];

        } catch (\Exception $e) {
            Log::error('Failed to save base64 media', [
                'error' => $e->getMessage(),
                'mimetype' => $mimetype,
                'workspace_id' => $workspaceId
            ]);
            throw $e;
        }
    }

    /**
     * Get file extension from MIME type
     *
     * @param string $mimetype
     * @return string
     */
    private static function getExtensionFromMimetype($mimetype)
    {
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'video/quicktime' => 'mov',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'audio/aac' => 'aac',
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/msword' => 'doc',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.ms-powerpoint' => 'ppt',
            'text/plain' => 'txt',
        ];

        return $mimeMap[$mimetype] ?? 'bin';
    }
}