<?php

namespace App\Services;

use App\Contracts\WhatsAppAdapterInterface;
use App\Exceptions\WhatsAppProviderNotFoundException;
use App\Exceptions\WhatsAppSessionNotFoundException;
use App\Models\WhatsAppSession;
use App\Services\Adapters\MetaAPIAdapter;
use App\Services\Adapters\WebJSAdapter;
use Illuminate\Support\Facades\Log;

class ProviderSelector
{
    /**
     * Select the best available provider for a workspace
     *
     * @param int $workspaceId
     * @param string|null $preferredProvider
     * @return WhatsAppAdapterInterface
     * @throws \Exception
     */
    public function selectProvider(int $workspaceId, ?string $preferredProvider = null): WhatsAppAdapterInterface
    {
        // If preferred provider is specified, try that first
        if ($preferredProvider) {
            $provider = $this->getProviderInstance($preferredProvider, $workspaceId);
            if ($provider && $provider->isAvailable()) {
                Log::info("Using preferred provider: {$preferredProvider} for workspace {$workspaceId}");
                return $provider;
            }
        }

        // Get all available sessions for the workspace
        $sessions = WhatsAppSession::forWorkspace($workspaceId)
            ->active()
            ->connected()
            ->orderBy('is_primary', 'desc')
            ->orderBy('last_connected_at', 'desc')
            ->get();

        if ($sessions->isEmpty()) {
            throw new WhatsAppSessionNotFoundException();
        }

        // Try each session until we find a working provider
        foreach ($sessions as $session) {
            try {
                $provider = $this->getProviderInstance($session->provider_type, $workspaceId, $session);
                if ($provider && $provider->isAvailable()) {
                    Log::info("Selected provider: {$session->provider_type} (session: {$session->session_id}) for workspace {$workspaceId}");
                    return $provider;
                }
            } catch (\Exception $e) {
                Log::warning("Provider {$session->provider_type} not available: " . $e->getMessage());
                continue;
            }
        }

        throw new WhatsAppProviderNotFoundException();
    }

    /**
     * Get provider instance based on type
     *
     * @param string $providerType
     * @param int $workspaceId
     * @param WhatsAppSession|null $session
     * @return WhatsAppAdapterInterface|null
     */
    private function getProviderInstance(string $providerType, int $workspaceId, ?WhatsAppSession $session = null): ?WhatsAppAdapterInterface
    {
        return match($providerType) {
            'meta' => app(MetaAPIAdapter::class, ['workspaceId' => $workspaceId, 'session' => $session]),
            'webjs' => app(WebJSAdapter::class, ['workspaceId' => $workspaceId, 'session' => $session]),
            default => null
        };
    }

    /**
     * Check if a provider is available for a workspace
     *
     * @param string $providerType
     * @param int $workspaceId
     * @return bool
     */
    public function isProviderAvailable(string $providerType, int $workspaceId): bool
    {
        try {
            $sessions = WhatsAppSession::forWorkspace($workspaceId)
                ->active()
                ->connected()
                ->byProvider($providerType)
                ->get();

            if ($sessions->isEmpty()) {
                return false;
            }

            $provider = $this->getProviderInstance($providerType, $workspaceId, $sessions->first());
            return $provider && $provider->isAvailable();
        } catch (\Exception $e) {
            Log::warning("Provider {$providerType} availability check failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all available providers for a workspace
     *
     * @param int $workspaceId
     * @return array
     */
    public function getAvailableProviders(int $workspaceId): array
    {
        $providers = [];

        foreach (['meta', 'webjs'] as $providerType) {
            if ($this->isProviderAvailable($providerType, $workspaceId)) {
                $providers[] = $providerType;
            }
        }

        return $providers;
    }

    /**
     * Get provider health status for a workspace
     *
     * @param int $workspaceId
     * @return array
     */
    public function getProviderHealth(int $workspaceId): array
    {
        $health = [];

        $sessions = WhatsAppSession::forWorkspace($workspaceId)->active()->get();

        foreach ($sessions as $session) {
            $providerType = $session->provider_type;
            if (!isset($health[$providerType])) {
                $health[$providerType] = [
                    'total_sessions' => 0,
                    'connected_sessions' => 0,
                    'healthy_sessions' => 0,
                    'average_health_score' => 0,
                ];
            }

            $health[$providerType]['total_sessions']++;

            if ($session->status === 'connected') {
                $health[$providerType]['connected_sessions']++;
            }

            if ($session->isHealthy()) {
                $health[$providerType]['healthy_sessions']++;
            }

            // Update average health score
            $currentAvg = $health[$providerType]['average_health_score'];
            $count = $health[$providerType]['total_sessions'];
            $health[$providerType]['average_health_score'] = (($currentAvg * ($count - 1)) + $session->health_score) / $count;
        }

        return $health;
    }

    /**
     * Auto-failover to next available provider
     *
     * @param int $workspaceId
     * @param string $failedProvider
     * @return WhatsAppAdapterInterface|null
     */
    public function failover(int $workspaceId, string $failedProvider): ?WhatsAppAdapterInterface
    {
        $availableProviders = $this->getAvailableProviders($workspaceId);

        // Remove the failed provider from available list
        $availableProviders = array_filter($availableProviders, fn($provider) => $provider !== $failedProvider);

        if (empty($availableProviders)) {
            Log::error("No providers available for failover in workspace {$workspaceId}");
            return null;
        }

        // Try the next available provider
        $nextProvider = reset($availableProviders);
        try {
            return $this->selectProvider($workspaceId, $nextProvider);
        } catch (\Exception $e) {
            Log::error("Failover to {$nextProvider} failed: " . $e->getMessage());
            return null;
        }
    }
}
