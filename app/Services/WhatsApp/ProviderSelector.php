<?php

namespace App\Services\WhatsApp;

use App\Models\Workspace;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class ProviderSelector
{
    /**
     * Select optimal WhatsApp provider for a workspace.
     * Priority: workspace metadata 'provider_priority' (default: webjs)
     * Fallbacks: other provider if primary unavailable; 'none' if all down.
     */
    public function selectProvider(Workspace $workspace): string
    {
        $metadata = $this->getWhatsappConfig($workspace);
        $priority = $metadata['provider_priority'] ?? 'webjs';
    $selected = null;

        if ($priority === 'webjs' && $this->isWebJSAvailable($workspace)) {
            Log::info('Provider selected: webjs (primary)', ['workspace_id' => $workspace->id]);
            $selected = 'webjs';
        } elseif ($priority === 'meta-api' && $this->isMetaAPIAvailable($workspace)) {
            Log::info('Provider selected: meta-api (primary)', ['workspace_id' => $workspace->id]);
            $selected = 'meta-api';
        } else {
            $fallback = $priority === 'webjs' ? 'meta-api' : 'webjs';
            if ($fallback === 'webjs' && $this->isWebJSAvailable($workspace)) {
                Log::warning('Provider fallback: webjs (secondary)', ['workspace_id' => $workspace->id]);
                $selected = 'webjs';
            } elseif ($fallback === 'meta-api' && $this->isMetaAPIAvailable($workspace)) {
                Log::warning('Provider fallback: meta-api (secondary)', ['workspace_id' => $workspace->id]);
                $selected = 'meta-api';
            }
        }

        if ($selected === null) {
            Log::error('No WhatsApp provider available', ['workspace_id' => $workspace->id]);
            $selected = 'none';
        }

        return $selected;
    }

    public function isWebJSAvailable(Workspace $workspace): bool
    {
        $whatsapp = $this->getWhatsappConfig($workspace);
        $status = $whatsapp['webjs_status'] ?? ($whatsapp['webjs']['status'] ?? 'disconnected');
        $sessionId = $whatsapp['webjs_session_id'] ?? ($whatsapp['webjs']['session_id'] ?? null);

        if ($status !== 'connected' || !$sessionId) {
            return false;
        }

        // Verify session via Node service (best-effort)
        try {
            $client = new Client(['timeout' => 3]);
            $nodeUrl = rtrim(config('services.whatsapp_node.url'), '/');
            $res = $client->get($nodeUrl . "/api/sessions/{$sessionId}/status", [
                'headers' => [
                    'X-Workspace-ID' => (string) $workspace->id,
                    'X-API-Token' => (string) config('services.whatsapp_node.api_token'),
                ],
            ]);
            $data = json_decode($res->getBody()->getContents(), true);
            return ($data['status'] ?? null) === 'connected';
        } catch (\Throwable $e) {
            Log::warning('Node session check failed', [
                'workspace_id' => $workspace->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function isMetaAPIAvailable(Workspace $workspace): bool
    {
        $whatsapp = $this->getWhatsappConfig($workspace);
        $accessToken = $whatsapp['access_token'] ?? null;
        $phoneNumberId = $whatsapp['phone_number_id'] ?? null;
        return (bool) ($accessToken && $phoneNumberId);
    }

    private function getWhatsappConfig(Workspace $workspace): array
    {
        $metadata = $workspace->metadata ? json_decode($workspace->metadata, true) : [];
        return $metadata['whatsapp'] ?? [];
    }
}
