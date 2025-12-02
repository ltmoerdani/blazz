# 🔧 TECHNICAL SPECIFICATION - MEDIA STORAGE FOR CAMPAIGNS

**Version:** 1.1  
**Date:** 3 Desember 2025  
**Status:** ✅ VERIFIED & CORRECTED FROM LIVE DATABASE  
**Parent:** `00-media-storage-analysis.md`  
**Last Verification:** 2025-12-03 (localhost:3306)

> **⚠️ IMPORTANT CORRECTIONS APPLIED:**
> 1. Location enum: Keep 'amazon' for backward compatibility
> 2. Chat relationship: Direct FK (media_id), NOT pivot table
> 3. Model: Use `$guarded = []` per dev pattern

---

## 1. DATABASE SPECIFICATION

### 1.1 Migration: Enhance `chat_media` Table

**File:** `database/migrations/2025_12_xx_enhance_chat_media_for_campaigns.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_media', function (Blueprint $table) {
            // 1. Add UUID (generate for existing records)
            $table->uuid('uuid')->after('id')->nullable();
            
            // 2. Rename path to original_path
            $table->renameColumn('path', 'original_path');
            
            // 3. Add new path columns
            $table->string('compressed_path', 512)->nullable()->after('original_path');
            $table->string('thumbnail_path', 512)->nullable()->after('compressed_path');
            $table->string('webp_path', 512)->nullable()->after('thumbnail_path');
            $table->string('cdn_url', 512)->nullable()->after('webp_path');
            
            // 4. Update location enum
            // Note: MySQL enum change requires raw SQL
            
            // 5. Add processing columns
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])
                  ->default('completed') // Existing records are considered completed
                  ->after('cdn_url');
            $table->timestamp('processed_at')->nullable()->after('processing_status');
            $table->text('processing_error')->nullable()->after('processed_at');
            
            // 6. Add metadata JSON
            $table->json('metadata')->nullable()->after('processing_error');
            
            // 7. Add workspace_id
            $table->unsignedBigInteger('workspace_id')->nullable()->after('metadata');
            
            // 8. Add timestamps
            $table->timestamp('updated_at')->nullable()->after('created_at');
            $table->softDeletes();
        });
        
        // Generate UUIDs for existing records
        DB::table('chat_media')->whereNull('uuid')->orderBy('id')->each(function ($media) {
            DB::table('chat_media')
                ->where('id', $media->id)
                ->update(['uuid' => Str::uuid()]);
        });
        
        // Make UUID required and unique after populating
        Schema::table('chat_media', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->unique()->change();
        });
        
        // Change size from varchar to bigint
        // Note: Existing data may have string values like "1024" - they convert automatically
        DB::statement('ALTER TABLE chat_media MODIFY size BIGINT UNSIGNED NOT NULL DEFAULT 0');
        
        // Update location enum - IMPORTANT: Keep 'amazon' for backward compatibility
        // Current live DB has: enum('local','amazon')
        // Step 1: Add new values while preserving 'amazon'
        DB::statement("ALTER TABLE chat_media MODIFY location ENUM('local', 'amazon', 's3', 's3_cdn') DEFAULT 'local'");
        
        // Step 2: Migrate any existing 'amazon' records to 's3' (optional, run separately if needed)
        // DB::table('chat_media')->where('location', 'amazon')->update(['location' => 's3']);
        
        // Add indexes
        Schema::table('chat_media', function (Blueprint $table) {
            $table->index(['workspace_id'], 'idx_chat_media_workspace');
            $table->index(['processing_status', 'created_at'], 'idx_chat_media_processing');
            $table->index(['type', 'workspace_id'], 'idx_chat_media_type_workspace');
        });
        
        // Add foreign key (only if workspace_id is populated)
        // This will be added in a separate migration after data migration
    }

    public function down(): void
    {
        Schema::table('chat_media', function (Blueprint $table) {
            // Remove indexes
            $table->dropIndex('idx_chat_media_workspace');
            $table->dropIndex('idx_chat_media_processing');
            $table->dropIndex('idx_chat_media_type_workspace');
            
            // Remove columns
            $table->dropColumn([
                'uuid',
                'compressed_path',
                'thumbnail_path',
                'webp_path',
                'cdn_url',
                'processing_status',
                'processed_at',
                'processing_error',
                'metadata',
                'workspace_id',
                'updated_at',
                'deleted_at',
            ]);
            
            // Rename back
            $table->renameColumn('original_path', 'path');
        });
        
        // Revert size to varchar
        DB::statement('ALTER TABLE chat_media MODIFY size VARCHAR(128) NOT NULL');
        
        // Revert location enum
        DB::statement("ALTER TABLE chat_media MODIFY location ENUM('local', 'amazon') DEFAULT 'local'");
    }
};
```

