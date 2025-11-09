<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WhatsAppGroup extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_groups';

    protected $fillable = [
        'uuid',
        'workspace_id',
        'whatsapp_session_id',
        'group_jid',
        'name',
        'description',
        'owner_phone',
        'participants',
        'invite_code',
        'settings',
        'group_created_at',
    ];

    protected $casts = [
        'participants' => 'array', // JSON cast untuk participants
        'settings' => 'array', // JSON cast untuk settings
        'group_created_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'participants_count',
        'admin_count',
    ];

    /**
     * Boot function untuk auto-generate UUID
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Relationship: Workspace
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function workspace()
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    /**
     * Relationship: WhatsApp Session
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function session()
    {
        return $this->belongsTo(WhatsAppSession::class, 'whatsapp_session_id');
    }

    /**
     * Relationship: Chats (messages in this group)
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function chats()
    {
        return $this->hasMany(Chat::class, 'group_id');
    }

    /**
     * Accessor: Get participant count
     *
     * @return int
     */
    public function getParticipantsCountAttribute()
    {
        if (empty($this->participants) || !is_array($this->participants)) {
            return 0;
        }

        return count($this->participants);
    }

    /**
     * Accessor: Get admin count
     *
     * @return int
     */
    public function getAdminCountAttribute()
    {
        if (empty($this->participants) || !is_array($this->participants)) {
            return 0;
        }

        return collect($this->participants)
            ->filter(fn($participant) => $participant['isAdmin'] ?? false)
            ->count();
    }

    /**
     * Scope: Filter by workspace
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $workspaceId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * Scope: Filter by session
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $sessionId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForSession($query, $sessionId)
    {
        return $query->where('whatsapp_session_id', $sessionId);
    }

    /**
     * Scope: Active groups (with recent activity)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $days - Days to consider as active (default 30)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query, $days = 30)
    {
        return $query->where('updated_at', '>=', now()->subDays($days));
    }

    /**
     * Helper: Get participant by phone
     *
     * @param string $phone
     * @return array|null
     */
    public function getParticipant($phone)
    {
        if (empty($this->participants) || !is_array($this->participants)) {
            return null;
        }

        return collect($this->participants)
            ->firstWhere('phone', $phone);
    }

    /**
     * Helper: Check if user is admin
     *
     * @param string $phone
     * @return bool
     */
    public function isAdmin($phone)
    {
        $participant = $this->getParticipant($phone);

        return $participant && ($participant['isAdmin'] ?? false);
    }

    /**
     * Helper: Get formatted participant list
     *
     * @return string
     */
    public function getFormattedParticipantList()
    {
        if (empty($this->participants) || !is_array($this->participants)) {
            return 'No participants';
        }

        return collect($this->participants)
            ->map(function ($participant) {
                $name = $participant['name'] ?? 'Unknown';
                $role = ($participant['isAdmin'] ?? false) ? ' (Admin)' : '';
                return $name . $role;
            })
            ->join(', ');
    }

    /**
     * Helper: Update participant list
     *
     * @param array $newParticipants - Array of participants from WhatsApp Web.js
     * @return bool
     */
    public function updateParticipants(array $newParticipants)
    {
        $this->participants = $newParticipants;

        return $this->save();
    }
}
