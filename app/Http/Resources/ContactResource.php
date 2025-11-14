<?php

namespace App\Http\Resources;

use App\Helpers\DateTimeHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Propaganistas\LaravelPhone\PhoneNumber;

class ContactResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        $data['unread_messages'] = $this->chats()
            ->where('type', 'inbound')
            ->whereNull('deleted_at')
            ->where('is_read', 0)
            ->count();
        
        // Include last_chat relationship if loaded
        if ($this->relationLoaded('lastChat')) {
            $data['last_chat'] = $this->lastChat ? [
                'id' => $this->lastChat->id,
                'created_at' => $this->lastChat->created_at,
                'metadata' => $this->lastChat->metadata,
                'deleted_at' => $this->lastChat->deleted_at,
                'media' => $this->lastChat->media ?? null,
            ] : null;
        }

        // Include last_inbound_chat relationship if loaded
        if ($this->relationLoaded('lastInboundChat')) {
            $data['last_inbound_chat'] = $this->lastInboundChat;
        }
        
        return $data;
    }
}
