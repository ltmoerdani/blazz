# 📡 API Reference

## Mobile Activity Conflict Detection System

**Version:** 1.1  
**Last Updated:** November 29, 2025

---

## 1. Webhook Endpoints

### 1.1 Mobile Activity Detected

Webhook yang dikirim dari Node.js WhatsApp Service ketika mendeteksi aktivitas mobile.

**Endpoint:** `POST /api/v1/whatsapp/webhook`

**Headers:**
```http
Content-Type: application/json
X-Webhook-Source: whatsapp-service
Authorization: Bearer {api_token}
```

**Request Body:**
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

**Response - Success (200):**
```json
{
    "success": true,
    "message": "Mobile activity queued for processing",
    "data": {
        "queued": true
    }
}
```

**Response - Validation Error (422):**
```json
{
    "success": false,
    "message": "Missing required fields: workspace_id, session_id",
    "data": null
}
```

#### Field Descriptions

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `event` | string | Yes | Event type: `mobile_activity_detected` |
| `session_id` | string | Yes | WhatsApp session identifier |
| `timestamp` | string (ISO8601) | Yes | Event timestamp |
| `data.device_type` | string | Yes | Device type: `android`, `ios`, `web`, `unknown` |
| `data.message_id` | string | No | WhatsApp message ID for tracking |
| `data.workspace_id` | integer | Yes | Workspace ID for scoping |

---

## 2. Internal APIs

### 2.1 Query Last Mobile Activity

Called by Laravel to check last mobile activity from Node.js service.

**Endpoint:** `GET /api/internal/sessions/{sessionId}/last-activity`

**Response - Has Activity:**
```json
{
    "success": true,
    "data": {
        "session_id": "session_abc123",
        "last_activity": "2025-11-29T10:30:00.000Z",
        "device_type": "android",
        "seconds_since_activity": 25,
        "message_count": 5
    }
}
```

**Response - No Activity:**
```json
{
    "success": true,
    "data": {
        "session_id": "session_abc123",
        "last_activity": null,
        "device_type": null,
        "seconds_since_activity": null
    }
}
```

---

## 3. Service APIs

### 3.1 CampaignConflictResolver

Service untuk mengelola pause/resume campaign berdasarkan mobile activity.

#### 3.1.1 Constructor

```php
$resolver = new CampaignConflictResolver(int $workspaceId);
```

---

#### 3.1.2 pauseAllCampaigns

Pause SEMUA campaign ongoing untuk session tertentu.

```php
public function pauseAllCampaigns(string $sessionId, string $deviceType): object
```

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$sessionId` | string | Yes | WhatsApp session ID |
| `$deviceType` | string | Yes | Device type: `android`, `ios` |

**Return:**

```php
(object) [
    'success' => true,
    'data' => [
        'paused_count' => 3,
        'campaign_ids' => [101, 102, 103],
    ],
    'message' => 'Paused 3 campaigns',
]
```

---

#### 3.1.3 resumeCampaign

Resume campaign yang di-pause.

```php
public function resumeCampaign(int $campaignId): object
```

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$campaignId` | integer | Yes | Campaign ID to resume |

**Return:**

```php
(object) [
    'success' => true,
    'data' => [
        'campaign_id' => 101,
        'status' => 'ongoing',
        'pause_duration_seconds' => 45,
    ],
    'message' => 'Campaign resumed successfully',
]
```

---

#### 3.1.4 shouldResume

Check apakah campaign aman untuk di-resume berdasarkan last activity.

```php
public function shouldResume(int $campaignId, ?\DateTime $lastMobileActivity): bool
```

**Return:** `true` if safe to resume, `false` if still active

---

#### 3.1.5 getOngoingCampaigns

Get semua campaign ongoing untuk session.

```php
public function getOngoingCampaigns(string $sessionId): \Illuminate\Support\Collection
```

**Return:** Collection of `Campaign` models

---

#### 3.1.6 getTierCooldown

Get cooldown period berdasarkan tier account.

```php
public function getTierCooldown(int $whatsappAccountId): int
```

**Return:** Cooldown in seconds

