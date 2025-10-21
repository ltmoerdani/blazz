<?php

namespace App\Services\Adapters;

use App\Models\WhatsAppSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebJSHealthChecker
{
    private WhatsAppSession $session;
    private int $workspaceId;
    private string $nodeServiceUrl;

    public function __construct(WhatsAppSession $session, int $workspaceId, string $nodeServiceUrl)
    {
        $this->session = $session;
        $this->workspaceId = $workspaceId;
        $this->nodeServiceUrl = $nodeServiceUrl;
    }

    /**
     * Check if WebJS service is available and ready
     */
    public function isAvailable(): bool
    {
        // Early validation checks
        if ($this->session->status !== 'connected') {
            return false;
        }

        return $this->performHealthCheck();
    }

    /**
     * Perform the actual health check logic
     */
    private function performHealthCheck(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->nodeServiceUrl}/health");

            if (!$response->successful()) {
                return false;
            }

            $health = $response->json();
            return $this->validateSessionInHealth($health);
        } catch (\Exception $e) {
            Log::warning('WebJS availability check failed', [
                'workspace_id' => $this->workspaceId,
                'session_id' => $this->session->session_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Validate if our session exists in health data
     */
    private function validateSessionInHealth(array $health): bool
    {
        if (!isset($health['sessions'])) {
            return false;
        }

        foreach ($health['sessions'] as $sessionInfo) {
            if ($sessionInfo['session_id'] === $this->session->session_id &&
                $sessionInfo['status'] === 'connected') {
                return true;
            }
        }

        return false;
    }

    /**
     * Get provider health information
     */
    public function getHealthInfo(): array
    {
        return [
            'status' => $this->session->status,
            'provider' => 'webjs',
            'health_score' => $this->session->health_score,
            'is_available' => $this->isAvailable(),
            'last_activity' => $this->session->last_activity_at,
            'phone_number' => $this->session->phone_number,
            'session_id' => $this->session->session_id
        ];
    }
}
