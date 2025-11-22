<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RateLimitViolation extends Model
{
    use HasFactory;

    protected $table = 'rate_limit_violations';

    protected $guarded = [];

    protected $casts = [
        'details' => 'json',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
    public function scopeInWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeByIp($query, $ip)
    {
        return $query->where('ip_address', $ip);
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Helper Methods
     */
    public static function logViolation(string $ip, string $route, int $requestCount, ?int $workspaceId = null): self
    {
        return static::create([
            'workspace_id' => $workspaceId,
            'ip_address' => $ip,
            'route' => $route,
            'request_count' => $requestCount,
            'details' => [
                'user_agent' => request()->userAgent(),
                'referer' => request()->header('referer'),
            ],
        ]);
    }

    public static function getViolationCount(string $ip, int $hours = 24): int
    {
        return static::byIp($ip)
            ->recent($hours)
            ->count();
    }
}
