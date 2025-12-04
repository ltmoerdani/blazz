# 📱 Mobile Activity Conflict Detection System

## Overview

Dokumen ini menjelaskan arsitektur dan implementasi sistem deteksi konflik antara **Campaign Messaging** dan **Mobile WhatsApp Activity** di platform Blazz.

**Problem Statement:**
Ketika user menjalankan campaign melalui Blazz dan secara bersamaan melakukan chat melalui WhatsApp Mobile, ada risiko:
1. **Rate Limit Terlampaui** - Pengiriman berlebihan dalam waktu singkat
2. **Over-Use Detection** - WhatsApp mendeteksi aktivitas tidak wajar
3. **Delivery Order Terganggu** - Pesan campaign dan mobile saling overlap
4. **Spam Detection Risk** - Pengiriman ke kontak yang sama dari berbagai sumber

**Solution:**
Implementasi **Mobile Activity Detection System** yang:
- Mendeteksi ketika user mengirim pesan via WhatsApp Mobile
- **Otomatis pause SELURUH campaign** yang sedang berjalan pada session tersebut
- **Resume otomatis** jika tidak ada aktivitas mobile dalam rentang waktu tier
- Logging dan monitoring untuk audit trail

---

## 📚 Dokumen Terkait

| Dokumen | Deskripsi |
|---------|-----------|
| [01-technical-specification.md](./01-technical-specification.md) | Spesifikasi teknis lengkap |
| [02-implementation-guide.md](./02-implementation-guide.md) | Panduan implementasi step-by-step |
| [03-api-reference.md](./03-api-reference.md) | API endpoints dan contracts |
| [04-testing-guide.md](./04-testing-guide.md) | Test cases dan scenarios |
| [05-research-analysis.md](./05-research-analysis.md) | Background research & analysis |

---

## 🏗️ Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        MOBILE CONFLICT DETECTION FLOW                        │
└─────────────────────────────────────────────────────────────────────────────┘

┌──────────────┐     message_create event     ┌──────────────────────────────┐
│   WhatsApp   │ ────────────────────────────▶│  Node.js WhatsApp Service    │
│    Mobile    │     (fromMe = true)          │                              │
└──────────────┘                              │  ┌────────────────────────┐  │
                                              │  │ MobileActivityMonitor  │  │
                                              │  │ - Track session activity│  │
                                              │  │ - Emit webhook event   │  │
                                              │  └────────────────────────┘  │
                                              └──────────────┬───────────────┘
                                                             │
                                                             │ Webhook: mobile_activity_detected
                                                             ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           Laravel Backend                                    │
│                                                                              │
│  ┌─────────────────────┐      ┌──────────────────────────────────────────┐  │
│  │  WebhookController  │─────▶│  HandleMobileActivityJob (Queue)         │  │
│  └─────────────────────┘      └──────────────────────────────────────────┘  │
│                                              │                               │
│                                              ▼                               │
│                               ┌──────────────────────────────────────────┐  │
│                               │  CampaignConflictResolver Service        │  │
│                               │  - Pause ALL ongoing campaigns           │  │
│                               │  - Update campaign status                │  │
│                               │  - Schedule auto-resume check            │  │
│                               └──────────────────────────────────────────┘  │
│                                              │                               │
│                                              ▼                               │
│                               ┌──────────────────────────────────────────┐  │
│                               │  AutoResumeCampaignJob                   │  │
│                               │  - Wait for tier cooldown period         │  │
│                               │  - Check for continued activity          │  │
│                               │  - Resume campaign or re-queue           │  │
│                               └──────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 🎯 Key Components

### 1. Node.js Layer

| Component | File | Responsibility |
|-----------|------|----------------|
| `MobileActivityMonitor` | `whatsapp-service/src/monitors/MobileActivityMonitor.js` | Track mobile activity per session |
| `SessionManager` (Enhanced) | `whatsapp-service/src/managers/SessionManager.js` | Emit mobile_activity events |

### 2. Laravel Layer

