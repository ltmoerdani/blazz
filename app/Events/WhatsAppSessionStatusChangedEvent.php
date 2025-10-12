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
            return $this->getBroadcastChannel();
        } catch (Exception $e) {
            Log::error('Failed to broadcast WhatsAppSessionStatusChangedEvent: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get the appropriate broadcast channel based on driver configuration
     */
    private function getBroadcastChannel()
    {
        $driver = config('broadcasting.default', 'reverb');

        if ($driver === 'reverb') {
            return $this->getReverbChannel();
        }

        if ($driver === 'pusher') {
            return $this->getPusherChannel();
        }

        Log::error('Unsupported broadcast driver: ' . $driver);
        return null;
    }

    /**
     * Get Reverb broadcast channel
     */
    private function getReverbChannel()
    {
        $channel = 'workspace.' . $this->workspaceId;
        return new Channel($channel);
    }

    /**
     * Get Pusher broadcast channel
     */
    private function getPusherChannel()
    {
        if (config('broadcasting.connections.pusher.key') && config('broadcasting.connections.pusher.secret')) {
            $channel = 'workspace.' . $this->workspaceId;
            return new Channel($channel);
        }

        Log::error('Pusher settings are not configured.');
        return null;
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