| Tier | Cooldown |
|------|----------|
| 1 | 60s |
| 2 | 45s |
| 3 | 30s |
| 4 | 20s |

---

#### 3.1.7 queryLastMobileActivity

Query Node.js untuk last mobile activity.

```php
public function queryLastMobileActivity(string $sessionId): ?\DateTime
```

**Return:** `DateTime` or `null` if no activity

---

#### 3.1.8 getStatistics

Get statistik conflict untuk workspace.

```php
public function getStatistics(): object
```

**Return:**

```php
(object) [
    'success' => true,
    'data' => [
        'total_paused' => 5,
        'total_resumed_today' => 12,
        'average_pause_duration' => 38.5,
        'campaigns_paused_multiple_times' => 2,
    ],
    'message' => 'Statistics retrieved successfully',
]
```

---

## 4. Job APIs

### 4.1 HandleMobileActivityJob

Job untuk memproses mobile activity detection.

#### Dispatch

```php
use App\Jobs\HandleMobileActivityJob;

HandleMobileActivityJob::dispatch(
    int $workspaceId,
    string $sessionId,
    string $deviceType
);
```

**Queue:** `campaign-conflict`  
**Tries:** 3  
**Timeout:** 30 seconds  
**Backoff:** [5, 30, 60] seconds

---

### 4.2 AutoResumeCampaignJob

Job untuk auto-resume campaign setelah cooldown.

#### Dispatch

```php
use App\Jobs\AutoResumeCampaignJob;

AutoResumeCampaignJob::dispatch(
    int $workspaceId,
    int $campaignId,
    string $sessionId,
    int $attemptNumber = 1
)->delay(now()->addSeconds($cooldownSeconds));
```

**Queue:** `campaign-conflict`  
**Tries:** 3  
**Timeout:** 60 seconds  
**Backoff:** [10, 60, 120] seconds

---

## 5. Node.js APIs

### 5.1 MobileActivityMonitor

Class untuk tracking mobile activity per SESSION.

#### Constructor

```javascript
const monitor = new MobileActivityMonitor({
    logger: winstonLogger,
    webhookUrl: 'http://localhost/api/v1/whatsapp/webhook',
    activityTimeoutMs: 60000
});
```

---

#### trackActivity

Track mobile activity untuk session.

```javascript
const result = await monitor.trackActivity(
    sessionId,      // string: WhatsApp session ID
    deviceType,     // string: android, ios, web, unknown
    messageId,      // string: Message ID
    workspaceId     // number: Workspace ID
);
```

**Return:**

```javascript
{
    success: true,
    data: {
        tracked: true,
        activityData: {
            lastActivity: Date,
            deviceType: 'android',
            messageCount: 3,
            firstActivity: Date
        },
        webhookSent: true
    },
    message: 'Activity tracked successfully'
}
```

---

#### isSessionActive

Check apakah session punya aktivitas recent.

```javascript
const isActive = monitor.isSessionActive(sessionId, withinSeconds = 30);
// Returns: boolean
```

---

#### getLastActivity

Get timestamp aktivitas terakhir.

```javascript
const lastActivity = monitor.getLastActivity(sessionId);
// Returns: Date or null
```

---

#### getSecondsSinceLastActivity

Get waktu sejak aktivitas terakhir.

```javascript
const seconds = monitor.getSecondsSinceLastActivity(sessionId);
// Returns: number or null
```

---

#### getActivityData

Get full activity data untuk API response.

```javascript
const data = monitor.getActivityData(sessionId);
// Returns: object or null
```

---

#### getStats

Get statistik monitor.

```javascript
const stats = monitor.getStats();
// Returns: { totalSessions, activeSessions, inactiveSessions }
```

---

## 6. Model APIs

### 6.1 Campaign Model Extensions

#### Constants

```php
// Status constants
Campaign::STATUS_PAUSED_MOBILE // 'paused_mobile'

// Pause reasons
Campaign::PAUSE_REASON_MOBILE_ACTIVITY // 'mobile_activity'
Campaign::PAUSE_REASON_MANUAL          // 'manual'
```

#### Methods

```php
// Check if paused for mobile
$campaign->isPausedForMobile(): bool

// Pause for mobile activity
$campaign->pauseForMobileActivity(string $sessionId): void

// Resume from pause
$campaign->resumeFromPause(): void
```

