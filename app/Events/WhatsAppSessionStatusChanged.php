<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsAppSessionStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $workspaceId;
    public string $status; // connected | disconnected | qr_required | connecting
    public ?string $sessionId;

    public function __construct(int $workspaceId, string $status, ?string $sessionId = null)
    {
        $this->workspaceId = $workspaceId;
        $this->status = $status;
        $this->sessionId = $sessionId;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('whatsapp.' . $this->workspaceId);
    }

    public function broadcastAs(): string
    {
        return 'whatsapp.session.status';
    }

    public function broadcastWith(): array
    {
        return [
            'workspace_id' => $this->workspaceId,
            'status' => $this->status,
            'session_id' => $this->sessionId,
        ];
    }
}
