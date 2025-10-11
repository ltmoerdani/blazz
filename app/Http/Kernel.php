<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \App\Http\Middleware\CheckAppStatus::class,
        \App\Http\Middleware\SecurityHeadersMiddleware::class,
        \App\Http\Middleware\QueryPerformanceMiddleware::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\SetWorkspaceFromSession::class, // FIRST: Set workspace context
            \App\Http\Middleware\AuditLoggingMiddleware::class,       // SECOND: Log dengan workspace context
            \App\Http\Middleware\Localization::class,
        ],

        'api' => [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            // REMOVED: \Illuminate\Routing\Middleware\ThrottleRequests::class.':api', // Replaced by AdvancedRateLimitMiddleware
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\AdvancedRateLimitMiddleware::class,  // workspace-aware rate limiting
            \App\Http\Middleware\AuditLoggingMiddleware::class,       // API audit dengan workspace context
        ],
    ];

    /**
     * The application's middleware aliases.
     *
     * Aliases may be used instead of class names to conveniently assign middleware to routes and groups.
     *
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'precognitive' => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        'signed' => \App\Http\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'check.subscription' => \App\Http\Middleware\CheckSubscriptionStatus::class,
        'check.workspace' => \App\Http\Middleware\CheckWorkspaceId::class,
        'check.email.verification' => \App\Http\Middleware\CheckEmailVerification::class,
        'check.client.role' => \App\Http\Middleware\CheckClientRole::class,
        'auth.bearer' => \App\Http\Middleware\AuthenticateBearerToken::class,
        'setWorkspace' => \App\Http\Middleware\SetWorkspaceFromSession::class,
        'advanced.rate.limit' => \App\Http\Middleware\AdvancedRateLimitMiddleware::class,
        'audit.logging' => \App\Http\Middleware\AuditLoggingMiddleware::class,
        'security.headers' => \App\Http\Middleware\SecurityHeadersMiddleware::class,
        'redirectIfAuthenticated' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'query.performance' => \App\Http\Middleware\QueryPerformanceMiddleware::class,
        'verify.whatsapp.hmac' => \App\Http\Middleware\VerifyWhatsAppHmacSignature::class,
    ];
}
