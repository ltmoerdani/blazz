# 🛠️ Implementation Guide

## Mobile Activity Conflict Detection System

**Version:** 1.1  
**Last Updated:** November 29, 2025

---

## 📋 Prerequisites

Sebelum memulai implementasi, pastikan:

1. ✅ Development environment sudah berjalan (`./start-dev.sh`)
2. ✅ Database migrations up-to-date
3. ✅ Queue worker berjalan
4. ✅ WhatsApp service terhubung

---

## 📦 Implementation Phases

### Phase Overview

| Phase | Description | Estimated Time |
|-------|-------------|----------------|
| Phase 1 | Database & Config | 30 min |
| Phase 2 | Node.js Components | 1 hour |
| Phase 3 | Laravel Components | 2 hours |
| Phase 4 | Integration & Testing | 2 hours |

---

## Phase 1: Database & Configuration

### Step 1.1: Create Migration

```bash
php artisan make:migration add_mobile_conflict_columns_to_campaigns --table=campaigns
```

### Step 1.2: Migration Content

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // Mobile conflict tracking columns
            $table->timestamp('paused_at')->nullable()->after('completed_at');
            $table->string('pause_reason', 100)->nullable()->after('paused_at');
            $table->timestamp('auto_resume_at')->nullable()->after('pause_reason');
            $table->unsignedTinyInteger('pause_count')->default(0)->after('auto_resume_at');
            $table->string('paused_by_session', 255)->nullable()->after('pause_count');
            
            // Indexes
            $table->index(['status', 'paused_at'], 'idx_campaigns_status_paused');
            $table->index(['workspace_id', 'status'], 'idx_campaigns_workspace_status');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex('idx_campaigns_status_paused');
            $table->dropIndex('idx_campaigns_workspace_status');
            
            $table->dropColumn([
                'paused_at',
                'pause_reason',
                'auto_resume_at',
                'pause_count',
                'paused_by_session',
            ]);
        });
    }
};
```

### Step 1.3: Run Migration

```bash
php artisan migrate
```

### Step 1.4: Update config/campaign.php

Tambahkan di akhir file:

```php
/**
 * Mobile conflict detection settings
 */
'mobile_conflict' => [
    'enabled' => env('CAMPAIGN_CONFLICT_ENABLED', true),
    'queue' => env('CAMPAIGN_CONFLICT_QUEUE', 'campaign-conflict'),
    'default_cooldown_seconds' => env('CAMPAIGN_CONFLICT_DEFAULT_COOLDOWN', 30),
    'max_resume_attempts' => env('CAMPAIGN_CONFLICT_MAX_ATTEMPTS', 5),
    
    // Tier-based cooldown (in seconds)
    'tier_cooldown' => [
        1 => 60,  // Tier 1: New account
        2 => 45,  // Tier 2: Warming
        3 => 30,  // Tier 3: Established
        4 => 20,  // Tier 4: Trusted
    ],
    
    'trigger_device_types' => ['android', 'ios'],
],
```

### Step 1.5: Update .env

```env
# Mobile Conflict Detection
CAMPAIGN_CONFLICT_ENABLED=true
CAMPAIGN_CONFLICT_QUEUE=campaign-conflict
CAMPAIGN_CONFLICT_DEFAULT_COOLDOWN=30
CAMPAIGN_CONFLICT_MAX_ATTEMPTS=5
```

### Step 1.6: Update Campaign Model

Add to `app/Models/Campaign.php`:

```php
// Status constants - add new constant
const STATUS_PAUSED_MOBILE = 'paused_mobile';

// Pause reasons
const PAUSE_REASON_MOBILE_ACTIVITY = 'mobile_activity';
const PAUSE_REASON_MANUAL = 'manual';

/**
 * Add to $fillable array
 */
protected $fillable = [
    // ... existing
    'paused_at',
    'pause_reason',
    'auto_resume_at',
    'pause_count',
    'paused_by_session',
];

/**
 * Add to $casts array
 */
protected $casts = [
    // ... existing
    'paused_at' => 'datetime',
    'auto_resume_at' => 'datetime',
];