### 1.2 Pivot Table: Campaign Media Relation

**File:** `database/migrations/2025_12_xx_create_campaign_media_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('media_id');
            $table->string('usage_type', 50)->default('header'); // header, body, attachment
            $table->json('parameters')->nullable(); // WhatsApp API parameters
            $table->timestamps();
            
            $table->foreign('campaign_id')
                  ->references('id')
                  ->on('campaigns')
                  ->onDelete('cascade');
                  
            $table->foreign('media_id')
                  ->references('id')
                  ->on('chat_media')
                  ->onDelete('cascade');
                  
            $table->unique(['campaign_id', 'media_id', 'usage_type']);
            $table->index('media_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_media');
    }
};
```

---

## 2. MODEL SPECIFICATION

### 2.1 Enhanced ChatMedia Model

**File:** `app/Models/ChatMedia.php`

```php
<?php

namespace App\Models;

use App\Helpers\DateTimeHelper;
use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class ChatMedia extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'chat_media';
    
    // Per dev pattern (06-development-patterns-guidelines.md): use $guarded instead of $fillable
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'size' => 'integer',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Processing Status Constants
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    // Media Type Categories
    const TYPE_IMAGE = 'image';
    const TYPE_VIDEO = 'video';
    const TYPE_AUDIO = 'audio';
    const TYPE_DOCUMENT = 'document';

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'campaign_media', 'media_id', 'campaign_id')
            ->withPivot(['usage_type', 'parameters'])
            ->withTimestamps();
    }

    /**
     * Get chats that use this media
     * Note: chats table has direct media_id FK, NOT a pivot table
     */
    public function chats(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Chat::class, 'media_id', 'id');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeForWorkspace($query, int $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopePending($query)
    {
        return $query->where('processing_status', self::STATUS_PENDING);
    }

    public function scopeProcessing($query)
    {
        return $query->where('processing_status', self::STATUS_PROCESSING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('processing_status', self::STATUS_COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('processing_status', self::STATUS_FAILED);
    }

    public function scopeImages($query)
    {
        return $query->where('type', 'like', 'image/%');
    }

    public function scopeVideos($query)
    {
        return $query->where('type', 'like', 'video/%');
    }

    public function scopeDocuments($query)
    {
        return $query->whereNotIn('type', function($q) {
            $q->select('type')
              ->from('chat_media')
              ->where('type', 'like', 'image/%')
              ->orWhere('type', 'like', 'video/%')
              ->orWhere('type', 'like', 'audio/%');
        });
    }

    // ==========================================
    // ACCESSORS
    // ==========================================

    public function getCreatedAtAttribute($value)
    {
        return DateTimeHelper::convertToWorkspaceTimezone($value)?->toDateTimeString();
    }

    /**
     * Get the optimal URL for display (compressed > original)
     */
    public function getUrlAttribute(): ?string
    {
        return $this->getOptimalUrl();
    }

    /**
     * Get original file URL
     */
    public function getOriginalUrlAttribute(): ?string
    {
        return $this->generateUrl($this->original_path);
    }

    /**
     * Get compressed file URL
     */
    public function getCompressedUrlAttribute(): ?string
    {
        return $this->generateUrl($this->compressed_path);
    }

    /**
     * Get thumbnail URL
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->generateUrl($this->thumbnail_path);
    }

    /**
     * Get WebP URL
     */
    public function getWebpUrlAttribute(): ?string
    {
        return $this->generateUrl($this->webp_path);
    }

    /**
     * Get human-readable file size
     */
    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get media type category
     */
    public function getTypeCategoryAttribute(): string
    {
        if (str_starts_with($this->type, 'image/')) return self::TYPE_IMAGE;
        if (str_starts_with($this->type, 'video/')) return self::TYPE_VIDEO;
        if (str_starts_with($this->type, 'audio/')) return self::TYPE_AUDIO;
        return self::TYPE_DOCUMENT;
    }

    /**
     * Get image dimensions from metadata
     */
    public function getDimensionsAttribute(): ?array
    {
        return $this->metadata['dimensions'] ?? null;
    }

    /**
     * Get compression ratio from metadata
     */
    public function getCompressionRatioAttribute(): ?float
    {
        return $this->metadata['compression_ratio'] ?? null;
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Get optimal URL based on available versions
     */
    public function getOptimalUrl(string $preferredVariant = 'compressed'): ?string
    {
        // If CDN URL is available, use it
        if ($this->cdn_url) {
            return $this->cdn_url;
        }

        // Priority: compressed > webp > original
        $priorities = match($preferredVariant) {
            'thumbnail' => ['thumbnail_path', 'compressed_path', 'original_path'],
            'webp' => ['webp_path', 'compressed_path', 'original_path'],
            default => ['compressed_path', 'webp_path', 'original_path'],
        };

        foreach ($priorities as $pathField) {
            if (!empty($this->$pathField)) {
                return $this->generateUrl($this->$pathField);
            }
        }

        return null;
    }

    /**
     * Generate full URL from path
     */
    protected function generateUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // If already a full URL, return as-is
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // Generate URL based on location
        return match($this->location) {
            'local' => rtrim(config('app.url'), '/') . '/storage/' . ltrim($path, '/'),
            's3', 'amazon', 's3_cdn' => Storage::disk('s3')->url($path),
            default => $path,
        };
    }

    /**
     * Check if media is an image
     */
    public function isImage(): bool
    {
        return $this->type_category === self::TYPE_IMAGE;
    }

    /**
     * Check if media is a video
     */
    public function isVideo(): bool
    {
        return $this->type_category === self::TYPE_VIDEO;
    }

    /**
     * Check if media is a document
     */
    public function isDocument(): bool
    {
        return $this->type_category === self::TYPE_DOCUMENT;
    }

    /**
     * Check if media is audio
     */
    public function isAudio(): bool
    {
        return $this->type_category === self::TYPE_AUDIO;
    }

    /**
     * Check if processing is complete
     */
    public function isProcessed(): bool
    {
        return $this->processing_status === self::STATUS_COMPLETED;
    }

    /**
     * Check if processing failed
     */
    public function isFailed(): bool
    {
        return $this->processing_status === self::STATUS_FAILED;
    }

    /**
     * Check if still processing
     */
    public function isProcessing(): bool
    {
        return in_array($this->processing_status, [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
        ]);
    }

    // ==========================================
    // STATUS MANAGEMENT
    // ==========================================

    /**
     * Mark media as processing
     */
    public function markAsProcessing(): self
    {
        $this->update([
            'processing_status' => self::STATUS_PROCESSING,
            'processing_error' => null,
        ]);
        
        return $this;
    }

    /**
     * Mark media as completed
     */
    public function markAsCompleted(array $metadata = []): self
    {
        $this->update([
            'processing_status' => self::STATUS_COMPLETED,
            'processed_at' => now(),
            'processing_error' => null,
            'metadata' => array_merge($this->metadata ?? [], $metadata),
        ]);
        
        return $this;
    }

    /**
     * Mark media as failed
     */
    public function markAsFailed(string $error): self
    {
        $this->update([
            'processing_status' => self::STATUS_FAILED,
            'processing_error' => $error,
        ]);
        
        return $this;
    }

    // ==========================================
    // STATIC HELPERS
    // ==========================================

    /**
     * Create placeholder media record for async processing
     */
    public static function createPlaceholder(
        string $name,
        string $type,
        int $size,
        int $workspaceId,
        ?string $tempPath = null
    ): self {
        return static::create([
            'name' => $name,
            'type' => $type,
            'size' => $size,
            'workspace_id' => $workspaceId,
            'original_path' => $tempPath,
            'location' => config('filesystems.default') === 's3' ? 's3' : 'local',
            'processing_status' => self::STATUS_PENDING,
        ]);
    }

    /**
     * Find by content hash (for deduplication)
     */
    public static function findByContentHash(string $hash, int $workspaceId): ?self
    {
        return static::forWorkspace($workspaceId)
            ->where('metadata->hash', $hash)
            ->completed()
            ->first();
    }
}
```

