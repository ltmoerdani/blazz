# Template System Architecture

> **Document Version:** 1.1  
> **Created:** November 27, 2025  
> **Updated:** November 27, 2025  
> **Status:** Scenario A Approved - Implementation Ready  
> **Chosen Approach:** Draft Template (Local-First)

---

## 📋 Table of Contents

1. [Executive Summary](#executive-summary)
2. [Current System Analysis](#current-system-analysis)
3. [Database Schema](#database-schema)
4. [Code Architecture](#code-architecture)
5. [Problem Statement](#problem-statement)
6. [Proposed Solution](#proposed-solution)
7. [Implementation Roadmap](#implementation-roadmap)
8. [API Reference](#api-reference)

---

## Executive Summary

### Current Behavior
Halaman `/templates/create` saat ini **mensyaratkan koneksi WhatsApp** (Meta API atau WhatsApp WebJS) sebelum user dapat membuat template. Ini membatasi fleksibilitas user dalam mempersiapkan template.

### Proposed Behavior
User dapat membuat template **tanpa memerlukan koneksi WhatsApp terlebih dahulu**. Template disimpan sebagai "draft" lokal dan dapat di-publish ke Meta API atau digunakan langsung untuk WhatsApp WebJS kemudian.

### Feasibility
✅ **MEMUNGKINKAN** - dengan modifikasi pada frontend, backend, dan database schema.

---

## Current System Analysis

### Component Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                         FRONTEND (Vue.js)                           │
│  resources/js/Pages/User/Templates/Add.vue                          │
│  - Checks isWhatsAppConnected before showing form                   │
│  - Submits to /templates/create via POST                            │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      CONTROLLER (Laravel)                           │
│  app/Http/Controllers/User/TemplateController.php                   │
│  - Routes requests to TemplateService                               │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        SERVICE LAYER                                │
│  app/Services/TemplateService.php                                   │
│  - Handles GET (render page with settings)                          │
│  - Handles POST (validate & delegate to TemplateManagementService)  │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    WHATSAPP SERVICE LAYER                           │
│  app/Services/WhatsApp/TemplateManagementService.php                │
│  - Calls Meta Graph API to create/update/delete templates          │
│  - Saves to database ONLY after successful API response             │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                       META GRAPH API                                │
│  https://graph.facebook.com/{version}/{waba_id}/message_templates   │
│  - Requires: access_token, waba_id                                  │
│  - Returns: template ID, status                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Frontend Connection Check Logic

**File:** `resources/js/Pages/User/Templates/Add.vue`

```javascript
// Current implementation
const isWhatsAppConnected = computed(() => {
    // Check for Meta API connection
    const hasMetaApi = settings.value?.whatsapp;

    // Check for WhatsApp Web JS sessions
    const hasWebJsAccounts = props.whatsappAccounts && props.whatsappAccounts.length > 0;

    return hasMetaApi || hasWebJsAccounts;
});
```

**Kondisi yang dicek:**
| Condition | Source | Description |
|-----------|--------|-------------|
| `settings.value?.whatsapp` | Workspace metadata (JSON) | Meta API credentials configured |
| `whatsappAccounts.length > 0` | WhatsAppAccount model | WebJS sessions with status='connected' |

---

## Database Schema

### Table: `templates`

```sql
CREATE TABLE `templates` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uuid` CHAR(50) NOT NULL,
    `workspace_id` BIGINT UNSIGNED NOT NULL,
    `meta_id` VARCHAR(128),           -- ID from Meta Graph API
    `name` VARCHAR(128) NOT NULL,
    `category` VARCHAR(128) NOT NULL, -- UTILITY, MARKETING, AUTHENTICATION
    `language` VARCHAR(128) NOT NULL,
    `metadata` TEXT,                   -- JSON: components, examples, etc.
    `status` VARCHAR(128) NOT NULL,    -- PENDING, APPROVED, REJECTED
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    `deleted_at` TIMESTAMP NULL,       -- Soft delete
    
    FOREIGN KEY (`workspace_id`) REFERENCES `workspaces`(`id`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
);
```

### Current Schema Issues

| Column | Issue | Proposed Fix |
|--------|-------|--------------|
| `meta_id` | Marked as required in migration but should be nullable for drafts | Make nullable |
| `status` | Only supports Meta API statuses | Add `DRAFT` status |
| `metadata` | Named incorrectly, stores `components` | Rename consideration |

### Related Tables

```
templates
    └── workspace_id → workspaces.id
    └── created_by → users.id

whatsapp_accounts
    └── workspace_id → workspaces.id
    └── Used for WebJS provider check
```

---

## Code Architecture

### File Locations

```
app/
├── Http/
│   └── Controllers/
│       └── User/
│           └── TemplateController.php      # Route handler
├── Models/
│   └── Template.php                         # Eloquent model
├── Services/
│   ├── TemplateService.php                  # Business logic
│   └── WhatsApp/
│       └── TemplateManagementService.php    # Meta API integration
└── Providers/
    ├── WhatsAppServiceProvider.php          # DI for WhatsApp services
    └── UtilityServiceProvider.php           # DI for TemplateService

resources/js/
└── Pages/
    └── User/
        └── Templates/
            ├── Add.vue                      # Create template page
            ├── Edit.vue                     # Edit template page
            └── Index.vue                    # Template list page
```

### Service Dependencies

```php
// WhatsAppServiceProvider.php
$this->app->singleton(TemplateManagementService::class, function ($app) {
    $workspace = WorkspaceHelper::getCurrentWorkspace();
    return new TemplateManagementService(
        $workspace->meta_token,        // Required for API calls
        $workspace->meta_version,
        $workspace->meta_app_id,
        $workspace->meta_phone_number_id,
        $workspace->meta_waba_id,      // Required for API calls
        $workspace->id
    );
});
```

**Key Insight:** `TemplateManagementService` adalah singleton yang di-resolve dengan Meta credentials dari workspace. Jika credentials null, API calls akan fail.

---

## Problem Statement

### User Pain Points

1. **Cannot prepare templates in advance** - User harus setup WhatsApp connection dulu sebelum bisa membuat template
2. **Workflow disruption** - Marketing team tidak bisa prepare template saat technical team masih setup connection
3. **Single provider dependency** - Sistem tightly coupled dengan Meta API

### Technical Constraints

| Constraint | Impact |
|------------|--------|
| Meta API required for create | Cannot save template locally without API |
| `meta_id` as identifier | Database assumes all templates are from Meta |
| Singleton with credentials | Service fails if no credentials configured |

---

## Approved Solution: Scenario A (Draft Template)

### ✅ Chosen Approach

**Scenario A: Template "Draft" Lokal** dipilih sebagai pendekatan implementasi karena:

1. **Flexibility** - User bisa membuat template kapanpun tanpa dependency
2. **Multi-Provider Support** - Template bisa digunakan untuk Meta API dan WebJS
3. **Better UX** - Marketing team bisa prepare template tanpa menunggu technical setup
4. **Backward Compatible** - Tidak breaking existing functionality

### Architecture Changes

```
┌─────────────────────────────────────────────────────────────────────┐
│                  SCENARIO A: DRAFT-FIRST ARCHITECTURE               │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────────────────┐ │
│  │   CREATE    │───▶│   DRAFT     │───▶│  PUBLISH TO META        │ │
│  │   Template  │    │   (Local)   │    │  (When ready/optional)  │ │
│  └─────────────┘    └─────────────┘    └─────────────────────────┘ │
│                            │                       │                │
│                            │                       ▼                │
│                            │                 ┌───────────┐          │
│                            │                 │  PENDING  │          │
│                            │                 └─────┬─────┘          │
│                            │                       │                │
│                            ▼                       ▼                │
│                     ┌─────────────┐         ┌───────────┐          │
│                     │  USE WITH   │         │ APPROVED/ │          │
│                     │   WebJS     │         │ REJECTED  │          │
│                     │  (Direct)   │         └───────────┘          │
│                     └─────────────┘                                 │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### New Status Flow (Scenario A)

```
                    ┌─────────────────────────────────────┐
                    │         USE WITH WebJS              │
                    │      (No approval needed)           │
                    └─────────────────────────────────────┘
                                    ▲
                                    │
DRAFT ──────────────────────────────┼──────────────────────────────────┐
  │                                 │                                  │
  │  [Save as Draft]                │                                  │
  │                                 │                                  │
  └──────────────────▶ PENDING ─────┴──────▶ APPROVED ─────────────────┤
     [Publish to Meta]      │                   │                      │
                            │                   │                      │
                            ▼                   ▼                      │
                       REJECTED            [Use with Meta API]         │
                            │                                          │
                            └──▶ [Edit & Resubmit] ────────────────────┘
```

### Key Principles (Scenario A)

1. **Template Creation is Always Available** - No WhatsApp connection required
2. **Draft First** - All templates start as DRAFT
3. **Optional Publishing** - Meta API publishing is optional
4. **WebJS Always Works** - Draft templates can be used with WebJS immediately
5. **User Choice** - User decides when/if to publish to Meta

### Required Changes Summary

#### 1. Frontend (`Add.vue`)
- Remove `isWhatsAppConnected` gate
- Add "Save as Draft" button
- Add "Submit to Meta" button (conditional on Meta API configured)
- Show provider compatibility badges

#### 2. Backend (`TemplateService.php`)
- Add `saveDraft()` method - saves to DB only
- Rename existing `createTemplate()` to `publishToMeta()`
- Handle template without Meta credentials gracefully

#### 3. Database Migration
```sql
ALTER TABLE templates MODIFY meta_id VARCHAR(128) NULL;
-- Status will now include: DRAFT, PENDING, APPROVED, REJECTED
```

#### 4. Model (`Template.php`)
- Add status constants
- Add scope for drafts
- Add `canUseWithProvider()` method

---

## Implementation Roadmap

### Phase 1: Database & Model (Low Risk)
- [ ] Create migration to make `meta_id` nullable
- [ ] Update Template model with new status constants
- [ ] Add model scopes for filtering

### Phase 2: Backend Service (Medium Risk)
- [ ] Add `saveDraft()` method to TemplateService
- [ ] Refactor `createTemplate()` to support draft-first workflow
- [ ] Add `publishToMeta()` as separate action

### Phase 3: Frontend (Low Risk)
- [ ] Remove connection gate in Add.vue
- [ ] Add dual-button UI (Save Draft / Submit to Meta)
- [ ] Update template list to show status badges

### Phase 4: Integration (Medium Risk)
- [ ] Test WebJS direct usage with draft templates
- [ ] Test Meta API submission flow
- [ ] Handle edge cases (offline, API errors)

---

## API Reference

### Current Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/templates` | List all templates |
| GET | `/templates/create` | Show create form |
| POST | `/templates/create` | Create template (currently requires Meta API) |
| GET | `/templates/{uuid}` | Get template detail |
| PUT | `/templates/{uuid}` | Update template |
| DELETE | `/templates/{uuid}` | Delete template |
| GET | `/templates/sync` | Sync from Meta API |

### Proposed New Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/templates/draft` | Save template as draft (local only) |
| POST | `/templates/{uuid}/publish` | Publish draft to Meta API |
| POST | `/templates/{uuid}/duplicate` | Clone existing template |

---

## Appendix

### Configuration Files

**Workspace metadata structure:**
```json
{
    "whatsapp": {
        "access_token": "EAAxxxxxxxx",
        "app_id": "123456789",
        "phone_number_id": "987654321",
        "waba_id": "111222333"
    }
}
```

### Template Categories (Meta API)
- `UTILITY` - Transactional messages
- `MARKETING` - Promotional messages  
- `AUTHENTICATION` - OTP/verification codes

### Template Languages
Defined in `config/languages.php`

---

## Decision Record

### Why Scenario A?

| Criteria | Scenario A (Draft) | Scenario B (Require Connection) |
|----------|-------------------|--------------------------------|
| User Flexibility | ✅ High | ❌ Limited |
| Implementation Complexity | Medium | Low |
| Breaking Changes | None | None |
| Multi-Provider Support | ✅ Excellent | ⚠️ Coupled to Meta |
| Team Collaboration | ✅ Marketing can prepare | ❌ Blocked by tech setup |

**Decision:** Scenario A provides better user experience and future-proofs the system for multi-provider architecture.

---

## References

- [Meta WhatsApp Business API - Message Templates](https://developers.facebook.com/docs/whatsapp/business-management-api/message-templates)
- [Laravel Service Container](https://laravel.com/docs/container)
- Internal: `docs/whatsapp-webjs-integration/`
