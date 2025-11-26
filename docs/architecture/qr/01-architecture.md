# WhatsApp QR Code - System Architecture

**Version:** 2.0  
**Last Updated:** November 22, 2025  

---

## 🏗️ Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                         FRONTEND (Vue.js)                        │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  WhatsAppAccounts.vue                                       │ │
│  │  - QR Display Modal                                         │ │
│  │  - Status Polling (3s interval, 18s timeout)               │ │
│  │  - WebSocket Listeners (private-workspace.{id})            │ │
│  └────────────────────────────────────────────────────────────┘ │
└──────────────┬────────────────────────────────┬─────────────────┘
               │                                │
               │ HTTP API                       │ WebSocket
               │                                │
┌──────────────▼────────────────┐   ┌──────────▼──────────────────┐
│   LARAVEL BACKEND (PHP)       │   │  LARAVEL REVERB (WebSocket) │
│                               │   │                              │
│  - WebhookController          │   │  - Port: 8080               │
│  - AccountController          │   │  - Private Channels         │
│  - HMAC Middleware            │   │  - Broadcast Events         │
│  - Database (MySQL)           │   └─────────────────────────────┘
└──────────────┬────────────────┘
               │
               │ HMAC-secured Webhooks
               │
┌──────────────▼────────────────────────────────────────────────┐
│            WHATSAPP SERVICE (Node.js)                          │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │  SessionManager.js                                        │ │
│  │  - QR Generation (7-9s)                                  │ │
│  │  - Phone Extraction (3-4s with retry)                   │ │
│  │  - Event Handlers (ready, authenticated, qr_received)   │ │
│  │  - Webhook Notifier (HMAC signing)                      │ │
│  └──────────────────────────────────────────────────────────┘ │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │  WhatsApp Web.js (Puppeteer-based)                       │ │
│  │  - LocalAuth Strategy                                     │ │
│  │  - Chromium Headless                                      │ │
│  │  - Session Storage: ./whatsapp-service/.wwebjs_auth/     │ │
│  └──────────────────────────────────────────────────────────┘ │
└───────────────────────────────────────────────────────────────┘
```

---

## 🔄 Complete Flow Diagram

### Phase 1: QR Code Generation (7-9 seconds)

```
USER ACTION: Click "Add WhatsApp Number"
     │
     ▼
┌─────────────────────────────────────────────────┐
│ Frontend: Create Account Request                │
│ POST /settings/whatsapp-accounts                │
│ { provider_type: 'webjs' }                     │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Laravel: Create DB Record                       │
│ - Generate UUID                                 │
│ - Generate session_id: webjs_1_{timestamp}     │
│ - Status: qr_scanning                          │
│ - Store in whatsapp_accounts table             │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Laravel: HTTP Request to Node.js                │
│ POST http://127.0.0.1:3001/sessions/create      │
│ { workspace_id, session_id, user_id }          │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Node.js: Initialize WhatsApp Client             │
│ 1. Create new Client instance                   │
│ 2. Set LocalAuth strategy                       │
│ 3. Configure Puppeteer (30s timeout)           │
│ 4. Attach event listeners                      │
│ 5. client.initialize()                         │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼ (Wait 7-9 seconds)
┌─────────────────────────────────────────────────┐
│ WhatsApp Web.js: QR Event Fired                │
│ Event: 'qr'                                     │
│ Data: QR code string                            │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Node.js: Generate QR Image                      │
│ - Convert string to PNG (base64)               │
│ - Add 300s expiry timestamp                    │
│ - Store in memory (this.qrCode)                │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Node.js: Send QR Webhook to Laravel             │
│ POST /api/whatsapp/webhooks/webjs              │
│ Event: qr_code_generated                       │
│ HMAC: SHA256 signature                         │
│ Data: { qr_code, expires_in, workspace_id }   │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Laravel: Update DB + Broadcast                  │
│ 1. Validate HMAC signature                     │
│ 2. Update whatsapp_accounts.qr_code            │
│ 3. Broadcast to private-workspace.{id}        │
│ 4. Frontend receives via WebSocket             │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Frontend: Display QR Code in Modal             │
│ - Show QR image                                 │
│ - Start 5-minute countdown                     │
│ - Start status polling (3s interval)           │
└─────────────────────────────────────────────────┘
```

---

### Phase 2: User Scans QR (Instant)

```
USER ACTION: Scan QR with WhatsApp Mobile App
     │
     ▼