### 2.2 Update Campaign Model (Relationship Addition)

**Add to `app/Models/Campaign.php`:**

```php
// Add import
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Add relationship method
public function media(): BelongsToMany
{
    return $this->belongsToMany(ChatMedia::class, 'campaign_media', 'campaign_id', 'media_id')
        ->withPivot(['usage_type', 'parameters'])
        ->withTimestamps();
}

/**
 * Get header media
 */
public function getHeaderMedia(): ?ChatMedia
{
    return $this->media()
        ->wherePivot('usage_type', 'header')
        ->first();
}

/**
 * Attach media to campaign
 */
public function attachMedia(ChatMedia $media, string $usageType = 'header', array $parameters = []): void
{
    $this->media()->attach($media->id, [
        'usage_type' => $usageType,
        'parameters' => json_encode($parameters),
    ]);
}
```

---

## 3. SERVICE SPECIFICATION

### 3.1 MediaStorageService (Main Service)

**File:** `app/Services/Media/MediaStorageService.php`

```php
<?php

namespace App\Services\Media;

use App\Models\ChatMedia;
use App\Jobs\ProcessCampaignMediaJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class MediaStorageService
{
    protected ImageProcessor $imageProcessor;
    protected VideoProcessor $videoProcessor;
    
    public function __construct(
        ImageProcessor $imageProcessor,
        VideoProcessor $videoProcessor
    ) {
        $this->imageProcessor = $imageProcessor;
        $this->videoProcessor = $videoProcessor;
    }

    /**
     * Upload media asynchronously (recommended for campaign uploads)
     * Returns media ID immediately, processing happens in background
     */
    public function uploadAsync(
        UploadedFile $file,
        int $workspaceId,
        array $options = []
    ): ChatMedia {
        // 1. Store to temp location
        $tempPath = $this->storeTemp($file, $workspaceId);
        
        // 2. Create placeholder record
        $media = ChatMedia::createPlaceholder(
            name: $file->getClientOriginalName(),
            type: $file->getMimeType(),
            size: $file->getSize(),
            workspaceId: $workspaceId,
            tempPath: $tempPath
        );
        
        // 3. Dispatch processing job
        ProcessCampaignMediaJob::dispatch($media->id)
            ->onQueue('media-high')
            ->delay(now()->addSeconds(1));
        
        return $media;
    }

    /**
     * Upload media synchronously (for backwards compatibility)
     */
    public function upload(
        UploadedFile $file,
        int $workspaceId,
        array $options = []
    ): ChatMedia {
        // 1. Store original
        $originalPath = $this->storeOriginal($file, $workspaceId);
        
        // 2. Create record
        $media = ChatMedia::create([
            'name' => $file->getClientOriginalName(),
            'type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'workspace_id' => $workspaceId,
            'original_path' => $originalPath,
            'location' => $this->getStorageLocation(),
            'processing_status' => ChatMedia::STATUS_COMPLETED,
            'processed_at' => now(),
        ]);
        
        // 3. Process if enabled
        if ($options['process'] ?? true) {
            $this->process($media);
        }
        
        return $media;
    }

    /**
     * Process media (compress, thumbnail, etc.)
     * Note: Video processing is optional and depends on FFmpeg availability
     */
    public function process(ChatMedia $media): void
    {
        $media->markAsProcessing();
        
        try {
            $metadata = [];
            
            if ($media->isImage()) {
                $result = $this->imageProcessor->process($media);
                $metadata = array_merge($metadata, $result);
            } elseif ($media->isVideo()) {
                // Video processing is optional - gracefully handle if FFmpeg not available
                if ($this->videoProcessor->isAvailable()) {
                    $result = $this->videoProcessor->process($media);
                    $metadata = array_merge($metadata, $result);
                } else {
                    Log::info('Video processing skipped - FFmpeg not available', [
                        'media_id' => $media->id
                    ]);
                    $metadata['video_processing'] = 'skipped';
                }
            }
            
            // Add content hash for deduplication
            $metadata['hash'] = $this->generateContentHash($media);
            
            $media->markAsCompleted($metadata);
            
        } catch (\Exception $e) {
            Log::error('Media processing failed', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
            ]);
            
            $media->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Store file to temp location
     */
    protected function storeTemp(UploadedFile $file, int $workspaceId): string
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = "temp/workspace_{$workspaceId}/" . $filename;
        
        Storage::disk($this->getDisk())->put($path, file_get_contents($file));
        
        return $path;
    }

    /**
     * Store file to permanent original location
     */
    protected function storeOriginal(UploadedFile $file, int $workspaceId): string
    {
        $hash = md5(file_get_contents($file));
        $extension = $file->getClientOriginalExtension();
        $filename = $hash . '.' . $extension;
        
        $path = sprintf(
            'original/workspace_%d/%s/%s/%s',
            $workspaceId,
            date('Y'),
            date('m'),
            $filename
        );
        
        Storage::disk($this->getDisk())->put($path, file_get_contents($file));
        
        return $path;
    }

    /**
     * Generate content hash for deduplication
     */
    protected function generateContentHash(ChatMedia $media): string
    {
        $content = Storage::disk($this->getDisk())->get($media->original_path);
        return 'sha256:' . hash('sha256', $content);
    }

    /**
     * Get storage disk name
     */
    protected function getDisk(): string
    {
        return config('filesystems.default') === 's3' ? 's3' : 'local';
    }

    /**
     * Get storage location enum value
     */
    protected function getStorageLocation(): string
    {
        return config('filesystems.default') === 's3' ? 's3' : 'local';
    }

    /**
     * Delete media and all its versions
     */
    public function delete(ChatMedia $media): bool
    {
        $paths = array_filter([
            $media->original_path,
            $media->compressed_path,
            $media->thumbnail_path,
            $media->webp_path,
        ]);
        
        foreach ($paths as $path) {
            Storage::disk($this->getDisk())->delete($path);
        }
        
        return $media->delete();
    }

    /**
     * Get URL for media
     */
    public function getUrl(ChatMedia $media, string $variant = 'compressed'): ?string
    {
        return $media->getOptimalUrl($variant);
    }
}
```

