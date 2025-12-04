<?php

namespace App\Services\Media;

use App\Models\ChatMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Media Storage Service
 * 
 * Central orchestrator for media handling including:
 * - File upload and storage
 * - Processing delegation (images/videos)
 * - Path management
 * - Content deduplication
 * 
 * S3 Path Structure:
 * ├── campaigns/{workspace_id}/{campaign_uuid}/{usage_type}/{filename}
 * ├── chats/{workspace_id}/{direction}/{YYYY}/{MM}/{filename}
 * ├── templates/{workspace_id}/{template_uuid}/{filename}
 * └── shared/{workspace_id}/{YYYY}/{MM}/{filename}
 */
class MediaStorageService
{
    // ==========================================
    // MEDIA CONTEXT CONSTANTS
    // ==========================================

    public const CONTEXT_CAMPAIGN = 'campaigns';
    public const CONTEXT_CHAT = 'chats';
    public const CONTEXT_TEMPLATE = 'templates';
    public const CONTEXT_SHARED = 'shared';

    public const DIRECTION_RECEIVED = 'received';
    public const DIRECTION_SENT = 'sent';

    public const USAGE_HEADER = 'header';
    public const USAGE_BODY = 'body';
    public const USAGE_ATTACHMENT = 'attachment';

    // ==========================================
    // DEPENDENCIES
    // ==========================================

    protected ImageProcessor $imageProcessor;
    protected VideoProcessor $videoProcessor;

    // ==========================================
    // CONFIGURATION
    // ==========================================

    /** @var string Storage disk to use */
    protected string $disk = 's3';

    /** @var string S3 bucket name */
    protected string $bucket = '';

    /** @var bool Whether to enable deduplication */
    protected bool $deduplicationEnabled = true;

    /** @var array Allowed MIME types */
    protected array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'video/mp4',
        'video/mpeg',
        'video/quicktime',
        'video/3gpp',
        'audio/mpeg',
        'audio/ogg',
        'audio/wav',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /** @var int Maximum file size in bytes (default: 100MB) */
    protected int $maxFileSize = 104857600;

    // ==========================================
    // CONSTRUCTOR
    // ==========================================

    public function __construct(ImageProcessor $imageProcessor, VideoProcessor $videoProcessor)
    {
        $this->imageProcessor = $imageProcessor;
        $this->videoProcessor = $videoProcessor;
        $this->loadConfig();
    }

    /**
     * Load configuration from config/media.php
     */
    protected function loadConfig(): void
    {
        $this->disk = config('media.storage.disk', 's3');
        $this->bucket = config('filesystems.disks.s3.bucket', 's3-blazz');
        $this->deduplicationEnabled = config('media.deduplication.enabled', true);
        $this->maxFileSize = config('media.storage.max_file_size', 100 * 1024 * 1024);
    }

    // ==========================================
    // PUBLIC METHODS - UPLOAD
    // ==========================================

