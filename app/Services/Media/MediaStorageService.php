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
 */
class MediaStorageService
{
    // ==========================================
    // DEPENDENCIES
    // ==========================================

    protected ImageProcessor $imageProcessor;
    protected VideoProcessor $videoProcessor;

    // ==========================================
    // CONFIGURATION
    // ==========================================

    /** @var string Storage disk to use */
    protected string $disk = 'local';

    /** @var string Base path for media storage */
    protected string $basePath = 'chat_media';

    /** @var string Base path for campaign media */
    protected string $campaignPath = 'campaign_media';

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
        $this->disk = config('media.storage.disk', 'local');
        $this->basePath = config('media.storage.base_path', 'chat_media');
        $this->campaignPath = config('media.storage.campaign_path', 'campaign_media');
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
     * @param array $options Additional options
     * @return ChatMedia Created media record
     */
    public function uploadForCampaign(UploadedFile $file, int $workspaceId, array $options = []): ChatMedia
    {
        // Validate file
        $this->validateFile($file);

        // Check for duplicate
        if ($this->deduplicationEnabled) {
            $contentHash = md5_file($file->getRealPath());
            $existing = ChatMedia::findByContentHash($contentHash, $workspaceId);
            
            if ($existing) {
                Log::info('[MediaStorageService] Found duplicate media', [
                    'hash' => $contentHash,
                    'existing_id' => $existing->id,
                ]);
                return $existing;
            }
        }

        // Generate unique filename
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = $this->generateFilename($originalName, $extension);
        
        // Build storage path
        $storagePath = $this->buildStoragePath($workspaceId, 'campaigns');
        $fullPath = "{$storagePath}/{$filename}";

        // Store the original file
        $path = $file->storeAs($storagePath, $filename, $this->disk);

        if (!$path) {
            throw new \Exception('Failed to store file');
        }

        // Create media record with pending status
        $media = ChatMedia::create([
            'workspace_id' => $workspaceId,
            'name' => $originalName,
            'original_path' => $path,
            'type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'location' => $this->disk === 's3' ? 's3' : 'local',
            'processing_status' => ChatMedia::STATUS_PENDING,
            'content_hash' => $contentHash ?? md5_file($file->getRealPath()),
            'metadata' => [
                'original_extension' => $extension,
                'uploaded_at' => now()->toIso8601String(),
                'source' => 'campaign_upload',
            ],
        ]);

        Log::info('[MediaStorageService] Uploaded campaign media', [
            'media_id' => $media->id,
            'workspace_id' => $workspaceId,
            'filename' => $originalName,
            'size' => $file->getSize(),
        ]);

        return $media;
    }

    /**
     * Store media from URL
     * 
     * @param string $url Remote file URL
     * @param int $workspaceId Workspace ID
     * @param array $options Additional options
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

            // Build storage path
            $storagePath = $this->buildStoragePath($workspaceId, 'campaigns');
            $fullPath = "{$storagePath}/{$filename}";

            // Store the file
            Storage::disk($this->disk)->put($fullPath, file_get_contents($tempPath));

            // Create media record
            $media = ChatMedia::create([
                'workspace_id' => $workspaceId,
                'name' => basename($url),
                'original_path' => $fullPath,
                'type' => $mimeType,
                'size' => $size,
                'location' => $this->disk === 's3' ? 's3' : 'local',
                'processing_status' => ChatMedia::STATUS_PENDING,
                'content_hash' => md5_file($tempPath),
                'metadata' => [
                    'source_url' => $url,
                    'downloaded_at' => now()->toIso8601String(),
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
            $sourcePath = $this->getAbsolutePath($media->original_path);
            $outputDir = dirname($sourcePath) . '/processed';

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

            // Update media record with processed paths
            $updateData = [];

            if (!empty($results['compressed'])) {
                $updateData['compressed_path'] = $this->toRelativePath($results['compressed']);
            }

            if (!empty($results['thumbnail'])) {
                $updateData['thumbnail_path'] = $this->toRelativePath($results['thumbnail']);
            }

            if (!empty($results['webp'])) {
                $updateData['webp_path'] = $this->toRelativePath($results['webp']);
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
            $updateData['metadata'] = $metadata;

            $media->update($updateData);
            $media->markAsCompleted();

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
     * Get absolute file path for media
     */
    public function getAbsolutePath(string $relativePath): string
    {
        return Storage::disk($this->disk)->path($relativePath);
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
     * Build storage path for workspace and type
     */
    protected function buildStoragePath(int $workspaceId, string $type = 'general'): string
    {
        $date = now()->format('Y/m');
        return "{$this->campaignPath}/{$workspaceId}/{$type}/{$date}";
    }

    /**
     * Convert absolute path to relative storage path
     */
    protected function toRelativePath(string $absolutePath): string
    {
        $storagePath = Storage::disk($this->disk)->path('');
        return str_replace($storagePath, '', $absolutePath);
    }

    /**
     * Generate URL for stored file
     */
    protected function generateUrl(string $path, string $location): string
    {
        if ($location === 's3' || $location === 's3_cdn') {
            return Storage::url($path);
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
