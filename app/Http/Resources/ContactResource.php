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

        // Use cached unread_messages column instead of counting every time
        $data['unread_messages'] = $this->unread_messages ?? 0;
        
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

        // Include additional fields for chats UI
        $data['full_name'] = $this->full_name ?? trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        $data['chat_type'] = $this->chat_type ?? 'private';
        $data['group_name'] = $this->group_name ?? null;
        $data['participants_count'] = $this->participants_count ?? null;
        $data['provider_type'] = $this->provider_type ?? null;
        $data['is_online'] = $this->is_online ?? false;
        $data['typing_status'] = $this->typing_status ?? 'idle';
        $data['last_activity'] = $this->last_activity ?? null;
        
        return $data;
    }
}