    /**
     * Upload and store media for campaigns
     * 
     * @param UploadedFile $file Uploaded file
     * @param int $workspaceId Workspace ID
     * @param array $options Additional options (campaign_uuid, usage_type)
     * @return ChatMedia Created media record
     */
    public function uploadForCampaign(UploadedFile $file, int $workspaceId, array $options = []): ChatMedia
    {
        // Validate file
        $this->validateFile($file);

        $originalName = $file->getClientOriginalName();
        $originalExtension = $file->getClientOriginalExtension();
        $mimeType = $file->getMimeType();
        $fileToUpload = $file->getRealPath();
        $tempConvertedPath = null;

        // Auto-convert video to MP4 if needed (MOV, AVI, etc.)
        // WhatsApp Web JS requires MP4 with H.264 codec
        if ($this->videoProcessor->needsConversion($file->getRealPath())) {
            Log::info('[MediaStorageService] Video needs conversion to MP4', [
                'original_name' => $originalName,
                'mime_type' => $mimeType,
            ]);

            $tempConvertedPath = $this->videoProcessor->convertToMp4($file->getRealPath());
            
            if ($tempConvertedPath && file_exists($tempConvertedPath)) {
                $fileToUpload = $tempConvertedPath;
                $originalExtension = 'mp4';
                $mimeType = 'video/mp4';
                
                Log::info('[MediaStorageService] Video converted to MP4 successfully', [
                    'original_name' => $originalName,
                    'converted_path' => $tempConvertedPath,
                ]);
            } else {
                Log::warning('[MediaStorageService] Video conversion failed, uploading original', [
                    'original_name' => $originalName,
                ]);
            }
        }

        // Check for duplicate (use converted file hash if applicable)
        $contentHash = md5_file($fileToUpload);
        if ($this->deduplicationEnabled) {
            $existing = ChatMedia::findByContentHash($contentHash, $workspaceId);
            
            if ($existing) {
                Log::info('[MediaStorageService] Found duplicate media', [
                    'hash' => $contentHash,
                    'existing_id' => $existing->id,
                ]);
                
                // Cleanup temp file
                if ($tempConvertedPath && file_exists($tempConvertedPath)) {
                    @unlink($tempConvertedPath);
                }
                
                return $existing;
            }
        }

        // Generate unique filename (with mp4 extension if converted)
        $filename = $this->generateFilename($originalName, $originalExtension);
        
        // Build storage path for campaign
        $campaignUuid = $options['campaign_uuid'] ?? 'draft';
        $usageType = $options['usage_type'] ?? self::USAGE_HEADER;
        $storagePath = $this->buildCampaignPath($workspaceId, $campaignUuid, $usageType);
        $fullPath = "{$storagePath}/{$filename}";

        // Store the file with public visibility
        // Use file_get_contents for converted files since they're not UploadedFile instances
        if ($tempConvertedPath && $fileToUpload === $tempConvertedPath) {
            $path = Storage::disk($this->disk)->put($fullPath, file_get_contents($fileToUpload), 'public');
            $path = $path ? $fullPath : null;
            $fileSize = filesize($fileToUpload);
        } else {
            $path = Storage::disk($this->disk)->putFileAs($storagePath, $file, $filename, 'public');
            $fileSize = $file->getSize();
        }

        // Cleanup temp converted file
        if ($tempConvertedPath && file_exists($tempConvertedPath)) {
            @unlink($tempConvertedPath);
        }

        if (!$path) {
            throw new \Exception('Failed to store file');
        }

        // Create media record with pending status
        $media = ChatMedia::create([
            'workspace_id' => $workspaceId,
            'name' => $originalName,
            'original_path' => $path,
            'type' => $mimeType,
            'size' => $fileSize,
            'location' => $this->disk === 's3' ? 's3' : 'local',
            'processing_status' => ChatMedia::STATUS_PENDING,
            'metadata' => [
                'original_extension' => $file->getClientOriginalExtension(),
                'uploaded_at' => now()->toIso8601String(),
                'source' => 'campaign_upload',
                'content_hash' => $contentHash,
                'converted_to_mp4' => $tempConvertedPath !== null,
            ],
        ]);

        Log::info('[MediaStorageService] Uploaded campaign media', [
            'media_id' => $media->id,
            'workspace_id' => $workspaceId,
            'filename' => $originalName,
            'size' => $fileSize,
            'converted' => $tempConvertedPath !== null,
        ]);

        return $media;
    }

