# 📋 Technical Specification

## Mobile Activity Conflict Detection System

**Version:** 1.1  
**Last Updated:** November 29, 2025  
**Status:** Draft

---

## 1. Introduction

### 1.1 Purpose

Dokumen ini berisi spesifikasi teknis lengkap untuk implementasi sistem deteksi konflik antara campaign messaging dan aktivitas WhatsApp Mobile.

### 1.2 Scope

Sistem ini mencakup:
- Deteksi pesan keluar dari WhatsApp Mobile (self-sent messages)
- **Pause otomatis SELURUH campaign** yang sedang berjalan pada session
- **Resume otomatis** setelah tidak ada aktivitas dalam rentang tier cooldown
- Logging dan audit trail

### 1.3 Key Difference from Previous Design

| Aspect | Previous Design | New Design |
|--------|----------------|------------|
| Pause Scope | Per contact | **Entire campaign** |
| Resume Trigger | Fixed cooldown | **Tier-based cooldown + activity check** |
| Database Target | campaign_logs | **campaigns** |
| Resume Logic | Always after cooldown | **Only if no activity detected** |

### 1.4 Background Research

#### whatsapp-web.js Capabilities

Berdasarkan riset pada library whatsapp-web.js v1.34.2:

| Feature | Description | Availability |
|---------|-------------|--------------|
| `message_create` event | Fires for all created messages including self-sent | ✅ Available |
| `Message.deviceType` | Returns device origin: android, ios, web, unknown | ✅ Available |
| `Message.fromMe` | Indicates if message sent by current session | ✅ Available |
| `Message.id._serialized` | Unique message identifier | ✅ Available |

**Key Discovery:**
```javascript
// message_create fires for messages sent from ANY device
client.on('message_create', async (message) => {
    if (message.fromMe) {
        // This could be from:
        // 1. Our whatsapp-web.js client (web)
        // 2. User's mobile phone (android/ios)
        // 3. Other linked devices
        
        const deviceType = message.deviceType; // 'android', 'ios', 'web', 'unknown'
    }
});
```

---

## 2. System Architecture

### 2.1 High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          DUAL-SERVER ARCHITECTURE                            │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   ┌─────────────────────────────────────────────────────────────────────┐   │
│   │                    NODE.JS WHATSAPP SERVICE                          │   │
│   │                                                                       │   │
│   │   ┌─────────────────┐     ┌──────────────────────────────────────┐  │   │
│   │   │ SessionManager  │     │    MobileActivityMonitor            │  │   │
│   │   │                 │     │                                      │  │   │
│   │   │ - message_create│────▶│ - trackActivity(session)            │  │   │
│   │   │ - fromMe check  │     │ - getLastActivity(session)          │  │   │
│   │   │ - deviceType    │     │ - isSessionActive(session)          │  │   │
│   │   │                 │     │ - emitWebhook()                     │  │   │
│   │   └─────────────────┘     └──────────────────────────────────────┘  │   │
│   │                                        │                             │   │
│   └────────────────────────────────────────┼─────────────────────────────┘   │
│                                            │                                  │
│                                            │ HTTP POST                        │
│                                            │ /api/v1/whatsapp/webhook         │
│                                            │                                  │
│   ┌────────────────────────────────────────┼─────────────────────────────┐   │
│   │                                        ▼                             │   │
│   │                    LARAVEL BACKEND                                   │   │
│   │                                                                       │   │
│   │   ┌─────────────────────┐     ┌──────────────────────────────────┐  │   │
│   │   │  WebhookController  │     │  HandleMobileActivityJob        │  │   │
│   │   │                     │────▶│  (Queued)                        │  │   │
│   │   │  - Validate payload │     │                                  │  │   │
│   │   │  - Dispatch job     │     │  - Process in background        │  │   │
│   │   └─────────────────────┘     └──────────────────────────────────┘  │   │
│   │                                        │                             │   │
│   │                                        ▼                             │   │
│   │   ┌──────────────────────────────────────────────────────────────┐  │   │
│   │   │               CampaignConflictResolver                       │  │   │
│   │   │                                                               │  │   │
│   │   │  - pauseAllCampaigns($sessionId)                             │  │   │
│   │   │  - resumeCampaign($campaignId)                               │  │   │
│   │   │  - getOngoingCampaigns($sessionId)                           │  │   │
│   │   │  - shouldResume($campaignId, $lastActivity)                  │  │   │
│   │   └──────────────────────────────────────────────────────────────┘  │   │
│   │                                        │                             │   │
│   │                                        ▼                             │   │
│   │   ┌──────────────────────────────────────────────────────────────┐  │   │
│   │   │             AutoResumeCampaignJob                            │  │   │
│   │   │             (Delayed dispatch after tier cooldown)           │  │   │
│   │   └──────────────────────────────────────────────────────────────┘  │   │
│   │                                                                       │   │
│   └───────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 2.2 Data Flow Diagram