### 3.2 ImageProcessor Service

**File:** `app/Services/Media/ImageProcessor.php`

```php
<?php

namespace App\Services\Media;

use App\Models\ChatMedia;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageProcessor
{
    protected ImageManager $imageManager;
    protected string $disk;

    // Configuration
    protected int $compressionQuality = 75;
    protected int $thumbnailSize = 150;
    protected int $lqipSize = 20;
    protected int $maxWidth = 1920;
    protected int $maxHeight = 1080;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
        $this->disk = config('filesystems.default') === 's3' ? 's3' : 'local';
    }

    /**
     * Process image: compress, thumbnail, webp, lqip
     */
    public function process(ChatMedia $media): array
    {
        $originalContent = Storage::disk($this->disk)->get($media->original_path);
        $image = $this->imageManager->read($originalContent);
        
        $metadata = [
            'dimensions' => [
                'width' => $image->width(),
                'height' => $image->height(),
            ],
            'original_size' => $media->size,
        ];

        // 1. Compress original
        $compressedPath = $this->compress($image, $media);
        if ($compressedPath) {
            $media->update(['compressed_path' => $compressedPath]);
            $metadata['compressed_size'] = Storage::disk($this->disk)->size($compressedPath);
            $metadata['compression_ratio'] = 1 - ($metadata['compressed_size'] / $metadata['original_size']);
        }

        // 2. Generate thumbnail
        $thumbnailPath = $this->generateThumbnail($image, $media);
        if ($thumbnailPath) {
            $media->update(['thumbnail_path' => $thumbnailPath]);
        }

        // 3. Generate WebP version
        $webpPath = $this->generateWebP($image, $media);
        if ($webpPath) {
            $media->update(['webp_path' => $webpPath]);
        }

        // 4. Generate LQIP (Low Quality Image Placeholder)
        $metadata['lqip'] = $this->generateLQIP($image);

        // 5. Extract dominant color
        $metadata['dominant_color'] = $this->extractDominantColor($image);

        return $metadata;
    }

    /**
     * Compress image to target quality
     */
    protected function compress($image, ChatMedia $media): ?string
    {
        // Resize if too large
        if ($image->width() > $this->maxWidth || $image->height() > $this->maxHeight) {
            $image->scale(width: $this->maxWidth, height: $this->maxHeight);
        }

        $extension = pathinfo($media->original_path, PATHINFO_EXTENSION);
        $basePath = dirname($media->original_path);
        $filename = pathinfo($media->original_path, PATHINFO_FILENAME);
        
        $compressedPath = str_replace('original/', 'compressed/', $basePath) 
            . '/' . $filename . '.' . $extension;

        $encoded = $image->toJpeg($this->compressionQuality);
        Storage::disk($this->disk)->put($compressedPath, $encoded);

        return $compressedPath;
    }

    /**
     * Generate thumbnail
     */
    protected function generateThumbnail($image, ChatMedia $media): ?string
    {
        $thumbnail = clone $image;
        $thumbnail->cover($this->thumbnailSize, $this->thumbnailSize);

        $basePath = dirname($media->original_path);
        $filename = pathinfo($media->original_path, PATHINFO_FILENAME);
        
        $thumbnailPath = str_replace('original/', 'thumbnails/', $basePath) 
            . '/' . $filename . '_thumb.jpg';

        $encoded = $thumbnail->toJpeg(80);
        Storage::disk($this->disk)->put($thumbnailPath, $encoded);

        return $thumbnailPath;
    }

    /**
     * Generate WebP version
     */
    protected function generateWebP($image, ChatMedia $media): ?string
    {
        $basePath = dirname($media->original_path);
        $filename = pathinfo($media->original_path, PATHINFO_FILENAME);
        
        $webpPath = str_replace('original/', 'compressed/', $basePath) 
            . '/' . $filename . '.webp';

        $encoded = $image->toWebp(80);
        Storage::disk($this->disk)->put($webpPath, $encoded);

        return $webpPath;
    }

    /**
     * Generate Low Quality Image Placeholder (base64)
     */
    protected function generateLQIP($image): string
    {
        $lqip = clone $image;
        $lqip->scale(width: $this->lqipSize);
        
        $encoded = $lqip->toJpeg(50);
        return 'data:image/jpeg;base64,' . base64_encode($encoded);
    }

    /**
     * Extract dominant color from image
     */
    protected function extractDominantColor($image): string
    {
        // Sample from center of image
        $color = $image->pickColor(
            $image->width() / 2,
            $image->height() / 2
        );
        
        return sprintf('#%02x%02x%02x', 
            $color->red()->toInt(),
            $color->green()->toInt(),
            $color->blue()->toInt()
        );
    }
}
```