┌─────────────────────────────────────────────────┐
│ WhatsApp Servers: Validate QR                   │
│ - Check QR not expired                          │
│ - Verify device info                            │
│ - Send authentication challenge                 │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ WhatsApp Web.js: Authenticated Event            │
│ Event: 'authenticated'                          │
│ Data: Session data, credentials                 │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Node.js: Send Authenticated Webhook             │
│ POST /api/whatsapp/webhooks/webjs              │
│ Event: session_authenticated                   │
│ Data: { workspace_id, session_id }            │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Laravel: Queue Processing                       │
│ Job: ProcessWhatsAppWebhookJob                 │
│ Queue: whatsapp-urgent                         │
│ Action: Update status to 'authenticated'       │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Frontend: Polling Detects 'authenticated'       │
│ Status: authenticated, phone_number: null       │
│ Counter: authWithoutPhoneAttempts = 1          │
└─────────────────────────────────────────────────┘
```

---

### Phase 3: Phone Extraction (3-4 seconds)

```
WhatsApp Web.js: Ready Event Fired
     │
     ▼
┌─────────────────────────────────────────────────┐
│ Node.js: extractPhoneNumberSafely()             │
│                                                 │
│ STEP 1: Initial Delay                          │
│ - Wait 2500ms (WhatsApp Web.js initialization)│
│                                                 │
│ STEP 2: Retry Loop (15 attempts × 500ms)      │
│ - Attempt 1: Check client.info.wid.user       │
│   → undefined (library not ready)              │
│ - Attempt 2: Check again after 500ms           │
│   → undefined                                  │
│ - Attempt 3: Check again after 500ms           │
│   → "62811801641" ✅ SUCCESS!                  │
│                                                 │
│ Total Time: 2500ms + (3 × 500ms) = 4000ms     │
│                                                 │
│ FALLBACK (if all retries fail):                │
│ - Access window.Store.Conn.me.user directly    │
│   via Puppeteer page.evaluate()                │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Node.js: Phone Extracted Successfully           │
│ phoneNumber: "62811801641"                      │
│ extractionMethod: "client.info.wid"             │
│ platform: "iphone"                              │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Node.js: Send session_ready Webhook             │
│ POST /api/whatsapp/webhooks/webjs              │
│ Event: session_ready                           │
│ Data: {                                        │
│   workspace_id: 1,                             │
│   session_id: "webjs_1_...",                   │
│   phone_number: "62811801641",                 │
│   status: "connected",                         │
│   platform: "iphone",                          │
│   extraction_method: "client.info.wid"         │
│ }                                               │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Laravel: handleSessionReady() INLINE            │
│ (NOT queued - processed synchronously)          │
│                                                 │
│ STEP 1: Validate phone number                  │
│ - Check not null/undefined                     │
│                                                 │
│ STEP 2: Check for auto-primary                 │
│ - Count connected accounts in workspace        │
│ - If 0: Set is_primary = true                  │
│                                                 │
│ STEP 3: Cleanup duplicates                     │
│ - Find accounts with same phone_number         │
│ - Set their phone_number = NULL                │
│ - Soft delete (deleted_at = now())             │
│ - This bypasses unique constraint              │
│                                                 │
│ STEP 4: Update database                        │
│ UPDATE whatsapp_accounts SET                   │
│   status = 'connected',                        │
│   phone_number = '62811801641',                │
│   is_primary = true,                           │
│   last_connected_at = NOW(),                   │
│   last_activity_at = NOW()                     │
│ WHERE session_id = 'webjs_1_...'               │
│                                                 │
│ STEP 5: Broadcast event                        │
│ Event: WhatsAppAccountStatusChangedEvent        │
│ Channel: private-workspace.1                   │
│ Data: { phone_number, is_primary, status }    │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ Frontend: Polling Gets Updated Data             │
│ - Next poll (3s): phone_number = "62811801641" │
│ - Status: connected                             │
│ - is_primary: true                              │
│ - Modal auto-closes                             │
│ - Account appears in list with "Primary" badge │
└─────────────────────────────────────────────────┘
```

---

## 🔧 Key Components

### 1. **SessionManager.js** (Node.js)

**Location:** `/whatsapp-service/src/managers/SessionManager.js`

**Responsibilities:**
- WhatsApp client lifecycle management
- QR generation and event handling
- Phone number extraction with retry
- Webhook delivery to Laravel

**Key Methods:**
```javascript
async createSession(workspaceId, sessionId, userId)
async extractPhoneNumberSafely(client, sessionId)
async sendToLaravel(event, data)
```

---

### 2. **WebhookController.php** (Laravel)

**Location:** `/app/Http/Controllers/Api/v1/WhatsApp/WebhookController.php`

**Responsibilities:**
- Receive webhooks from Node.js
- HMAC signature validation
- Process session_ready inline (synchronous)
- Manage database updates

**Key Methods:**
```php
public function webhook(Request $request)
private function handleSessionReady(array $data)
```

---

### 3. **WhatsAppAccounts.vue** (Frontend)

**Location:** `/resources/js/Pages/User/Settings/WhatsAppAccounts.vue`

**Responsibilities:**
- QR code display
- Status polling (3s interval, 18s timeout)
- WebSocket event handling
- Account list management

**Key Variables:**
```javascript
const pollingInterval = 3000 // ms
const maxAttemptsWithoutPhone = 6 // 18 seconds
```

---

## 🗄️ Database Schema

### Table: `whatsapp_accounts`

```sql
CREATE TABLE `whatsapp_accounts` (
  `id` bigint unsigned PRIMARY KEY AUTO_INCREMENT,
  `uuid` char(36) NOT NULL UNIQUE,
  `workspace_id` bigint unsigned NOT NULL,
  `session_id` varchar(191) NOT NULL UNIQUE,
  `phone_number` varchar(50) NULL,
  `status` enum('qr_scanning','authenticated','connected','disconnected','failed'),
  `is_primary` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `qr_code` text NULL,
  `last_connected_at` timestamp NULL,
  `last_activity_at` timestamp NULL,
  `metadata` json NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  `deleted_at` timestamp NULL,
  
  UNIQUE KEY `unique_active_phone_workspace` 
    (`phone_number`, `workspace_id`, `status`),
  
  INDEX `idx_workspace_status` (`workspace_id`, `status`),
  INDEX `idx_session_id` (`session_id`)
);
```

**Critical Constraint:**
- `unique_active_phone_workspace`: Prevents duplicate (phone, workspace, status)
- **Solution:** Set `phone_number = NULL` before soft delete to bypass constraint

---

## 🔒 Security Architecture

### HMAC Authentication

**Algorithm:** SHA256  
**Secret:** Shared between Node.js and Laravel (`.env`)

**Flow:**
```
1. Node.js: Generate signature
   - Payload: { event, data }
   - Timestamp: Unix timestamp
   - Signature: HMAC-SHA256(timestamp + JSON.stringify(payload), secret)