/**
 * Check if campaign is paused for mobile
 */
public function isPausedForMobile(): bool
{
    return $this->status === self::STATUS_PAUSED_MOBILE;
}

/**
 * Scope for paused campaigns
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
    return $query->where('status', self::STATUS_ONGOING);
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
 * Resume campaign from pause
 */
public function resumeFromPause(): void
{
    $this->status = self::STATUS_ONGOING;
    $this->auto_resume_at = now();
    $this->save();
}
```

---

## Phase 2: Node.js Components

### Step 2.1: Create MobileActivityMonitor

Create file: `whatsapp-service/src/monitors/MobileActivityMonitor.js`

```javascript
/**
 * MobileActivityMonitor
 * 
 * Monitors mobile WhatsApp activity per SESSION and emits webhooks.
 * Tracks when user sends messages from mobile device.
 * 
 * @module monitors/MobileActivityMonitor
 */

const axios = require('axios');

class MobileActivityMonitor {
    /**
     * @param {Object} options
     * @param {Object} options.logger - Logger instance
     * @param {string} options.webhookUrl - Laravel webhook URL
     * @param {number} options.activityTimeoutMs - Activity timeout (default: 60000)
     */
    constructor(options = {}) {
        this.logger = options.logger || console;
        this.webhookUrl = options.webhookUrl || process.env.LARAVEL_WEBHOOK_URL;
        this.activityTimeoutMs = options.activityTimeoutMs || 60000;
        
        // Map<sessionId, ActivityData>
        // Track per SESSION, not per chat
        this.activityMap = new Map();
        
        // Cleanup interval
        this.cleanupInterval = setInterval(() => {
            this.clearExpired();
        }, 60000);
    }
    
    /**
     * Track mobile activity for a session
     * 
     * @param {string} sessionId - WhatsApp session ID
     * @param {string} deviceType - Device type: android, ios, web, unknown
     * @param {string} messageId - Message ID
     * @param {number} workspaceId - Workspace ID
     * @returns {Promise<Object>}
     */
    async trackActivity(sessionId, deviceType, messageId, workspaceId) {
        try {
            // Skip if device type is 'web' (our own client)
            if (deviceType === 'web') {
                this.logger.debug('Skipping web device type', { sessionId });
                return {
                    success: true,
                    data: { skipped: true, reason: 'web_device' },
                    message: 'Web device type skipped'
                };
            }
            
            const now = new Date();
            const existing = this.activityMap.get(sessionId);
            
            const activityData = {
                lastActivity: now,
                deviceType,
                messageCount: existing ? existing.messageCount + 1 : 1,
                firstActivity: existing ? existing.firstActivity : now,
                lastMessageId: messageId
            };
            
            this.activityMap.set(sessionId, activityData);
            
            this.logger.info('Mobile activity tracked', {
                sessionId,
                deviceType,
                messageCount: activityData.messageCount
            });
            
            // Emit webhook to Laravel
            const webhookResult = await this._emitWebhook(
                sessionId, 
                deviceType, 
                messageId, 
                workspaceId
            );
            
            return {
                success: true,
                data: {
                    tracked: true,
                    activityData,
                    webhookSent: webhookResult.success
                },
                message: 'Activity tracked successfully'
            };
            
        } catch (error) {
            this.logger.error('Failed to track mobile activity', {
                sessionId,
                error: error.message
            });
            
            return {
                success: false,
                data: null,
                message: error.message
            };
        }
    }
    
    /**
     * Check if session has recent mobile activity
     * 
     * @param {string} sessionId
     * @param {number} withinSeconds - Check within this many seconds
     * @returns {boolean}
     */
    isSessionActive(sessionId, withinSeconds = 30) {
        const activity = this.activityMap.get(sessionId);
        if (!activity) return false;
        
        const elapsed = (Date.now() - activity.lastActivity.getTime()) / 1000;
        return elapsed < withinSeconds;
    }
    
    /**
     * Get last activity timestamp for session
     * 
     * @param {string} sessionId
     * @returns {Date|null}
     */
    getLastActivity(sessionId) {
        const activity = this.activityMap.get(sessionId);
        return activity ? activity.lastActivity : null;
    }
    