```
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│  Mobile  │    │ WhatsApp │    │  Node.js │    │  Laravel │    │   MySQL  │
│  Device  │    │  Server  │    │ Service  │    │  Backend │    │    DB    │
└────┬─────┘    └────┬─────┘    └────┬─────┘    └────┬─────┘    └────┬─────┘
     │               │               │               │               │
     │  Send msg     │               │               │               │
     │──────────────▶│               │               │               │
     │               │               │               │               │
     │               │ message_create│               │               │
     │               │ (websocket)   │               │               │
     │               │──────────────▶│               │               │
     │               │               │               │               │
     │               │               │ Detect mobile │               │
     │               │               │ origin        │               │
     │               │               │───────────────│               │
     │               │               │               │               │
     │               │               │    Webhook    │               │
     │               │               │ POST request  │               │
     │               │               │──────────────▶│               │
     │               │               │               │               │
     │               │               │               │ Dispatch job  │
     │               │               │               │───────────────│
     │               │               │               │               │
     │               │               │               │ Pause ALL     │
     │               │               │               │ campaigns     │
     │               │               │               │──────────────▶│
     │               │               │               │               │
     │               │               │               │ Schedule      │
     │               │               │               │ auto-resume   │
     │               │               │               │───────────────│
     │               │               │               │               │
     │               │               │               │ [Tier cooldown]│
     │               │               │               │ Check activity │
     │               │               │ Query last    │◀──────────────│
     │               │               │ activity      │               │
     │               │               │◀──────────────│               │
     │               │               │               │               │
     │               │               │ Return        │               │
     │               │               │ timestamp     │               │
     │               │               │──────────────▶│               │
     │               │               │               │               │
     │               │               │               │ Resume or     │
     │               │               │               │ re-queue      │
     │               │               │               │──────────────▶│
     │               │               │               │               │
```

---

## 3. Component Specifications

### 3.1 MobileActivityMonitor (Node.js)

**Location:** `whatsapp-service/src/monitors/MobileActivityMonitor.js`

**Purpose:** Track mobile activity per session dan emit webhook events.

#### 3.1.1 Class Definition

```javascript
/**
 * MobileActivityMonitor
 * 
 * Monitors and tracks mobile device activity for WhatsApp sessions.
 * Emits webhooks to Laravel backend when mobile activity is detected.
 * 
 * @class MobileActivityMonitor
 */
class MobileActivityMonitor {
    /**
     * @param {Object} options
     * @param {Object} options.logger - Logger instance
     * @param {Object} options.webhookService - Webhook service for HTTP calls
     * @param {number} options.activityTimeoutMs - Activity timeout in ms (default: 60000)
     */
    constructor(options = {}) {
        this.logger = options.logger || console;
        this.webhookService = options.webhookService;
        this.activityTimeoutMs = options.activityTimeoutMs || 60000;
        
        // Map<sessionId, { lastActivity: Date, deviceType: string, messageCount: number }>
        this.activityMap = new Map();
    }
    
    /**
     * Track activity from mobile device for a session
     * @param {string} sessionId - WhatsApp session identifier
     * @param {string} deviceType - Device type: 'android', 'ios', 'web', 'unknown'
     * @param {string} messageId - Message identifier
     * @param {number} workspaceId - Workspace ID
     * @returns {Promise<Object>} { success: boolean, data: Object, message: string }
     */
    async trackActivity(sessionId, deviceType, messageId, workspaceId) { }
    
    /**
     * Check if session has recent mobile activity
     * @param {string} sessionId
     * @param {number} withinSeconds - Check within this many seconds
     * @returns {boolean}
     */
    isSessionActive(sessionId, withinSeconds = 30) { }
    
    /**
     * Get last activity timestamp for session
     * @param {string} sessionId
     * @returns {Date|null}
     */
    getLastActivity(sessionId) { }
    
    /**
     * Get seconds since last activity
     * @param {string} sessionId
     * @returns {number|null}
     */
    getSecondsSinceLastActivity(sessionId) { }
    
    /**
     * Emit mobile activity webhook to Laravel
     * @private
     */
    async _emitWebhook(sessionId, deviceType, messageId, workspaceId) { }
}
```

#### 3.1.2 Activity Tracking Structure

