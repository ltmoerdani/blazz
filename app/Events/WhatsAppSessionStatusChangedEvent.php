<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsAppSessionStatusChangedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $sessionId;
    public string $status;
    public int $workspaceId;
    public ?string $phoneNumber;
    public array $metadata;

    /**
     * Create a new event instance.
     */
    public function __construct(string $sessionId, string $status, int $workspaceId, ?string $phoneNumber = null, array $metadata = [])
    {
        $this->sessionId = $sessionId;
        $this->status = $status;
        $this->workspaceId = $workspaceId;
        $this->phoneNumber = $phoneNumber;
        $this->metadata = $metadata;
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
        return 'session-status-changed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'status' => $this->status,
            'workspace_id' => $this->workspaceId,
            'phone_number' => $this->phoneNumber,
            'metadata' => $this->metadata,
            'timestamp' => now()->toISOString(),
        ];
    }
}