### 3.3 VideoProcessor Service (Basic)

> **⚠️ ENVIRONMENT NOTE:** FFmpeg is NOT INSTALLED on current dev environment.
> Video processing will be gracefully skipped. To enable:
> ```bash
> # macOS
> brew install ffmpeg
> 
> # Linux
> apt install ffmpeg
> 
> # Then install PHP package
> composer require php-ffmpeg/php-ffmpeg
> ```

**File:** `app/Services/Media/VideoProcessor.php`

```php
<?php

namespace App\Services\Media;

use App\Models\ChatMedia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * VideoProcessor - Handles video compression and thumbnail generation
 * 
 * REQUIRES: FFmpeg installed on server
 * If FFmpeg not available, processing is gracefully skipped
 */
class VideoProcessor
{
    protected $ffmpeg = null;
    protected string $disk;
    protected bool $isAvailable = false;

    public function __construct()
    {
        $this->disk = config('filesystems.default') === 's3' ? 's3' : 'local';
        
        // Check if video processing is enabled and FFmpeg is available
        if (!config('media.video_processing', false)) {
            Log::info('VideoProcessor: Video processing disabled via config');
            return;
        }
        
        // Initialize FFMpeg if available
        try {
            // Only try to load FFMpeg if the package is installed
            if (!class_exists('FFMpeg\\FFMpeg')) {
                Log::info('VideoProcessor: php-ffmpeg/php-ffmpeg package not installed');
                return;
            }
            
            $this->ffmpeg = \FFMpeg\FFMpeg::create([
                'ffmpeg.binaries' => config('ffmpeg.ffmpeg_path', '/usr/bin/ffmpeg'),
                'ffprobe.binaries' => config('ffmpeg.ffprobe_path', '/usr/bin/ffprobe'),
            ]);
            $this->isAvailable = true;
        } catch (\Exception $e) {
            Log::warning('FFMpeg not available, video processing disabled', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Check if video processing is available
     */
    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    /**
     * Process video: thumbnail, metadata extraction
     */
    public function process(ChatMedia $media): array
    {
        $metadata = [];

        if (!$this->isAvailable || !$this->ffmpeg) {
            // FFMpeg not available, return minimal metadata
            Log::info('VideoProcessor: Skipping video processing (FFmpeg not available)', [
                'media_id' => $media->id
            ]);
            return $metadata;
        }

        try {
            // Download to temp if on S3
            $localPath = $this->getLocalPath($media);
            
            $video = $this->ffmpeg->open($localPath);
            $streams = $video->getStreams();
            $videoStream = $streams->videos()->first();

            if ($videoStream) {
                $metadata['dimensions'] = [
                    'width' => $videoStream->get('width'),
                    'height' => $videoStream->get('height'),
                ];
                $metadata['duration'] = $videoStream->get('duration');
                $metadata['codec'] = $videoStream->get('codec_name');
                $metadata['bitrate'] = $videoStream->get('bit_rate');
            }

            // Generate thumbnail from first frame
            $thumbnailPath = $this->generateThumbnail($video, $media);
            if ($thumbnailPath) {
                $media->update(['thumbnail_path' => $thumbnailPath]);
            }

            // Cleanup temp file if downloaded from S3
            $this->cleanupTemp($localPath, $media);

        } catch (\Exception $e) {
            Log::error('Video processing error', [
                'media_id' => $media->id,
                'error' => $e->getMessage()
            ]);
        }

        return $metadata;
    }

    /**
     * Generate video thumbnail
     */
    protected function generateThumbnail($video, ChatMedia $media): ?string
    {
        try {
            $basePath = dirname($media->original_path);
            $filename = pathinfo($media->original_path, PATHINFO_FILENAME);
            
            $thumbnailPath = str_replace('original/', 'thumbnails/', $basePath) 
                . '/' . $filename . '_thumb.jpg';

            $tempThumb = tempnam(sys_get_temp_dir(), 'thumb');
            
            $video->frame(TimeCode::fromSeconds(1))
                ->save($tempThumb);

            // Upload to storage
            Storage::disk($this->disk)->put(
                $thumbnailPath, 
                file_get_contents($tempThumb)
            );
            
            unlink($tempThumb);

            return $thumbnailPath;
            
        } catch (\Exception $e) {
            Log::error('Video thumbnail generation failed', [
                'media_id' => $media->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get local path for processing (download from S3 if needed)
     */
    protected function getLocalPath(ChatMedia $media): string
    {
        if ($this->disk === 'local') {
            return Storage::disk('local')->path($media->original_path);
        }

        // Download from S3 to temp
        $content = Storage::disk('s3')->get($media->original_path);
        $tempPath = tempnam(sys_get_temp_dir(), 'video');
        file_put_contents($tempPath, $content);
        
        return $tempPath;
    }

    /**
     * Cleanup temporary files
     */
    protected function cleanupTemp(string $localPath, ChatMedia $media): void
    {
        if ($this->disk !== 'local' && file_exists($localPath)) {
            unlink($localPath);
        }
    }
}
```

