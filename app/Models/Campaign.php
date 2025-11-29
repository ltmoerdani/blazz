<?php

namespace App\Models;

use App\Helpers\DateTimeHelper;
use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model {
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = [];
    public $timestamps = false;

    protected $casts = [
        'buttons_data' => 'array',
        'metadata' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'paused_at' => 'datetime',
        'auto_resume_at' => 'datetime',
        'campaign_type' => 'string',
        'preferred_provider' => 'string',
        'speed_tier' => 'integer',
    ];

    protected $attributes = [
        'campaign_type' => 'template',
        'preferred_provider' => 'webjs',
        'messages_sent' => 0,
        'messages_delivered' => 0,
        'messages_read' => 0,
        'messages_failed' => 0,
        'speed_tier' => 2, // Default: Safe tier
    ];

    public function getCreatedAtAttribute($value)
    {
        return DateTimeHelper::convertToWorkspaceTimezone($value)->toDateTimeString();
    }

    public function getDeletedAtAttribute($value)
    {
        return DateTimeHelper::convertToWorkspaceTimezone($value)->toDateTimeString();
    }

    public function getScheduledAtAttribute($value)
    {
        return DateTimeHelper::convertToWorkspaceTimezone($value)->toDateTimeString();
    }

    public function workspace(){
        return $this->belongsTo(Workspace::class, 'workspace_id', 'id');
    }

    public function template(){
        return $this->belongsTo(Template::class, 'template_id', 'id');
    }

    public function contactGroup(){
        return $this->belongsTo(ContactGroup::class, 'contact_group_id', 'id');
    }

    public function campaignLogs(){
        return $this->hasMany(CampaignLog::class, 'campaign_id', 'id');
    }

    public function whatsappAccount(){
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id', 'id');
    }

    public function creator(){
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function contactsCount(){
        return $this->campaignLogs->count();
    }

    /**
     * Get speed tier info
     * 
     * @return array|null
     */
    public function getSpeedTierInfoAttribute(): ?array
    {
        $speedService = app(\App\Services\Campaign\CampaignSpeedService::class);
        return $speedService->getTierInfo($this->speed_tier ?? 2);
    }

    public function contactGroupCount(){
        return $this->contactGroup ? $this->contactGroup->contacts->count() : 0;
    }

    public function sentCount(){
        return $this->campaignLogs()
            ->where('status', 'success')
            ->whereHas('chat', function ($query) {
                $query->whereIn('message_status', ['accepted', 'sent', 'delivered', 'read']);
            })
            ->count();
    }

    public function deliveryCount(){
        return $this->campaignLogs()
            ->where('status', 'success')
            ->whereHas('chat', function ($query) {
                $query->whereIn('message_status', ['delivered', 'read']);
            })
            ->count();
    }

    public function failedCount(){
        $failedToSendCount = $this->campaignLogs()->where('status', 'failed')->count();

        $chatFailedCount = $this->campaignLogs()
            ->where('status', 'success')
            ->whereHas('chat', function ($query) {
                $query->where('message_status', 'failed');
            })
            ->count();

        return $failedToSendCount + $chatFailedCount;
    }

    public function readCount(){
        return $this->campaignLogs()
            ->where('status', 'success')
            ->whereHas('chat', function ($query) {
                $query->where('message_status', 'read');
            })
            ->count();
    }

    public function getCounts(){
        return $this->campaignLogs()
            ->selectRaw('
                COUNT(*) as total_message_count,
                SUM(CASE WHEN campaign_logs.status = "success" AND chat.message_status IN ("accepted", "sent", "delivered", "read") THEN 1 ELSE 0 END) as total_sent_count,
                SUM(CASE WHEN campaign_logs.status = "success" AND chat.message_status IN ("delivered", "read") THEN 1 ELSE 0 END) as total_delivered_count,
                SUM(CASE WHEN campaign_logs.status = "failed" THEN 1 ELSE 0 END) +
                    SUM(CASE WHEN campaign_logs.status = "success" AND chat.message_status = "failed" THEN 1 ELSE 0 END) as total_failed_count,
                SUM(CASE WHEN campaign_logs.status = "success" AND chat.message_status = "read" THEN 1 ELSE 0 END) as total_read_count
            ')
            ->leftJoin('chats as chat', 'chat.id', '=', 'campaign_logs.chat_id')
            ->where('campaign_logs.campaign_id', $this->id)
            ->first();
    }

    /**
     * Hybrid Campaign Methods
     */

    /**
     * Check if campaign is template-based
     */
    public function isTemplateBased(): bool
    {
        return $this->campaign_type === 'template';
    }

    /**
     * Check if campaign is direct message
     */
    public function isDirectMessage(): bool
    {
        return $this->campaign_type === 'direct';
    }

    /**
     * Get resolved message content based on campaign type
     */
    public function getResolvedMessageContent(): array
    {
        if ($this->isTemplateBased() && $this->template) {
            return [
                'header_type' => $this->template->header_type,
                'header_text' => $this->template->header_text,
                'header_media' => $this->template->header_media,
                'body_text' => $this->template->body_text,
                'footer_text' => $this->template->footer_text,
                'buttons_data' => is_array($this->template->buttons_data) 
                    ? $this->template->buttons_data 
                    : (is_string($this->template->buttons_data) 
                        ? json_decode($this->template->buttons_data, true) ?? [] 
                        : [])
            ];
        }

        // Ensure buttons_data is always an array
        $buttonsData = $this->buttons_data;
        if (is_string($buttonsData)) {
            $buttonsData = json_decode($buttonsData, true) ?? [];
        } elseif (!is_array($buttonsData)) {
            $buttonsData = [];
        }

        return [
            'header_type' => $this->header_type,
            'header_text' => $this->header_text,
            'header_media' => $this->header_media,
            'body_text' => $this->body_text,
            'footer_text' => $this->footer_text,
            'buttons_data' => $buttonsData
        ];
    }

    /**
     * Get campaign statistics using performance counters (optimized)
     */
    public function getStatistics(): array
    {
        $totalContacts = $this->contactGroupCount();
        $sentCount = max($this->messages_sent, $this->sentCount());
        $deliveredCount = max($this->messages_delivered, $this->deliveryCount());
        $readCount = max($this->messages_read, $this->readCount());
        $failedCount = max($this->messages_failed, $this->failedCount());

        return [
            // For backward compatibility with frontend
            'total_message_count' => $totalContacts,
            'total_sent_count' => $sentCount,
            'total_delivered_count' => $deliveredCount,
            'total_read_count' => $readCount,
            'total_failed_count' => $failedCount,
            // Additional stats
            'total_contacts' => $totalContacts,
            'messages_sent' => $sentCount,
            'messages_delivered' => $deliveredCount,
            'messages_read' => $readCount,
            'messages_failed' => $failedCount,
            'pending_count' => max(0, $totalContacts - $sentCount - $failedCount),
            'delivery_rate' => $sentCount > 0 ? round(($deliveredCount / $sentCount) * 100, 2) : 0,
            'read_rate' => $deliveredCount > 0 ? round(($readCount / $deliveredCount) * 100, 2) : 0,
            'success_rate' => $totalContacts > 0 ? round(($sentCount / $totalContacts) * 100, 2) : 0,
        ];
    }

    /**
     * Update performance counters (optimized for large campaigns)
     */
    public function updatePerformanceCounters(): void
    {
        $counts = $this->getCounts();

        $this->update([
            'messages_sent' => $counts->total_sent_count ?? 0,
            'messages_delivered' => $counts->total_delivered_count ?? 0,
            'messages_read' => $counts->total_read_count ?? 0,
            'messages_failed' => $counts->total_failed_count ?? 0,
        ]);
    }

    /**
     * Mark campaign as started
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'ongoing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark campaign as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Update final performance counters
        $this->updatePerformanceCounters();
    }

    /**
     * Mark campaign as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Check if campaign can be processed
     */
    public function canBeProcessed(): bool
    {
        return in_array($this->status, ['pending', 'scheduled']) &&
               (!$this->scheduled_at || $this->scheduled_at->isPast());
    }

    /**
     * Check if campaign is active (processing or completed)
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['ongoing', 'completed']);
    }

    /**
     * Scope to get campaigns by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('campaign_type', $type);
    }

    /**
     * Scope to get campaigns by provider
     */
    public function scopeByProvider($query, $provider)
    {
        return $query->where('preferred_provider', $provider);
    }

    /**
     * Scope to get active campaigns
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'scheduled', 'ongoing']);
    }

    /**
     * Scope to get completed campaigns
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope query to specific workspace
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $workspaceId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * Mobile Conflict Detection Constants
     */
    const STATUS_PAUSED_MOBILE = 'paused_mobile';

    const PAUSE_REASON_MOBILE_ACTIVITY = 'mobile_activity';
    const PAUSE_REASON_MANUAL = 'manual';

    /**
     * Check if campaign is paused due to mobile activity
     */
    public function isPausedForMobile(): bool
    {
        return $this->status === self::STATUS_PAUSED_MOBILE;
    }

    /**
     * Scope for campaigns paused due to mobile activity
     */
    public function scopePausedForMobile($query)
    {
        return $query->where('status', self::STATUS_PAUSED_MOBILE);
    }

    /**
     * Scope for ongoing campaigns
     */
    public function scopeOngoing($query)
    {
        return $query->where('status', 'ongoing');
    }

    /**
     * Pause campaign for mobile activity
     */
    public function pauseForMobileActivity(string $sessionId): void
    {
        $this->status = self::STATUS_PAUSED_MOBILE;
        $this->paused_at = now();
        $this->pause_reason = self::PAUSE_REASON_MOBILE_ACTIVITY;
        $this->paused_by_session = $sessionId;
        $this->pause_count = ($this->pause_count ?? 0) + 1;
        $this->save();
    }

    /**
     * Resume campaign from mobile activity pause
     */
    public function resumeFromPause(): void
    {
        $this->status = 'ongoing';
        $this->auto_resume_at = now();
        $this->save();
    }

    /**
     * Get session ID from WhatsApp account relationship
     */
    public function getSessionIdAttribute(): ?string
    {
        return $this->whatsappAccount?->session_id;
    }
}
