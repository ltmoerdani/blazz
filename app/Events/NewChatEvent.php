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
     */
    public function broadcastOn(): Channel
    {
        $channel = 'chats.' . 'ch' . $this->workspaceId;
        return new Channel($channel);
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