---

## 4. JOB SPECIFICATION

### 4.1 ProcessCampaignMediaJob

**File:** `app/Jobs/ProcessCampaignMediaJob.php`

```php
<?php

namespace App\Jobs;

use App\Models\ChatMedia;
use App\Services\Media\MediaStorageService;
use App\Events\MediaProcessingCompleted;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCampaignMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 3;
    public array $backoff = [30, 60, 120];

    public function __construct(
        protected int $mediaId
    ) {
        $this->onQueue('media-high');
    }

    public function handle(MediaStorageService $mediaService): void
    {
        $media = ChatMedia::find($this->mediaId);
        
        if (!$media) {
            Log::warning('ProcessCampaignMediaJob: Media not found', [
                'media_id' => $this->mediaId
            ]);
            return;
        }

        if ($media->processing_status === ChatMedia::STATUS_COMPLETED) {
            Log::info('ProcessCampaignMediaJob: Media already processed', [
                'media_id' => $this->mediaId
            ]);
            return;
        }

        try {
            Log::info('ProcessCampaignMediaJob: Starting processing', [
                'media_id' => $this->mediaId,
                'name' => $media->name,
                'type' => $media->type,
            ]);

            $mediaService->process($media);

            Log::info('ProcessCampaignMediaJob: Processing completed', [
                'media_id' => $this->mediaId,
            ]);

            // Broadcast completion event
            broadcast(new MediaProcessingCompleted($media))->toOthers();

        } catch (\Exception $e) {
            Log::error('ProcessCampaignMediaJob: Processing failed', [
                'media_id' => $this->mediaId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $media = ChatMedia::find($this->mediaId);
        
        if ($media) {
            $media->markAsFailed($exception->getMessage());
        }

        Log::error('ProcessCampaignMediaJob: Job failed permanently', [
            'media_id' => $this->mediaId,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

---

## 5. EVENT SPECIFICATION

### 5.1 MediaProcessingCompleted Event

**File:** `app/Events/MediaProcessingCompleted.php`

```php
<?php

