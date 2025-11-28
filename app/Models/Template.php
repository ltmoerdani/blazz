<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Template Model
 * 
 * Represents WhatsApp message templates that can be used with Meta API or WebJS.
 * Supports draft-first workflow where templates can be created without WhatsApp connection.
 * 
 * @see docs/templates/template-system-architecture.md
 */
class Template extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $guarded = [];

    // Status constants - align with Meta API statuses + DRAFT
    const STATUS_DRAFT = 'DRAFT';           // Local only, not submitted to Meta
    const STATUS_PENDING = 'PENDING';       // Submitted to Meta, awaiting review
    const STATUS_APPROVED = 'APPROVED';     // Approved by Meta
    const STATUS_REJECTED = 'REJECTED';     // Rejected by Meta

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Workspace relationship - REQUIRED for multi-tenancy
     */
    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Creator relationship
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope: Query by workspace (existing pattern)
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $workspaceId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * Scope: Only draft templates
     */
    public function scopeDrafts($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope: Only published templates (non-drafts)
     */
    public function scopePublished($query)
    {
        return $query->where('status', '!=', self::STATUS_DRAFT);
    }

    /**
     * Scope: Templates approved by Meta
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope: Templates pending review
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope: Templates rejected by Meta
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope: Usable templates for specific provider
     * 
     * @param string $provider 'webjs' or 'meta_api'
     */
    public function scopeUsableFor($query, string $provider)
    {
        return match($provider) {
            'webjs' => $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_PENDING, self::STATUS_APPROVED]),
            'meta_api' => $query->where('status', self::STATUS_APPROVED)->whereNotNull('meta_id'),
            default => $query,
        };
    }

    /**
     * Check if template can be used with specific provider
     * 
     * @param string $provider 'webjs' or 'meta_api'
     * @return bool
     */
    public function canUseWithProvider(string $provider): bool
    {
        return match($provider) {
            'meta_api' => $this->status === self::STATUS_APPROVED && !empty($this->meta_id),
            'webjs' => in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING, self::STATUS_APPROVED]),
            default => false,
        };
    }

    /**
     * Check if template is a draft
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if template is published to Meta
     */
    public function isPublishedToMeta(): bool
    {
        return !empty($this->meta_id) && $this->status !== self::STATUS_DRAFT;
    }

    /**
     * Check if template is pending review
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if template is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if template is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Get status badge color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_PENDING => 'yellow',
            self::STATUS_APPROVED => 'green',
            self::STATUS_REJECTED => 'red',
            default => 'gray',
        };
    }

    /**
     * Get human-readable status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => __('Draft'),
            self::STATUS_PENDING => __('Pending Review'),
            self::STATUS_APPROVED => __('Approved'),
            self::STATUS_REJECTED => __('Rejected'),
            default => __('Unknown'),
        };
    }

    /**
     * Get parsed components from metadata
     */
    public function getComponentsAttribute(): ?array
    {
        if (empty($this->metadata)) {
            return null;
        }

        $decoded = json_decode($this->metadata, true);
        return is_array($decoded) ? $decoded : null;
    }
}