```javascript
// Internal structure - track per SESSION, not per chat
activityMap = Map {
    'session_abc123' => {
        lastActivity: Date('2025-11-29T10:30:00.000Z'),
        deviceType: 'android',
        messageCount: 5,
        firstActivity: Date('2025-11-29T10:25:00.000Z')
    },
    'session_xyz789' => {
        lastActivity: Date('2025-11-29T10:28:00.000Z'),
        deviceType: 'ios',
        messageCount: 2,
        firstActivity: Date('2025-11-29T10:28:00.000Z')
    }
}
```

### 3.2 CampaignConflictResolver (Laravel Service)

**Location:** `app/Services/Campaign/CampaignConflictResolver.php`

**Purpose:** Handle business logic untuk pause dan resume CAMPAIGN (bukan campaign_logs).

#### 3.2.1 Class Definition

```php
<?php

namespace App\Services\Campaign;

use App\Models\Campaign;
use App\Models\WhatsappAccount;
use App\Jobs\AutoResumeCampaignJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * CampaignConflictResolver
 * 
 * Handles conflict resolution between active campaigns and mobile WhatsApp activity.
 * Pauses ENTIRE campaigns (not individual logs) when mobile activity is detected.
 * Auto-resumes when no activity detected within tier cooldown period.
 */
class CampaignConflictResolver
{
    /**
     * @var int Workspace ID for scoping all queries
     */
    protected int $workspaceId;
    
    /**
     * @var string Queue name for conflict resolution jobs
     */
    protected string $queue;
    
    /**
     * @var int Maximum resume attempts
     */
    protected int $maxResumeAttempts;

    /**
     * Constructor
     *
     * @param int $workspaceId
     */
    public function __construct(int $workspaceId)
    {
        $this->workspaceId = $workspaceId;
        $this->queue = config('campaign.mobile_conflict.queue', 'campaign-conflict');
        $this->maxResumeAttempts = config('campaign.mobile_conflict.max_resume_attempts', 5);
    }

    /**
     * Pause all ongoing campaigns for a session due to mobile activity
     *
     * @param string $sessionId WhatsApp session ID
     * @param string $deviceType Device type: android, ios
     * @return object { success: bool, data: array, message: string }
     */
    public function pauseAllCampaigns(string $sessionId, string $deviceType): object { }

    /**
     * Resume a paused campaign
     *
     * @param int $campaignId
     * @return object { success: bool, data: array, message: string }
     */
    public function resumeCampaign(int $campaignId): object { }

    /**
     * Check if campaign should resume based on last activity
     *
     * @param int $campaignId
     * @param \DateTime $lastMobileActivity
     * @return bool
     */
    public function shouldResume(int $campaignId, \DateTime $lastMobileActivity): bool { }

    /**
     * Get all ongoing campaigns for a session
     *
     * @param string $sessionId
     * @return \Illuminate\Support\Collection
     */
    public function getOngoingCampaigns(string $sessionId): \Illuminate\Support\Collection { }

    /**
     * Get tier cooldown for a WhatsApp account
     *
     * @param int $whatsappAccountId
     * @return int Cooldown in seconds
     */
    public function getTierCooldown(int $whatsappAccountId): int { }

    /**
     * Query Node.js service for last mobile activity
     *
     * @param string $sessionId
     * @return \DateTime|null
     */
    public function queryLastMobileActivity(string $sessionId): ?\DateTime { }

    /**
     * Get conflict statistics for workspace
     *
     * @return object { success: bool, data: array, message: string }
     */
    public function getStatistics(): object { }
}
```

#### 3.2.2 Method Signatures & Returns

| Method | Parameters | Return Type | Description |
|--------|-----------|-------------|-------------|
| `pauseAllCampaigns` | `$sessionId`, `$deviceType` | `object` | Pause all ongoing campaigns for session |
| `resumeCampaign` | `$campaignId` | `object` | Resume single campaign |
| `shouldResume` | `$campaignId`, `$lastMobileActivity` | `bool` | Check if safe to resume |
| `getOngoingCampaigns` | `$sessionId` | `Collection` | Get ongoing campaigns |
| `getTierCooldown` | `$whatsappAccountId` | `int` | Get cooldown based on tier |
| `queryLastMobileActivity` | `$sessionId` | `DateTime|null` | Query Node.js for activity |
| `getStatistics` | - | `object` | Get conflict stats |

### 3.3 HandleMobileActivityJob (Laravel Job)

**Location:** `app/Jobs/HandleMobileActivityJob.php`

**Purpose:** Async processing webhook mobile activity dari queue.

