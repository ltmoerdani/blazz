<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * WorkspaceSetting Model
 * 
 * Workspace-specific settings that override global settings.
 * Allows each workspace to customize their configuration.
 * 
 * @property int $id
 * @property int $workspace_id
 * @property string $key
 * @property string $value
 * @property string $type
 * @property string $description
 * @property bool $is_encrypted
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class WorkspaceSetting extends Model
{
    use HasFactory;

    protected $table = 'workspace_settings';

    protected $guarded = [];

    protected $casts = [
        'is_encrypted' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Scopes
     */
    public function scopeInWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeByKey($query, $key)
    {
        return $query->where('key', $key);
    }

    /**
     * Helper Methods
     */

    /**
     * Get setting value by key for workspace
     */
    public static function getValueByKey($workspaceId, $key, $default = null)
    {
        $setting = self::where('workspace_id', $workspaceId)
            ->where('key', $key)
            ->first();

        if (!$setting) {
            // Fallback to global settings
            return Setting::getValueByKey($key, $default);
        }

        return $setting->value ?? $default;
    }

    /**
     * Set or update setting value
     */
    public static function setValue($workspaceId, $key, $value, $type = 'string')
    {
        return self::updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'key' => $key,
            ],
            [
                'value' => $value,
                'type' => $type,
            ]
        );
    }

    /**
     * Get multiple settings at once
     */
    public static function getMultiple($workspaceId, array $keys)
    {
        $settings = self::where('workspace_id', $workspaceId)
            ->whereIn('key', $keys)
            ->pluck('value', 'key');

        // Fill missing with global settings
        foreach ($keys as $key) {
            if (!isset($settings[$key])) {
                $settings[$key] = Setting::getValueByKey($key);
            }
        }

        return $settings;
    }

    /**
     * Delete setting by key
     */
    public static function deleteByKey($workspaceId, $key)
    {
        return self::where('workspace_id', $workspaceId)
            ->where('key', $key)
            ->delete();
    }
}
