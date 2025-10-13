<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsAppQRGeneratedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $qrCodeBase64;
    public int $expiresInSeconds;
    public int $workspaceId;
    public string $sessionId;

    /**
     * Create a new event instance.
     */
    public function __construct(string $qrCodeBase64, int $expiresInSeconds, int $workspaceId, string $sessionId)
    {
        $this->qrCodeBase64 = $qrCodeBase64;
        $this->expiresInSeconds = $expiresInSeconds;
        $this->workspaceId = $workspaceId;
        $this->sessionId = $sessionId;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('workspace.' . $this->workspaceId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'qr-code-generated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'qr_code_base64' => $this->qrCodeBase64,
            'expires_in_seconds' => $this->expiresInSeconds,
            'workspace_id' => $this->workspaceId,
            'session_id' => $this->sessionId,
            'timestamp' => now()->toISOString(),
        ];
    }
}