    /**
     * Store media from URL
     * 
     * @param string $url Remote file URL
     * @param int $workspaceId Workspace ID
     * @param array $options Additional options (context, campaign_uuid, direction, etc.)
     * @return ChatMedia|null Created media record or null on failure
     */
    public function storeFromUrl(string $url, int $workspaceId, array $options = []): ?ChatMedia
    {
        try {
            // Download the file
            $tempPath = $this->downloadFile($url);
            
            if (!$tempPath) {
                Log::warning('[MediaStorageService] Failed to download file', ['url' => $url]);
                return null;
            }

            // Get file info
            $mimeType = mime_content_type($tempPath);
            $size = filesize($tempPath);
            $extension = $this->getExtensionFromMime($mimeType);
            $filename = $this->generateFilename(basename($url), $extension);

            // Build storage path based on context
            $context = $options['context'] ?? self::CONTEXT_SHARED;
            $storagePath = $this->buildContextPath($workspaceId, $context, $options);
            $fullPath = "{$storagePath}/{$filename}";

            // Store the file with public visibility
            Storage::disk($this->disk)->put($fullPath, file_get_contents($tempPath), 'public');

            // Create media record
            $media = ChatMedia::create([
                'workspace_id' => $workspaceId,
                'name' => basename($url),
                'original_path' => $fullPath,
                'type' => $mimeType,
                'size' => $size,
                'location' => $this->disk === 's3' ? 's3' : 'local',
                'processing_status' => ChatMedia::STATUS_PENDING,
                'metadata' => [
                    'source_url' => $url,
                    'downloaded_at' => now()->toIso8601String(),
                    'content_hash' => md5_file($tempPath),
                ],
            ]);

            // Clean up temp file
            @unlink($tempPath);

            return $media;

        } catch (\Exception $e) {
            Log::error('[MediaStorageService] Failed to store from URL', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Upload and store media for chat messages
     * 
     * @param UploadedFile|string $file Uploaded file or base64 content
     * @param int $workspaceId Workspace ID
     * @param array $options Additional options (direction, contact_id, mime_type)
     * @return ChatMedia Created media record
     */
    public function uploadForChat($file, int $workspaceId, array $options = []): ChatMedia
    {
        $direction = $options['direction'] ?? self::DIRECTION_RECEIVED;
        $storagePath = $this->buildChatPath($workspaceId, $direction);

        // Handle base64 content
        if (is_string($file)) {
            $content = base64_decode($file);
            $mimeType = $options['mime_type'] ?? 'application/octet-stream';
            $extension = $this->getExtensionFromMime($mimeType);
            $filename = $this->generateFilename($options['filename'] ?? 'media', $extension);
            $fullPath = "{$storagePath}/{$filename}";

            Storage::disk($this->disk)->put($fullPath, $content, 'public');

            return ChatMedia::create([
                'workspace_id' => $workspaceId,
                'name' => $options['filename'] ?? $filename,
                'original_path' => $fullPath,
                'type' => $mimeType,
                'size' => strlen($content),
                'location' => $this->disk === 's3' ? 's3' : 'local',
                'processing_status' => ChatMedia::STATUS_PENDING,
                'metadata' => [
                    'source' => 'chat_' . $direction,
                    'contact_id' => $options['contact_id'] ?? null,
                    'uploaded_at' => now()->toIso8601String(),
                    'content_hash' => md5($content), // Store hash in metadata for deduplication reference
                ],
            ]);
        }

        // Handle UploadedFile
        $this->validateFile($file);
        $filename = $this->generateFilename($file->getClientOriginalName(), $file->getClientOriginalExtension());
        $fullPath = "{$storagePath}/{$filename}";

        $path = Storage::disk($this->disk)->putFileAs($storagePath, $file, $filename, 'public');

        return ChatMedia::create([
            'workspace_id' => $workspaceId,
            'name' => $file->getClientOriginalName(),
            'original_path' => $path,
            'type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'location' => $this->disk === 's3' ? 's3' : 'local',
            'processing_status' => ChatMedia::STATUS_PENDING,
            'metadata' => [
                'source' => 'chat_' . $direction,
                'contact_id' => $options['contact_id'] ?? null,
                'uploaded_at' => now()->toIso8601String(),
                'content_hash' => md5_file($file->getRealPath()),
            ],
        ]);
    }

    /**
     * Upload and store media for templates
     * 
     * @param UploadedFile $file Uploaded file
     * @param int $workspaceId Workspace ID
     * @param array $options Additional options (template_uuid)
     * @return ChatMedia Created media record
     */
    public function uploadForTemplate(UploadedFile $file, int $workspaceId, array $options = []): ChatMedia
    {
        $this->validateFile($file);

        $templateUuid = $options['template_uuid'] ?? Str::uuid()->toString();
        $storagePath = $this->buildTemplatePath($workspaceId, $templateUuid);
        
        $filename = $this->generateFilename($file->getClientOriginalName(), $file->getClientOriginalExtension());
        $path = Storage::disk($this->disk)->putFileAs($storagePath, $file, $filename, 'public');

        if (!$path) {
            throw new \Exception('Failed to store template file');
        }

        return ChatMedia::create([
            'workspace_id' => $workspaceId,
            'name' => $file->getClientOriginalName(),
            'original_path' => $path,
            'type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'location' => $this->disk === 's3' ? 's3' : 'local',
            'processing_status' => ChatMedia::STATUS_PENDING,
            'metadata' => [
                'source' => 'template_upload',
                'template_uuid' => $templateUuid,
                'uploaded_at' => now()->toIso8601String(),
                'content_hash' => md5_file($file->getRealPath()),
            ],
        ]);
    }

    /**
     * Upload shared/general media
     * 
     * @param UploadedFile $file Uploaded file
     * @param int $workspaceId Workspace ID
     * @param array $options Additional options
     * @return ChatMedia Created media record
     */
    public function uploadShared(UploadedFile $file, int $workspaceId, array $options = []): ChatMedia
    {
        $this->validateFile($file);

        // Check for duplicate
        if ($this->deduplicationEnabled) {
            $contentHash = md5_file($file->getRealPath());
            $existing = ChatMedia::findByContentHash($contentHash, $workspaceId);
            
            if ($existing) {
                Log::info('[MediaStorageService] Found duplicate shared media', [
                    'hash' => $contentHash,
                    'existing_id' => $existing->id,
                ]);
                return $existing;
            }
        }

        $storagePath = $this->buildSharedPath($workspaceId);
        $filename = $this->generateFilename($file->getClientOriginalName(), $file->getClientOriginalExtension());
        $path = Storage::disk($this->disk)->putFileAs($storagePath, $file, $filename, 'public');

        return ChatMedia::create([
            'workspace_id' => $workspaceId,
            'name' => $file->getClientOriginalName(),
            'original_path' => $path,
            'type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'location' => $this->disk === 's3' ? 's3' : 'local',
            'processing_status' => ChatMedia::STATUS_PENDING,
            'metadata' => [
                'source' => 'shared_upload',
                'uploaded_at' => now()->toIso8601String(),
                'content_hash' => $contentHash ?? md5_file($file->getRealPath()),
            ],
        ]);
    }

    // ==========================================
    // PUBLIC METHODS - PROCESSING
    // ==========================================

    /**
     * Process media (compress, thumbnails, etc.)
     * 
     * @param ChatMedia $media Media record to process
     * @param array $options Processing options
     * @return ChatMedia Updated media record
     */
    public function processMedia(ChatMedia $media, array $options = []): ChatMedia
    {
        if ($media->processing_status === ChatMedia::STATUS_COMPLETED) {
            Log::debug('[MediaStorageService] Media already processed', ['media_id' => $media->id]);
            return $media;
        }

        $media->markAsProcessing();

        try {
            // For S3 storage, download file to temp location first
            $sourcePath = $this->downloadToTemp($media->original_path);
            $outputDir = sys_get_temp_dir() . '/blazz_media_' . $media->id;

            // Ensure output directory exists
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $results = [];

            if ($media->isImage()) {
                $results = $this->imageProcessor->process($sourcePath, $outputDir, $options);
            } elseif ($media->isVideo()) {
                $results = $this->videoProcessor->process($sourcePath, $outputDir, $options);
            } else {
                // For other file types (audio, documents), just mark as completed
                $media->markAsCompleted();
                return $media;
            }

            // Upload processed files back to S3 and update paths
            $updateData = [];
            $basePath = dirname($media->original_path);

            if (!empty($results['compressed'])) {
                $compressedPath = $this->uploadProcessedFile($results['compressed'], $basePath);
                $updateData['compressed_path'] = $compressedPath;
            }

            if (!empty($results['thumbnail'])) {
                $thumbnailPath = $this->uploadProcessedFile($results['thumbnail'], $basePath);
                $updateData['thumbnail_path'] = $thumbnailPath;
            }

            if (!empty($results['webp'])) {
                $webpPath = $this->uploadProcessedFile($results['webp'], $basePath);
                $updateData['webp_path'] = $webpPath;
            }

            // Update metadata
            $metadata = $media->metadata ?? [];
            if (!empty($results['dimensions'])) {
                $metadata['dimensions'] = $results['dimensions'];
            }
            if (!empty($results['metadata'])) {
                $metadata['video'] = $results['metadata'];
            }
            if (!empty($results['file_sizes'])) {
                $metadata['processed_sizes'] = $results['file_sizes'];
            }
            $metadata['processed_at'] = now()->toIso8601String();
            $metadata['storage_location'] = $this->disk;
            $updateData['metadata'] = $metadata;

            $media->update($updateData);
            $media->markAsCompleted();

            // Cleanup temp files
            $this->cleanupTempFiles($sourcePath, $outputDir);

            Log::info('[MediaStorageService] Successfully processed media', [
                'media_id' => $media->id,
                'type' => $media->type,
                'compressed' => !empty($results['compressed']),
                'thumbnail' => !empty($results['thumbnail']),
            ]);

            return $media->fresh();

        } catch (\Exception $e) {
            Log::error('[MediaStorageService] Media processing failed', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
            ]);
            $media->markAsFailed($e->getMessage());
            
            // Cleanup temp files on error
            if (isset($sourcePath) && file_exists($sourcePath)) {
                @unlink($sourcePath);
            }
            if (isset($outputDir) && is_dir($outputDir)) {
                $this->cleanupTempFiles(null, $outputDir);
            }
            
            return $media;
        }
    }

    // ==========================================
    // PUBLIC METHODS - RETRIEVAL
    // ==========================================

    /**
     * Get the best available URL for media
     * 
     * @param ChatMedia $media Media record
     * @param string $variant 'original', 'compressed', 'thumbnail', 'webp'
     * @return string|null URL to the media
     */
    public function getUrl(ChatMedia $media, string $variant = 'compressed'): ?string
    {
        $path = match($variant) {
            'original' => $media->original_path,
            'compressed' => $media->compressed_path ?? $media->original_path,
            'thumbnail' => $media->thumbnail_path,
            'webp' => $media->webp_path,
            default => $media->original_path,
        };

        if (!$path) {
            return null;
        }

        // Check for CDN URL
        if ($media->cdn_url && $variant === 'compressed') {
            return $media->cdn_url;
        }

        // Generate URL based on storage location
        return $this->generateUrl($path, $media->location);
    }

    /**
     * Get absolute file path for media (only works for local storage)
     * For S3, use downloadToTemp() instead
     */
    public function getAbsolutePath(string $relativePath): string
    {
        if ($this->disk === 's3') {
            // S3 doesn't have a local path, return the relative path
            return $relativePath;
        }
        return Storage::disk($this->disk)->path($relativePath);
    }

    /**
     * Download file from storage to temp location for processing
     */
    protected function downloadToTemp(string $relativePath): string
    {
        $tempPath = sys_get_temp_dir() . '/' . Str::random(32) . '_' . basename($relativePath);
        
        $content = Storage::disk($this->disk)->get($relativePath);
        
        if ($content === null) {
            throw new \Exception("Failed to download file from storage: {$relativePath}");
        }
        
        if (file_put_contents($tempPath, $content) === false) {
            throw new \Exception("Failed to write temp file: {$tempPath}");
        }
        
        return $tempPath;
    }

    /**
     * Upload processed file back to S3
     */
    protected function uploadProcessedFile(string $localPath, string $basePath): string
    {
        $filename = basename($localPath);
        $remotePath = "{$basePath}/{$filename}";
        
        $success = Storage::disk($this->disk)->put(
            $remotePath,
            file_get_contents($localPath),
            'public'
        );
        
        if (!$success) {
            throw new \Exception("Failed to upload processed file: {$remotePath}");
        }
        
        return $remotePath;
    }

    /**
     * Cleanup temporary files and directories
     */
    protected function cleanupTempFiles(?string $tempFile, ?string $tempDir): void
    {
        if ($tempFile && file_exists($tempFile)) {
            @unlink($tempFile);
        }
        
        if ($tempDir && is_dir($tempDir)) {
            $files = glob($tempDir . '/*');
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($tempDir);
        }
    }

    // ==========================================
    // PUBLIC METHODS - DELETION
    // ==========================================

    /**
     * Delete media and all associated files
     * 
     * @param ChatMedia $media Media record to delete
     * @param bool $hardDelete Whether to permanently delete
     */
    public function deleteMedia(ChatMedia $media, bool $hardDelete = false): void
    {
        // Delete physical files
        $pathsToDelete = array_filter([
            $media->original_path,
            $media->compressed_path,
            $media->thumbnail_path,
            $media->webp_path,
        ]);

        foreach ($pathsToDelete as $path) {
            if (Storage::disk($this->disk)->exists($path)) {
                Storage::disk($this->disk)->delete($path);
            }
        }

        // Delete the record
        if ($hardDelete) {
            $media->forceDelete();
        } else {
            $media->delete();
        }

        Log::info('[MediaStorageService] Deleted media', [
            'media_id' => $media->id,
            'hard_delete' => $hardDelete,
        ]);
    }

    // ==========================================
    // VALIDATION
    // ==========================================

    /**
     * Validate uploaded file
     * 
     * @throws \Exception
     */
    protected function validateFile(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new \Exception('Invalid file upload');
        }

        if ($file->getSize() > $this->maxFileSize) {
            throw new \Exception(sprintf(
                'File size %s exceeds maximum allowed size of %s',
                $this->formatBytes($file->getSize()),
                $this->formatBytes($this->maxFileSize)
            ));
        }

        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            throw new \Exception("File type '{$mimeType}' is not allowed");
        }
    }

