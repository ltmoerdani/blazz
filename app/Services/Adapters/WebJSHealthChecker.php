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
        // Health endpoint returns {"sessions": {"total": X, "connected": Y}}
        // not an array of session objects
        if (!isset($health['sessions'])) {
            return false;
        }

        // If there are connected sessions and our session is marked connected in DB,
        // assume it's available (Node.js has the session)
        $sessionsData = $health['sessions'];

        if (isset($sessionsData['connected']) && $sessionsData['connected'] > 0) {
            // Additional verification: check Node.js has our specific session
            try {
                $response = Http::timeout(3)->get("{$this->nodeServiceUrl}/api/sessions/{$this->session->session_id}/status", [
                    'workspace_id' => $this->workspaceId,
                    'api_key' => config('whatsapp.node_api_key')
                ]);

                if ($response->successful()) {
                    $sessionData = $response->json();
                    return isset($sessionData['status']) && $sessionData['status'] === 'connected';
                }
            } catch (\Exception $e) {
                // If specific check fails, fallback to simple connected count check
                Log::debug('Session-specific check failed, using connected count', [
                    'session_id' => $this->session->session_id
                ]);
            }

            // Fallback: if Node.js has any connected session, trust DB status
            return true;
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