#### Scopes

```php
// Get paused campaigns
Campaign::pausedForMobile()->get();

// Get ongoing campaigns
Campaign::ongoing()->get();
```

---

## 7. Configuration Reference

### 7.1 config/campaign.php

```php
'mobile_conflict' => [
    // Enable/disable feature
    'enabled' => env('CAMPAIGN_CONFLICT_ENABLED', true),
    
    // Dedicated queue name
    'queue' => env('CAMPAIGN_CONFLICT_QUEUE', 'campaign-conflict'),
    
    // Default cooldown if tier not determined
    'default_cooldown_seconds' => env('CAMPAIGN_CONFLICT_DEFAULT_COOLDOWN', 30),
    
    // Max resume attempts before force resume
    'max_resume_attempts' => env('CAMPAIGN_CONFLICT_MAX_ATTEMPTS', 5),
    
    // Tier-based cooldown (in seconds)
    'tier_cooldown' => [
        1 => 60,  // Tier 1: New account
        2 => 45,  // Tier 2: Warming
        3 => 30,  // Tier 3: Established
        4 => 20,  // Tier 4: Trusted
    ],
    
    // Device types that trigger pause
    'trigger_device_types' => ['android', 'ios'],
],
```

### 7.2 Environment Variables

| Variable | Type | Default | Description |
|----------|------|---------|-------------|
| `CAMPAIGN_CONFLICT_ENABLED` | boolean | `true` | Enable/disable feature |
| `CAMPAIGN_CONFLICT_QUEUE` | string | `campaign-conflict` | Queue name |
| `CAMPAIGN_CONFLICT_DEFAULT_COOLDOWN` | integer | `30` | Default cooldown seconds |
| `CAMPAIGN_CONFLICT_MAX_ATTEMPTS` | integer | `5` | Max resume attempts |

---

## 8. Event Reference

### 8.1 Webhook Events

| Event | Direction | Description |
|-------|-----------|-------------|
| `mobile_activity_detected` | Node.js → Laravel | Mobile activity detected for session |

### 8.2 Campaign Status Transitions

```
ongoing ──────► paused_mobile ──────► ongoing
           (mobile detected)      (auto-resume)
```

---

## 9. Examples

### 9.1 Complete Webhook Example

```bash
curl -X POST http://localhost/api/v1/whatsapp/webhook \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your_api_token" \
  -d '{
    "event": "mobile_activity_detected",
    "session_id": "session_abc123",
    "timestamp": "2025-11-29T10:30:00.000Z",
    "data": {
        "device_type": "android",
        "message_id": "true_6281234567890@c.us_3EB0ABC123",
        "workspace_id": 1
    }
}'
```

### 9.2 Service Usage Example

```php
use App\Services\Campaign\CampaignConflictResolver;

// Initialize resolver
$resolver = new CampaignConflictResolver($workspaceId);

// Pause all campaigns for session
$result = $resolver->pauseAllCampaigns(
    'session_abc123',
    'android'
);

if ($result->success) {
    echo "Paused {$result->data['paused_count']} campaigns";
}

// Check if should resume
$lastActivity = $resolver->queryLastMobileActivity('session_abc123');
if ($resolver->shouldResume($campaignId, $lastActivity)) {
    $resolver->resumeCampaign($campaignId);
}
```

### 9.3 Node.js Usage Example

```javascript
const MobileActivityMonitor = require('./monitors/MobileActivityMonitor');

const monitor = new MobileActivityMonitor({
    logger: winston.createLogger({...}),
    webhookUrl: process.env.LARAVEL_WEBHOOK_URL + '/api/v1/whatsapp/webhook',
    activityTimeoutMs: 60000
});

// Track activity
const result = await monitor.trackActivity(
    'session_abc123',
    'android',
    'msg_123',
    1
);

// Check if session is active
const isActive = monitor.isSessionActive('session_abc123', 30);

// Get activity data for API
const data = monitor.getActivityData('session_abc123');
```

---

**Document Version:** 1.1  
**Last Updated:** November 29, 2025
