<?php

namespace App\Models;

use App\Helpers\DateTimeHelper;
use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model {
    use HasFactory;
    use HasUuid;

    protected $guarded = [];
    public $timestamps = false;

    protected static function boot()
    {
        parent::boot();

        static::created(function ($chat) {
            $contact = $chat->contact;
            if ($contact) {
                $contact->latest_chat_created_at = $chat->created_at;
                $contact->save();
            }
        });

    }
    
    public function getCreatedAtAttribute($value)
    {
        return DateTimeHelper::convertToWorkspaceTimezone($value)->toDateTimeString();
    }

    /**
     * Get message body from metadata JSON
     */
    public function getBodyAttribute()
    {
        if ($this->metadata) {
            $data = is_string($this->metadata) ? json_decode($this->metadata, true) : $this->metadata;
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

    public function whatsappSession()
    {
        return $this->belongsTo(WhatsAppSession::class, 'whatsapp_session_id', 'id');
    }

    public function group()
    {
        return $this->belongsTo(WhatsAppGroup::class, 'group_id', 'id');
    }
}
