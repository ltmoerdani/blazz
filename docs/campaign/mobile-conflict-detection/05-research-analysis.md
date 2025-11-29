# 🔬 Research & Analysis

## Mobile Activity Conflict Detection System

**Version:** 1.1  
**Last Updated:** November 29, 2025

---

## 1. Executive Summary

Dokumen ini berisi hasil research dan analisis mendalam mengenai:

1. **Kapabilitas whatsapp-web.js** untuk deteksi aktivitas mobile
2. **State codebase saat ini** terkait deteksi mobile
3. **Arsitektur solusi** untuk pause campaign saat mobile aktif
4. **Perbandingan pendekatan** dan rekomendasi

---

## 2. Research: whatsapp-web.js

### 2.1 Library Overview

| Attribute | Value |
|-----------|-------|
| **Library** | whatsapp-web.js |
| **Version** | 1.34.2 |
| **GitHub** | https://github.com/pedroslopez/whatsapp-web.js |
| **Documentation** | https://docs.wwebjs.dev/ |

### 2.2 Key Feature: Message Device Type

whatsapp-web.js menyediakan property `Message.deviceType` yang dapat mendeteksi dari device mana pesan dikirim.

```javascript
// Possible values
message.deviceType = 'android' | 'ios' | 'web' | 'unknown';
```

**Source Detection:**

| Device Type | Description |
|-------------|-------------|
| `android` | Pesan dikirim dari Android WhatsApp app |
| `ios` | Pesan dikirim dari iOS WhatsApp app |
| `web` | Pesan dikirim dari WhatsApp Web (termasuk API) |
| `unknown` | Device tidak terdeteksi |

### 2.3 Key Event: message_create

Event `message_create` triggered untuk SETIAP pesan yang dibuat di chat, termasuk pesan yang dikirim dari device lain (mobile).

```javascript
client.on('message_create', async (message) => {
    // Triggered untuk semua pesan yang dibuat
    // Termasuk pesan yang dikirim dari mobile
    
    if (message.fromMe && message.deviceType !== 'web') {
        // Ini adalah pesan yang dikirim dari mobile
        console.log('Mobile message detected:', message.deviceType);
    }
});
```

**Penting:**
- `message` event hanya triggered untuk pesan MASUK
- `message_create` triggered untuk SEMUA pesan (masuk + keluar)
- Untuk deteksi aktivitas mobile, gunakan `message_create`

### 2.4 Limitations

| Limitation | Impact | Mitigation |
|------------|--------|------------|
| Requires active session | Tidak bisa detect jika session disconnected | Health check monitoring |
| Event delay | 100-500ms delay pada event | Acceptable untuk use case ini |
| Device type accuracy | Kadang return `unknown` | Treat `unknown` as potential mobile |

---

## 3. Codebase Analysis

### 3.1 Existing Mobile Detection

#### SessionManager.js

**Location:** `whatsapp-service/managers/SessionManager.js`

**Current Implementation:**

```javascript
// Sudah ada handler untuk message_create
client.on('message_create', async (message) => {
    if (message.fromMe) {
        this.sendWebhook({
            event: 'message_sent',
            data: {
                to: message.to,
                body: message.body,
                source: 'message_create_event', // Marker for mobile
                deviceType: message.deviceType,
            }
        });
    }
});
```

**Findings:**
- ✅ Sudah ada handler untuk `message_create`
- ✅ Sudah mengirim `source: 'message_create_event'` sebagai marker
- ✅ Sudah include `deviceType` di webhook
- ❌ Belum ada logic untuk trigger campaign pause

#### WebhookController.php

**Location:** `app/Http/Controllers/WhatsappWebhookController.php`

**Current Implementation:**

```php
protected function handleMessageSent(array $data): void
{
    $source = $data['source'] ?? 'api';
    $deviceSource = $source === 'message_create_event' ? 'mobile' : 'web';
    
    // Store dengan device_source
    Message::create([
        'device_source' => $deviceSource,
        // ...
    ]);
}
```

**Findings:**
- ✅ Sudah bisa distinguish `mobile` vs `web` source
- ✅ Sudah menyimpan `device_source` ke database
- ❌ Belum ada trigger untuk campaign pause

