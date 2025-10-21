<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WhatsAppSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'whatsapp_sessions';

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
    ];

    protected $casts = [
        'session_data' => 'encrypted:array',
        'metadata' => 'array',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'last_activity_at' => 'datetime',
        'last_connected_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid()->toString();
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
        return $this->hasMany(Chat::class);
    }

    /**
     * Get all campaign logs for this session
     */
    public function campaignLogs(): HasMany
    {
        return $this->hasMany(CampaignLog::class);
    }

    /**
     * Get all contact sessions for this session
     */
    public function contactSessions(): HasMany
    {
        return $this->hasMany(ContactSession::class);
    }

    /**
     * Get all contacts associated with this session
     */
    public function contacts()
    {
        return $this->belongsToMany(Contact::class, 'contact_sessions')
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
}
