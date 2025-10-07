<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use App\Models\Chat;
use App\Models\workspace;
use App\Models\User;

/**
 * PHASE-3 Performance Cache Service
 * Advanced caching strategy untuk Laravel 12 enhancement
 */
class PerformanceCacheService
{
    // Cache TTL configurations
    const CACHE_SHORT = 300;      // 5 minutes
    const CACHE_MEDIUM = 1800;    // 30 minutes
    const CACHE_LONG = 3600;      // 1 hour
    const CACHE_DAILY = 86400;    // 24 hours
    
    // Cache statistics prefixes
    const CACHE_STATS_GLOBAL = 'cache_stats:global';

    /**
     * Advanced cache tagging untuk intelligent invalidation
     */
    public function getChatTimeline($workspaceId, $contactId = null, $limit = 50)
    {
        $cacheKey = "chat_timeline:{$workspaceId}:{$contactId}:{$limit}";
        $tags = ['chats', "org:{$workspaceId}"];
        
        if ($contactId) {
            $tags[] = "contact:{$contactId}";
        }

        return Cache::tags($tags)->remember($cacheKey, self::CACHE_MEDIUM, function() use ($workspaceId, $contactId, $limit) {
            $query = Chat::with(['contact:id,name,phone', 'media:id,file_name,file_url'])
                ->where('workspace_id', $workspaceId)
                ->orderBy('created_at', 'desc')
                ->limit($limit);

            if ($contactId) {
                $query->where('contact_id', $contactId);
            }

            return $query->get();
        });
    }

    /**
     * workspace dashboard metrics dengan aggressive caching
     */
    public function getWorkspaceMetrics($workspaceId)
    {
        $cacheKey = "org_metrics:{$workspaceId}";
        $tags = ['org_metrics', "org:{$workspaceId}"];

        return Cache::tags($tags)->remember($cacheKey, self::CACHE_LONG, function() use ($workspaceId) {
            return [
                'total_chats' => Chat::where('workspace_id', $workspaceId)->count(),
                'today_chats' => Chat::where('workspace_id', $workspaceId)
                    ->whereDate('created_at', today())->count(),
                'active_contacts' => Chat::where('workspace_id', $workspaceId)
                    ->distinct('contact_id')
                    ->whereDate('created_at', '>=', now()->subDays(7))
                    ->count('contact_id'),
                'team_members' => DB::table('teams')
                    ->where('workspace_id', $workspaceId)
                    ->count(),
                'response_time_avg' => $this->calculateAverageResponseTime($workspaceId),
            ];
        });
    }

    /**
     * User-specific data dengan personal caching
     */
    public function getUserDashboard($userId, $workspaceId)
    {
        $cacheKey = "user_dashboard:{$userId}:{$workspaceId}";
        $tags = ['user_data', "user:{$userId}", "org:{$workspaceId}"];

        return Cache::tags($tags)->remember($cacheKey, self::CACHE_MEDIUM, function() use ($userId, $workspaceId) {
            return [
                'my_chats_count' => Chat::where('workspace_id', $workspaceId)
                    ->where(function($q) use ($userId) {
                        $q->whereJsonContains('metadata->assigned_to', $userId)
                          ->orWhereJsonContains('metadata->handled_by', $userId);
                    })->count(),
                'pending_chats' => Chat::where('workspace_id', $workspaceId)
                    ->where('status', 'pending')
                    ->count(),
                'recent_activity' => $this->getUserRecentActivity($userId, $workspaceId),
            ];
        });
    }

    /**
     * High-frequency contact search dengan Redis caching
     */
    public function searchContacts($workspaceId, $searchTerm, $limit = 20)
    {
        // Use Redis untuk high-frequency searches
        $cacheKey = "contact_search:{$workspaceId}:" . md5(strtolower($searchTerm));
        
        $cached = Redis::get($cacheKey);
        if ($cached) {
            return json_decode($cached, true);
        }

        $results = DB::table('contacts')
            ->where('workspace_id', $workspaceId)
            ->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('phone', 'like', "%{$searchTerm}%");
            })
            ->limit($limit)
            ->get();

        // Cache for 5 minutes
        Redis::setex($cacheKey, self::CACHE_SHORT, json_encode($results));

        return $results;
    }

    /**
     * workspace list dengan pagination caching
     */
    public function getWorkspaceList($page = 1, $perPage = 10, $searchTerm = null)
    {
        $cacheKey = "org_list:p{$page}:pp{$perPage}:" . md5($searchTerm ?? '');
        $tags = ['workspaces', 'org_list'];

        return Cache::tags($tags)->remember($cacheKey, self::CACHE_MEDIUM, function() use ($page, $perPage, $searchTerm) {
            $query = workspace::with(['teams:Workspace_id,user_id,role', 'teams.user:id,name,avatar'])
                ->withCount('teams');

            if ($searchTerm) {
                $query->where('name', 'like', "%{$searchTerm}%");
            }

            return $query->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
        });
    }

    /**
     * Smart cache invalidation methods
     */
    public function invalidateChatCache($workspaceId, $contactId = null)
    {
        $tags = ['chats', "org:{$workspaceId}"];
        
        if ($contactId) {
            $tags[] = "contact:{$contactId}";
        }

        Cache::tags($tags)->flush();
    }

    public function invalidateWorkspaceCache($workspaceId)
    {
        Cache::tags(["org:{$workspaceId}", 'org_metrics'])->flush();
    }

    public function invalidateUserCache($userId, $workspaceId = null)
    {
        $tags = ["user:{$userId}", 'user_data'];
        
        if ($workspaceId) {
            $tags[] = "org:{$workspaceId}";
        }

        Cache::tags($tags)->flush();
    }

    /**
     * Performance monitoring helpers
     */
    public function getCacheHitRate($tag = null)
    {
        $prefix = $tag ? "cache_stats:{$tag}" : self::CACHE_STATS_GLOBAL;
        
        $hits = Redis::get("{$prefix}:hits") ?? 0;
        $misses = Redis::get("{$prefix}:misses") ?? 0;
        $total = $hits + $misses;

        return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
    }

    public function recordCacheHit($tag = null)
    {
        $prefix = $tag ? "cache_stats:{$tag}" : self::CACHE_STATS_GLOBAL;
        Redis::incr("{$prefix}:hits");
    }

    public function recordCacheMiss($tag = null)
    {
        $prefix = $tag ? "cache_stats:{$tag}" : self::CACHE_STATS_GLOBAL;
        Redis::incr("{$prefix}:misses");
    }

    /**
     * Helper methods
     */
    private function calculateAverageResponseTime($workspaceId)
    {
        // Simplified calculation - in production, this would use more sophisticated metrics
        return Cache::remember("response_time:{$workspaceId}", self::CACHE_LONG, function() use ($workspaceId) {
            return DB::table('chats')
                ->where('workspace_id', $workspaceId)
                ->whereNotNull('metadata->response_time')
                ->avg(DB::raw('JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.response_time"))')) ?? 0;
        });
    }

    private function getUserRecentActivity($userId, $workspaceId)
    {
        return Cache::remember("user_activity:{$userId}:{$workspaceId}", self::CACHE_SHORT, function() use ($userId, $workspaceId) {
            return Chat::where('workspace_id', $workspaceId)
                ->whereJsonContains('metadata->handled_by', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'contact_id', 'status', 'created_at']);
        });
    }
}