### 3.2 Campaign System Analysis

#### Campaign.php Model

**Location:** `app/Models/Campaign.php`

**Current Statuses:**

```php
const STATUS_DRAFT = 'draft';
const STATUS_SCHEDULED = 'scheduled';
const STATUS_ONGOING = 'ongoing';
const STATUS_PAUSED = 'paused';
const STATUS_COMPLETED = 'completed';
const STATUS_CANCELLED = 'cancelled';
```

**Required Addition:**

```php
const STATUS_PAUSED_MOBILE = 'paused_mobile';
```

#### campaigns Table Schema

**Current Columns:**

| Column | Type | Description |
|--------|------|-------------|
| status | string | Campaign status |
| started_at | timestamp | Start time |
| completed_at | timestamp | Completion time |

**Required Additions:**

| Column | Type | Description |
|--------|------|-------------|
| paused_at | timestamp | Time when paused |
| resume_after | integer | Seconds to wait before resume |
| pause_reason | string | Reason for pause |

---

## 4. Architecture Analysis

### 4.1 Current Flow (Without Conflict Detection)

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   User Start    │───▶│    Campaign     │───▶│  Send Messages  │
│    Campaign     │    │   Processing    │    │    to Queue     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                                                       │
                                                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   WhatsApp      │◀───│   Job Worker    │◀───│   Queue Job     │
│    Delivery     │    │   Process       │    │   Dispatch      │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

**Problem:** Tidak ada intervensi saat user sedang aktif di mobile.

### 4.2 Proposed Flow (With Conflict Detection)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                          CAMPAIGN RUNNING                                │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────┐    ┌─────────────────┐
│  Mobile User    │───▶│   WhatsApp      │
│  Sends Message  │    │   Client        │
└─────────────────┘    └─────────────────┘
                                    │
                                    │ message_create event
                                    ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│  MobileActivity │◀───│  SessionManager │───▶│    Webhook      │
│    Monitor      │    │    (Node.js)    │    │   to Laravel    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                                                       │
                                                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│    Campaign     │◀───│  HandleMobile   │◀───│  Webhook        │
│    PAUSED       │    │  ActivityJob    │    │  Controller     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                                    │
                                    │ Schedule resume
                                    ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│    Campaign     │◀───│   AutoResume    │◀───│  Check Last     │
│    RESUMED      │    │  CampaignJob    │    │  Mobile Activity│
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

---

## 5. Approach Comparison

### 5.1 Option A: Per-Contact Pause (Rejected)

**Concept:** Hanya pause pengiriman ke kontak yang sedang aktif.

**Pros:**
- Granular control
- Campaign tetap berjalan untuk kontak lain

**Cons:**
- Kompleks untuk tracking
- Tidak natural dari perspektif user
- Sulit untuk resume coordination

**Decision:** ❌ Rejected

### 5.2 Option B: Entire Campaign Pause (Selected)

**Concept:** Pause seluruh campaign saat terdeteksi aktivitas mobile.

**Pros:**
- Sederhana dan predictable
- Consistent behavior
- Easy to implement dan debug

**Cons:**
- Semua kontak affected (mitigated by tier-based resume)
- Potential delay untuk kontak yang tidak chatting

**Decision:** ✅ Selected

---

## 6. Tier-Based Resume Analysis

### 6.1 Tier Definitions

| Tier | Account Age | Message Volume | Cooldown |
|------|-------------|----------------|----------|
| 1 | < 30 days | < 100/day | 60s |
| 2 | 30-90 days | 100-500/day | 45s |
| 3 | 90-180 days | 500-1000/day | 30s |
| 4 | > 180 days | > 1000/day | 20s |

### 6.2 Rationale

**Tier 1 (New Account):**
- Higher risk of ban
- Conservative cooldown
- Protect account reputation

**Tier 4 (Established Account):**
- Lower risk
- Faster resume acceptable
- Minimize campaign delay

### 6.3 Auto-Resume Logic

