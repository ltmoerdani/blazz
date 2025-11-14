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
        'whatsapp_account_id',
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
     * Relationship: WhatsApp Account
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
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
     * Scope: Filter by account
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $accountId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForAccount($query, $accountId)
    {
        return $query->where('whatsapp_account_id', $accountId);
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

    // Business Methods
    /**
     * Add participant to group
     */
    public function addParticipant(array $participant): self
    {
        $participants = $this->participants ?? [];
        $participants[] = $participant;

        $this->participants = $participants;
        $this->save();

        return $this;
    }

    /**
     * Remove participant from group
     */
    public function removeParticipant(string $phone): self
    {
        if (empty($this->participants)) {
            return $this;
        }

        $participants = collect($this->participants)
            ->filter(fn($p) => $p['phone'] !== $phone)
            ->values()
            ->all();

        $this->participants = $participants;
        $this->save();

        return $this;
    }

    /**
     * Update participant role
     */
    public function updateParticipantRole(string $phone, bool $isAdmin): self
    {
        $participants = collect($this->participants ?? [])
            ->map(function ($participant) use ($phone, $isAdmin) {
                if ($participant['phone'] === $phone) {
                    $participant['isAdmin'] = $isAdmin;
                }
                return $participant;
            })
            ->all();

        $this->participants = $participants;
        $this->save();

        return $this;
    }

    /**
     * Sync group with WhatsApp data
     */
    public function syncWithWhatsApp(array $whatsappData): self
    {
        $this->update([
            'name' => $whatsappData['name'] ?? $this->name,
            'description' => $whatsappData['desc'] ?? $this->description,
            'participants' => $whatsappData['participants'] ?? $this->participants,
            'invite_code' => $whatsappData['inviteCode'] ?? $this->invite_code,
            'group_created_at' => isset($whatsappData['creationTime'])
                ? now()->setTimestamp($whatsappData['creationTime'])
                : $this->group_created_at,
        ]);

        return $this;
    }

    // Standardized Workspace Scopes
    /**
     * Scope query to only include groups in specific workspace
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
     * Get groups for specific workspace with optional filters
     */
    public static function getForWorkspace(int $workspaceId, array $filters = [])
    {
        $query = static::inWorkspace($workspaceId);

        if (!empty($filters['whatsapp_account_id'])) {
            $query->where('whatsapp_account_id', $filters['whatsapp_account_id']);
        }

        if (!empty($filters['active'])) {
            $query->active($filters['active_days'] ?? 30);
        }

        if (!empty($filters['has_participant'])) {
            $phone = $filters['has_participant'];
            $query->whereJsonContains('participants', [['phone' => $phone]]);
        }

        if (!empty($filters['min_participants'])) {
            $query->whereRaw("JSON_LENGTH(participants) >= ?", [$filters['min_participants']]);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereJsonContains('participants', [['name' => $search]]);
            });
        }

        return $query;
    }

    /**
     * Get workspace group statistics
     */
    public static function getWorkspaceStats(int $workspaceId): array
    {
        $query = static::inWorkspace($workspaceId);

        return [
            'total_groups' => $query->count(),
            'active_groups' => $query->active(30)->count(),
            'total_participants' => $query->get()->sum('participants_count'),
            'average_participants' => $query->avg('participants_count'),
            'groups_with_admins' => $query->get()->filter(fn($group) => $group->admin_count > 0)->count(),
        ];
    }
}