| Component | File | Responsibility |
|-----------|------|----------------|
| `CampaignConflictResolver` | `app/Services/Campaign/CampaignConflictResolver.php` | Business logic for pause/resume campaign |
| `HandleMobileActivityJob` | `app/Jobs/HandleMobileActivityJob.php` | Async handling of mobile detection |
| `AutoResumeCampaignJob` | `app/Jobs/AutoResumeCampaignJob.php` | Auto-resume after tier cooldown |
| `WebhookController` (Enhanced) | `app/Http/Controllers/Api/v1/WhatsApp/WebhookController.php` | Receive mobile_activity webhook |

### 3. Database Changes

| Table | Changes |
|-------|---------|
| `campaigns` | Add: `paused_at`, `pause_reason`, `auto_resume_at` columns |
| `campaigns` | Update status enum: add `paused_mobile` |

---

## ⏱️ Tier-Based Cooldown

Resume otomatis menggunakan **tier-based cooldown** yang disesuaikan dengan konfigurasi anti-ban:

| Tier | Cooldown Period | Description |
|------|-----------------|-------------|
| Tier 1 (New) | 60 seconds | Akun baru, lebih konservatif |
| Tier 2 (Warming) | 45 seconds | Akun dalam proses warming |
| Tier 3 (Established) | 30 seconds | Akun sudah stabil |
| Tier 4 (Trusted) | 20 seconds | Akun trusted dengan history baik |

---

## ⚙️ Configuration

### Environment Variables

```env
# Mobile Conflict Detection
CAMPAIGN_CONFLICT_ENABLED=true
CAMPAIGN_CONFLICT_QUEUE=campaign-conflict
CAMPAIGN_CONFLICT_DEFAULT_COOLDOWN=30
```

### Config File: `config/campaign.php`

```php
return [
    // ... existing config
    
    'mobile_conflict' => [
        'enabled' => env('CAMPAIGN_CONFLICT_ENABLED', true),
        'queue' => env('CAMPAIGN_CONFLICT_QUEUE', 'campaign-conflict'),
        'default_cooldown_seconds' => env('CAMPAIGN_CONFLICT_DEFAULT_COOLDOWN', 30),
        'max_resume_attempts' => 5,
        
        // Tier-based cooldown (in seconds)
        'tier_cooldown' => [
            1 => 60,  // Tier 1: New account
            2 => 45,  // Tier 2: Warming
            3 => 30,  // Tier 3: Established
            4 => 20,  // Tier 4: Trusted
        ],
    ],
];
```

---

## 📊 Flow Sequence

```
User sends message via WhatsApp Mobile
         │
         ▼
┌─────────────────────────────────────┐
│ 1. WhatsApp triggers message_create │
│    event with fromMe=true           │
└─────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ 2. SessionManager detects self-sent │
│    message, identifies as "mobile"  │
└─────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ 3. MobileActivityMonitor tracks     │
│    session as "active"              │
└─────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ 4. Webhook sent to Laravel:         │
│    event: mobile_activity_detected  │
└─────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ 5. HandleMobileActivityJob queued   │
│    on 'campaign-conflict' queue     │
└─────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ 6. CampaignConflictResolver:        │
│    - Find ALL ongoing campaigns     │
│    - Update status → paused_mobile  │
│    - Schedule auto-resume job       │
└─────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│ 7. After tier cooldown (e.g. 30s):  │
│    AutoResumeCampaignJob runs       │
│    - Check for new mobile activity  │
│    - If no activity → Resume        │
│    - If still active → Re-queue     │
└─────────────────────────────────────┘
```

---

## 🔄 Auto-Resume Logic

