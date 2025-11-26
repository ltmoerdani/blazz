<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Integration Model
 * 
 * Stores third-party integrations (payment gateways, storage, APIs, etc.)
 * Each workspace can have their own integration configurations.
 * 
 * @property int $id
 * @property string $uuid
 * @property int $workspace_id
 * @property string $name
 * @property string $provider_type
 * @property string $status
 * @property array $credentials
 * @property array $settings
 * @property array $metadata
 * @property bool $is_active
 * @property bool $is_test_mode
 * @property \Carbon\Carbon $last_used_at
 * @property int $usage_count
 * @property int $created_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 */
class Integration extends Model
{
    use HasFactory;

    protected $table = 'integrations';

    protected $guarded = [];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'settings' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'is_test_mode' => 'boolean',
        'last_used_at' => 'datetime',
        'usage_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($integration) {
            if (empty($integration->uuid)) {
                $integration->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Relationships
     */
    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scopes
     */
    public function scopeInWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeByName($query, $name)
    {
        return $query->where('name', $name);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('status', 'active');
    }

    public function scopeByProvider($query, $providerType)
    {
        return $query->where('provider_type', $providerType);
    }

    public function scopePaymentGateways($query)
    {
        return $query->where('provider_type', 'payment');
    }

    /**
     * Helper Methods
     */

    /**
     * Get integration by name for workspace
     */
    public static function getByName($workspaceId, $name)
    {
        return self::where('workspace_id', $workspaceId)
            ->where('name', $name)
            ->first();
    }

    /**
     * Get active integration by name
     */
    public static function getActive($workspaceId, $name)
    {
        return self::where('workspace_id', $workspaceId)
            ->where('name', $name)
            ->where('is_active', true)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Record usage
     */
    public function recordUsage()
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Activate integration
     */
    public function activate()
    {
        $this->update([
            'is_active' => true,
            'status' => 'active',
        ]);
    }

    /**
     * Deactivate integration
     */
    public function deactivate()
    {
        $this->update([
            'is_active' => false,
            'status' => 'inactive',
        ]);
    }

    /**
     * Check if integration is ready to use
     */
    public function isReady()
    {
        return $this->is_active && 
               $this->status === 'active' && 
               !empty($this->credentials);
    }

    /**
     * Get credential safely
     */
    public function getCredential($key, $default = null)
    {
        return $this->credentials[$key] ?? $default;
    }

    /**
     * Get setting safely
     */
    public function getSetting($key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }
}
