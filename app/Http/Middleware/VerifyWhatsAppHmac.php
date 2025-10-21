<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VerifyWhatsAppHmac
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Only validate HMAC for WhatsApp webhook endpoints
        if (!$this->shouldValidateHmac($request)) {
            return $next($request);
        }

        $signature = $request->header('X-HMAC-Signature');
        $timestamp = $request->header('X-Timestamp');
        $payload = $request->getContent();

        // Validate required headers
        if (!$signature || !$timestamp) {
            Log::warning('WhatsApp HMAC validation failed: Missing headers', [
                'path' => $request->path(),
                'has_signature' => !empty($signature),
                'has_timestamp' => !empty($timestamp),
                'ip' => $request->ip()
            ]);

            throw new HttpException(401, 'Missing required security headers');
        }

        // Validate timestamp (prevent replay attacks)
        $this->validateTimestamp($timestamp);

        // Verify HMAC signature
        $this->verifySignature($timestamp, $payload, $signature);

        // Rate limiting check
        $this->checkRateLimit($request);

        return $next($request);
    }

    /**
     * Check if HMAC validation should be applied to this request
     */
    private function shouldValidateHmac(Request $request): bool
    {
        $whatsappRoutes = [
            'api/whatsapp/webhooks/webjs',
            'api/whatsapp/sessions/*/status',
        ];

        foreach ($whatsappRoutes as $route) {
            if ($request->is(str_replace('*', '*', $route))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate timestamp to prevent replay attacks
     */
    private function validateTimestamp(string $timestamp): void
    {
        $requestTime = (int) $timestamp;
        $now = time();
        $maxAge = config('whatsapp.security.signature_ttl', 300); // 5 minutes default

        // Debug: Log ALL timestamp validations
        Log::debug('WhatsApp HMAC timestamp validation', [
            'timestamp_received' => $timestamp,
            'request_time_int' => $requestTime,
            'current_time' => $now,
            'age_seconds' => abs($now - $requestTime),
            'max_age' => $maxAge,
            'is_valid' => abs($now - $requestTime) <= $maxAge
        ]);

        if (abs($now - $requestTime) > $maxAge) {
            Log::warning('WhatsApp HMAC validation failed: Timestamp expired', [
                'request_time' => $requestTime,
                'current_time' => $now,
                'age_seconds' => abs($now - $requestTime),
                'max_age' => $maxAge
            ]);

            throw new HttpException(401, 'Request timestamp expired');
        }
    }

    /**
     * Verify HMAC signature
     */
    private function verifySignature(string $timestamp, string $payload, string $signature): void
    {
        $secret = config('whatsapp.node_api_secret');
        $algorithm = config('whatsapp.security.hmac_algorithm', 'sha256');

        if (!$secret) {
            Log::error('WhatsApp HMAC secret not configured');
            throw new HttpException(500, 'Server configuration error');
        }

        $expectedSignature = hash_hmac($algorithm, $timestamp . $payload, $secret);

        // Use constant-time comparison to prevent timing attacks
        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('WhatsApp HMAC validation failed: Invalid signature', [
                'expected_prefix' => substr($expectedSignature, 0, 10) . '...',
                'received_prefix' => substr($signature, 0, 10) . '...',
                'algorithm' => $algorithm
            ]);

            throw new HttpException(401, 'Invalid signature');
        }
    }

    /**
     * Check rate limiting for WhatsApp requests
     */
    private function checkRateLimit(Request $request): void
    {
        $key = 'whatsapp_hmac_rate_limit:' . $request->ip();
        $maxAttempts = 100; // 100 requests per minute
        $decayMinutes = 1;

        $attempts = cache()->get($key, 0);

        if ($attempts >= $maxAttempts) {
            Log::warning('WhatsApp rate limit exceeded', [
                'ip' => $request->ip(),
                'attempts' => $attempts,
                'limit' => $maxAttempts
            ]);

            throw new HttpException(429, 'Too many requests');
        }

        // Increment attempt count
        cache()->put($key, $attempts + 1, now()->addMinutes($decayMinutes));
    }
}