    // ==========================================
    // UTILITY METHODS
    // ==========================================

    /**
     * Generate unique filename
     */
    protected function generateFilename(string $originalName, string $extension): string
    {
        $slug = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $slug = Str::limit($slug, 50, '');
        $unique = Str::random(8);
        
        return "{$slug}_{$unique}.{$extension}";
    }

    /**
     * Build storage path for campaigns
     * Path: campaigns/{workspace_id}/{campaign_uuid}/{usage_type}/
     */
    protected function buildCampaignPath(int $workspaceId, string $campaignUuid, string $usageType = 'header'): string
    {
        return self::CONTEXT_CAMPAIGN . "/{$workspaceId}/{$campaignUuid}/{$usageType}";
    }

    /**
     * Build storage path for chats
     * Path: chats/{workspace_id}/{direction}/{YYYY}/{MM}/
     */
    protected function buildChatPath(int $workspaceId, string $direction = 'received'): string
    {
        $date = now()->format('Y/m');
        return self::CONTEXT_CHAT . "/{$workspaceId}/{$direction}/{$date}";
    }

    /**
     * Build storage path for templates
     * Path: templates/{workspace_id}/{template_uuid}/
     */
    protected function buildTemplatePath(int $workspaceId, string $templateUuid): string
    {
        return self::CONTEXT_TEMPLATE . "/{$workspaceId}/{$templateUuid}";
    }

