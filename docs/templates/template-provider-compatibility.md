# Template Provider Compatibility Matrix

> **Document Version:** 1.1  
> **Created:** November 27, 2025  
> **Updated:** November 27, 2025  
> **Applies To:** Scenario A Implementation

---

## Overview

Dengan **Scenario A (Draft-First)**, template system mendukung multiple WhatsApp providers dengan fleksibilitas tinggi. Dokumen ini menjelaskan kompatibilitas template dengan masing-masing provider.

---

## Scenario A: Draft-First Compatibility

### How It Works

```
┌───────────────┐     ┌───────────────┐     ┌───────────────┐
│    DRAFT      │     │    PENDING    │     │   APPROVED    │
│   Template    │     │   (In Review) │     │   Template    │
└───────┬───────┘     └───────┬───────┘     └───────┬───────┘
        │                     │                     │
        ▼                     ▼                     ▼
   ┌──────────┐       ┌──────────┐       ┌──────────┐
   │  WebJS   │       │  WebJS   │       │  WebJS   │
   │    ✅    │       │    ✅    │       │    ✅    │
   └──────────┘       └──────────┘       └──────────┘
   ┌──────────┐       ┌──────────┐       ┌──────────┐
   │ Meta API │       │ Meta API │       │ Meta API │
   │    ❌    │       │    ❌    │       │    ✅    │
   └──────────┘       └──────────┘       └──────────┘
```

### Key Insight: WebJS Always Works

Dalam Scenario A, **WebJS dapat menggunakan template dengan status apapun** (DRAFT, PENDING, APPROVED). Ini memberikan fleksibilitas maksimal untuk user.

---

## Provider Types

| Provider | Description | Approval Required | Real-time Delivery |
|----------|-------------|-------------------|-------------------|
| **Meta API** | Official WhatsApp Business API | ✅ Yes (Meta review) | ✅ Yes |
| **WhatsApp WebJS** | Unofficial web-based connection | ❌ No | ✅ Yes |
| **Local (Draft)** | Stored locally, not sent | N/A | N/A |

---

## Template Status vs Provider Compatibility

| Template Status | Meta API | WebJS | Notes |
|-----------------|----------|-------|-------|
| `DRAFT` | ❌ Cannot use | ✅ Can use | Not yet submitted to Meta |
| `PENDING` | ❌ Waiting review | ✅ Can use | Submitted, awaiting Meta approval |
| `APPROVED` | ✅ Can use | ✅ Can use | Full compatibility |
| `REJECTED` | ❌ Cannot use | ✅ Can use* | *Content may violate Meta policy |

---

## Feature Comparison

### Meta API Templates

**Advantages:**
- Official support from WhatsApp/Meta
- Supports all template features (buttons, media, etc.)
- Better deliverability
- Required for sending to users who haven't messaged in 24h

**Limitations:**
- Requires approval (can take hours to days)
- Must follow strict Meta guidelines
- Rate limits apply
- Costs per message

### WebJS Templates

**Advantages:**
- No approval required
- Instant use after creation
- No per-message cost (beyond infrastructure)
- Full flexibility in content

**Limitations:**
- Unofficial, may break with WhatsApp updates
- Risk of account ban if misused
- Cannot initiate conversations after 24h window
- No official media hosting

---

## Template Components Compatibility

| Component | Meta API | WebJS | Notes |
|-----------|----------|-------|-------|
| **Header - Text** | ✅ | ✅ | Full support |
| **Header - Image** | ✅ | ⚠️ Partial | WebJS requires local/URL media |
| **Header - Video** | ✅ | ⚠️ Partial | WebJS has size limits |
| **Header - Document** | ✅ | ⚠️ Partial | WebJS has format limits |
| **Body - Text** | ✅ | ✅ | Full support |
| **Body - Variables** | ✅ | ✅ | `{{1}}`, `{{2}}`, etc. |
| **Body - Formatting** | ✅ | ✅ | Bold, italic, strikethrough |
| **Footer** | ✅ | ✅ | Full support |
| **Button - URL** | ✅ | ⚠️ Partial | WebJS as regular text |
| **Button - Phone** | ✅ | ⚠️ Partial | WebJS as regular text |
| **Button - Quick Reply** | ✅ | ❌ | Not supported in WebJS |
| **Button - Copy Code** | ✅ | ⚠️ Partial | WebJS as regular text |

### Legend
- ✅ Full support
- ⚠️ Partial support (with limitations)
- ❌ Not supported

---

## Use Case Recommendations

### Scenario 1: Marketing Campaign
**Recommended:** Meta API with MARKETING template
- Better deliverability
- Can reach users outside 24h window
- Trackable metrics

### Scenario 2: Transactional Notifications
**Recommended:** Meta API with UTILITY template
- Lower cost than marketing
- Faster approval
- Official support

### Scenario 3: Quick Testing / Development
**Recommended:** Draft + WebJS
- Instant deployment
- No approval wait
- Easy iteration

### Scenario 4: High-Volume Personal Use
**Recommended:** WebJS with drafts
- No per-message cost
- Flexible content
- Risk: account safety

---

## Migration Path: Draft → Meta API

```
1. Create Draft Template
   └─ Saved locally with status=DRAFT
   
2. Test with WebJS (optional)
   └─ Send test messages using draft
   
3. Publish to Meta
   └─ Submit via /templates/{uuid}/publish
   └─ Status changes to PENDING
   
4. Wait for Approval
   └─ Webhook updates status to APPROVED/REJECTED
   
5. Use in Production
   └─ Both Meta API and WebJS can use
```

---

## Database Flags Reference

```sql
-- Query drafts only
SELECT * FROM templates 
WHERE workspace_id = ? AND status = 'DRAFT' AND deleted_at IS NULL;

-- Query Meta-ready templates
SELECT * FROM templates 
WHERE workspace_id = ? AND status = 'APPROVED' AND meta_id IS NOT NULL AND deleted_at IS NULL;

-- Query WebJS-compatible templates (all non-deleted)
SELECT * FROM templates 
WHERE workspace_id = ? AND status IN ('DRAFT', 'PENDING', 'APPROVED') AND deleted_at IS NULL;

-- Query usable for campaigns
SELECT * FROM templates 
WHERE workspace_id = ? AND status != 'REJECTED' AND deleted_at IS NULL;
```

---

## API Response Examples

### Draft Template (can use with WebJS)
```json
{
    "uuid": "abc-123",
    "name": "welcome_message",
    "status": "DRAFT",
    "status_label": "Draft",
    "status_color": "gray",
    "meta_id": null,
    "can_use_meta": false,
    "can_use_webjs": true,
    "is_draft": true
}
```

### Approved Template (can use with both)
```json
{
    "uuid": "def-456",
    "name": "order_confirmation",
    "status": "APPROVED",
    "status_label": "Approved",
    "status_color": "green",
    "meta_id": "789012345",
    "can_use_meta": true,
    "can_use_webjs": true,
    "is_draft": false
}
```

### Pending Template (submitted to Meta)
```json
{
    "uuid": "ghi-789",
    "name": "promo_message",
    "status": "PENDING",
    "status_label": "Pending Review",
    "status_color": "yellow",
    "meta_id": "123456789",
    "can_use_meta": false,
    "can_use_webjs": true,
    "is_draft": false
}
```

---

## Related Documentation

- [Template System Architecture](./template-system-architecture.md)
- [Template Independence Implementation](./template-independence-implementation.md)
- [WhatsApp WebJS Integration](../whatsapp-webjs-integration/)
