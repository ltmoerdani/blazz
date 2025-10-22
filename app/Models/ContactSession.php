<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactSession extends Model
{
    use HasFactory;

    protected $table = 'contact_sessions';

    protected $fillable = [
        'contact_id',
        'whatsapp_session_id',
        'first_interaction_at',
        'last_interaction_at',
        'total_messages',
    ];

    protected $casts = [
        'first_interaction_at' => 'datetime',
        'last_interaction_at' => 'datetime',
    ];

    /**
     * Get the contact associated with this session
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the WhatsApp session associated with this contact session
     */
    public function whatsappSession(): BelongsTo
    {
        return $this->belongsTo(WhatsAppSession::class, 'whatsapp_session_id');
    }

    /**
     * Update interaction timestamp and message count
     */
    public function updateInteraction(): void
    {
        $now = now();

        // Update last interaction if this is the first record
        if (!$this->last_interaction_at) {
            $this->update([
                'first_interaction_at' => $now,
                'last_interaction_at' => $now,
                'total_messages' => 1,
            ]);
        } else {
            $this->update([
                'last_interaction_at' => $now,
                'total_messages' => $this->total_messages + 1,
            ]);
        }
    }

    /**
     * Scope to get contact sessions for a specific WhatsApp session
     */
    public function scopeForWhatsAppSession($query, $sessionId)
    {
        return $query->where('whatsapp_session_id', $sessionId);
    }

    /**
     * Scope to get contact sessions for a specific contact
     */
    public function scopeForContact($query, $contactId)
    {
        return $query->where('contact_id', $contactId);
    }

    /**
     * Get the most recent contact sessions
     */
    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('last_interaction_at', 'desc')->limit($limit);
    }
}
