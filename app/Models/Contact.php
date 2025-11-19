<?php

namespace App\Models;

use App\Helpers\DateTimeHelper;
use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Propaganistas\LaravelPhone\PhoneNumber;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model {
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = [];
    protected $appends = ['formatted_phone_number'];
    protected $dates = ['deleted_at'];
    public $timestamps = true;

    protected static function boot()
    {
        parent::boot();

        // Auto-populate full_name when creating or updating
        static::saving(function ($contact) {
            if ($contact->isDirty(['first_name', 'last_name'])) {
                $firstName = trim($contact->first_name ?? '');
                $lastName = trim($contact->last_name ?? '');
                $contact->full_name = trim("$firstName $lastName");
            }
        });
    }

    public function getCreatedAtAttribute($value)
    {
        return DateTimeHelper::convertToWorkspaceTimezone($value)->toDateTimeString();
    }

    public function getUpdatedAtAttribute($value)
    {
        return DateTimeHelper::convertToWorkspaceTimezone($value)->toDateTimeString();
    }

    public function getAllContacts($workspaceId, $searchTerm)
    {
        return $this->with('contactGroups')
            ->where('workspace_id', $workspaceId)
            ->where('deleted_at', null)
            ->where(function ($query) use ($searchTerm) {
                $query->where('contacts.first_name', 'like', '%' . $searchTerm . '%')
                ->orWhere('contacts.last_name', 'like', '%' . $searchTerm . '%')
                
                // Split the search term into parts and check for matches in both columns
                ->orWhere(function ($subQuery) use ($searchTerm) {
                    $searchParts = explode(' ', $searchTerm);
                    if (count($searchParts) > 1) {
                        $subQuery->where('contacts.first_name', 'like', '%' . $searchParts[0] . '%')
                                ->where('contacts.last_name', 'like', '%' . $searchParts[1] . '%');
                    }
                })
                
                // Match phone or email
                ->orWhere('contacts.phone', 'like', '%' . $searchTerm . '%')
                ->orWhere('contacts.email', 'like', '%' . $searchTerm . '%');
            })
            ->orderByDesc('is_favorite')
            ->latest()
            ->orderBy('id')
            ->paginate(10);
    }

    public function getAllContactGroups($workspaceId)
    {
        return ContactGroup::where('workspace_id', $workspaceId)->whereNull('deleted_at')->get();
    }

    public function countContacts($workspaceId)
    {
        return $this->where('workspace_id', $workspaceId)->whereNull('deleted_at')->count();
    }

    public function contactGroups()
    {
        return $this->belongsToMany(ContactGroup::class, 'contact_contact_group', 'contact_id', 'contact_group_id')
            ->using(ContactContactGroup::class)
            ->withTimestamps();
    }

    public function notes()
    {
        return $this->hasMany(ChatNote::class, 'contact_id')->orderBy('created_at', 'desc');
    }

    public function chats()
    {
        return $this->hasMany(Chat::class, 'contact_id')->orderBy('created_at', 'asc');
    }

    public function lastChat()
    {
        return $this->hasOne(Chat::class, 'contact_id')->with('media')->latest();
    }

    public function lastInboundChat()
    {
        return $this->hasOne(Chat::class, 'contact_id')
                    ->where('type', 'inbound')
                    ->with('media')
                    ->latest();
    }

    public function chatLogs()
    {
        return $this->hasMany(ChatLog::class);
    }

    public function contactsWithChats($workspaceId, $searchTerm = null, $ticketingActive = false, $ticketState = null, $sortDirection = 'asc', $role = 'owner', $allowAgentsViewAllChats = true, $sessionId = null)
    {
        $query = $this->newQuery()
            ->where('contacts.Workspace_id', $workspaceId)
            ->whereHas('chats', function ($q) use ($workspaceId, $sessionId) {
                $q->where('chats.workspace_id', $workspaceId)
                  ->whereNull('chats.deleted_at');

                // Filter by session if specified
                if ($sessionId) {
                    $q->where('chats.whatsapp_account_id', $sessionId);
                }
            })
            ->with(['lastChat', 'lastInboundChat'])
            ->whereNull('contacts.deleted_at')
            ->select('contacts.*')
            ->selectSub(function ($subquery) use ($workspaceId, $sessionId) {
                $subquery->from('chats')
                    ->selectRaw('MAX(created_at)')
                    ->whereColumn('chats.contact_id', 'contacts.id')
                    ->whereNull('chats.deleted_at')
                    ->where('chats.workspace_id', $workspaceId);

                // Filter by session if specified
                if ($sessionId) {
                    $subquery->where('chats.whatsapp_account_id', $sessionId);
                }
            }, 'last_chat_created_at')
            ->orderBy('last_chat_created_at', $sortDirection === 'desc' ? 'desc' : 'asc');

        // Apply ticketing conditions if active
        if ($ticketingActive) {
            $query->leftJoin('chat_tickets', 'contacts.id', '=', 'chat_tickets.contact_id');

            if ($ticketState === 'unassigned') {
                $query->whereNull('chat_tickets.assigned_to');
            } elseif ($ticketState !== null && $ticketState !== 'all') {
                $query->where('chat_tickets.status', $ticketState);
            }

            if ($role === 'agent' && !$allowAgentsViewAllChats) {
                $query->where('chat_tickets.assigned_to', Auth::user()->id);
            }
        }

        // Search filter
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('contacts.first_name', 'like', "%$searchTerm%")
                    ->orWhere('contacts.last_name', 'like', "%$searchTerm%")
                    ->orWhereRaw("CONCAT(contacts.first_name, ' ', contacts.last_name) LIKE ?", ["%$searchTerm%"])
                    ->orWhere('contacts.phone', 'like', "%$searchTerm%")
                    ->orWhere('contacts.email', 'like', "%$searchTerm%");
            });
        }

        // Order by the latest chat's created_at
        $query->orderBy('last_chat_created_at', $sortDirection); // Order contacts by last chat created_at

        // Use simple pagination for infinite scroll (no total count needed)
        return $query->simplePaginate(15);

    }

    public function contactsWithChatsCount($workspaceId, $searchTerm = null, $ticketingActive = false, $ticketState = null, $sortDirection = 'asc', $role = 'owner', $allowAgentsViewAllChats = true, $sessionId = null)
    {
        $query = $this->newQuery()
            ->where('contacts.Workspace_id', $workspaceId)
            ->whereHas('chats', function ($q) use ($workspaceId, $sessionId) {
                $q->where('chats.workspace_id', $workspaceId)
                  ->whereNull('chats.deleted_at');

                // Filter by session if specified
                if ($sessionId) {
                    $q->where('chats.whatsapp_account_id', $sessionId);
                }
            })
            ->whereNull('contacts.deleted_at')
            ->with(['lastChat', 'lastInboundChat'])
            ->select('contacts.*');

        if($ticketingActive){
            // Conditional join with chat_tickets table and comparison with ticketState
            if ($ticketState === 'unassigned') {
                $query->leftJoin('chat_tickets', 'contacts.id', '=', 'chat_tickets.contact_id')
                    ->whereNull('chat_tickets.assigned_to');
            } elseif ($ticketState !== null && $ticketState !== 'all') {
                $query->leftJoin('chat_tickets', 'contacts.id', '=', 'chat_tickets.contact_id')
                    ->where('chat_tickets.status', $ticketState);
            } elseif($ticketState === 'all'){
                $query->leftJoin('chat_tickets', 'contacts.id', '=', 'chat_tickets.contact_id');
            }

            if($role == 'agent' && !$allowAgentsViewAllChats){
                $query->where(function($q) {
                    $q->where('chat_tickets.assigned_to', Auth::user()->id);
                });
            }
        }

        if($role == 'agent' && !$allowAgentsViewAllChats){
            $query->where(function($q) {
                $q->whereNull('chat_tickets.assigned_to')
                  ->orWhere('chat_tickets.assigned_to', Auth::user()->id);
            });
        }

        // Include the search term in the query if provided
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('contacts.first_name', 'like', '%' . $searchTerm . '%')
                ->orWhere('contacts.last_name', 'like', '%' . $searchTerm . '%')
                
                // Split the search term into parts and check for matches in both columns
                ->orWhere(function ($subQuery) use ($searchTerm) {
                    $searchParts = explode(' ', $searchTerm);
                    if (count($searchParts) > 1) {
                        $subQuery->where('contacts.first_name', 'like', '%' . $searchParts[0] . '%')
                                ->where('contacts.last_name', 'like', '%' . $searchParts[1] . '%');
                    }
                })
                
                // Match phone or email
                ->orWhere('contacts.phone', 'like', '%' . $searchTerm . '%')
                ->orWhere('contacts.email', 'like', '%' . $searchTerm . '%');
            });
        }

        return $query->count();
    }

    public function getFirstNameAttribute()
    {
        $firstName = $this->attributes['first_name'];
        $firstName = $this->decodeUnicodeBytes($firstName);

        return $firstName;
    }

    public function getLastNameAttribute()
    {
        $lastName = $this->attributes['last_name'];
        $lastName = $this->decodeUnicodeBytes($lastName);

        return $lastName;
    }

    public function getFullNameAttribute()
    {
        $firstName = $this->attributes['first_name'];
        $lastName = $this->attributes['last_name'];

        // Convert byte sequences to Unicode characters
        $firstName = $this->decodeUnicodeBytes($firstName);
        $lastName = $this->decodeUnicodeBytes($lastName);

        // Return the full name combining first name and last name
        return $firstName . ' ' . $lastName;

    }

    public function getFormattedPhoneNumberAttribute($value)
    {
        // Use the phone() helper function to format the phone number to international format
        if (!$this->phone) {
            return '';
        }
        return phone($this->phone)->formatInternational();
    }

    protected function decodeUnicodeBytes($value)
    {
        if (!$value) {
            return '';
        }
        return preg_replace_callback('/\\\\x([0-9A-F]{2})/i', function ($matches) {
            return chr(hexdec($matches[1]));
        }, $value);
    }

    // Business Methods
    /**
     * Update contact presence information
     */
    public function updatePresence(array $data): self
    {
        if (isset($data['is_online'])) {
            $this->is_online = $data['is_online'];
        }

        if (isset($data['typing_status'])) {
            $this->typing_status = $data['typing_status'];
        }

        if (isset($data['last_activity'])) {
            $this->last_activity = $data['last_activity'];
        }

        if (isset($data['last_message_at'])) {
            $this->last_message_at = $data['last_message_at'];
        }

        $this->save();
        return $this;
    }

    /**
     * Set contact as online
     */
    public function setOnline(): self
    {
        return $this->updatePresence([
            'is_online' => true,
            'last_activity' => now()
        ]);
    }

    /**
     * Set contact as offline
     */
    public function setOffline(): self
    {
        return $this->updatePresence([
            'is_online' => false,
            'typing_status' => 'idle',
            'last_activity' => now()
        ]);
    }

    /**
     * Set typing status
     */
    public function setTyping(string $status = 'typing'): self
    {
        return $this->updatePresence([
            'typing_status' => $status,
            'last_activity' => now()
        ]);
    }

    /**
     * Update last message timestamp
     */
    public function updateLastMessageTime(): self
    {
        return $this->updatePresence([
            'last_message_at' => now(),
            'last_activity' => now()
        ]);
    }

    // Workspace Scopes
    /**
     * Scope query to only include contacts in specific workspace
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
     * Scope query to get online contacts
     */
    public function scopeOnline($query)
    {
        return $query->where('is_online', true);
    }

    /**
     * Scope query to get typing contacts
     */
    public function scopeTyping($query)
    {
        return $query->where('typing_status', 'typing');
    }

    /**
     * Scope query to get contacts with recent activity
     */
    public function scopeWithRecentActivity($query, int $minutes = 30)
    {
        return $query->where('last_activity', '>=', now()->subMinutes($minutes));
    }

    /**
     * Get contacts for specific workspace with optional filters
     */
    public static function getForWorkspace(int $workspaceId, array $filters = [])
    {
        $query = static::inWorkspace($workspaceId)->whereNull('deleted_at');

        if (!empty($filters['is_online'])) {
            $query->online();
        }

        if (!empty($filters['typing_status'])) {
            if ($filters['typing_status'] === 'typing') {
                $query->typing();
            } else {
                $query->where('typing_status', $filters['typing_status']);
            }
        }

        if (!empty($filters['recent_activity'])) {
            $query->withRecentActivity($filters['recent_activity_minutes'] ?? 30);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Get workspace presence statistics
     */
    public static function getWorkspacePresenceStats(int $workspaceId): array
    {
        $contacts = static::inWorkspace($workspaceId)->whereNull('deleted_at');

        return [
            'total_contacts' => $contacts->count(),
            'online_contacts' => $contacts->online()->count(),
            'typing_contacts' => $contacts->typing()->count(),
            'recent_activity_contacts' => $contacts->withRecentActivity(30)->count(),
        ];
    }
}