namespace App\Events;

use App\Models\ChatMedia;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MediaProcessingCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ChatMedia $media
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("workspace.{$this->media->workspace_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'media.processing.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'media_id' => $this->media->id,
            'uuid' => $this->media->uuid,
            'name' => $this->media->name,
            'status' => $this->media->processing_status,
            'thumbnail_url' => $this->media->thumbnail_url,
            'url' => $this->media->url,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
```

---

## 6. API ENDPOINTS

### 6.1 Media Upload Endpoint

**Add to routes/api.php:**

```php
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    Route::prefix('media')->group(function () {
        Route::post('/upload', [MediaController::class, 'upload']);
        Route::get('/{uuid}', [MediaController::class, 'show']);
        Route::get('/{uuid}/status', [MediaController::class, 'status']);
        Route::delete('/{uuid}', [MediaController::class, 'destroy']);
    });
});
```

### 6.2 MediaController

**File:** `app/Http/Controllers/Api/v1/MediaController.php`

```php
<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\ChatMedia;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MediaController extends Controller
{
    public function __construct(
        protected MediaStorageService $mediaService
    ) {}

    /**
     * Upload media for campaigns
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:16384', // 16MB max
            'async' => 'boolean',
        ]);

        $workspaceId = session('current_workspace');
        $file = $request->file('file');
        $async = $request->boolean('async', true);

        if ($async) {
            $media = $this->mediaService->uploadAsync($file, $workspaceId);
        } else {
            $media = $this->mediaService->upload($file, $workspaceId);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $media->id,
                'uuid' => $media->uuid,
                'name' => $media->name,
                'type' => $media->type,
                'size' => $media->size,
                'status' => $media->processing_status,
                'url' => $media->url,
                'thumbnail_url' => $media->thumbnail_url,
            ]
        ], 201);
    }

    /**
     * Get media status
     */
    public function status(string $uuid): JsonResponse
    {
        $media = ChatMedia::where('uuid', $uuid)
            ->forWorkspace(session('current_workspace'))
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'uuid' => $media->uuid,
                'status' => $media->processing_status,
                'processed_at' => $media->processed_at?->toIso8601String(),
                'error' => $media->processing_error,
                'url' => $media->url,
                'thumbnail_url' => $media->thumbnail_url,
            ]
        ]);
    }

    /**
     * Get media details
     */
    public function show(string $uuid): JsonResponse
    {
        $media = ChatMedia::where('uuid', $uuid)
            ->forWorkspace(session('current_workspace'))
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $media->id,
                'uuid' => $media->uuid,
                'name' => $media->name,
                'type' => $media->type,
                'size' => $media->size,
                'human_size' => $media->human_size,
                'status' => $media->processing_status,
                'urls' => [
                    'original' => $media->original_url,
                    'compressed' => $media->compressed_url,
                    'thumbnail' => $media->thumbnail_url,
                    'webp' => $media->webp_url,
                ],
                'metadata' => $media->metadata,
                'created_at' => $media->created_at,
            ]
        ]);
    }

    /**
     * Delete media
     */
    public function destroy(string $uuid): JsonResponse
    {
        $media = ChatMedia::where('uuid', $uuid)
            ->forWorkspace(session('current_workspace'))
            ->firstOrFail();

        $this->mediaService->delete($media);

        return response()->json([
            'success' => true,
            'message' => 'Media deleted successfully'
        ]);
    }
}
```

---

## 7. CONFIGURATION

### 7.1 Queue Configuration

**Add to `config/queue.php`:**

```php
'connections' => [
    // ... existing connections
    
    'redis' => [
        // ... existing config
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
        'after_commit' => false,
    ],
],