    /**
     * Build storage path for shared/general media
     * Path: shared/{workspace_id}/{YYYY}/{MM}/
     */
    protected function buildSharedPath(int $workspaceId): string
    {
        $date = now()->format('Y/m');
        return self::CONTEXT_SHARED . "/{$workspaceId}/{$date}";
    }

    /**
     * Build storage path based on context
     */
    protected function buildContextPath(int $workspaceId, string $context, array $options = []): string
    {
        return match($context) {
            self::CONTEXT_CAMPAIGN => $this->buildCampaignPath(
                $workspaceId,
                $options['campaign_uuid'] ?? 'draft',
                $options['usage_type'] ?? self::USAGE_HEADER
            ),
            self::CONTEXT_CHAT => $this->buildChatPath(
                $workspaceId,
                $options['direction'] ?? self::DIRECTION_RECEIVED
            ),
            self::CONTEXT_TEMPLATE => $this->buildTemplatePath(
                $workspaceId,
                $options['template_uuid'] ?? 'draft'
            ),
            default => $this->buildSharedPath($workspaceId),
        };
    }

    /**
     * Generate URL for stored file
     */
    protected function generateUrl(string $path, string $location): string
    {
        if ($location === 's3' || $location === 's3_cdn') {
            // For S3-compatible storage (CloudHost IS3)
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk('s3');
            return $disk->url($path);
        }

        // For local storage, generate URL via route or asset
        return asset("storage/{$path}");
    }

