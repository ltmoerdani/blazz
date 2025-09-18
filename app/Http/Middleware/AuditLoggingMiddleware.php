<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * PHASE-3 Comprehensive Audit Logging Middleware
 * GDPR-compliant activity tracking dan security monitoring
 */
class AuditLoggingMiddleware
{
    // Events yang harus diaudit
    const AUDIT_EVENTS = [
        'auth.login' => 'User Login',
        'auth.logout' => 'User Logout',
        'auth.failed' => 'Failed Login Attempt',
        'user.created' => 'User Account Created',
        'user.updated' => 'User Profile Updated',
        'user.deleted' => 'User Account Deleted',
        'organization.created' => 'Organization Created',
        'organization.updated' => 'Organization Updated',
        'chat.created' => 'Chat Started',
        'chat.message' => 'Message Sent',
        'api.access' => 'API Access',
        'admin.access' => 'Admin Panel Access',
        'data.export' => 'Data Export',
        'data.import' => 'Data Import',
        'settings.updated' => 'Settings Changed',
        'payment.processed' => 'Payment Processed',
        'subscription.changed' => 'Subscription Modified',
    ];

    // Sensitive endpoints yang selalu diaudit
    const ALWAYS_AUDIT_ENDPOINTS = [
        'auth.*',
        'admin.*',
        'api.*',
        'user.profile.*',
        'organization.*',
        'billing.*',
        'settings.*',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        // Pre-request audit (untuk tracking attempts)
        $auditId = $this->logPreRequest($request);
        
        // Process request
        $response = $next($request);
        
        // Post-request audit (untuk tracking results)
        $this->logPostRequest($request, $response, $auditId, $startTime);
        
        return $response;
    }

    /**
     * Log pre-request information
     */
    private function logPreRequest(Request $request): ?string
    {
        // Only audit selected endpoints
        if (!$this->shouldAudit($request)) {
            return null;
        }
        
        $auditData = [
            'event_type' => 'request_attempt',
            'endpoint' => $request->route()?->getName() ?? 'unknown',
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->id,
            'organization_id' => session('current_organization'),
            'session_id' => session()->getId(),
            'request_id' => $this->generateRequestId(),
            'timestamp' => now(),
            'additional_data' => $this->getAdditionalRequestData($request),
        ];
        
        // Store audit log
        $auditId = $this->storeAuditLog($auditData);
        
        // Log untuk real-time monitoring
        if ($this->isHighRiskEndpoint($request)) {
            Log::info('High-risk endpoint accessed', [
                'audit_id' => $auditId,
                'endpoint' => $auditData['endpoint'],
                'user_id' => $auditData['user_id'],
                'ip_address' => $auditData['ip_address'],
            ]);
        }
        
        return $auditId;
    }

    /**
     * Log post-request information
     */
    private function logPostRequest(Request $request, Response $response, ?string $auditId, float $startTime): void
    {
        if (!$auditId) {
            return;
        }
        
        $executionTime = (microtime(true) - $startTime) * 1000; // milliseconds
        
        $responseData = [
            'status_code' => $response->getStatusCode(),
            'response_size' => strlen($response->getContent()),
            'execution_time' => $executionTime,
            'memory_usage' => memory_get_peak_usage(true),
            'success' => $response->isSuccessful(),
            'event_result' => $this->determineEventResult($response),
        ];
        
        // Update existing audit log
        $this->updateAuditLog($auditId, $responseData);
        
        // Special handling untuk authentication events
        if ($this->isAuthenticationEndpoint($request)) {
            $this->logAuthenticationResult($request, $response, $auditId);
        }
        
        // Log security incidents
        if ($this->isSecurityIncident($response)) {
            $this->logSecurityIncident($request, $response, $auditId);
        }
    }

    /**
     * Determine if request should be audited
     */
    private function shouldAudit(Request $request): bool
    {
        // Skip audit untuk health checks dan internal requests
        if ($request->is('health', 'status', 'ping', '_debugbar*')) {
            return false;
        }
        
        // Always audit sensitive endpoints
        $endpoint = $request->route()?->getName() ?? '';
        foreach (self::ALWAYS_AUDIT_ENDPOINTS as $pattern) {
            if (fnmatch($pattern, $endpoint)) {
                return true;
            }
        }
        
        // Audit based on method OR authenticated sensitive areas
        return in_array($request->method(), ['POST', 'PUT', 'DELETE', 'PATCH']) ||
               ($request->user() && $this->isSensitiveArea($request));
    }