// Add queue priorities
'priorities' => [
    'media-high' => 10,
    'media-low' => 1,
    'default' => 5,
],
```

### 7.2 Media Configuration

**Create `config/media.php`:**

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Image Processing Settings
    |--------------------------------------------------------------------------
    */
    'image' => [
        'compression_quality' => env('MEDIA_IMAGE_QUALITY', 75),
        'thumbnail_size' => env('MEDIA_THUMBNAIL_SIZE', 150),
        'max_width' => env('MEDIA_MAX_WIDTH', 1920),
        'max_height' => env('MEDIA_MAX_HEIGHT', 1080),
        'generate_webp' => env('MEDIA_GENERATE_WEBP', true),
        'generate_lqip' => env('MEDIA_GENERATE_LQIP', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Video Processing Settings
    |--------------------------------------------------------------------------
    */
    'video' => [
        'enabled' => env('MEDIA_VIDEO_PROCESSING', true),
        'max_duration' => env('MEDIA_VIDEO_MAX_DURATION', 300), // seconds
        'thumbnail_time' => env('MEDIA_VIDEO_THUMBNAIL_TIME', 1), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Settings
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'temp_lifetime' => env('MEDIA_TEMP_LIFETIME', 86400), // 24 hours
        'deduplication' => env('MEDIA_DEDUPLICATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_file_size' => env('MEDIA_MAX_FILE_SIZE', 16777216), // 16MB
        'allowed_image_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'allowed_video_types' => ['video/mp4', 'video/quicktime', 'video/3gpp'],
        'allowed_document_types' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
    ],
];
```

---

## 8. TESTING SPECIFICATION

### 8.1 Unit Tests

**File:** `tests/Unit/Services/Media/MediaStorageServiceTest.php`

```php
<?php

namespace Tests\Unit\Services\Media;

use App\Models\ChatMedia;
use App\Services\Media\MediaStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MediaStorageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->service = app(MediaStorageService::class);
    }

    public function test_can_upload_image_synchronously(): void
    {
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);
        $workspaceId = 1;

        $media = $this->service->upload($file, $workspaceId, ['process' => false]);

        $this->assertInstanceOf(ChatMedia::class, $media);
        $this->assertEquals('test.jpg', $media->name);
        $this->assertEquals('image/jpeg', $media->type);
        $this->assertEquals($workspaceId, $media->workspace_id);
    }

    public function test_can_upload_image_asynchronously(): void
    {
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);
        $workspaceId = 1;

        $media = $this->service->uploadAsync($file, $workspaceId);

        $this->assertInstanceOf(ChatMedia::class, $media);
        $this->assertEquals(ChatMedia::STATUS_PENDING, $media->processing_status);
    }

    // Add more tests...
}
```

---

**END OF TECHNICAL SPECIFICATION**

**Status:** ✅ READY FOR IMPLEMENTATION  
**Next Document:** `02-implementation-guide.md`
