<?php

namespace App\Models;

use App\Helpers\DateTimeHelper;
use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Chat extends Model {
    use HasFactory;
    use HasUuid;

    protected $guarded = [];
    public $timestamps = true;
    
    protected $appends = ['body', 'contact_name'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'is_read' => 'boolean',
        'retry_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($chat) {
            $contact = $chat->contact;
            if ($contact) {
                // Update contact's latest chat timestamps
                $contact->latest_chat_created_at = $chat->created_at;
                $contact->last_message_at = $chat->created_at;
                $contact->last_activity = $chat->created_at;
                
                // Increment unread counter for incoming messages
                if ($chat->type === 'inbound' && !$chat->is_read) {
                    $contact->increment('unread_messages');
                }
                
                $contact->save();

                Log::info('Chat created - Contact updated', [
                    'chat_id' => $chat->id,
                    'contact_id' => $contact->id,
                    'contact_name' => $contact->first_name,
                    'latest_chat_created_at' => $chat->created_at,
                    'unread_messages' => $contact->unread_messages,
                ]);
            }
        });

        // When message is marked as read, decrement unread counter
        static::updating(function ($chat) {
            if ($chat->isDirty('is_read') && $chat->is_read && $chat->type === 'inbound') {
                $contact = $chat->contact;
                if ($contact && $contact->unread_messages > 0) {
                    $contact->decrement('unread_messages');
                    $contact->save();
                }
            }
        });
    }
    
    public function getCreatedAtAttribute($value)
    {
        return DateTimeHelper::convertToWorkspaceTimezone($value)->toDateTimeString();
    }

    /**
     * Get message body from metadata JSON
     * Supports both Meta API format and legacy WebJS format
     */
    public function getBodyAttribute()
    {
        if ($this->metadata) {
            $data = is_string($this->metadata) ? json_decode($this->metadata, true) : $this->metadata;

            // Try Meta API format first (text.body, image.caption, video.caption, etc.)
            if (isset($data['text']['body'])) {
                return $data['text']['body'];
            } elseif (isset($data['image']['caption'])) {
                return $data['image']['caption'];
            } elseif (isset($data['video']['caption'])) {
                return $data['video']['caption'];
            } elseif (isset($data['document']['caption'])) {
                return $data['document']['caption'];
            }

            // Fallback to legacy format (direct body field)
            return $data['body'] ?? null;
        }
        return null;
    }

    /**
     * Get contact name for UI display
     */
    public function getContactNameAttribute()
    {
        return $this->contact ? $this->contact->first_name : null;
    }
    
    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id', 'id');
    }

    public function media()
    {
        return $this->belongsTo(ChatMedia::class, 'media_id', 'id');
    }

    public function logs()
    {
        return $this->hasMany(ChatStatusLog::class, 'chat_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function whatsappAccount()
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id', 'id');
    }

    public function group()
    {
        return $this->belongsTo(WhatsAppGroup::class, 'group_id', 'id');
    }

    // Business Methods
    /**
     * Mark chat as failed
     */
    public function markAsFailed(?string $error = null): self
    {
        $this->message_status = 'failed';
        $this->retry_count = ($this->retry_count ?? 0) + 1;

        if ($error) {
            $metadata = $this->metadata ? json_decode($this->metadata, true) : [];
            $metadata['error'] = $error;
            $this->metadata = json_encode($metadata);
        }

        $this->save();
        return $this;
    }

    /**
     * Update message status with proper timestamps
     */
    public function updateStatus(string $status, ?int $ackLevel = null): self
    {
        $this->message_status = $status;

        if ($ackLevel) {
            $this->ack_level = $ackLevel;
        }

        // Update specific timestamps based on status
        if ($status === 'delivered') {
            $this->delivered_at = now();
        } elseif ($status === 'read') {
            $this->read_at = now();
        }

        $this->save();
        return $this;
    }

    // Workspace Scopes
    /**
     * Scope query to only include chats in specific workspace
     */
    public function scopeInWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * Scope query to include workspace relationship
     */
    public function scopeWithWorkspace($query)
    {
        return $query->with('workspace');
    }

    /**
     * Get chats for specific workspace with optional filters
     */
    public static function getForWorkspace(int $workspaceId, array $filters = [])
    {
        $query = static::inWorkspace($workspaceId);

        if (!empty($filters['contact_id'])) {
            $query->where('contact_id', $filters['contact_id']);
        }

        if (!empty($filters['message_status'])) {
            $query->where('message_status', $filters['message_status']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * Get workspace chat statistics
     */
    public static function getWorkspaceStats(int $workspaceId): array
    {
        return [
            'total_messages' => static::inWorkspace($workspaceId)->count(),
            'sent_messages' => static::inWorkspace($workspaceId)->where('type', 'outbound')->count(),
            'received_messages' => static::inWorkspace($workspaceId)->where('type', 'inbound')->count(),
            'failed_messages' => static::inWorkspace($workspaceId)->where('message_status', 'failed')->count(),
            'pending_messages' => static::inWorkspace($workspaceId)->where('message_status', 'pending')->count(),
            'delivered_messages' => static::inWorkspace($workspaceId)->where('message_status', 'delivered')->count(),
            'read_messages' => static::inWorkspace($workspaceId)->where('message_status', 'read')->count(),
        ];
    }
}
