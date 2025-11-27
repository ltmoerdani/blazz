<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleWhatsAppSync
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract session ID from request (either from route or payload)
        $sessionId = $this->extractSessionId($request);

        if (!$sessionId) {
            Log::warning('WhatsApp sync rate limit: Missing session ID', [
                'ip' => $request->ip(),
                'path' => $request->path()
            ]);

            return response()->json([
                'error' => 'Session ID is required'
            ], 400);
        }

        // Create rate limit key per session
        $key = 'whatsapp_sync:' . $sessionId;

        // Maximum attempts: 500 requests per minute (increased for initial sync)
        $maxAttempts = config('whatsapp.rate_limiting.sync_max_requests', 500);
        $decayMinutes = config('whatsapp.rate_limiting.sync_decay_minutes', 1);

        // Check if rate limit exceeded
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            Log::warning('WhatsApp sync rate limit exceeded', [
                'session_id' => $sessionId,
                'ip' => $request->ip(),
                'retry_after' => $retryAfter,
                'limit' => $maxAttempts,
                'window' => "{$decayMinutes} minute(s)"
            ]);

            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => 'Too many sync requests. Please try again later.',
                'retry_after' => $retryAfter,
                'limit' => $maxAttempts,
                'window' => "{$decayMinutes} minute(s)"
            ], 429)
            ->header('Retry-After', $retryAfter)
            ->header('X-RateLimit-Limit', $maxAttempts)
            ->header('X-RateLimit-Remaining', 0)
            ->header('X-RateLimit-Reset', now()->addSeconds($retryAfter)->timestamp);
        }

        // Increment the rate limiter
        RateLimiter::hit($key, $decayMinutes * 60);

        // Get remaining attempts
        $remaining = $maxAttempts - RateLimiter::attempts($key);

        // Add rate limit headers to response
        $response = $next($request);

        // Add rate limit headers using headers property (compatible with Symfony Response)
        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', max(0, $remaining));
        $response->headers->set('X-RateLimit-Reset', now()->addMinutes($decayMinutes)->timestamp);

        return $response;
    }

    /**
     * Extract session ID from request
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    private function extractSessionId(Request $request): ?string
    {
        // Try to get from route parameter first
        $sessionId = $request->route('sessionId');

        // If not in route, try to get from request payload (main session_id field)
        if (!$sessionId) {
            $sessionId = $request->input('session_id');
        }

        // For batch sync endpoint, session_id should be in the root payload
        // The WhatsApp service sends: { session_id: 123, workspace_id: 1, chats: [...] }
        if (!$sessionId) {
            Log::warning('WhatsApp sync rate limit: Missing session ID in request payload', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'request_data' => $request->only(['session_id', 'workspace_id']),
                'all_keys' => array_keys($request->all())
            ]);
        }

        return $sessionId;
    }

    /**
     * Get rate limit configuration for a specific session
     * This allows per-workspace or per-plan custom limits in the future
     *
     * @param  string  $sessionId
     * @return array
     */
    private function getRateLimitConfig(string $sessionId): array
    {
        // Default configuration
        $defaultConfig = [
            'max_requests' => config('whatsapp.rate_limiting.sync_max_requests', 60),
            'decay_minutes' => config('whatsapp.rate_limiting.sync_decay_minutes', 1),
        ];

        // Future enhancement: Load workspace-specific or plan-specific limits
        // $session = WhatsAppAccount::find($sessionId);
        // if ($session && $session->workspace) {
        //     return $session->workspace->whatsapp_rate_limits ?? $defaultConfig;
        // }

        return $defaultConfig;
    }
}