    /**
     * Get seconds since last activity
     * 
     * @param {string} sessionId
     * @returns {number|null}
     */
    getSecondsSinceLastActivity(sessionId) {
        const activity = this.activityMap.get(sessionId);
        if (!activity) return null;
        
        return Math.floor((Date.now() - activity.lastActivity.getTime()) / 1000);
    }
    
    /**
     * Get activity data for a session (for internal API)
     * 
     * @param {string} sessionId
     * @returns {Object|null}
     */
    getActivityData(sessionId) {
        const activity = this.activityMap.get(sessionId);
        if (!activity) return null;
        
        return {
            session_id: sessionId,
            last_activity: activity.lastActivity.toISOString(),
            device_type: activity.deviceType,
            seconds_since_activity: this.getSecondsSinceLastActivity(sessionId),
            message_count: activity.messageCount
        };
    }
    
    /**
     * Clear expired activity entries
     * 
     * @returns {number} Count of cleared entries
     */
    clearExpired() {
        let clearedCount = 0;
        const now = Date.now();
        
        for (const [sessionId, activity] of this.activityMap.entries()) {
            const elapsed = now - activity.lastActivity.getTime();
            if (elapsed >= this.activityTimeoutMs) {
                this.activityMap.delete(sessionId);
                clearedCount++;
            }
        }
        
        if (clearedCount > 0) {
            this.logger.debug('Cleared expired activity entries', { count: clearedCount });
        }
        
        return clearedCount;
    }
    
    /**
     * Emit webhook to Laravel backend
     * 
     * @private
     */
    async _emitWebhook(sessionId, deviceType, messageId, workspaceId) {
        try {
            const payload = {
                event: 'mobile_activity_detected',
                session_id: sessionId,
                timestamp: new Date().toISOString(),
                data: {
                    device_type: deviceType,
                    message_id: messageId,
                    workspace_id: workspaceId
                }
            };
            
            const response = await axios.post(this.webhookUrl, payload, {
                headers: {
                    'Content-Type': 'application/json',
                    'X-Webhook-Source': 'whatsapp-service'
                },
                timeout: 5000
            });
            
            this.logger.info('Webhook sent successfully', {
                sessionId,
                status: response.status
            });
            
            return { success: true, data: response.data };
            
        } catch (error) {
            this.logger.error('Failed to send webhook', {
                sessionId,
                error: error.message
            });
            
            return { success: false, message: error.message };
        }
    }
    
    /**
     * Get statistics
     */
    getStats() {
        let activeSessions = 0;
        const now = Date.now();
        
        for (const [sessionId, activity] of this.activityMap.entries()) {
            const elapsed = (now - activity.lastActivity.getTime()) / 1000;
            if (elapsed < 60) {
                activeSessions++;
            }
        }
        
        return {
            totalSessions: this.activityMap.size,
            activeSessions,
            inactiveSessions: this.activityMap.size - activeSessions
        };
    }
    
    /**
     * Cleanup resources
     */
    destroy() {
        if (this.cleanupInterval) {
            clearInterval(this.cleanupInterval);
        }
        this.activityMap.clear();
    }
}

module.exports = MobileActivityMonitor;
```

### Step 2.2: Create API Endpoint for Activity Query

Add to `whatsapp-service/src/routes/api.js` or similar:

```javascript
// GET /api/internal/sessions/:sessionId/last-activity
router.get('/internal/sessions/:sessionId/last-activity', (req, res) => {
    const { sessionId } = req.params;
    const activityData = mobileActivityMonitor.getActivityData(sessionId);
    
    if (!activityData) {
        return res.json({
            success: true,
            data: {
                session_id: sessionId,
                last_activity: null,
                device_type: null,
                seconds_since_activity: null
            }
        });
    }
    
    res.json({
        success: true,
        data: activityData
    });
});
```

### Step 2.3: Update SessionManager.js

Update the `message_create` event handler:

```javascript
// At the top, import or instantiate monitor
const MobileActivityMonitor = require('../monitors/MobileActivityMonitor');