    /**
     * Download file from URL to temp location
     */
    protected function downloadFile(string $url): ?string
    {
        $tempPath = sys_get_temp_dir() . '/' . Str::random(32);
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0',
            ],
        ]);

        $content = @file_get_contents($url, false, $context);
        
        if ($content === false) {
            return null;
        }

        if (file_put_contents($tempPath, $content) === false) {
            return null;
        }

        return $tempPath;
    }

    /**
     * Get file extension from MIME type
     */
    protected function getExtensionFromMime(string $mimeType): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/mpeg' => 'mpg',
            'video/quicktime' => 'mov',
            'video/3gpp' => '3gp',
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
            'audio/wav' => 'wav',
            'application/pdf' => 'pdf',
        ];

        return $map[$mimeType] ?? 'bin';
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
    }

    // ==========================================
    // ACCESSORS
    // ==========================================

    /**
     * Get the image processor instance
     */
    public function getImageProcessor(): ImageProcessor
    {
        return $this->imageProcessor;
    }

    /**
     * Get the video processor instance
     */
    public function getVideoProcessor(): VideoProcessor
    {
        return $this->videoProcessor;
    }

    /**
     * Check if a MIME type is allowed
     */
    public function isAllowedMimeType(string $mimeType): bool
    {
        return in_array($mimeType, $this->allowedMimeTypes);
    }
}
