<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class WhatsAppQRGeneratedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $qrCodeBase64;
    public $expiresInSeconds;
    public $workspaceId;

    /**
     * Create a new event instance.
     *
     * @param string $qrCodeBase64
     * @param int $expiresInSeconds
     * @param int $workspaceId
     */
    public function __construct($qrCodeBase64, $expiresInSeconds, $workspaceId)
    {
        $this->qrCodeBase64 = $qrCodeBase64;
        $this->expiresInSeconds = $expiresInSeconds;
        $this->workspaceId = $workspaceId;
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
            Log::error('Failed to broadcast WhatsAppQRGeneratedEvent: ' . $e->getMessage());
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
        return 'qr-code-generated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'qr_code_base64' => $this->qrCodeBase64,
            'expires_in_seconds' => $this->expiresInSeconds,
            'workspace_id' => $this->workspaceId,
        ];
    }
}
