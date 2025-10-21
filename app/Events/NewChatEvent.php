<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class NewChatEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chat;
    public $workspaceId;

    /**
     * Create a new event instance.
     *
     * @param mixed $chat
     * @param int $workspaceId
     */
    public function __construct($chat, $workspaceId)
    {
        $this->chat = $chat;
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
            Log::error('Failed to broadcast NewChatEvent: ' . $e->getMessage());
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
        $channel = 'chats.' . 'ch' . $this->workspaceId;
        return new Channel($channel);
    }

    /**
     * Get Pusher broadcast channel
     */
    private function getPusherChannel()
    {
        if (config('broadcasting.connections.pusher.key') && config('broadcasting.connections.pusher.secret')) {
            $channel = 'chats.' . 'ch' . $this->workspaceId;
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
    public function broadcastWith()
    {
        return ['chat' => $this->chat];
    }
}