#### 3.3.1 Class Definition

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

    public function handle(): void { }
    public function failed(\Throwable $exception): void { }
}
```

### 3.4 AutoResumeCampaignJob (Laravel Job)

**Location:** `app/Jobs/AutoResumeCampaignJob.php`

**Purpose:** Auto-resume campaign setelah tier cooldown jika tidak ada aktivitas.

#### 3.4.1 Class Definition

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
        $resolver = new CampaignConflictResolver($this->workspaceId);
        
        // Query last mobile activity from Node.js
        $lastActivity = $resolver->queryLastMobileActivity($this->sessionId);
        
        // Get tier cooldown
        $campaign = Campaign::find($this->campaignId);
        $cooldownSeconds = $resolver->getTierCooldown($campaign->whatsapp_account_id);
        
        // Check if should resume
        if ($lastActivity === null || $resolver->shouldResume($this->campaignId, $lastActivity)) {
            // No recent activity, safe to resume
            $result = $resolver->resumeCampaign($this->campaignId);
            
            Log::info('Campaign auto-resumed', [
                'campaign_id' => $this->campaignId,
                'workspace_id' => $this->workspaceId,
            ]);
        } else {
            // Still active, re-queue if under max attempts
            if ($this->attemptNumber < config('campaign.mobile_conflict.max_resume_attempts', 5)) {
                self::dispatch(
                    $this->workspaceId,
                    $this->campaignId,
                    $this->sessionId,
                    $this->attemptNumber + 1
                )->delay(now()->addSeconds($cooldownSeconds));
                
                Log::info('Mobile still active, re-queued resume', [
                    'campaign_id' => $this->campaignId,
                    'attempt' => $this->attemptNumber,
                    'next_attempt_in' => $cooldownSeconds,
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

    public function failed(\Throwable $exception): void { }
}
```

---

## 4. Database Schema

### 4.1 Migration: Add Conflict Columns to campaigns

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // Conflict tracking columns
            $table->timestamp('paused_at')->nullable()->after('completed_at');
            $table->string('pause_reason', 100)->nullable()->after('paused_at');
            $table->timestamp('auto_resume_at')->nullable()->after('pause_reason');
            $table->unsignedTinyInteger('pause_count')->default(0)->after('auto_resume_at');
            $table->string('paused_by_session', 255)->nullable()->after('pause_count');
            
            // Index for querying paused campaigns
            $table->index(['status', 'paused_at'], 'idx_campaigns_status_paused');
            $table->index(['workspace_id', 'status'], 'idx_campaigns_workspace_status');
        });
    }

    /**
     * Reverse the migrations.
     */
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

### 4.2 Status Enum Update

Existing `status` column perlu mendukung value baru:

| Status | Description |
|--------|-------------|
| `pending` | Campaign created, waiting to start |
| `scheduled` | Scheduled for future execution |
| `ongoing` | Currently sending messages |
| `completed` | All messages sent |
| `failed` | Campaign failed |
| `paused_mobile` | **NEW**: Paused due to mobile activity |

### 4.3 Updated Model: Campaign.php

```php
// Add to app/Models/Campaign.php

/**
 * Status constants
 */
const STATUS_PENDING = 'pending';
const STATUS_SCHEDULED = 'scheduled';
const STATUS_ONGOING = 'ongoing';
const STATUS_COMPLETED = 'completed';
const STATUS_FAILED = 'failed';
const STATUS_PAUSED_MOBILE = 'paused_mobile'; // NEW

/**
 * Pause reasons
 */
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
 * Check if campaign is paused due to mobile
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
 * Resume campaign
 */
public function resumeFromPause(): void
{
    $this->status = self::STATUS_ONGOING;
    $this->auto_resume_at = now();
    $this->save();
}
```

---

## 5. API Contracts

### 5.1 Webhook: Mobile Activity Detected

**Endpoint:** `POST /api/v1/whatsapp/webhook`

**Payload:**

```json
{
    "event": "mobile_activity_detected",
    "session_id": "session_abc123",
    "timestamp": "2025-11-29T10:30:00.000Z",
    "data": {
        "device_type": "android",
        "message_id": "true_6281234567890@c.us_3EB0ABC123",
        "workspace_id": 1
    }
}
```

**Response:**

```json
{
    "success": true,
    "message": "Mobile activity queued for processing",
    "data": {
        "queued": true
    }
}
```

### 5.2 Internal API: Query Last Activity

**Endpoint:** `GET /api/internal/sessions/{sessionId}/last-activity`

Called by Laravel to check last mobile activity from Node.js.

**Response:**

```json
{
    "success": true,
    "data": {
        "session_id": "session_abc123",
        "last_activity": "2025-11-29T10:30:00.000Z",
        "device_type": "android",
        "seconds_since_activity": 25
    }
}
```

---

## 6. Configuration

