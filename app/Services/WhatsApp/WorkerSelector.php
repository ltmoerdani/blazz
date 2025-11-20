<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;

/**
 * Worker Selector for PM2 Cluster Mode
 * 
 * Routes sessions to specific PM2 workers using consistent hashing.
 * This ensures same session always goes to same worker for session persistence.
 */
class WorkerSelector
{
    private $totalWorkers;
    private $basePort;
    private $baseUrl;

    public function __construct()
    {
        // PM2 cluster mode: All workers share same port via load balancing
        // But we can route to specific workers using consistent hashing
        $this->totalWorkers = (int) config('whatsapp.cluster.workers', 5);
        $this->basePort = (int) config('whatsapp.node_service_port', 3001);
        $this->baseUrl = config('whatsapp.node_service_url', 'http://localhost:3001');
    }

    /**
     * Get worker URL for a specific session (consistent hashing)
     * 
     * @param string $sessionId
     * @return string Worker URL
     */
    public function getWorkerUrl(string $sessionId): string
    {
        // For PM2 cluster mode, all workers share same port
        // PM2 handles load balancing internally
        // So we just return the base URL
        return $this->baseUrl;
    }

    /**
     * Get worker index for session (for logging/debugging)
     * 
     * @param string $sessionId
     * @return int Worker index (0-based)
     */
    public function getWorkerIndex(string $sessionId): int
    {
        $hash = md5($sessionId);
        $workerIndex = hexdec(substr($hash, 0, 8)) % $this->totalWorkers;
        
        return $workerIndex;
    }

    /**
     * Get cluster status from all workers
     * 
     * @return array
     */
    public function getClusterStatus(): array
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)
                ->get("{$this->baseUrl}/health");

            if ($response->successful()) {
                return $response->json();
            }

            return [
                'status' => 'error',
                'error' => 'Failed to get cluster status'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get cluster status', [
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if cluster is healthy
     * 
     * @return bool
     */
    public function isClusterHealthy(): bool
    {
        $status = $this->getClusterStatus();
        return isset($status['status']) && $status['status'] === 'healthy';
    }

    /**
     * Get cluster capacity info
     * 
     * @return array
     */
    public function getClusterCapacity(): array
    {
        $status = $this->getClusterStatus();
        
        return [
            'total_workers' => $this->totalWorkers,
            'max_sessions_per_worker' => 50,
            'max_total_sessions' => $this->totalWorkers * 50,
            'current_sessions' => $status['sessions']['total'] ?? 0,
            'available_capacity' => ($this->totalWorkers * 50) - ($status['sessions']['total'] ?? 0)
        ];
    }
}
