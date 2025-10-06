<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApiSecurityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Comprehensive authorization check dengan combined validation
        $validationChecks = [
            'api_key' => $this->hasValidApiKey(),
            'signature' => !$this->requiresSignature() || $this->hasValidSignature(),
            'not_suspicious' => !$this->isSuspiciousRequest(),
        ];
        
        // Log incidents for failed checks
        foreach ($validationChecks as $check => $passed) {
            if (!$passed) {
                $this->logSecurityIncident("failed_{$check}_check");
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Base security rules
            'timestamp' => 'sometimes|integer|between:' . (time() - 300) . ',' . (time() + 300),
            'nonce' => 'sometimes|string|min:16|max:64',
            'signature' => 'sometimes|string|min:64|max:256',
            
            // Content validation
            'content_type' => 'sometimes|in:application/json,application/xml,text/plain',
            'content_length' => 'sometimes|integer|max:10485760', // 10MB max
            
            // Request metadata
            'user_agent' => 'sometimes|string|max:500|regex:/^[a-zA-Z0-9\s\-\.\(\)\/]+$/',
            'client_version' => 'sometimes|string|max:20|regex:/^[0-9\.]+$/',
            
            // Rate limiting context
            'operation_type' => 'sometimes|in:read,write,delete,admin',
            'resource_type' => 'sometimes|string|max:50|alpha_dash',
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'timestamp.between' => 'Request timestamp is outside acceptable window (±5 minutes)',
            'nonce.required' => 'Nonce is required for authenticated requests',
            'signature.required' => 'Request signature is required for this operation',
            'content_length.max' => 'Request payload exceeds maximum allowed size',
            'user_agent.regex' => 'Invalid user agent format detected',
            'client_version.regex' => 'Invalid client version format',
        ];
    }

    /**
     * Handle a failed validation attempt
     */
    protected function failedValidation(Validator $validator)
    {
        $this->logSecurityIncident('validation_failed', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->getSafeInput()
        ]);

        // Increment failed validation counter
        $this->incrementFailureCounter();

        throw new HttpResponseException(response()->json([
            'error' => 'Security validation failed',
            'message' => 'Request does not meet security requirements',
            'code' => 'SECURITY_VALIDATION_FAILED',
            'timestamp' => now()->toISOString(),
        ], 422));
    }

    /**
     * Check if request has valid API key
     */
    protected function hasValidApiKey(): bool
    {
        $apiKey = $this->header('X-API-Key') ?? $this->input('api_key');
        
        if (!$apiKey) {
            return false;
        }

        // Rate limit API key validation attempts
        $cacheKey = "api_key_attempts:{$this->ip()}";
        $attempts = Cache::get($cacheKey, 0);
        
        if ($attempts >= 10) {
            $this->logSecurityIncident('api_key_brute_force');
            return false;
        }

        // Validate API key (implement your own validation logic)
        $isValid = $this->validateApiKey($apiKey);
        
        if (!$isValid) {
            Cache::put($cacheKey, $attempts + 1, 3600); // 1 hour
        } else {
            Cache::forget($cacheKey);
        }

        return $isValid;
    }

    /**
     * Validate API key against your system
     */
    protected function validateApiKey(string $apiKey): bool
    {
        // Implement your API key validation logic
        // This could check against database, external service, etc.
        
        // Example: Check if it's a valid format (modify as needed)
        if (strlen($apiKey) < 32) {
            return false;
        }

        // Check against known API keys or generate hash comparison
        // For demo purposes, we'll use a simple check
        return preg_match('/^[a-f0-9]{64}$/', $apiKey);
    }

    /**
     * Check if request requires signature validation
     */
    protected function requiresSignature(): bool
    {
        $criticalOperations = [
            'user.delete',
            'workspace.delete',
            'payment.process',
            'admin.backup',
            'admin.restore',
        ];

        $operation = $this->input('operation') ?? $this->route()->getName();
        
        return in_array($operation, $criticalOperations);
    }

    /**
     * Validate request signature
     */
    protected function hasValidSignature(): bool
    {
        $signature = $this->header('X-Signature') ?? $this->input('signature');
        $timestamp = $this->header('X-Timestamp') ?? $this->input('timestamp');
        $nonce = $this->header('X-Nonce') ?? $this->input('nonce');

        if (!$signature || !$timestamp || !$nonce) {
            return false;
        }

        // Prevent replay attacks
        $replayCacheKey = "signature_nonce:{$nonce}";
        if (Cache::has($replayCacheKey)) {
            $this->logSecurityIncident('replay_attack_attempt');
            return false;
        }

        // Generate expected signature
        $payload = $this->getSignaturePayload();
        $expectedSignature = $this->generateSignature($payload, $timestamp, $nonce);

        $isValid = hash_equals($expectedSignature, $signature);

        if ($isValid) {
            // Cache nonce to prevent replay (valid for 5 minutes)
            Cache::put($replayCacheKey, true, 300);
        }

        return $isValid;
    }

    /**
     * Get payload for signature generation
     */
    protected function getSignaturePayload(): string
    {
        $method = $this->method();
        $path = $this->path();
        $body = $this->getContent();
        
        return "{$method}|{$path}|{$body}";
    }

    /**
     * Generate signature for payload
     */
    protected function generateSignature(string $payload, string $timestamp, string $nonce): string
    {
        $secret = config('app.api_secret_key'); // Set in your config
        $data = "{$payload}|{$timestamp}|{$nonce}";
        
        return hash_hmac('sha256', $data, $secret);
    }

    /**
     * Check for suspicious request patterns
     */
    protected function isSuspiciousRequest(): bool
    {
        // Combine all suspicious checks into one validation
        $content = $this->getContent();
        $userAgent = $this->header('User-Agent', '');
        $queryString = $this->server('QUERY_STRING', '');
        
        // Pattern detection
        $patternDetected = $this->detectSuspiciousPatterns($content, $userAgent, $queryString);
        
        // Size and header checks
        $oversized = strlen($content) > 10 * 1024 * 1024; // 10MB
        $unusualHeaders = $this->hasUnusualHeaders();
        
        // Log incidents and return combined result
        $isSuspicious = $patternDetected || $oversized || $unusualHeaders;
        
        if ($isSuspicious) {
            $this->logSecurityIncident('suspicious_request_detected', [
                'pattern_detected' => $patternDetected,
                'oversized' => $oversized,
                'unusual_headers' => $unusualHeaders,
            ]);
        }
        
        return $isSuspicious;
    }
    
    /**
     * Detect suspicious patterns in request data
     */
    private function detectSuspiciousPatterns(string $content, string $userAgent, string $queryString): bool
    {
        $suspiciousPatterns = [
            'sql_injection' => '/(\bor\b|\band\b).*[\'"].*[\'"]|union.*select|drop.*table/i',
            'xss_attempt' => '/<script|javascript:|on\w+\s*=/i',
            'path_traversal' => '/\.\.\//i',
            'command_injection' => '/[\|&;`\$\(\)]/i',
        ];

        foreach ($suspiciousPatterns as $type => $pattern) {
            if (preg_match($pattern, $content) ||
                preg_match($pattern, $userAgent) ||
                preg_match($pattern, $queryString)) {

                $this->logSecurityIncident('suspicious_pattern_detected', [
                    'pattern_type' => $type,
                    'matched_content' => substr($content, 0, 200)
                ]);

                return true;
            }
        }
        
        return false;
    }

    /**
     * Check for unusual or suspicious headers
     */
    protected function hasUnusualHeaders(): bool
    {
        $suspiciousHeaders = [
            'X-Forwarded-For' => '/[^0-9\.,\s]/',
            'X-Real-IP' => '/[^0-9\.]/',
            'User-Agent' => '/(bot|crawler|spider|scan)/i',
        ];

        foreach ($suspiciousHeaders as $header => $pattern) {
            $value = $this->header($header);
            if ($value && preg_match($pattern, $value)) {
                $this->logSecurityIncident('suspicious_header', [
                    'header' => $header,
                    'value' => substr($value, 0, 100)
                ]);
                return true;
            }
        }

        return false;
    }

    /**
     * Log security incident
     */
    protected function logSecurityIncident(string $type, array $context = []): void
    {
        $incident = [
            'type' => $type,
            'ip' => $this->ip(),
            'user_agent' => $this->header('User-Agent'),
            'method' => $this->method(),
            'url' => $this->fullUrl(),
            'timestamp' => now()->toISOString(),
            'user_id' => Auth::id(),
            'workspace_id' => session('current_organization_id'),
            'context' => $context,
        ];

        Log::channel('security')->warning("Security incident: {$type}", $incident);

        // Store in database for analysis
        DB::table('security_incidents')->insert([
            'type' => $type,
            'severity' => $this->getSeverityLevel($type),
            'ip_address' => $this->ip(),
            'user_agent' => substr($this->header('User-Agent', ''), 0, 500),
            'request_method' => $this->method(),
            'request_url' => substr($this->fullUrl(), 0, 500),
            'user_id' => Auth::id(),
            'workspace_id' => session('current_organization_id'),
            'context' => json_encode($context),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Get severity level for incident type
     */
    protected function getSeverityLevel(string $type): string
    {
        $severityMap = [
            'invalid_api_key' => 'medium',
            'invalid_signature' => 'high',
            'suspicious_request_pattern' => 'high',
            'validation_failed' => 'low',
            'api_key_brute_force' => 'critical',
            'replay_attack_attempt' => 'critical',
            'suspicious_pattern_detected' => 'high',
            'oversized_request' => 'medium',
            'suspicious_header' => 'medium',
        ];

        return $severityMap[$type] ?? 'medium';
    }

    /**
     * Increment failure counter for rate limiting
     */
    protected function incrementFailureCounter(): void
    {
        $cacheKey = "security_failures:{$this->ip()}";
        $failures = Cache::get($cacheKey, 0) + 1;
        
        Cache::put($cacheKey, $failures, 3600); // 1 hour

        // Block IP if too many failures
        if ($failures >= 20) {
            Cache::put("blocked_ip:{$this->ip()}", true, 86400); // 24 hours
            $this->logSecurityIncident('ip_blocked_for_violations');
        }
    }

    /**
     * Get safe input for logging (remove sensitive data)
     */
    protected function getSafeInput(): array
    {
        $input = $this->except(['password', 'api_key', 'signature', 'token']);
        
        // Truncate long values
        foreach ($input as $key => $value) {
            if (is_string($value) && strlen($value) > 200) {
                $input[$key] = substr($value, 0, 200) . '...';
            }
        }

        return $input;
    }

    /**
     * Additional security headers to be set
     */
    public function getSecurityHeaders(): array
    {
        return [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Content-Security-Policy' => "default-src 'self'",
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ];
    }
}