    /**
     * Check if endpoint is high-risk
     */
    private function isHighRiskEndpoint(Request $request): bool
    {
        $highRiskPatterns = [
            'admin.*',
            'auth.login',
            'user.delete',
            'organization.delete',
            'billing.*',
            'api.admin.*',
        ];
        
        $endpoint = $request->route()?->getName() ?? '';
        foreach ($highRiskPatterns as $pattern) {
            if (fnmatch($pattern, $endpoint)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get additional request data untuk audit
     */
    private function getAdditionalRequestData(Request $request): array
    {
        $data = [
            'referer' => $request->header('referer'),
            'accept_language' => $request->header('accept-language'),
            'is_ajax' => $request->ajax(),
            'expects_json' => $request->expectsJson(),
        ];
        
        // Add sanitized input data untuk audit trail (tanpa passwords)
        if ($request->isMethod('POST') || $request->isMethod('PUT')) {
            $inputData = $request->except(['password', 'password_confirmation', 'current_password']);
            
            // Limit input data size untuk prevent large audit logs
            if (json_encode($inputData) && strlen(json_encode($inputData)) < 5000) {
                $data['input_summary'] = $this->sanitizeInputData($inputData);
            }
        }
        
        return $data;
    }

    /**
     * Sanitize input data untuk audit logs
     */
    private function sanitizeInputData(array $input): array
    {
        $sensitiveFields = [
            'password', 'token', 'secret', 'key', 'api_key',
            'credit_card', 'ssn', 'bank_account'
        ];
        
        $sanitized = [];
        foreach ($input as $key => $value) {
            $lowerKey = strtolower($key);
            $isSensitive = false;
            
            foreach ($sensitiveFields as $field) {
                if (strpos($lowerKey, $field) !== false) {
                    $isSensitive = true;
                    break;
                }
            }
            
            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_string($value) && strlen($value) > 100) {
                $sanitized[$key] = substr($value, 0, 100) . '...';
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Store audit log ke database
     */
    private function storeAuditLog(array $data): string
    {
        try {
            $auditId = $data['request_id'];
            
            DB::table('audit_logs')->insert([
                'id' => $auditId,
                'event_type' => $data['event_type'],
                'endpoint' => $data['endpoint'],
                'method' => $data['method'],
                'url' => $data['url'],
                'ip_address' => $data['ip_address'],
                'user_agent' => $data['user_agent'],
                'user_id' => $data['user_id'],
                'organization_id' => $data['organization_id'],
                'session_id' => $data['session_id'],
                'request_data' => json_encode($data['additional_data']),
                'created_at' => $data['timestamp'],
                'updated_at' => $data['timestamp'],
            ]);
            
            return $auditId;
        } catch (\Exception $e) {
            // Fallback ke file logging jika database fail
            Log::error('Failed to store audit log to database', [
                'error' => $e->getMessage(),
                'audit_data' => $data
            ]);
            
            return $data['request_id'];
        }
    }

    /**
     * Update audit log dengan response data
     */
    private function updateAuditLog(string $auditId, array $responseData): void
    {
        try {
            DB::table('audit_logs')->where('id', $auditId)->update([
                'status_code' => $responseData['status_code'],
                'response_size' => $responseData['response_size'],
                'execution_time' => $responseData['execution_time'],
                'memory_usage' => $responseData['memory_usage'],
                'success' => $responseData['success'],
                'event_result' => $responseData['event_result'],
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update audit log', [
                'audit_id' => $auditId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Log authentication results
     */
    private function logAuthenticationResult(Request $request, Response $response, string $auditId): void
    {
        $isSuccess = $response->isSuccessful();
        $endpoint = $request->route()?->getName() ?? '';
        
        if ($endpoint === 'login' || strpos($endpoint, 'auth') !== false) {
            $logData = [
                'audit_id' => $auditId,
                'authentication_result' => $isSuccess ? 'success' : 'failure',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'attempted_email' => $request->input('email', 'unknown'),
            ];
            
            if (!$isSuccess) {
                // Track failed login attempts
                $this->trackFailedLogin($request);
                Log::warning('Authentication failure', $logData);
            } else {
                Log::info('Successful authentication', $logData);
            }
        }
    }

    /**
     * Track failed login attempts untuk security
     */
    private function trackFailedLogin(Request $request): void
    {
        $ip = $request->ip();
        $email = $request->input('email', 'unknown');
        
        // Track by IP
        $ipKey = "failed_login:ip:{$ip}";
        $ipAttempts = cache()->increment($ipKey, 1);
        cache()->expire($ipKey, 3600); // 1 hour
        
        // Track by email
        $emailKey = "failed_login:email:{$email}";
        $emailAttempts = cache()->increment($emailKey, 1);
        cache()->expire($emailKey, 3600); // 1 hour
        
        // Alert pada excessive attempts
        if ($ipAttempts > 10 || $emailAttempts > 5) {
            Log::alert('Excessive failed login attempts detected', [
                'ip' => $ip,
                'email' => $email,
                'ip_attempts' => $ipAttempts,
                'email_attempts' => $emailAttempts,
            ]);
        }
    }

    /**
     * Log security incidents
     */
    private function logSecurityIncident(Request $request, Response $response, string $auditId): void
    {
        $incidentData = [
            'audit_id' => $auditId,
            'incident_type' => $this->determineIncidentType($response),
            'severity' => $this->determineIncidentSeverity($response),
            'ip_address' => $request->ip(),
            'user_id' => $request->user()?->id,
            'endpoint' => $request->route()?->getName(),
            'status_code' => $response->getStatusCode(),
        ];
        
        Log::warning('Security incident detected', $incidentData);
        
        // Store security incident untuk additional tracking
        try {
            DB::table('security_incidents')->insert([
                'audit_id' => $auditId,
                'incident_type' => $incidentData['incident_type'],
                'severity' => $incidentData['severity'],
                'ip_address' => $incidentData['ip_address'],
                'user_id' => $incidentData['user_id'],
                'endpoint' => $incidentData['endpoint'],
                'details' => json_encode($incidentData),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store security incident', [
                'error' => $e->getMessage(),
                'incident_data' => $incidentData
            ]);
        }
    }

    /**
     * Generate unique request ID
     */
    private function generateRequestId(): string
    {
        return 'req_' . uniqid() . '_' . mt_rand(1000, 9999);
    }

    /**
     * Determine event result based on response
     */
    private function determineEventResult(Response $response): string
    {
        $statusCode = $response->getStatusCode();
        
        if ($statusCode >= 200 && $statusCode < 300) {
            return 'success';
        }
        
        if ($statusCode >= 400 && $statusCode < 500) {
            return 'client_error';
        }
        
        return $statusCode >= 500 ? 'server_error' : 'unknown';
    }

    /**
     * Check if endpoint is authentication-related
     */
    private function isAuthenticationEndpoint(Request $request): bool
    {
        $authEndpoints = ['login', 'logout', 'register', 'password.reset', 'password.update'];
        $endpoint = $request->route()?->getName() ?? '';
        
        return in_array($endpoint, $authEndpoints) || strpos($endpoint, 'auth.') === 0;
    }

    /**
     * Check if response indicates security incident
     */
    private function isSecurityIncident(Response $response): bool
    {
        $securityStatusCodes = [401, 403, 419, 429, 500, 503];
        return in_array($response->getStatusCode(), $securityStatusCodes);
    }

    /**
     * Determine incident type
     */
    private function determineIncidentType(Response $response): string
    {
        return match($response->getStatusCode()) {
            401 => 'unauthorized_access',
            403 => 'forbidden_access',
            419 => 'csrf_token_mismatch',
            429 => 'rate_limit_exceeded',
            500 => 'server_error',
            503 => 'service_unavailable',
            default => 'unknown_incident'
        };
    }

    /**
     * Determine incident severity
     */
    private function determineIncidentSeverity(Response $response): string
    {
        return match($response->getStatusCode()) {
            401, 403 => 'medium',
            419, 429 => 'low',
            500, 503 => 'high',
            default => 'low'
        };
    }

    /**
     * Check if request is to sensitive area
     */
    private function isSensitiveArea(Request $request): bool
    {
        $sensitivePatterns = [
            'admin/*',
            'user/profile/*',
            'organization/settings/*',
            'billing/*',
            'api/admin/*',
        ];
        
        foreach ($sensitivePatterns as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }
        
        return false;
    }
}
