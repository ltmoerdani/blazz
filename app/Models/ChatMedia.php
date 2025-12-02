<?php

namespace App\Models;

use App\Helpers\DateTimeHelper;
use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * ChatMedia Model - Enhanced for Campaign Media Storage
 * 
 * Handles media files for campaigns and chats with:
 * - Multiple file versions (original, compressed, thumbnail, webp)
 * - Processing status tracking for async operations
 * - Workspace isolation (multi-tenancy)
 * 
 * @see docs/campaign/media/01-technical-specification.md
 * @property int $id
 * @property string $uuid
 * @property string $name Original filename
 * @property string $type MIME type
 * @property int $size File size in bytes
 * @property string|null $original_path Full-size original
 * @property string|null $compressed_path Optimized version
 * @property string|null $thumbnail_path Thumbnail preview
 * @property string|null $webp_path WebP version
 * @property string $location Storage location (local, amazon, s3, s3_cdn)
 * @property string|null $cdn_url CloudFront CDN URL
 * @property string $processing_status pending|processing|completed|failed
 * @property \Carbon\Carbon|null $processed_at
 * @property string|null $processing_error
 * @property array|null $metadata JSON metadata
 * @property int|null $workspace_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
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

    // ==========================================
    // CONSTANTS
    // ==========================================

    // Processing Status
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

    /**
     * Get the workspace that owns the media
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get campaigns using this media
     */
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
    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class, 'media_id', 'id');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Scope to filter by workspace (REQUIRED for multi-tenancy)
     */
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

    public function scopeAudio($query)
    {
        return $query->where('type', 'like', 'audio/%');
    }

    public function scopeDocuments($query)
    {
        return $query->where('type', 'not like', 'image/%')
                     ->where('type', 'not like', 'video/%')
                     ->where('type', 'not like', 'audio/%');
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
        if (str_starts_with($this->type ?? '', 'image/')) return self::TYPE_IMAGE;
        if (str_starts_with($this->type ?? '', 'video/')) return self::TYPE_VIDEO;
        if (str_starts_with($this->type ?? '', 'audio/')) return self::TYPE_AUDIO;
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

        // Priority based on preferred variant
        $priorities = match($preferredVariant) {
            'thumbnail' => ['thumbnail_path', 'compressed_path', 'original_path'],
            'webp' => ['webp_path', 'compressed_path', 'original_path'],
            'original' => ['original_path', 'compressed_path'],
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
            's3', 'amazon', 's3_cdn' => $this->getS3Url($path),
            default => $path,
        };
    }

    /**
     * Get S3 URL for a path
     */
    protected function getS3Url(string $path): string
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('s3');
        return $disk->url($path);
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
            ->whereJsonContains('metadata->hash', $hash)
            ->completed()
            ->first();
    }
}
