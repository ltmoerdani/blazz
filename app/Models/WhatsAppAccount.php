<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WhatsAppAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'whatsapp_accounts';

    protected $fillable = [
        'uuid',
        'workspace_id',
        'session_id',
        'phone_number',
        'provider_type',
        'status',
        'qr_code',
        'session_data',
        'is_primary',
        'is_active',
        'last_activity_at',
        'last_connected_at',
        'metadata',
        'created_by',
        // Multi-instance tracking
        'assigned_instance_index',
        'assigned_instance_url',
        'previous_instance_index',
        'instance_migration_count',
        'last_instance_migration_at',
        // Disconnect tracking
        'disconnected_at',
        'disconnect_reason',
        'disconnect_details',
        // Storage metadata
        'session_storage_path',
        'session_file_size_bytes',
        'session_storage_verified_at',
    ];

    protected $casts = [
        'session_data' => 'encrypted:array',
        'metadata' => 'array',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'last_activity_at' => 'datetime',
        'last_connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
        'session_storage_verified_at' => 'datetime',
        'last_instance_migration_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid()->toString();
            }
        });
        
        // ✅ PHASE 1: Auto-invalidate cache when instance URL changes
        static::updated(function ($model) {
            if ($model->isDirty('assigned_instance_url')) {
                // Invalidate cache for this account
                \Illuminate\Support\Facades\Cache::forget("whatsapp_instance:{$model->uuid}");
                \Illuminate\Support\Facades\Cache::forget("whatsapp_instance:{$model->session_id}");
                
                \Illuminate\Support\Facades\Log::info('Cache invalidated due to instance URL change', [
                    'account_id' => $model->id,
                    'phone_number' => $model->phone_number,
                    'session_id' => $model->session_id,
                    'old_url' => $model->getOriginal('assigned_instance_url'),
                    'new_url' => $model->assigned_instance_url,
                ]);
            }
        });
    }

    /**
     * Get the workspace that owns the session
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the user who created the session
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all chats for this session
     */
    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class, 'whatsapp_account_id');
    }

    /**
     * Get all campaign logs for this session
     */
    public function campaignLogs(): HasMany
    {
        return $this->hasMany(CampaignLog::class, 'whatsapp_account_id');
    }

    /**
     * Get all campaigns for this session
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'whatsapp_account_id');
    }

    /**
     * Get all contact sessions for this session
     */
    public function contactSessions(): HasMany
    {
        return $this->hasMany(ContactAccount::class, 'whatsapp_account_id');
    }

    /**
     * Get all contacts associated with this session
     */
    public function contacts()
    {
        return $this->belongsToMany(Contact::class, 'contact_accounts')
            ->withPivot(['first_interaction_at', 'last_interaction_at', 'total_messages'])
            ->withTimestamps();
    }

    /**
     * Scope to get only active sessions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only connected sessions
     */
    public function scopeConnected($query)
    {
        return $query->where('status', 'connected');
    }

    /**
     * Scope to get only primary sessions
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope to get sessions by provider type
     */
    public function scopeByProvider($query, $provider)
    {
        return $query->where('provider_type', $provider);
    }

    /**
     * Scope to get sessions for a specific workspace
     */
    public function scopeForWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * Scope: Sessions on specific instance
     */
    public function scopeOnInstance($query, int $instanceIndex)
    {
        return $query->where('assigned_instance_index', $instanceIndex);
    }

    /**
     * Scope: Recently disconnected
     */
    public function scopeRecentlyDisconnected($query, int $hours = 24)
    {
        return $query->where('status', 'disconnected')
            ->where('disconnected_at', '>=', now()->subHours($hours));
    }

    /**
     * Get the session health score (0-100)
     */
    public function getHealthScoreAttribute(): int
    {
        $score = 100;

        // Connection status
        if ($this->status !== 'connected') {
            $score -= 50;
        }

        // Last activity (inactive > 1 hour)
        if ($this->last_activity_at && $this->last_activity_at->lt(now()->subHour())) {
            $score -= 20;
        }

        // Metadata health metrics
        if ($this->metadata) {
            $metadata = $this->metadata;

            // Check for recent failures
            if (isset($metadata['recent_failures']) && $metadata['recent_failures'] > 0) {
                $score -= min($metadata['recent_failures'] * 10, 30);
            }

            // Check for ban risk
            if (isset($metadata['ban_risk_score']) && $metadata['ban_risk_score'] > 50) {
                $score -= min($metadata['ban_risk_score'], 50);
            }
        }

        return max(0, $score);
    }

    /**
     * Check if session is healthy
     */
    public function isHealthy(): bool
    {
        return $this->health_score >= 70 && $this->status === 'connected';
    }

    /**
     * Get formatted phone number
     */
    public function getFormattedPhoneNumberAttribute(): ?string
    {
        if (!$this->phone_number) {
            return null;
        }

        // Remove any non-digit characters except +
        $phone = preg_replace('/[^\d+]/', '', $this->phone_number);

        // Format as international number
        if (strlen($phone) >= 10) {
            return '+' . ltrim($phone, '+');
        }

        return $phone;
    }

    /**
     * Update session statistics
     */
    public function updateStatistics(): void
    {
        $stats = [
            'messages_sent' => $this->chats()->where('type', 'outbound')->count(),
            'messages_received' => $this->chats()->where('type', 'inbound')->count(),
            'chats_count' => $this->chats()->distinct('contact_id')->count(),
            'campaigns_sent' => $this->campaignLogs()->count(),
            'last_health_check' => now()->toISOString(),
        ];

        $this->update([
            'metadata' => array_merge($this->metadata ?? [], $stats)
        ]);
    }

    /**
     * Assign session to a specific instance
     */
    public function assignToInstance(int $index, string $url): void
    {
        $this->update([
            'previous_instance_index' => $this->assigned_instance_index,
            'assigned_instance_index' => $index,
            'assigned_instance_url' => $url,
            'instance_migration_count' => ($this->instance_migration_count ?? 0) + 1,
            'last_instance_migration_at' => now(),
        ]);
    }

    /**
     * Mark session as disconnected with reason
     */
    public function markDisconnected(string $reason, ?string $details = null): void
    {
        $this->update([
            'status' => 'disconnected',
            'disconnected_at' => now(),
            'disconnect_reason' => $reason,
            'disconnect_details' => $details,
        ]);
    }
}
