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

class NewPaymentEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $payment;
    public $workspaceId;

    /**
     * Create a new event instance.
     *
     * @param mixed $payment
     * @param int $workspaceId
     */
    public function __construct($payment, $workspaceId)
    {
        $this->payment = $payment;
        $this->workspaceId = $workspaceId;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        $channel = 'payments.' . 'ch' . $this->workspaceId;
        return new Channel($channel);
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return ['payment' => $this->payment];
    }
}