### 6.1 config/campaign.php

```php
<?php

return [
    // ... existing configuration
    
    /**
     * Mobile conflict detection settings
     */
    'mobile_conflict' => [
        /**
         * Enable/disable mobile conflict detection
         */
        'enabled' => env('CAMPAIGN_CONFLICT_ENABLED', true),
        
        /**
         * Queue name for conflict resolution jobs
         */
        'queue' => env('CAMPAIGN_CONFLICT_QUEUE', 'campaign-conflict'),
        
        /**
         * Default cooldown if tier not determined
         */
        'default_cooldown_seconds' => env('CAMPAIGN_CONFLICT_DEFAULT_COOLDOWN', 30),
        
        /**
         * Maximum attempts to auto-resume before forcing
         */
        'max_resume_attempts' => env('CAMPAIGN_CONFLICT_MAX_ATTEMPTS', 5),
        
        /**
         * Tier-based cooldown periods (in seconds)
         * Based on account trust level
         */
        'tier_cooldown' => [
            1 => 60,  // Tier 1: New account - conservative
            2 => 45,  // Tier 2: Warming up
            3 => 30,  // Tier 3: Established
            4 => 20,  // Tier 4: Trusted
        ],
        
        /**
         * Device types that trigger pause
         * 'web' excluded because it's our own client
         */
        'trigger_device_types' => ['android', 'ios'],
    ],
];
```

### 6.2 Environment Variables

```env
# Mobile Conflict Detection
CAMPAIGN_CONFLICT_ENABLED=true
CAMPAIGN_CONFLICT_QUEUE=campaign-conflict
CAMPAIGN_CONFLICT_DEFAULT_COOLDOWN=30
CAMPAIGN_CONFLICT_MAX_ATTEMPTS=5
```

---

## 7. Error Handling

### 7.1 Error Scenarios

| Scenario | Handling | Recovery |
|----------|----------|----------|
| Webhook validation fails | Return 422 | Log and skip |
| Workspace not found | Throw exception | Job will retry |
| Campaign not found | Log warning | Skip gracefully |
| Node.js service unreachable | Use cached activity | Retry query |
| Database transaction fails | Rollback | Job will retry |
| Max resume attempts | Force resume | Log warning |

### 7.2 Exception Classes

```php
<?php

namespace App\Exceptions\Campaign;

class MobileConflictException extends \Exception
{
    protected string $sessionId;
    protected int $workspaceId;
    
    public function __construct(
        string $message,
        string $sessionId,
        int $workspaceId,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->sessionId = $sessionId;
        $this->workspaceId = $workspaceId;
    }
}
```

---

## 8. Performance Considerations

### 8.1 Expected Load

| Metric | Expected | Peak |
|--------|----------|------|
| Mobile activity webhooks/min | 50 | 200 |
| Pause operations/min | 10 | 50 |
| Resume checks/min | 20 | 100 |
| Queue depth | 50 | 500 |

### 8.2 Optimization Strategies

1. **Session-level tracking**: Track per session, not per chat (reduces memory)
2. **Index Optimization**: Indexes on workspace_id + status
3. **Queue Prioritization**: Dedicated queue for conflict resolution
4. **Caching**: Cache tier information in Redis

### 8.3 Resource Requirements

| Resource | Minimum | Recommended |
|----------|---------|-------------|
| Queue Workers | 1 | 2 |
| Memory per Worker | 128MB | 256MB |
| Redis Memory | 25MB | 50MB |

---

## 9. Security Considerations

### 9.1 Authentication

- Webhook endpoint protected by API token
- Internal API between Laravel and Node.js uses shared secret

### 9.2 Data Privacy

- Session IDs logged without sensitive data
- Phone numbers not stored in conflict tracking

---

## 10. Monitoring & Alerting

### 10.1 Metrics to Monitor

| Metric | Threshold | Alert Level |
|--------|-----------|-------------|
| Pause rate > 30% | > 30% | Warning |
| Resume failure rate | > 10% | Critical |
| Queue depth | > 500 | Warning |
| Max attempts reached | > 5/hour | Warning |

### 10.2 Log Patterns

```
# Campaign paused
[INFO] Campaign paused for mobile activity workspace_id=1 campaign_id=123 session_id=abc

# Auto-resume successful
[INFO] Campaign auto-resumed campaign_id=123 pause_duration=45s

# Re-queue resume
[INFO] Mobile still active, re-queued campaign_id=123 attempt=2 next_check=30s

# Force resume
[WARNING] Max attempts reached, force resuming campaign_id=123 attempts=5
```

---

**Document Version:** 1.1  
**Review Status:** Pending  
**Next Review:** December 2025
