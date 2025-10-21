<?php

namespace App\Services\Adapters;

use App\Models\WhatsAppSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebJSUtility
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
     * Mark a message as read via WebJS
     */
    public function markAsRead(string $messageId): bool
    {
        try {
            $response = Http::timeout(10)->post("{$this->nodeServiceUrl}/api/messages/mark-read", [
                'session_id' => $this->session->session_id,
                'workspace_id' => $this->workspaceId,
                'message_id' => $messageId,
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('WebJS mark as read failed', [
                'workspace_id' => $this->workspaceId,
                'session_id' => $this->session->session_id,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get message status via WebJS
     */
    public function getMessageStatus(string $messageId): ?string
    {
        return $this->fetchMessageStatus($messageId);
    }

    /**
     * Fetch message status from Node.js service
     */
    private function fetchMessageStatus(string $messageId): ?string
    {
        try {
            $response = Http::timeout(10)->get("{$this->nodeServiceUrl}/api/messages/{$messageId}/status", [
                'session_id' => $this->session->session_id,
                'workspace_id' => $this->workspaceId,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['status'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            $this->logMessageStatusError($messageId, $e);
            return null;
        }
    }

    /**
     * Log message status error
     */
    private function logMessageStatusError(string $messageId, \Exception $e): void
    {
        Log::warning('WebJS get message status failed', [
            'workspace_id' => $this->workspaceId,
            'session_id' => $this->session->session_id,
            'message_id' => $messageId,
            'error' => $e->getMessage()
        ]);
    }
}
