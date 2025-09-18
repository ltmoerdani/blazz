<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * PHASE-3 Advanced Rate Limiting Middleware
 * Multi-layer rate limiting dengan Redis integration dan intelligent throttling
 */
class AdvancedRateLimitMiddleware
{
    // Rate limiting configurations per type
    const RATE_LIMITS = [
        'auth' => ['attempts' => 5, 'window' => 300],        // 5 attempts per 5 minutes
        'api' => ['attempts' => 100, 'window' => 3600],      // 100 requests per hour
        'webhook' => ['attempts' => 500, 'window' => 3600],  // 500 requests per hour
        'admin' => ['attempts' => 200, 'window' => 3600],    // 200 requests per hour
        'user' => ['attempts' => 150, 'window' => 3600],     // 150 requests per hour
        'guest' => ['attempts' => 50, 'window' => 3600],     // 50 requests per hour
    ];

    // Suspicious activity thresholds
    const SECURITY_THRESHOLDS = [
        'burst_threshold' => 20,    // Requests dalam 60 detik
        'ip_daily_limit' => 5000,   // Daily requests per IP
        'user_daily_limit' => 10000, // Daily requests per user
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $type = 'api'): Response
    {
        // Skip rate limiting untuk internal requests
        if ($this->shouldSkipRateLimit($request)) {
            return $next($request);
        }

        // Generate unique keys untuk different rate limiting layers
        $keys = $this->generateRateLimitKeys($request, $type);
        
        // Check each rate limiting layer
        foreach ($keys as $keyData) {
            $result = $this->checkRateLimit($keyData['key'], $keyData['limit'], $keyData['window']);
            
            if (!$result['allowed']) {
                return $this->buildRateLimitResponse($result, $keyData['type']);
            }
        }
        
        // Check untuk suspicious activity patterns
        $this->detectSuspiciousActivity($request);
        
        // Log legitimate request
        $this->logRequest($type);
        
        $response = $next($request);
        
        // Add rate limit headers
        return $this->addRateLimitHeaders($response, $keys);
    }

    /**
     * Generate multiple rate limiting keys untuk layered protection
     */
    private function generateRateLimitKeys(Request $request, string $type): array
    {
        $config = self::RATE_LIMITS[$type] ?? self::RATE_LIMITS['api'];
        $ip = $request->ip();
        $user = $request->user();
        
        $keys = [];
        
        // Layer 1: IP-based rate limiting
        $keys[] = [
            'key' => "rate_limit:ip:{$ip}:{$type}",
            'limit' => $config['attempts'],
            'window' => $config['window'],
            'type' => 'ip'
        ];
        
        // Layer 2: User-based rate limiting (jika authenticated)
        if ($user) {
            $keys[] = [
                'key' => "rate_limit:user:{$user->id}:{$type}",
                'limit' => $config['attempts'] * 2, // Users get higher limits
                'window' => $config['window'],
                'type' => 'user'
            ];
        }
        
        // Layer 3: Endpoint-specific rate limiting
        $endpoint = $request->route() ? $request->route()->getName() : 'unknown';
        if ($endpoint && in_array($type, ['api', 'webhook'])) {
            $keys[] = [
                'key' => "rate_limit:endpoint:{$endpoint}:{$ip}",
                'limit' => $this->getEndpointSpecificLimit($endpoint, $config['attempts']),
                'window' => $config['window'],
                'type' => 'endpoint'
            ];
        }
        
        // Layer 4: Organization-based rate limiting (untuk multi-tenant)
        $orgId = session('current_organization') ?? $request->get('organization_id');
        if ($orgId && $user) {
            $keys[] = [
                'key' => "rate_limit:org:{$orgId}:{$type}",
                'limit' => $config['attempts'] * 5, // Organizations get much higher limits
                'window' => $config['window'],
                'type' => 'organization'
            ];
        }
        
        return $keys;
    }

    /**
     * Check rate limit menggunakan Redis sliding window
     */
    private function checkRateLimit(string $key, int $limit, int $window): array
    {
        $now = time();
        
        // Get current request count dalam window
        $current = Cache::get($key, 0);
        
        // Check if limit exceeded
        if ($current >= $limit) {
            $ttl = max($window, 60); // Fallback TTL
            $resetTime = $now + $ttl;
            
            return [
                'allowed' => false,
                'current' => $current,
                'limit' => $limit,
                'remaining' => 0,
                'reset_time' => $resetTime,
                'retry_after' => $ttl
            ];
        }
        
        // Increment counter dengan expiration
        Cache::put($key, $current + 1, $window);
        
        return [
            'allowed' => true,
            'current' => $current + 1,
            'limit' => $limit,
            'remaining' => $limit - ($current + 1),
            'reset_time' => $now + $window,
            'retry_after' => 0
        ];
    }

