<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsAppQRGenerated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $workspaceId;
    public string $qr;
    public string $sessionId;

    public function __construct(int $workspaceId, string $qr, string $sessionId)
    {
        $this->workspaceId = $workspaceId;
        $this->qr = $qr;
        $this->sessionId = $sessionId;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('whatsapp.' . $this->workspaceId);
    }

    public function broadcastAs(): string
    {
        return 'whatsapp.qr.generated';
    }

    public function broadcastWith(): array
    {
        return [
            'workspace_id' => $this->workspaceId,
            'qr' => $this->qr,
            'session_id' => $this->sessionId,
        ];
    }
}