// In constructor or initialization
this.mobileActivityMonitor = new MobileActivityMonitor({
    logger: this.logger,
    webhookUrl: process.env.LARAVEL_WEBHOOK_URL + '/api/v1/whatsapp/webhook',
    activityTimeoutMs: 60000
});

// In the message_create event handler
client.on('message_create', async (message) => {
    try {
        // Handle self-sent messages from mobile devices
        if (message.fromMe) {
            const deviceType = message.deviceType || 'unknown';
            
            // Track mobile activity if not from web client
            if (deviceType !== 'web') {
                const messageId = message.id._serialized;
                
                await this.mobileActivityMonitor.trackActivity(
                    sessionId,
                    deviceType,
                    messageId,
                    workspaceId
                );
            }
            
            // ... existing code
        }
    } catch (error) {
        this.logger.error('Error in message_create handler', {
            error: error.message,
            sessionId
        });
    }
});
```

---

## Phase 3: Laravel Components

### Step 3.1: Create CampaignConflictResolver Service

Create file: `app/Services/Campaign/CampaignConflictResolver.php`

```php
<?php

namespace App\Services\Campaign;

use App\Models\Campaign;
use App\Models\WhatsappAccount;
use App\Jobs\AutoResumeCampaignJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

/**
 * CampaignConflictResolver
 * 
 * Handles conflict resolution between active campaigns and mobile WhatsApp activity.
 * Pauses ENTIRE campaigns when mobile activity is detected.
 * Auto-resumes when no activity detected within tier cooldown period.
 */
class CampaignConflictResolver
{
    protected int $workspaceId;
    protected string $queue;
    protected int $maxResumeAttempts;

    public function __construct(int $workspaceId)
    {
        $this->workspaceId = $workspaceId;
        $this->queue = config('campaign.mobile_conflict.queue', 'campaign-conflict');
        $this->maxResumeAttempts = config('campaign.mobile_conflict.max_resume_attempts', 5);
    }

