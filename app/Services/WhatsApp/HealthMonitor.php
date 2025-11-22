<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HealthMonitor
{
    /**
     * Check the health of all configured WhatsApp instances.
     *
     * @return array Health status for each instance
     */
    public function checkAllInstances(): array
    {
        $instances = config('whatsapp.instances', []);
        $health = [];

        foreach ($instances as $idx => $url) {
            try {
                $response = Http::timeout(config('whatsapp.health_check.timeout', 5))
                    ->get("{$url}/health");

                $health[$idx] = [
                    'url' => $url,
                    'status' => $response->successful() ? 'healthy' : 'unhealthy',
                    'response_time_ms' => $this->getResponseTime($url),
                    'sessions' => $response->json('sessions.total', 0),
                    'utilization' => $response->json('sessions.utilization', 0),
                    'memory_usage' => $response->json('system.memory_percent', 0),
                    'cpu_usage' => $response->json('system.cpu_percent', 0),
                    'uptime' => $response->json('system.uptime_seconds', 0),
                    'last_check' => now()->toISOString(),
                ];

                if ($response->failed()) {
                    $health[$idx]['error'] = $response->body();
                    Log::warning("WhatsApp instance {$idx} ({$url}) health check failed", [
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                }
            } catch (\Exception $e) {
                $health[$idx] = [
                    'url' => $url,
                    'status' => 'unreachable',
                    'error' => $e->getMessage(),
                    'last_check' => now()->toISOString(),
                ];

                Log::error("WhatsApp instance {$idx} ({$url}) unreachable", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $health;
    }

    /**
     * Get health status for a specific instance.
     *
     * @param int $instanceIndex
     * @return array|null
     */
    public function checkInstance(int $instanceIndex): ?array
    {
        $url = config("whatsapp.instances.{$instanceIndex}");

        if (!$url) {
            return null;
        }

        try {
            $response = Http::timeout(config('whatsapp.health_check.timeout', 5))
                ->get("{$url}/health");

            return [
                'url' => $url,
                'status' => $response->successful() ? 'healthy' : 'unhealthy',
                'response_time_ms' => $this->getResponseTime($url),
                'sessions' => $response->json('sessions.total', 0),
                'utilization' => $response->json('sessions.utilization', 0),
                'memory_usage' => $response->json('system.memory_percent', 0),
                'cpu_usage' => $response->json('system.cpu_percent', 0),
                'uptime' => $response->json('system.uptime_seconds', 0),
                'last_check' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            return [
                'url' => $url,
                'status' => 'unreachable',
                'error' => $e->getMessage(),
                'last_check' => now()->toISOString(),
            ];
        }
    }

    /**
     * Check if any instances are unhealthy.
     *
     * @return array Array of unhealthy instance indices
     */
    public function getUnhealthyInstances(): array
    {
        $health = $this->checkAllInstances();
        $unhealthy = [];

        foreach ($health as $index => $status) {
            if ($status['status'] !== 'healthy') {
                $unhealthy[] = $index;
            }
        }

        return $unhealthy;
    }

    /**
     * Get the best available instance for new sessions.
     *
     * @return int|null Index of best instance, or null if none available
     */
    public function getBestInstance(): ?int
    {
        $health = $this->checkAllInstances();
        $bestInstance = null;
        $bestScore = -1;

        foreach ($health as $index => $status) {
            // Skip unhealthy instances
            if ($status['status'] !== 'healthy') {
                continue;
            }

            // Calculate score (lower utilization = better)
            $score = 100 - ($status['utilization'] ?? 0);

            // Prefer instances with lower response time
            if (isset($status['response_time_ms'])) {
                $score -= min($status['response_time_ms'] / 10, 20);
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestInstance = $index;
            }
        }

        return $bestInstance;
    }

    /**
     * Get overall system health summary.
     *
     * @return array
     */
    public function getHealthSummary(): array
    {
        $health = $this->checkAllInstances();
        $totalInstances = count($health);
        $healthyInstances = 0;
        $totalSessions = 0;
        $avgUtilization = 0;
        $unhealthyInstances = [];

        foreach ($health as $index => $status) {
            if ($status['status'] === 'healthy') {
                $healthyInstances++;
                $totalSessions += $status['sessions'] ?? 0;
                $avgUtilization += $status['utilization'] ?? 0;
            } else {
                $unhealthyInstances[] = $index;
            }
        }

        if ($healthyInstances > 0) {
            $avgUtilization = $avgUtilization / $healthyInstances;
        }

        return [
            'total_instances' => $totalInstances,
            'healthy_instances' => $healthyInstances,
            'unhealthy_instances' => $unhealthyInstances,
            'overall_status' => $healthyInstances === $totalInstances ? 'healthy' : 'degraded',
            'total_sessions' => $totalSessions,
            'average_utilization' => round($avgUtilization, 2),
            'capacity_used_percent' => $totalInstances > 0
                ? round(($totalSessions / ($totalInstances * config('whatsapp.limits.max_sessions_per_instance', 500))) * 100, 2)
                : 0,
            'last_check' => now()->toISOString(),
        ];
    }

    /**
     * Measure response time for an instance.
     *
     * @param string $url
     * @return float Response time in milliseconds
     */
    private function getResponseTime(string $url): float
    {
        try {
            $startTime = microtime(true);
            Http::timeout(3)->get("{$url}/health");
            $endTime = microtime(true);

            return round(($endTime - $startTime) * 1000, 2);
        } catch (\Exception $e) {
            return 9999; // High value indicating timeout/error
        }
    }

    /**
     * Check if instance has capacity for more sessions.
     *
     * @param int $instanceIndex
     * @return bool
     */
    public function instanceHasCapacity(int $instanceIndex): bool
    {
        $health = $this->checkInstance($instanceIndex);

        if (!$health || $health['status'] !== 'healthy') {
            return false;
        }

        $maxSessions = config('whatsapp.limits.max_sessions_per_instance', 500);
        $currentSessions = $health['sessions'] ?? 0;

        // Consider unhealthy if over 90% capacity
        return $currentSessions < ($maxSessions * 0.9);
    }
}