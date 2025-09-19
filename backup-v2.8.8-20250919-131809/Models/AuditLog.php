<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';
    
    // Use request_id as the unique identifier instead of auto-increment id
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'request_id',
        'event_type',
        'endpoint',
        'method',
        'url',
        'ip_address',
        'user_agent',
        'user_id',
        'organization_id',
        'session_id',
        'request_data',
        'status_code',
        'response_size',
        'execution_time',
        'memory_usage',
        'success',
        'event_result',
    ];

    protected $casts = [
        'request_data' => 'json',
        'success' => 'boolean',
        'execution_time' => 'float',
        'response_size' => 'integer',
        'memory_usage' => 'integer',
        'status_code' => 'integer',
    ];

    /**
     * Generate unique ID for audit log if not provided
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = $model->request_id ?: 'audit_' . uniqid();
            }
            
            // Auto-generate request_id if not provided
            if (empty($model->request_id)) {
                $model->request_id = 'req_' . uniqid() . '_' . time();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
    
    public function securityIncidents()
    {
        return $this->hasMany(SecurityIncident::class, 'audit_id', 'id');
    }
}
