<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class WhatsAppSessionStatusChangedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $workspaceId;
    public $sessionId;
    public $status;
    public $phoneNumber;
    public $timestamp;

    /**
     * Create a new event instance.
     *
     * @param int $workspaceId
     * @param string $sessionId
     * @param string $status
     * @param string|null $phoneNumber
     */
    public function __construct($workspaceId, $sessionId, $status, $phoneNumber = null)
    {
        $this->workspaceId = $workspaceId;
        $this->sessionId = $sessionId;
        $this->status = $status;
        $this->phoneNumber = $phoneNumber;
        $this->timestamp = now()->toISOString();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel
     */
    public function broadcastOn()
    {
        try {
            // Support both Reverb (default) and Pusher (optional)
            $driver = config('broadcasting.default', 'reverb');

            if ($driver === 'reverb') {
                // Laravel Reverb (default, free)
                $channel = 'workspace.' . $this->workspaceId;
                return new Channel($channel);
            } elseif ($driver === 'pusher') {
                // Pusher (optional, paid)
                if (config('broadcasting.connections.pusher.key') && config('broadcasting.connections.pusher.secret')) {
                    $channel = 'workspace.' . $this->workspaceId;
                    return new Channel($channel);
                } else {
                    Log::error('Pusher settings are not configured.');
                    return;
                }
            } else {
                Log::error('Unsupported broadcast driver: ' . $driver);
                return;
            }
        } catch (Exception $e) {
            Log::error('Failed to broadcast WhatsAppSessionStatusChangedEvent: ' . $e->getMessage());
            return;
        }
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastAs()
    {
        return 'session-status-changed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'workspace_id' => $this->workspaceId,
            'session_id' => $this->sessionId,
            'status' => $this->status,
            'phone_number' => $this->phoneNumber,
            'timestamp' => $this->timestamp,
        ];
    }
}