    /**
     * Pause all ongoing campaigns for a session
     */
    public function pauseAllCampaigns(string $sessionId, string $deviceType): object
    {
        if (!config('campaign.mobile_conflict.enabled', true)) {
            return (object) [
                'success' => true,
                'data' => ['skipped' => true, 'reason' => 'feature_disabled'],
                'message' => 'Mobile conflict detection is disabled',
            ];
        }

        $triggerDeviceTypes = config('campaign.mobile_conflict.trigger_device_types', ['android', 'ios']);
        if (!in_array($deviceType, $triggerDeviceTypes)) {
            return (object) [
                'success' => true,
                'data' => ['skipped' => true, 'reason' => 'device_type_not_trigger'],
                'message' => "Device type '{$deviceType}' does not trigger pause",
            ];
        }

        DB::beginTransaction();

        try {
            $campaigns = $this->getOngoingCampaigns($sessionId);

            if ($campaigns->isEmpty()) {
                DB::commit();
                Log::info('No ongoing campaigns to pause', [
                    'workspace_id' => $this->workspaceId,
                    'session_id' => $sessionId,
                ]);

                return (object) [
                    'success' => true,
                    'data' => ['paused_count' => 0],
                    'message' => 'No ongoing campaigns to pause',
                ];
            }

            $pausedIds = [];

            foreach ($campaigns as $campaign) {
                $campaign->pauseForMobileActivity($sessionId);
                $pausedIds[] = $campaign->id;

                // Get tier cooldown for this campaign's WhatsApp account
                $cooldownSeconds = $this->getTierCooldown($campaign->whatsapp_account_id);

                // Schedule auto-resume job
                AutoResumeCampaignJob::dispatch(
                    $this->workspaceId,
                    $campaign->id,
                    $sessionId,
                    1
                )->delay(now()->addSeconds($cooldownSeconds))
                 ->onQueue($this->queue);
            }

            DB::commit();

            Log::info('Campaigns paused for mobile activity', [
                'workspace_id' => $this->workspaceId,
                'session_id' => $sessionId,
                'device_type' => $deviceType,
                'paused_count' => count($pausedIds),
                'campaign_ids' => $pausedIds,
            ]);

            return (object) [
                'success' => true,
                'data' => [
                    'paused_count' => count($pausedIds),
                    'campaign_ids' => $pausedIds,
                ],
                'message' => 'Paused ' . count($pausedIds) . ' campaigns',
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to pause campaigns', [
                'workspace_id' => $this->workspaceId,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'success' => false,
                'data' => null,
                'message' => 'Failed to pause campaigns: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Resume a paused campaign
     */
    public function resumeCampaign(int $campaignId): object
    {
        try {
            $campaign = Campaign::where('id', $campaignId)
                ->where('workspace_id', $this->workspaceId)
                ->first();

            if (!$campaign) {
                return (object) [
                    'success' => false,
                    'data' => null,
                    'message' => 'Campaign not found',
                ];
            }

            if (!$campaign->isPausedForMobile()) {
                return (object) [
                    'success' => true,
                    'data' => ['already_resumed' => true, 'status' => $campaign->status],
                    'message' => 'Campaign already resumed or not paused',
                ];
            }

            $pauseDuration = $campaign->paused_at ? now()->diffInSeconds($campaign->paused_at) : 0;

            $campaign->resumeFromPause();

            Log::info('Campaign resumed', [
                'campaign_id' => $campaignId,
                'workspace_id' => $this->workspaceId,
                'pause_duration_seconds' => $pauseDuration,
            ]);

            return (object) [
                'success' => true,
                'data' => [
                    'campaign_id' => $campaignId,
                    'status' => $campaign->status,
                    'pause_duration_seconds' => $pauseDuration,
                ],
                'message' => 'Campaign resumed successfully',
            ];

        } catch (\Exception $e) {
            Log::error('Failed to resume campaign', [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'success' => false,
                'data' => null,
                'message' => 'Failed to resume: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check if campaign should resume based on last activity
     */
    public function shouldResume(int $campaignId, ?\DateTime $lastMobileActivity): bool
    {
        if ($lastMobileActivity === null) {
            return true; // No activity, safe to resume
        }

        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            return false;
        }

        $cooldownSeconds = $this->getTierCooldown($campaign->whatsapp_account_id);
        $secondsSinceActivity = now()->diffInSeconds($lastMobileActivity);

        return $secondsSinceActivity >= $cooldownSeconds;
    }

    /**
     * Get ongoing campaigns for a session
     */
    public function getOngoingCampaigns(string $sessionId): Collection
    {
        return Campaign::where('workspace_id', $this->workspaceId)
            ->whereHas('whatsappAccount', function ($query) use ($sessionId) {
                $query->where('session_id', $sessionId);
            })
            ->where('status', Campaign::STATUS_ONGOING)
            ->get();
    }

    /**
     * Get tier cooldown for a WhatsApp account
     */
    public function getTierCooldown(int $whatsappAccountId): int
    {
        $account = WhatsappAccount::find($whatsappAccountId);
        $tier = $account ? ($account->tier ?? 1) : 1;

        $tierCooldowns = config('campaign.mobile_conflict.tier_cooldown', [
            1 => 60,
            2 => 45,
            3 => 30,
            4 => 20,
        ]);

        return $tierCooldowns[$tier] ?? config('campaign.mobile_conflict.default_cooldown_seconds', 30);
    }

    /**
     * Query Node.js service for last mobile activity
     */
    public function queryLastMobileActivity(string $sessionId): ?\DateTime
    {
        try {
            $nodeServiceUrl = config('services.whatsapp.url', 'http://localhost:3000');
            
            $response = Http::timeout(5)->get(
                "{$nodeServiceUrl}/api/internal/sessions/{$sessionId}/last-activity"
            );

            if ($response->successful()) {
                $data = $response->json('data');
                if ($data && $data['last_activity']) {
                    return new \DateTime($data['last_activity']);
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::warning('Failed to query mobile activity', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get statistics
     */
    public function getStatistics(): object
    {
        try {
            $stats = [
                'total_paused' => Campaign::where('workspace_id', $this->workspaceId)
                    ->where('status', Campaign::STATUS_PAUSED_MOBILE)
                    ->count(),

                'total_resumed_today' => Campaign::where('workspace_id', $this->workspaceId)
                    ->whereNotNull('auto_resume_at')
                    ->whereDate('auto_resume_at', today())
                    ->count(),

                'average_pause_duration' => Campaign::where('workspace_id', $this->workspaceId)
                    ->whereNotNull('paused_at')
                    ->whereNotNull('auto_resume_at')
                    ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, paused_at, auto_resume_at)) as avg_duration')
                    ->value('avg_duration') ?? 0,

                'campaigns_paused_multiple_times' => Campaign::where('workspace_id', $this->workspaceId)
                    ->where('pause_count', '>', 1)
                    ->count(),
            ];

            return (object) [
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics retrieved successfully',
            ];

        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'data' => null,
                'message' => 'Failed to get statistics: ' . $e->getMessage(),
            ];
        }
    }
}
```

### Step 3.2: Create HandleMobileActivityJob

Create file: `app/Jobs/HandleMobileActivityJob.php`

```php
<?php

namespace App\Jobs;

use App\Services\Campaign\CampaignConflictResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class HandleMobileActivityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;
    public array $backoff = [5, 30, 60];

    protected int $workspaceId;
    protected string $sessionId;
    protected string $deviceType;

    public function __construct(
        int $workspaceId,
        string $sessionId,
        string $deviceType
    ) {
        $this->workspaceId = $workspaceId;
        $this->sessionId = $sessionId;
        $this->deviceType = $deviceType;
        $this->onQueue(config('campaign.mobile_conflict.queue', 'campaign-conflict'));
    }

    public function handle(): void
    {
        Log::info('Processing mobile activity', [
            'workspace_id' => $this->workspaceId,
            'session_id' => $this->sessionId,
            'device_type' => $this->deviceType,
        ]);

        $resolver = new CampaignConflictResolver($this->workspaceId);
        $result = $resolver->pauseAllCampaigns($this->sessionId, $this->deviceType);

        if (!$result->success && !isset($result->data['skipped'])) {
            throw new \Exception($result->message);
        }

        Log::info('Mobile activity processed', [
            'workspace_id' => $this->workspaceId,
            'paused_count' => $result->data['paused_count'] ?? 0,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('HandleMobileActivityJob failed', [
            'workspace_id' => $this->workspaceId,
            'session_id' => $this->sessionId,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

### Step 3.3: Create AutoResumeCampaignJob

Create file: `app/Jobs/AutoResumeCampaignJob.php`

```php
<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Services\Campaign\CampaignConflictResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoResumeCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [10, 60, 120];

    protected int $workspaceId;
    protected int $campaignId;
    protected string $sessionId;
    protected int $attemptNumber;

    public function __construct(
        int $workspaceId,
        int $campaignId,
        string $sessionId,
        int $attemptNumber = 1
    ) {
        $this->workspaceId = $workspaceId;
        $this->campaignId = $campaignId;
        $this->sessionId = $sessionId;
        $this->attemptNumber = $attemptNumber;
        $this->onQueue(config('campaign.mobile_conflict.queue', 'campaign-conflict'));
    }

    public function handle(): void
    {
        Log::info('Checking if campaign can auto-resume', [
            'campaign_id' => $this->campaignId,
            'session_id' => $this->sessionId,
            'attempt' => $this->attemptNumber,
        ]);

        $resolver = new CampaignConflictResolver($this->workspaceId);

        // Check if campaign is still paused
        $campaign = Campaign::find($this->campaignId);
        if (!$campaign || !$campaign->isPausedForMobile()) {
            Log::info('Campaign no longer paused, skipping', [
                'campaign_id' => $this->campaignId,
            ]);
            return;
        }

        // Query last mobile activity from Node.js
        $lastActivity = $resolver->queryLastMobileActivity($this->sessionId);

        // Check if should resume
        if ($resolver->shouldResume($this->campaignId, $lastActivity)) {
            // No recent activity, safe to resume
            $result = $resolver->resumeCampaign($this->campaignId);

            Log::info('Campaign auto-resumed', [
                'campaign_id' => $this->campaignId,
                'workspace_id' => $this->workspaceId,
                'pause_duration' => $result->data['pause_duration_seconds'] ?? 0,
            ]);
        } else {
            // Still active, re-queue if under max attempts
            $maxAttempts = config('campaign.mobile_conflict.max_resume_attempts', 5);

            if ($this->attemptNumber < $maxAttempts) {
                $cooldownSeconds = $resolver->getTierCooldown($campaign->whatsapp_account_id);

                self::dispatch(
                    $this->workspaceId,
                    $this->campaignId,
                    $this->sessionId,
                    $this->attemptNumber + 1
                )->delay(now()->addSeconds($cooldownSeconds))
                 ->onQueue(config('campaign.mobile_conflict.queue', 'campaign-conflict'));

                Log::info('Mobile still active, re-queued resume', [
                    'campaign_id' => $this->campaignId,
                    'attempt' => $this->attemptNumber,
                    'next_check_in' => $cooldownSeconds,
                ]);
            } else {
                // Max attempts reached, force resume
                $resolver->resumeCampaign($this->campaignId);

                Log::warning('Max resume attempts reached, force resuming', [
                    'campaign_id' => $this->campaignId,
                    'attempts' => $this->attemptNumber,
                ]);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('AutoResumeCampaignJob failed', [
            'campaign_id' => $this->campaignId,
            'workspace_id' => $this->workspaceId,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

### Step 3.4: Update WebhookController

Add handler in `app/Http/Controllers/Api/v1/WhatsApp/WebhookController.php`:

```php
/**
 * Handle mobile activity detected webhook
 */
protected function handleMobileActivityDetected(array $payload): \Illuminate\Http\JsonResponse
{
    $sessionId = $payload['session_id'] ?? null;
    $data = $payload['data'] ?? [];
    
    $workspaceId = $data['workspace_id'] ?? null;
    $deviceType = $data['device_type'] ?? 'unknown';
    
    if (!$workspaceId || !$sessionId) {
        return response()->json([
            'success' => false,
            'message' => 'Missing required fields: workspace_id, session_id',
        ], 422);
    }
    
    // Dispatch job for async processing
    HandleMobileActivityJob::dispatch(
        (int) $workspaceId,
        $sessionId,
        $deviceType
    );
    
    Log::info('Mobile activity webhook queued', [
        'workspace_id' => $workspaceId,
        'session_id' => $sessionId,
        'device_type' => $deviceType,
    ]);
    
    return response()->json([
        'success' => true,
        'message' => 'Mobile activity queued for processing',
    ]);
}
```

Update the event routing:

```php
// In the switch/if statement for event routing
case 'mobile_activity_detected':
    return $this->handleMobileActivityDetected($payload);
```

---

## Phase 4: Integration & Testing

### Step 4.1: Start Queue Worker

```bash
php artisan queue:work --queue=campaign-conflict
```

### Step 4.2: Test Service in Tinker

```php
use App\Services\Campaign\CampaignConflictResolver;

$resolver = new CampaignConflictResolver(1);
$stats = $resolver->getStatistics();
print_r($stats);
```

### Step 4.3: Manual Test Flow

1. Start a campaign
2. Send message from mobile
3. Verify campaign status changes to `paused_mobile`
4. Wait for tier cooldown
5. Verify campaign auto-resumes to `ongoing`

---

## 🔧 Troubleshooting

### Issue: Webhook Not Received

1. Check Node.js logs for webhook emission
2. Verify LARAVEL_WEBHOOK_URL
3. Check API route exists
4. Verify API authentication

### Issue: Campaign Not Pausing

1. Check feature is enabled in config
2. Verify session_id matches
3. Check campaign status is `ongoing`
4. Check device_type is android/ios

### Issue: Campaign Not Resuming

1. Check queue worker is running
2. Check job logs
3. Verify Node.js activity API is accessible
4. Check tier cooldown configuration

---

**Document Version:** 1.1  
**Last Updated:** November 29, 2025