```
┌─────────────────┐
│  Resume Job     │
│  Triggered      │
└────────┬────────┘
         │
         ▼
┌─────────────────┐     Yes    ┌─────────────────┐
│  Check Last     │───────────▶│   Reschedule    │
│  Mobile Activity│            │   Next Tier     │
└────────┬────────┘            └─────────────────┘
         │ No Activity
         ▼
┌─────────────────┐
│  Resume         │
│  Campaign       │
└─────────────────┘
```

---

## 7. Risk Assessment

### 7.1 Technical Risks

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| False positive detection | Low | Medium | Device type validation |
| Webhook failure | Medium | Low | Retry mechanism + queue |
| Race condition | Low | Medium | Database transactions |
| Session disconnect | Medium | Medium | Reconnection handling |

### 7.2 Business Risks

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Campaign delay | High | Low | Tier-based cooldown |
| User confusion | Medium | Medium | UI indicators |
| Over-pausing | Low | Medium | Max attempt limit |

---

## 8. Performance Considerations

### 8.1 Database Impact

| Operation | Frequency | Query Complexity | Index Needed |
|-----------|-----------|------------------|--------------|
| Find ongoing campaigns | Per mobile activity | Simple | status + session_id |
| Update campaign status | Per pause/resume | Simple | id (PK) |
| Query last activity | Per resume attempt | API call | N/A |

### 8.2 Queue Impact

| Job Type | Expected Volume | Priority | Timeout |
|----------|----------------|----------|---------|
| HandleMobileActivityJob | ~10-50/hour | High | 30s |
| AutoResumeCampaignJob | ~10-50/hour | Normal | 60s |

### 8.3 Memory Impact (Node.js)

| Data Structure | Size per Session | Cleanup Interval |
|----------------|------------------|------------------|
| Activity Map | ~100 bytes | 60 seconds |
| Message Cache | ~500 bytes | On activity |

---

## 9. Monitoring Requirements

### 9.1 Metrics to Track

| Metric | Type | Alert Threshold |
|--------|------|-----------------|
| Campaigns paused/hour | Counter | > 100/hour |
| Average pause duration | Gauge | > 120 seconds |
| Resume failure rate | Rate | > 5% |
| Webhook latency | Histogram | p99 > 500ms |

### 9.2 Logs to Capture

| Event | Log Level | Data |
|-------|-----------|------|
| Mobile activity detected | INFO | session_id, device_type |
| Campaign paused | INFO | campaign_id, session_id |
| Campaign resumed | INFO | campaign_id, pause_duration |
| Resume rescheduled | DEBUG | campaign_id, attempt_number |
| Max attempts reached | WARN | campaign_id |

---

## 10. Conclusion

### 10.1 Feasibility Assessment

| Aspect | Status | Notes |
|--------|--------|-------|
| Technical feasibility | ✅ High | Existing infrastructure supports implementation |
| Codebase readiness | ✅ High | 70% existing code can be reused |
| Risk level | ✅ Low | Well-understood problem domain |
| Implementation effort | ✅ Medium | 2-3 days estimated |

### 10.2 Recommendations

1. **Implement entire campaign pause** (Option B)
2. **Use tier-based cooldown** for intelligent resume
3. **Add max attempt limit** (5 attempts) for force resume
4. **Add comprehensive monitoring** for production visibility
5. **Create feature toggle** for gradual rollout

### 10.3 Success Criteria

| Criteria | Target |
|----------|--------|
| Detection accuracy | > 99% |
| False positive rate | < 1% |
| Average pause duration | < 60 seconds |
| Resume success rate | > 95% |
| No impact on campaign completion rate | 0% decrease |

---

## 11. References

### 11.1 External Documentation

- [whatsapp-web.js Documentation](https://docs.wwebjs.dev/)
- [whatsapp-web.js GitHub](https://github.com/pedroslopez/whatsapp-web.js)
- [Message API Reference](https://docs.wwebjs.dev/Message.html)

### 11.2 Internal Documentation

- `/docs/campaign/` - Campaign system documentation
- `/docs/whatsapp-migration/` - WhatsApp integration docs
- `/docs/architecture/` - System architecture

---

**Document Version:** 1.1  
**Last Updated:** November 29, 2025
