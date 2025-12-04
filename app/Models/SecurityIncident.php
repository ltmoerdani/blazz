<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityIncident extends Model
{
    use HasFactory;

    protected $table = 'security_incidents';

    protected $guarded = [];

    protected $casts = [
        'details' => 'json',
        'resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function auditLog()
    {
        return $this->belongsTo(AuditLog::class, 'audit_id', 'id');
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for workspace-specific incidents (optional workspace_id after migration)
     */
    public function scopeInWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * Scope for system-wide incidents (no workspace)
     */
    public function scopeSystemWide($query)
    {
        return $query->whereNull('workspace_id');
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