```
┌─────────────────────────────────────────────────────────────────┐
│                     AUTO-RESUME DECISION FLOW                    │
└─────────────────────────────────────────────────────────────────┘

AutoResumeCampaignJob executes after tier cooldown
                    │
                    ▼
        ┌───────────────────────┐
        │ Check last mobile     │
        │ activity timestamp    │
        └───────────┬───────────┘
                    │
        ┌───────────┴───────────┐
        │                       │
   No new activity         New activity detected
   since pause             within cooldown
        │                       │
        ▼                       ▼
┌───────────────┐       ┌───────────────────┐
│ RESUME        │       │ RE-QUEUE JOB      │
│ Campaign      │       │ (wait another     │
│ status →      │       │  tier cooldown)   │
│ 'ongoing'     │       └───────────────────┘
└───────────────┘               │
                                │
                    ┌───────────┴───────────┐
                    │ Max attempts reached? │
                    └───────────┬───────────┘
                                │
                    ┌───────────┴───────────┐
                    │                       │
                   No                      Yes
                    │                       │
                    ▼                       ▼
            Re-queue again          Force resume with
                                    warning log
```

---

## ✅ Compliance with Development Patterns

Implementasi ini mengikuti **Development Patterns & Guidelines** (docs/architecture/06-development-patterns-guidelines.md):

| Pattern | Compliance | Notes |
|---------|------------|-------|
| Service Layer Pattern | ✅ | CampaignConflictResolver dengan workspace constructor |
| Standard Job Pattern | ✅ | $tries, $timeout, $backoff, failed() method |
| Logging Pattern | ✅ | Log::info/error di semua operasi |
| Return Object Pattern | ✅ | Return (object) dengan success, data, message |
| Database Transaction | ✅ | DB::beginTransaction/commit/rollBack |
| Workspace Scoping | ✅ | All queries scoped by workspace_id |
| Error Handling | ✅ | Try-catch dengan proper rollback |
| Queue Specific | ✅ | Dedicated 'campaign-conflict' queue |

---

## 📈 Monitoring & Metrics

### Key Metrics to Track

1. **Pause Rate**: Persentase campaigns yang di-pause karena mobile activity
2. **Resume Success Rate**: Persentase campaigns yang berhasil auto-resume
3. **Average Pause Duration**: Rata-rata waktu pause sebelum resume
4. **Resume Attempt Count**: Berapa kali resume job di-requeue

### Log Patterns

```php
// Campaign paused
Log::info('Campaign paused due to mobile activity', [
    'workspace_id' => $workspaceId,
    'campaign_id' => $campaignId,
    'session_id' => $sessionId,
    'tier' => $tier,
    'cooldown_seconds' => $cooldownSeconds,
]);

// Campaign auto-resumed
Log::info('Campaign auto-resumed after cooldown', [
    'campaign_id' => $campaignId,
    'workspace_id' => $workspaceId,
    'pause_duration_seconds' => $pauseDuration,
]);

// Re-queue due to continued activity
Log::info('Mobile activity still detected, re-queued resume job', [
    'campaign_id' => $campaignId,
    'attempt' => $attemptNumber,
    'next_check_at' => $nextCheckAt,
]);
```

---

## 🚀 Implementation Checklist

### Phase 1: Database & Config
- [ ] Create migration for campaigns columns
- [ ] Add config/campaign.php mobile_conflict section
- [ ] Add environment variables

### Phase 2: Node.js Components
- [ ] Create/Update MobileActivityMonitor class
- [ ] Update SessionManager for mobile_activity webhook
- [ ] Test webhook emission

### Phase 3: Laravel Components
- [ ] Create CampaignConflictResolver service
- [ ] Create HandleMobileActivityJob
- [ ] Create AutoResumeCampaignJob
- [ ] Update WebhookController

### Phase 4: Integration & Testing
- [ ] Unit tests for all components
- [ ] Integration tests for full flow
- [ ] Manual testing with real WhatsApp

### Phase 5: Monitoring
- [ ] Add logging throughout
- [ ] Create monitoring dashboard (optional)
- [ ] Set up alerts for high pause rates

---

**Document Version:** 1.1  
**Created:** November 29, 2025  
**Updated:** November 29, 2025  
**Author:** Development Team  
**Status:** Draft - Pending Implementation