    /**
     * Build rate limit exceeded response
     */
    private function buildRateLimitResponse(array $result, string $type): Response
    {
        $message = match($type) {
            'ip' => 'Too many requests from your IP address. Please try again later.',
            'user' => 'You have exceeded your request quota. Please try again later.',
            'endpoint' => 'This endpoint is temporarily rate limited. Please try again later.',
            'organization' => 'Your organization has exceeded its request quota.',
            default => 'Rate limit exceeded. Please try again later.'
        };
        
        $headers = [
            'X-RateLimit-Limit' => $result['limit'],
            'X-RateLimit-Remaining' => $result['remaining'],
            'X-RateLimit-Reset' => $result['reset_time'],
            'Retry-After' => $result['retry_after'],
            'X-RateLimit-Type' => $type,
        ];
        
        // Log rate limit violation
        Log::warning('Rate limit exceeded', [
            'type' => $type,
            'current' => $result['current'],
            'limit' => $result['limit'],
            'ip' => request()->ip(),
            'user_id' => request()->user()?->id,
            'endpoint' => request()->route()?->getName(),
            'user_agent' => request()->userAgent(),
        ]);
        
        // Return JSON response untuk API, HTML untuk web
        if (request()->expectsJson()) {
            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => $message,
                'retry_after' => $result['retry_after'],
                'limit' => $result['limit'],
                'remaining' => $result['remaining'],
                'reset_time' => $result['reset_time'],
            ], 429, $headers);
        }
        
        return response()->view('errors.429', [
            'message' => $message,
            'retry_after' => $result['retry_after']
        ], 429, $headers);
    }

    /**
     * Add rate limit headers ke response
     */
    private function addRateLimitHeaders(Response $response, array $keys): Response
    {
        // Use primary rate limit untuk headers (IP-based)
        if (!empty($keys)) {
            $primaryKey = $keys[0]; // Use IP-based as primary
            $current = Cache::get($primaryKey['key'], 0);
            
            $response->headers->set('X-RateLimit-Limit', $primaryKey['limit']);
            $response->headers->set('X-RateLimit-Remaining', max(0, $primaryKey['limit'] - $current));
            $response->headers->set('X-RateLimit-Reset', time() + $primaryKey['window']);
        }
        
        return $response;
    }

    /**
     * Detect suspicious activity patterns
     */
    private function detectSuspiciousActivity(Request $request): void
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        
        // Check untuk burst requests (banyak requests dalam waktu singkat)
        $burstKey = "burst_check:{$ip}";
        $burstCount = Cache::get($burstKey, 0);
        
        if ($burstCount > self::SECURITY_THRESHOLDS['burst_threshold']) {
            Log::warning('Suspicious burst activity detected', [
                'ip' => $ip,
                'burst_count' => $burstCount,
                'user_agent' => $userAgent,
                'endpoint' => $request->route()?->getName(),
            ]);
            
            // Optionally block atau additional monitoring
            Cache::put("suspicious_ip:{$ip}", true, 3600); // Flag for 1 hour
        }
        
        Cache::put($burstKey, $burstCount + 1, 60); // Track dalam 60 detik window
        
        // Check untuk unusual patterns
        $this->checkUnusualPatterns($request);
    }

    /**
     * Check unusual request patterns
     */
    private function checkUnusualPatterns(Request $request): void
    {
        $ip = $request->ip();
        $patterns = [];
        
        // Pattern 1: Empty atau suspicious user agents
        $userAgent = $request->userAgent();
        if (empty($userAgent) || $this->isSuspiciousUserAgent($userAgent)) {
            $patterns[] = 'suspicious_user_agent';
        }
        
        // Pattern 2: Rapid endpoint switching
        $endpointKey = "endpoint_pattern:{$ip}";
        $recentEndpoints = Cache::get($endpointKey, []);
        $currentEndpoint = $request->route()?->getName() ?? 'unknown';
        $recentEndpoints[] = $currentEndpoint;
        
        // Keep only last 10 endpoints
        $recentEndpoints = array_slice($recentEndpoints, -10);
        Cache::put($endpointKey, $recentEndpoints, 300); // 5 minutes
        
        // Check untuk rapid switching (different endpoint setiap request)
        if (count(array_unique($recentEndpoints)) >= 8 && count($recentEndpoints) >= 10) {
            $patterns[] = 'rapid_endpoint_switching';
        }
        
        // Log patterns if found
        if (!empty($patterns)) {
            Log::info('Unusual request patterns detected', [
                'ip' => $ip,
                'patterns' => $patterns,
                'user_agent' => $userAgent,
                'recent_endpoints' => $recentEndpoints,
            ]);
        }
    }

    /**
     * Check if user agent is suspicious
     */
    private function isSuspiciousUserAgent(string $userAgent): bool
    {
        $suspiciousPatterns = [
            'bot', 'crawl', 'spider', 'scrape', 'curl', 'wget', 'python', 'java',
            'automated', 'script', 'tool', 'scanner', 'monitor'
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get endpoint-specific limits
     */
    private function getEndpointSpecificLimit(string $endpoint, int $defaultLimit): int
    {
        // Special limits untuk specific endpoints
        $endpointLimits = [
            'auth.login' => 10,           // Login attempts
            'auth.register' => 5,         // Registration attempts
            'webhook.whatsapp' => 1000,   // WhatsApp webhooks
            'api.send-message' => 300,    // Send message API
            'api.contacts.import' => 10,  // Contact import
        ];
        
        return $endpointLimits[$endpoint] ?? $defaultLimit;
    }

    /**
     * Log legitimate request untuk monitoring
     */
    private function logRequest(string $type): void
    {
        // Only log untuk monitoring purposes - not for every request dalam production
        if (config('app.debug') || config('performance.monitoring.request_logging', false)) {
            Cache::increment("stats:requests:{$type}:" . date('Y-m-d-H'), 1, 3600);
            Cache::increment("stats:requests:total:" . date('Y-m-d'), 1, 86400);
        }
    }

    /**
     * Determine if rate limiting should be skipped
     */
    private function shouldSkipRateLimit(Request $request): bool
    {
        // Skip untuk internal health checks
        if ($request->is('health', 'status', 'ping')) {
            return true;
        }
        
        // Skip untuk trusted IPs dalam development
        if (config('app.debug')) {
            $trustedIPs = ['127.0.0.1', '::1', 'localhost'];
            if (in_array($request->ip(), $trustedIPs)) {
                return true;
            }
        }
        
        // Skip if rate limiting is disabled
        return !config('performance.rate_limiting.enabled', true);
    }
}
