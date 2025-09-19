<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityIncident extends Model
{
    use HasFactory;

    protected $table = 'security_incidents';

    protected $fillable = [
        'audit_id',
        'organization_id',
        'incident_type',
        'severity',
        'ip_address',
        'user_id',
        'endpoint',
        'details',
        'resolved',
        'resolution_notes',
        'resolved_at',
    ];

    protected $casts = [
        'details' => 'json',
        'resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function auditLog()
    {
        return $this->belongsTo(AuditLog::class, 'audit_id', 'id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for unresolved incidents
     */
    public function scopeUnresolved($query)
    {
        return $query->where('resolved', false);
    }

    /**
     * Scope for high severity incidents
     */
    public function scopeHighSeverity($query)
    {
        return $query->whereIn('severity', ['high', 'critical']);
    }

    /**
     * Mark incident as resolved
     */
    public function markResolved($notes = null)
    {
        $this->update([
            'resolved' => true,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);
    }
}
