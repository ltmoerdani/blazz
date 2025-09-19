<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationApiKey extends Model
{
    use HasFactory;
    use HasUuid;

    protected $guarded = [];
    public $timestamps = true;
    
    protected $fillable = [
        'organization_id',
        'api_key',
        'name',
        'permissions',
        'is_active',
        'expires_at',
        'last_used_at',
    ];

    protected $casts = [
        'permissions' => 'json',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /**
     * Get the organization that owns this API key
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Check if the API key is currently active and not expired
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        
        return true;
    }

    /**
     * Update the last used timestamp
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
