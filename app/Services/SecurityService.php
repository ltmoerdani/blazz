<?php

namespace App\Services;

use App\Models\SecurityIncident;
use App\Models\RateLimitViolation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * Core Security Service - Simplified dengan delegation
 */
class SecurityService
{
    /**
     * Perform simplified security assessment
     */
    public function performSecurityAssessment(Request $request): array
    {
        $assessment = [
            'timestamp' => now()->toISOString(),
            'ip_address' => $request->ip(),
            'user_id' => Auth::id(),
            'workspace_id' => session('current_workspace'),
            'risk_score' => 0,
            'threats_detected' => [],
            'recommendations' => [],
            'blocked' => false,
        ];

        // Simplified threat detection
        $ipRisk = $this->assessBasicIpRisk($request->ip());
        $requestRisk = $this->assessBasicRequestPatterns($request);
        
        $assessment['risk_score'] = $ipRisk + $requestRisk;
        $assessment['blocked'] = $assessment['risk_score'] >= 80;

        return $assessment;
    }

    /**
     * Basic IP risk assessment
     */
    protected function assessBasicIpRisk(string $ip): int
    {
        $riskScore = 0;
        
        // Check basic threat patterns
        if ($this->isKnownThreatIp($ip)) {
            $riskScore += 50;
        }
        
        // Check recent violations
        $violations = $this->getRateLimitViolations($ip);
        $riskScore += min($violations * 5, 30);
        
        return $riskScore;
    }

    /**
     * Basic request pattern assessment
     */
    protected function assessBasicRequestPatterns(Request $request): int
    {
        $riskScore = 0;
        
        // Simple threat detection
        if ($this->detectSqlInjection($request)) {
            $riskScore += 40;
        }
        
        if ($this->detectXssAttempt($request)) {
            $riskScore += 35;
        }
        
        return $riskScore;
    }

    /**
     * Check if IP is in threat database
     */
    protected function isKnownThreatIp(string $ip): bool
    {
        return Cache::remember("threat_ip:{$ip}", 3600, function () use ($ip) {
            return SecurityIncident::systemWide()
                ->where('ip_address', $ip)
                ->where('severity', 'high')
                ->where('created_at', '>=', now()->subDays(7))
                ->exists();
        });
    }

    /**
     * Get rate limit violations count
     */
    protected function getRateLimitViolations(string $ip): int
    {
        return (int) Cache::get("violations:{$ip}", 0);
    }

    /**
     * Basic SQL injection detection
     */
    protected function detectSqlInjection(Request $request): bool
    {
        $content = strtolower($request->getContent());
        $patterns = ['union select', 'drop table', "' or 1=1", '" or 1=1'];
        
        foreach ($patterns as $pattern) {
            if (strpos($content, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Basic XSS detection
     */
    protected function detectXssAttempt(Request $request): bool
    {
        $content = strtolower($request->getContent());
        $patterns = ['<script', 'javascript:', 'onload=', 'onerror='];
        
        foreach ($patterns as $pattern) {
            if (strpos($content, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Log security incident
     */
    public function logSecurityIncident(string $type, array $data = []): void
    {
        try {
            SecurityIncident::create([
                'incident_type' => $type,
                'ip_address' => request()->ip(),
                'user_id' => Auth::id(),
                'workspace_id' => session('current_workspace'),
                'severity' => $this->determineSeverity($type),
                'details' => $data,
                'resolved' => false,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log security incident', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Determine severity based on incident type
     */
    protected function determineSeverity(string $type): string
    {
        $highSeverityTypes = ['sql_injection', 'privilege_escalation', 'data_exfiltration'];
        $mediumSeverityTypes = ['xss_attempt', 'csrf_attack', 'suspicious_login'];
        
        if (in_array($type, $highSeverityTypes)) {
            return 'high';
        }
        
        if (in_array($type, $mediumSeverityTypes)) {
            return 'medium';
        }
        
        return 'low';
    }

    /**
     * Get security metrics for dashboard
     */
    public function getSecurityMetrics(int $workspaceId, int $days = 7): array
    {
        $startDate = now()->subDays($days);
        
        return [
            'total_incidents' => $this->getIncidentCount($workspaceId, $startDate),
            'blocked_requests' => $this->getBlockedRequestCount($workspaceId, $startDate),
            'threat_levels' => $this->getThreatLevelDistribution($workspaceId, $startDate),
            'top_threats' => $this->getTopThreats($workspaceId, $startDate),
        ];
    }

    /**
     * Get incident count for workspace
     */
    protected function getIncidentCount(int $workspaceId, Carbon $startDate): int
    {
        return SecurityIncident::inWorkspace($workspaceId)
            ->where('created_at', '>=', $startDate)
            ->count();
    }

    /**
     * Get blocked request count
     */
    protected function getBlockedRequestCount(int $workspaceId, Carbon $startDate): int
    {
        return RateLimitViolation::inWorkspace($workspaceId)
            ->where('created_at', '>=', $startDate)
            ->count();
    }

    /**
     * Get threat level distribution
     */
    protected function getThreatLevelDistribution(int $workspaceId, Carbon $startDate): array
    {
        return SecurityIncident::inWorkspace($workspaceId)
            ->where('created_at', '>=', $startDate)
            ->groupBy('severity')
            ->selectRaw('severity, count(*) as count')
            ->pluck('count', 'severity')
            ->toArray();
    }

    /**
     * Get top threats for workspace
     */
    protected function getTopThreats(int $workspaceId, Carbon $startDate): array
    {
        return DB::table('security_incidents')
            ->where('workspace_id', $workspaceId)
            ->where('created_at', '>=', $startDate)
            ->groupBy('incident_type')
            ->selectRaw('incident_type, count(*) as count')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->toArray();
    }
}
