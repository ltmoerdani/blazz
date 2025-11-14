<?php

namespace App\Events;

use App\Models\Contact;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContactPresenceUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Contact $contact,
        public bool $isOnline,
        public ?int $userId = null,
        public ?string $typingStatus = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('workspace.' . $this->contact->workspace_id . '.presence'),
            new PrivateChannel('contact.' . $this->contact->id . '.presence'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'contact.presence.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'contact_id' => $this->contact->id,
            'contact_name' => $this->contact->first_name . ' ' . $this->contact->last_name,
            'phone' => $this->contact->phone,
            'is_online' => $this->isOnline,
            'typing_status' => $this->typingStatus ?? $this->contact->typing_status,
            'last_activity' => $this->contact->last_activity?->toISOString(),
            'last_message_at' => $this->contact->last_message_at?->toISOString(),
            'workspace_id' => $this->contact->workspace_id,
            'user_id' => $this->userId,
            'timestamp' => now()->toISOString(),
        ];
    }
}