2. Send to Laravel with headers:
   - X-HMAC-Signature: <signature>
   - X-Timestamp: <timestamp>

3. Laravel Middleware: Verify
   - Regenerate signature with same algorithm
   - Compare using hash_equals() (timing-safe)
   - Validate timestamp (max age: 5 minutes)
```

---

## 📊 Performance Characteristics

| Operation | Time | Details |
|-----------|------|---------|
| QR Generation | 7-9s | Puppeteer init + WhatsApp Web.js |
| Phone Extraction | 3-4s | 2.5s delay + 1-2 retry attempts |
| Webhook Delivery | 100-200ms | HTTP request to Laravel |
| Database Update | 50-100ms | Single UPDATE query |
| Broadcast | 50-100ms | WebSocket push via Reverb |
| **Total Flow** | **10-14s** | End-to-end user experience |

---

## 🌐 WebSocket Architecture

**Server:** Laravel Reverb (port 8080)  
**Protocol:** WebSocket  
**Authentication:** Sanctum token via Authorization header

**Channel Structure:**
```javascript
// Private channel (requires authentication)
Echo.private(`workspace.${workspaceId}`)
  .listen('WhatsAppQRGeneratedEvent', (e) => { ... })
  .listen('WhatsAppAccountStatusChangedEvent', (e) => { ... });
```

**Events:**
1. `WhatsAppQRGeneratedEvent` - QR code generated
2. `WhatsAppAccountStatusChangedEvent` - Status/phone updated

---

## 🔄 State Machine

```
┌─────────────┐
│ qr_scanning │ ← Initial state (DB record created)
└──────┬──────┘
       │
       │ User scans QR
       ▼
┌───────────────┐
│ authenticated │ ← WhatsApp verified QR
└──────┬────────┘
       │
       │ Phone extracted (3-4s)
       ▼
┌───────────┐
│ connected │ ← Final state (with phone_number)
└───────────┘

Error states:
- failed: Extraction failed after all retries
- disconnected: User logged out or session expired
```

---

## 📈 Scalability Considerations

**Current Architecture:**
- **Strategy:** LocalAuth (file-based sessions)
- **Capacity:** ~1000 concurrent sessions per server
- **Storage:** Local filesystem (`.wwebjs_auth/`)

**Future Scaling (>1000 users):**
- Deploy additional instances (4-8 instances = 2,000-4,000 users)
- Workspace-sharded architecture with InstanceRouter
- Shared session storage (EFS/NFS/GlusterFS)
- Database read replicas for query optimization
- For >3,000 users: Consider Official WhatsApp Business API

---

**Document Version:** 2.0  
**Maintainer:** Development Team  
**Last Review:** November 22, 2025
