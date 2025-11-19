<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class NewChatEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chat;
    public $workspaceId;
    public $contactId;

    /**
     * Create a new event instance.
     * Following best practice from riset: structured message data
     *
     * @param array $chat - Chat data in array format (not Eloquent model)
     * @param int $workspaceId
     * @param int|null $contactId - For specific contact channel
     */
    public function __construct($chat, $workspaceId, $contactId = null)
    {
        $this->chat = $chat;
        $this->workspaceId = $workspaceId;
        $this->contactId = $contactId ?? ($chat['contact_id'] ?? null);
        
        Log::info('📤 NewChatEvent created', [
            'workspace_id' => $this->workspaceId,
            'contact_id' => $this->contactId,
            'chat_id' => $chat['id'] ?? 'unknown',
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     * Following riset pattern: workspace-based channels
     *
     * @return array<\Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn()
    {
        try {
            $channels = [];
            
            // Primary workspace channel (all users in workspace see new messages)
            $channels[] = new PrivateChannel('workspace.' . $this->workspaceId);
            
            // Specific contact channel (for users viewing this specific contact)
            if ($this->contactId) {
                $channels[] = new PrivateChannel('workspace.' . $this->workspaceId . '.chat.' . $this->contactId);
            }
            
            Log::info('📡 Broadcasting to channels', [
                'channels' => array_map(fn($ch) => $ch->name, $channels),
            ]);
            
            return $channels;
        } catch (Exception $e) {
            Log::error('❌ Failed to get broadcast channels: ' . $e->getMessage(), [
                'workspace_id' => $this->workspaceId,
                'contact_id' => $this->contactId,
            ]);
            return [];
        }
    }

    /**
     * The event's broadcast name.
     * Following riset pattern: dot-prefixed event names for private channels
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'message.received';
    }

    /**
     * Get the data to broadcast.
     * Following riset pattern: fully structured message data
     *
     * @return array
     */
    public function broadcastWith()
    {
        try {
            $structuredData = [
                'message' => [
                    'id' => $this->chat['id'] ?? null,
                    'wam_id' => $this->chat['wam_id'] ?? null,
                    'contact_id' => $this->chat['contact_id'] ?? null,
                    
                    // Contact information - support both 'name' and 'first_name', 'avatar' and 'profile_picture_url'
                    'contact' => [
                        'id' => $this->chat['contact']['id'] ?? null,
                        'name' => $this->chat['contact']['name'] ?? $this->chat['contact']['first_name'] ?? null,
                        'phone' => $this->chat['contact']['phone'] ?? null,
                        'avatar' => $this->chat['contact']['avatar'] ?? $this->chat['contact']['profile_picture_url'] ?? null,
                        'unread_messages' => $this->chat['contact']['unread_messages'] ?? 0,
                    ],
                    
                    // Message details - support both 'message' and 'body' for content
                    'type' => $this->chat['type'] ?? 'inbound',
                    'message_type' => $this->chat['message_type'] ?? 'text',
                    'message_status' => $this->chat['message_status'] ?? 'sent',
                    'message' => $this->chat['message'] ?? $this->chat['body'] ?? null,
                    'body' => $this->chat['body'] ?? $this->chat['message'] ?? null,
                    'from_me' => $this->chat['from_me'] ?? ($this->chat['type'] === 'outbound'),
                    'device_source' => $this->chat['device_source'] ?? null,  // 🆕 Track device origin
                    
                    // Media information
                    'media_id' => $this->chat['media_id'] ?? null,
                    'media' => $this->chat['media'] ?? null,
                    
                    // User information (for outbound messages)
                    'user_id' => $this->chat['user_id'] ?? null,
                    'user' => $this->chat['user'] ?? null,
                    
                    // Timestamps
                    'created_at' => $this->chat['created_at'] ?? null,
                    'sent_at' => $this->chat['sent_at'] ?? null,
                    'delivered_at' => $this->chat['delivered_at'] ?? null,
                    'read_at' => $this->chat['read_at'] ?? null,
                    'is_read' => $this->chat['is_read'] ?? false,
                    
                    // Metadata
                    'metadata' => $this->chat['metadata'] ?? null,
                ],
                
                // Workspace context
                'workspace_id' => $this->workspaceId,
                
                // Timestamp of broadcast
                'broadcast_at' => now()->toISOString(),
            ];
            
            Log::info('✅ Broadcast data structured', [
                'message_id' => $structuredData['message']['id'],
                'contact_id' => $structuredData['message']['contact_id'],
                'contact_name' => $structuredData['message']['contact']['name'] ?? 'Unknown',
                'message_type' => $structuredData['message']['message_type'],
            ]);
            
            return $structuredData;
        } catch (Exception $e) {
            Log::error('❌ Failed to structure broadcast data: ' . $e->getMessage(), [
                'chat' => $this->chat,
            ]);
            
            // Fallback to simple structure
            return [
                'message' => $this->chat,
                'workspace_id' => $this->workspaceId,
            ];
        }
    }
}
