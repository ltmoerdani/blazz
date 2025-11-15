<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PHASE-3 Security Headers Middleware
 * Enhanced HTTP security headers untuk comprehensive protection
 */
class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Add comprehensive security headers
        $this->addSecurityHeaders($response, $request);
        
        return $response;
    }

    /**
     * Add comprehensive security headers
     */
    private function addSecurityHeaders(Response $response, Request $request): void
    {
        // X-Frame-Options: Prevent clickjacking attacks
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // X-Content-Type-Options: Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // X-XSS-Protection: Enable XSS filtering
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        // Referrer-Policy: Control referrer information
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Permissions-Policy: Control browser features
        $response->headers->set('Permissions-Policy', $this->getPermissionsPolicy());
        
        // Content Security Policy: Comprehensive XSS protection
        $response->headers->set('Content-Security-Policy', $this->getContentSecurityPolicy($request));
        
        // Strict-Transport-Security: Force HTTPS (only if HTTPS)
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
        
        // Remove server information disclosure
        $response->headers->remove('Server');
        $response->headers->remove('X-Powered-By');
        
        // Custom security headers
        $response->headers->set('X-Security-Enhanced', 'Blazz-PHASE3');
        $response->headers->set('X-Request-ID', $this->getRequestId());
        
        // Cache control untuk sensitive pages
        if ($this->isSensitivePage($request)) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }
    }

    /**
     * Get Content Security Policy
     */
    private function getContentSecurityPolicy(Request $request): string
    {
        $baseUrl = $request->getSchemeAndHttpHost();
        $isProduction = app()->environment('production');
        
        // Vite dev server URLs (removed IPv6 format yang tidak valid di CSP)
        $viteUrls = '';
        if (!$isProduction) {
            $viteUrls = ' http://localhost:5173 http://127.0.0.1:5173';
        }
        
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' cdn.jsdelivr.net unpkg.com{$viteUrls}",
            "style-src 'self' 'unsafe-inline' fonts.googleapis.com fonts.bunny.net cdn.jsdelivr.net{$viteUrls}",
            "font-src 'self' fonts.gstatic.com fonts.bunny.net data:",
            "img-src 'self' data: blob: *", // Allow images from any source untuk user uploads
            "media-src 'self' blob:",
            "object-src 'none'",
            "frame-src 'none'",
            "worker-src 'self' blob:",
            "child-src 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
        ];
        
        // Add connect-src untuk API endpoints dan Vite HMR (removed IPv6)
        $connectSrc = "'self' " . $baseUrl;
        
        // Allow external APIs dan Vite HMR dalam development
        if (!$isProduction) {
            $connectSrc .= " ws://localhost:5173 ws://127.0.0.1:5173 http://localhost:5173 http://127.0.0.1:5173 ws: wss: http: https:";
        }
        
        $csp[] = "connect-src {$connectSrc}";
        
        // Add upgrade-insecure-requests dalam production
        if ($isProduction && $request->isSecure()) {
            $csp[] = "upgrade-insecure-requests";
        }
        
        return implode('; ', $csp);
    }

    /**
     * Get Permissions Policy
     */
    private function getPermissionsPolicy(): string
    {
        $policies = [
            'accelerometer=()',
            'autoplay=()',
            'camera=(self)', // Allow camera untuk WhatsApp media
            'display-capture=()',
            'encrypted-media=()',
            'fullscreen=(self)',
            'geolocation=()',
            'gyroscope=()',
            'magnetometer=()',
            'microphone=(self)', // Allow microphone untuk voice messages
            'midi=()',
            'payment=()',
            'picture-in-picture=()',
            'publickey-credentials-get=()',
            'sync-xhr=()',
            'usb=()',
            'web-share=()',
            'xr-spatial-tracking=()',
        ];
        
        return implode(', ', $policies);
    }

    /**
     * Get atau generate request ID
     */
    private function getRequestId(): string
    {
        // Try to get existing request ID dari audit logging
        if (session()->has('current_request_id')) {
            return session('current_request_id');
        }
        
        // Generate new request ID
        $requestId = 'req_' . uniqid() . '_' . mt_rand(1000, 9999);
        session(['current_request_id' => $requestId]);
        
        return $requestId;
    }

    /**
     * Check if current page is sensitive (requires no-cache)
     */
    private function isSensitivePage(Request $request): bool
    {
        $sensitivePatterns = [
            'admin/*',
            'user/profile/*',
            'workspace/settings/*',
            'billing/*',
            'api/admin/*',
            'developer/*',
        ];
        
        foreach ($sensitivePatterns as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }
        
        // Also check route names
        $sensitiveRoutes = [
            'admin.dashboard',
            'user.profile',
            'workspace.settings',
            'billing.dashboard',
            'developer.index',
        ];
        
        $routeName = $request->route()?->getName() ?? '';
        foreach ($sensitiveRoutes as $route) {
            if (str_starts_with($routeName, $route)) {
                return true;
            }
        }
        
        return false;
    }
}
