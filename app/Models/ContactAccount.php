<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactAccount extends Model
{
    use HasFactory;

    protected $table = 'contact_accounts';

    protected $fillable = [
        'contact_id',
        'whatsapp_account_id',
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
     * Get the WhatsApp account associated with this contact account
     */
    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
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
     * Scope to get contact accounts for a specific WhatsApp account
     */
    public function scopeForWhatsAppAccount($query, $accountId)
    {
        return $query->where('whatsapp_account_id', $accountId);
    }

    /**
     * Scope to get contact sessions for a specific contact
     */
    public function scopeForContact($query, $contactId)
    {
        return $query->where('contact_id', $contactId);
    }

    /**
     * Get the most recent contact accounts
     */
    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('last_interaction_at', 'desc')->limit($limit);
    }
}
