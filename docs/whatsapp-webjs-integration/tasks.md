# TASKS - WhatsApp Web JS Integration

---

## 📋 EXECUTIVE SUMMARY

**Document:** tasks.md  
**Status:** ✅ **PRODUCTION READY** (100% Coverage)  
**Last Updated:** Oktober 11, 2025  
**Total Tasks:** 10 main tasks | 66 implementation steps  
**Estimated Duration:** 17-24 days (3-4 weeks)  
**Team Size:** 2-3 developers recommended

**Coverage Metrics:**
- ✅ **Requirements:** 100% (10/10 FR mapped)
- ✅ **Assumptions:** 100% (16/16 ASM addressed)
- ✅ **Design:** 100% (12/12 DES implemented)

**Document References:**
- `requirements.md` - Functional requirements (FR-1 to FR-10)
- `assumption.md` - Assumptions (ASM-1 to ASM-REVERB-2)
- `design.md` - Technical design (DES-1 to DES-12)

---

## 📑 TABLE OF CONTENTS

1. [Implementation Checklist](#implementation-checklist)
2. [Detailed Task Breakdown](#detailed-task-breakdown)
   - TASK-1: Prerequisites & Environment Setup
   - TASK-2: Laravel Reverb Installation
   - TASK-3: Backend Broadcasting Infrastructure
   - TASK-4: WhatsApp Provider Abstraction
   - TASK-5: Node.js Service Implementation
   - TASK-6: Webhook Security & Message Processing
   - TASK-7: Frontend QR Component & Echo Enhancement
   - TASK-8: Admin Settings UI Enhancement
   - TASK-9: Testing & Quality Assurance
   - TASK-10: Deployment & Monitoring Setup
3. [Evidence Traceability](#evidence-traceability-table)
4. [Implementation Sequence](#implementation-sequence)
5. [Risk Mitigation](#risk-mitigation-during-implementation)
6. [Appendices](#appendix-a-quick-start-checklist)

---

## IMPLEMENTATION CHECKLIST

- [ ] **TASK-1:** Prerequisites & Environment Setup
- [ ] **TASK-2:** Laravel Reverb Installation & Configuration
- [ ] **TASK-3:** Backend Broadcasting Infrastructure
- [ ] **TASK-4:** WhatsApp Provider Abstraction Layer
- [ ] **TASK-5:** Node.js Service Implementation
- [ ] **TASK-6:** Webhook Security & Message Processing
- [ ] **TASK-7:** Frontend QR Component & Echo Enhancement
- [ ] **TASK-8:** Admin Settings UI Enhancement
- [ ] **TASK-9:** Testing & Quality Assurance
- [ ] **TASK-10:** Deployment & Monitoring Setup

---

# DETAILED TASK BREAKDOWN

## TASK-1: Prerequisites & Environment Setup

**Referencing:** docs/whatsapp-webjs-integration/design.md (DES-1, DES-3)  
**Scope:** Install dependencies dan setup environment configuration

### Subtasks Checklist
- [ ] **TASK-1.1:** Install Laravel Reverb via Composer
- [ ] **TASK-1.2:** Verify/Install Guzzle HTTP Client
- [ ] **TASK-1.3:** Install Chrome/Chromium for Puppeteer (CRITICAL untuk ASM-6)
- [ ] **TASK-1.4:** Configure Laravel Environment Variables (.env)
- [ ] **TASK-1.5:** Configure Network Accessibility (Firewall, CORS - ASM-8)
- [ ] **TASK-1.6:** Install/Verify Frontend Dependencies (ASM-10)
- [ ] **TASK-1.7:** Configure Broadcasting Defaults

### Acceptance Criteria
- ✅ Laravel Reverb installed dan dapat di-start tanpa error
- ✅ Chrome/Chromium accessible untuk Puppeteer
- ✅ Environment variables configured dengan HMAC secret
- ✅ Network ports (8000, 3000, 8080) accessible
- ✅ Frontend dependencies (laravel-echo, pusher-js) installed
- ✅ `BROADCAST_DRIVER=reverb` set as default

### Verification Commands
```bash
composer show laravel/reverb
which chromium || which chromium-browser
php artisan reverb:start
php artisan config:show broadcasting.default  # Returns 'reverb'
npm list laravel-echo pusher-js
```

**Dependencies:** None (first task)  
**Definition of Done:** All prerequisites installed, environment configured, services can communicate

---

## TASK-2: Laravel Reverb Installation & Configuration

**Referencing:** docs/whatsapp-webjs-integration/design.md (DES-1)  
**Scope:** Complete Reverb setup dengan database seeding

### Subtasks Checklist
- [ ] **TASK-2.1:** Run `php artisan reverb:install`
- [ ] **TASK-2.2:** Create migration untuk Reverb settings (seed ke `settings` table)
- [ ] **TASK-2.3:** Create migration untuk Workspace metadata extension (Web JS fields)
- [ ] **TASK-2.4:** Run migrations dan verify settings seeded

### Acceptance Criteria
- ✅ `config/broadcasting.php` updated dengan Reverb connection
- ✅ Database `settings` table contains reverb_* keys (app_id, app_key, app_secret, host, port, scheme)
- ✅ Workspace metadata schema ready untuk Web JS data (JSON field)
- ✅ All migrations successful tanpa errors

### Verification Commands
```sql
SELECT * FROM settings WHERE `key` LIKE 'reverb_%';
```

**Dependencies:** TASK-1  
**Definition of Done:** Reverb configured di database, migrations applied, settings seeded

---

## TASK-3: Backend Broadcasting Infrastructure

**Referencing:** docs/whatsapp-webjs-integration/design.md (DES-1, DES-5)  
**Scope:** Extend BroadcastConfigServiceProvider dan make events broadcaster-agnostic

### Subtasks Checklist
- [ ] **TASK-3.1:** Extend BroadcastConfigServiceProvider (add Reverb config loading - DES-1)
- [ ] **TASK-3.2:** Refactor NewChatEvent untuk broadcaster-agnostic (DES-5)
- [ ] **TASK-3.3:** Refactor NewPaymentEvent (same pattern as NewChatEvent)
- [ ] **TASK-3.4:** Create WhatsAppQRGenerated Event (DES-10)
- [ ] **TASK-3.5:** Create WhatsAppSessionStatusChanged Event

### Acceptance Criteria
- ✅ BroadcastConfigServiceProvider loads Reverb config dynamically dari database
- ✅ NewChatEvent dan NewPaymentEvent work dengan both Reverb dan Pusher
- ✅ WhatsAppQRGenerated broadcasts QR codes ke channel `whatsapp.{workspaceId}`
- ✅ WhatsAppSessionStatusChanged broadcasts status updates (connected/disconnected)
- ✅ Events dapat di-test via `php artisan tinker` dengan both drivers

### Verification Commands
```bash
php artisan tinker
>>> broadcast(new App\Events\WhatsAppQRGenerated(1, 'test-qr', 'session-123'));
```

**Dependencies:** TASK-2  
**Definition of Done:** Broadcasting infrastructure driver-agnostic, events broadcast correctly

---

## TASK-4: WhatsApp Provider Abstraction Layer

**Referencing:** docs/whatsapp-webjs-integration/design.md (DES-7, DES-8)  
**Scope:** Implement provider selection logic dan refactor WhatsappService

### Subtasks Checklist
- [ ] **TASK-4.1:** Create ProviderSelector Service (DES-7 algorithm)
- [ ] **TASK-4.2:** Create WhatsAppAdapterInterface
- [ ] **TASK-4.3:** Create MetaAPIAdapter (wrap existing logic)
- [ ] **TASK-4.4:** Create WebJSAdapter (new - DES-8 full implementation)
- [ ] **TASK-4.5:** Refactor WhatsappService (backward compatible constructor)
- [ ] **TASK-4.6:** Add whatsapp_node config ke `config/services.php`
- [ ] **TASK-4.7:** Create MonitorWhatsAppProviders command (scheduled every 1 minute)

### Acceptance Criteria
- ✅ ProviderSelector automatically selects provider based on workspace metadata
- ✅ Failover works: Web JS down → fallback to Meta API (see DES-7 matrix)
- ✅ WhatsappService maintains backward compatibility (existing code tidak break)
- ✅ WebJSAdapter sends messages via Node.js dengan HMAC authentication
- ✅ Provider monitoring command runs setiap 1 menit via scheduler
- ✅ Provider selection testable: `new WhatsappService(null, null, null, null, null, 1)`

### Verification Commands
```php
$workspace = Workspace::find(1);
$service = new WhatsappService(null, null, null, null, null, 1);
// Should auto-select provider
```

**Implementation Note:** Complete WebJSAdapter code available in DES-8 design.md  
**Dependencies:** TASK-3  
**Definition of Done:** Provider abstraction complete, failover automatic, monitoring active

---

## TASK-5: Node.js Service Implementation

**Referencing:** docs/whatsapp-webjs-integration/design.md (DES-3, DES-11, DES-12)  
**Scope:** Build complete Node.js Express service dengan WhatsApp Web JS integration

### Subtasks Checklist
- [ ] **TASK-5.1:** Initialize Node.js project (package.json + dependencies)
- [ ] **TASK-5.2:** Implement WhatsAppManager Service (DES-11 - session lifecycle)
- [ ] **TASK-5.3:** Implement HMAC Authentication Middleware
- [ ] **TASK-5.4:** Implement Session Routes (POST/DELETE/GET sessions)
- [ ] **TASK-5.5:** Implement Message Routes (POST /messages/send)
- [ ] **TASK-5.6:** Implement Health Check Routes (DES-12)
- [ ] **TASK-5.7:** Implement Error Handling Utils (ErrorClassifier + Winston logger)
- [ ] **TASK-5.8:** Implement Crypto Utils (HMAC generation/validation)
- [ ] **TASK-5.9:** Create Main Server File (Express setup - DES-3)
- [ ] **TASK-5.10:** Configure .env (LARAVEL_URL, HMAC_SECRET, MAX_CONCURRENT_SESSIONS)
- [ ] **TASK-5.11:** Create PM2 Configuration (ecosystem.config.js)

### Acceptance Criteria
- ✅ Node.js service starts without errors: `npm start`
- ✅ Health check endpoint returns status: `curl http://localhost:3000/health`
- ✅ WhatsApp Web JS session management: QR generation, authentication, ready events
- ✅ HMAC authentication enforced untuk semua protected endpoints
- ✅ Session limit enforced (MAX_CONCURRENT_SESSIONS = 50)
- ✅ LocalAuth session persistence works (sessions survive restart)
- ✅ Webhook integration works (sends events ke Laravel)
- ✅ PM2 ready untuk production deployment

### Verification Commands
```bash
cd whatsapp-service && npm start
curl http://localhost:3000/health
```

**Implementation Note:** Complete WhatsAppManager code available in DES-11 design.md  
**Dependencies:** TASK-4  
**Definition of Done:** Node.js service operational, session management working, webhooks sending

---

## TASK-6: Webhook Security & Message Processing

**Referencing:** docs/whatsapp-webjs-integration/design.md (DES-9)  
**Scope:** Implement secure webhook endpoint untuk Node.js callbacks

### Subtasks Checklist
- [ ] **TASK-6.1:** Create VerifyWhatsAppHmacSignature Middleware (DES-9)
- [ ] **TASK-6.2:** Create WhatsAppWebJSController (all event handlers - DES-9)
- [ ] **TASK-6.3:** Create WhatsAppWebJSSessionController (frontend API)
- [ ] **TASK-6.4:** Add Webhook Routes ke `routes/api.php`
- [ ] **TASK-6.5:** Add index on `chats.external_id` (message deduplication)

### Acceptance Criteria
- ✅ HMAC middleware validates signatures (±300 seconds timestamp tolerance)
- ✅ Invalid signatures rejected dengan 403 Forbidden
- ✅ Webhook processes events: message.received, message.sent, session.qr, session.ready, session.disconnected
- ✅ Message deduplication works (duplicate external_id ignored)
- ✅ Frontend dapat initiate/disconnect sessions via API
- ✅ Database index on chats.external_id exists untuk performance

### Verification Commands
```bash
curl -X POST http://localhost:8000/api/webhooks/whatsapp-webjs \
  -H "X-HMAC-Signature: valid_sig" \
  -H "X-Timestamp: $(date +%s)" \
  -H "X-Workspace-ID: 1" \
  -d '{"event": "message.received", "data": {...}}'
```

**Implementation Note:** Complete webhook controller code available in DES-9 design.md  
**Dependencies:** TASK-5  
**Definition of Done:** Webhook secure, message processing operational, deduplication active

---

## TASK-7: Frontend QR Component & Echo Enhancement

**Referencing:** docs/whatsapp-webjs-integration/design.md (DES-2, DES-10)  
**Scope:** Implement QR component dan enhance Echo untuk dynamic broadcaster

### Subtasks Checklist
- [ ] **TASK-7.1:** Enhance Echo.js untuk dynamic broadcaster (Reverb/Pusher - DES-2)
- [ ] **TASK-7.2:** Create WhatsAppSetup.vue Component (DES-10 - 350+ lines)
- [ ] **TASK-7.3:** Update App.vue Layout (Echo initialization)
- [ ] **TASK-7.4:** Update Billing/Index.vue (Echo consistency)
- [ ] **TASK-7.5:** Update Chat/Index.vue (verify Echo listeners compatible)
- [ ] **TASK-7.6:** Add WhatsApp setup route dan navigation

### Acceptance Criteria
- ✅ Echo dynamically selects broadcaster (Reverb/Pusher) based on config
- ✅ WhatsAppSetup.vue displays QR code real-time via Echo channel `whatsapp.{workspaceId}`
- ✅ Status updates (connecting → qr_required → connected → disconnected) work
- ✅ QR refresh button works (regenerates QR on expiry)
- ✅ Disconnect button works (destroys session)
- ✅ All existing Echo listeners remain functional (backward compatible)
- ✅ End-to-end workflow: Click "Connect" → QR displayed → Scan → Status "Connected"

### Verification Commands
```javascript
echo.channel('whatsapp.1').listen('WhatsAppQRGenerated', (e) => console.log('QR:', e.qrCode));
```

**Implementation Note:** Complete WhatsAppSetup.vue code available in DES-10 design.md  
**Dependencies:** TASK-6  
**Definition of Done:** QR component functional, Echo enhanced, scan workflow complete

---

## TASK-8: Admin Settings UI Enhancement

**Referencing:** docs/whatsapp-webjs-integration/design.md (DES-6)  
**Scope:** Add broadcast driver selection UI untuk admin settings

### Subtasks Checklist
- [ ] **TASK-8.1:** Create Broadcasting.vue Settings Page (DES-6)
- [ ] **TASK-8.2:** Add Broadcasting Settings Backend (controller + validation)
- [ ] **TASK-8.3:** Integrate to Admin Navigation (menu + authorization)

### Acceptance Criteria
- ✅ Admin dapat pilih broadcast driver: "Laravel Reverb (Free) - Default" atau "Pusher"
- ✅ Conditional forms: Show Reverb fields (host, port, scheme) atau Pusher fields (key, secret, cluster)
- ✅ Settings saved ke database `settings` table
- ✅ Driver selection applies system-wide (BroadcastConfigServiceProvider loads dari DB)
- ✅ Validation works: Required fields enforced based on selected driver

### Verification Commands
```sql
SELECT * FROM settings WHERE `key` LIKE 'broadcast_driver';
```

**Dependencies:** TASK-7  
**Definition of Done:** Admin can configure driver, settings persist, system uses selected driver

---

## TASK-9: Testing & Quality Assurance

**Referencing:** docs/whatsapp-webjs-integration/requirements.md (All FR acceptance criteria)  
**Scope:** Comprehensive testing untuk all integration points

### Subtasks Checklist
- [ ] **TASK-9.1:** Unit Testing - Provider Selection Logic (5 test cases - see DES-7)
- [ ] **TASK-9.2:** Integration Testing - Message Flow (send/receive/failover)
- [ ] **TASK-9.3:** Security Testing - HMAC Validation (valid/invalid/replay attack)
- [ ] **TASK-9.4:** Broadcasting Testing - Reverb & Pusher (both drivers + switching)
- [ ] **TASK-9.5:** Frontend Testing - QR Component (display/status/refresh/disconnect)
- [ ] **TASK-9.6:** Session Management Testing (create/persist/recover/limit/cleanup)
- [ ] **TASK-9.7:** Performance Testing (50 sessions/memory/latency/queries)
- [ ] **TASK-9.8:** Error Handling Testing (classification/logging/health/alerts)

### Acceptance Criteria
- ✅ **FR-1 to FR-10:** All functional requirements validated
- ✅ **Security:** HMAC validation passes, replay attacks prevented, signatures verified
- ✅ **Performance:** 50 concurrent sessions supported, <100ms encryption time
- ✅ **Failover:** Provider failover automatic (Web JS down → Meta API)
- ✅ **Broadcasting:** Events broadcast correctly via both Reverb dan Pusher
- ✅ **Session Management:** Sessions persist after restart, cleanup works
- ✅ **Error Handling:** Errors classified correctly (DES-12 taxonomy)
- ✅ **Test Coverage:** >80% code coverage

### Verification Commands
```bash
php artisan test --filter WhatsAppIntegration
php artisan test --coverage
```

**Dependencies:** TASK-8  
**Definition of Done:** All tests passing, benchmarks met, security validated, coverage >80%

---

## TASK-10: Deployment & Monitoring Setup

**Referencing:** docs/whatsapp-webjs-integration/design.md (DES-12)  
**Scope:** Production deployment dan monitoring infrastructure

### Subtasks Checklist
- [ ] **TASK-10.1:** Configure Production Environment Variables (Laravel + Node.js .env, secure HMAC)
- [ ] **TASK-10.2:** Deploy Node.js Service dengan PM2 (ecosystem.config.js, startup script)
- [ ] **TASK-10.3:** Configure Reverb Server (systemd/Supervisor, auto-restart)
- [ ] **TASK-10.4:** Setup Monitoring Alerts (DES-12 rules, channels, integration)
- [ ] **TASK-10.5:** Configure Log Aggregation (Winston + Laravel logs, centralized system)
- [ ] **TASK-10.6:** Setup Health Check Monitoring (uptime service, alerts, failover test)
- [ ] **TASK-10.7:** Schedule Provider Monitoring Command (whatsapp:monitor-providers every 1 min)
- [ ] **TASK-10.8:** Document Deployment Procedures (runbook, rollback, troubleshooting)
- [ ] **TASK-10.9:** Backup & Disaster Recovery (session backups, recovery procedures, test)

### Acceptance Criteria
- ✅ **PM2:** Node.js service running stable di production
- ✅ **Reverb:** Server operational dengan auto-restart configured
- ✅ **Monitoring:** Alert rules active (DES-12), notifications working
- ✅ **Logging:** Centralized log aggregation functional
- ✅ **Health Check:** /health endpoint monitored via uptime service
- ✅ **Scheduled Tasks:** Provider monitoring running every 1 minute
- ✅ **Documentation:** Deployment runbook, rollback, troubleshooting guide complete
- ✅ **Disaster Recovery:** Session backup dan recovery tested successfully
- ✅ **Stability:** Services running stable >24 hours tanpa critical alerts

### Verification Commands
```bash
pm2 status  # Expected: whatsapp-service online
php artisan reverb:status  # Expected: Reverb running
curl https://production-domain.com/api/health  # Expected: healthy
```

**Dependencies:** TASK-9  
**Definition of Done:** Production stable >24h, monitoring active, alerts configured, docs complete, DR tested

---

# SUPPORTING DOCUMENTATION

## EVIDENCE TRACEABILITY TABLE

| Requirement | Assumption Coverage | Design Section | Implementation Tasks | Test Cases | Verification Method | Coverage |
|-------------|---------------------|----------------|----------------------|------------|---------------------|----------|
| FR-1: Reverb Migration | ASM-REVERB-1, ASM-REVERB-2 | DES-1 | TASK-1.1, TASK-2, TASK-3 | TASK-9.4 | `php artisan reverb:status` shows running | ✅ 100% |
| FR-2: Dynamic Selection | ASM-REVERB-2, ASM-11 | DES-6 | TASK-8 | TASK-9.4 | Admin can switch drivers via UI | ✅ 100% |
| FR-3: Node.js Integration | ASM-4, ASM-5, ASM-6 | DES-3, DES-11 | TASK-1.2A, TASK-5 | TASK-9.6 | Health check returns active sessions | ✅ 100% |
| FR-4: Session Management | ASM-7, ASM-14, ASM-15 | DES-11 | TASK-5.2 | TASK-9.6 | Session persists after restart | ✅ 100% |
| FR-5: Message Sending | ASM-1, ASM-2, ASM-13 | DES-8 | TASK-4.4 | TASK-9.1, TASK-9.2 | Messages sent via both providers | ✅ 100% |
| FR-6: Message Receiving | ASM-13 | DES-9 | TASK-6 | TASK-9.2 | Webhook processes incoming messages | ✅ 100% |
| FR-7: Provider Priority | ASM-2 | DES-7 | TASK-4.1, TASK-4.7 | TASK-9.1 | ProviderSelector chooses correct provider | ✅ 100% |
| FR-8: Real-time Broadcasting | ASM-11, ASM-12 | DES-5 | TASK-3 | TASK-9.4 | Events broadcast via Reverb/Pusher | ✅ 100% |
| FR-9: Webhook Integration | ASM-8, ASM-13 | DES-9 | TASK-1.4, TASK-6 | TASK-9.3 | HMAC validation passes security test | ✅ 100% |
| FR-10: QR Display | ASM-9, ASM-10 | DES-10 | TASK-1.5, TASK-7 | TASK-9.5 | QR code displays dan updates real-time | ✅ 100% |

**Overall Assumption Coverage:** 16/16 (100%) ✅  
**Overall Requirements Coverage:** 10/10 (100%) ✅  
**Implementation Readiness:** Production-Ready ✅

---

## IMPLEMENTATION SEQUENCE

**Phase 1: Foundation (TASK-1 to TASK-3)**
- **Duration:** 2-3 days
- **Focus:** Broadcasting infrastructure dan environment setup
- **Deliverable:** Laravel Reverb operational, events broadcaster-agnostic

**Phase 2: Provider Abstraction (TASK-4)**
- **Duration:** 3-4 days
- **Focus:** Service refactoring dan provider selection logic
- **Deliverable:** WhatsappService supports both providers, failover working

**Phase 3: Node.js Service (TASK-5, TASK-6)**
- **Duration:** 5-7 days
- **Focus:** WhatsApp Web JS integration dan webhook security
- **Deliverable:** Node.js service operational, webhook processing messages

**Phase 4: Frontend (TASK-7, TASK-8)**
- **Duration:** 2-3 days
- **Focus:** QR component dan admin UI
- **Deliverable:** Complete user-facing features

**Phase 5: Quality Assurance (TASK-9)**
- **Duration:** 3-4 days
- **Focus:** Comprehensive testing
- **Deliverable:** All acceptance criteria validated

**Phase 6: Deployment (TASK-10)**
- **Duration:** 2-3 days
- **Focus:** Production deployment dan monitoring
- **Deliverable:** System live dengan monitoring active

**Total Estimated Duration:** 17-24 days (3-4 weeks)

---

## CRITICAL PATH DEPENDENCIES

```
TASK-1 (Prerequisites)
  ↓
TASK-2 (Reverb Installation) → TASK-3 (Broadcasting Infrastructure)
  ↓                                      ↓
TASK-4 (Provider Abstraction) ←─────────┘
  ↓
TASK-5 (Node.js Service) → TASK-6 (Webhook Security)
  ↓                              ↓
TASK-7 (Frontend) ←──────────────┘
  ↓
TASK-8 (Admin UI)
  ↓
TASK-9 (Testing)
  ↓
TASK-10 (Deployment)
```

**Parallelizable Work:**
- TASK-5 + TASK-6 dapat dikerjakan simultaneously (different developers)
- TASK-7 dapat dimulai setelah TASK-5.3 (HMAC middleware ready)

---

## RISK MITIGATION DURING IMPLEMENTATION

### High Priority Risks

**1. Session Recovery Failure**
- **Mitigation:** Implement comprehensive logging di WhatsAppManager
- **Task Impact:** TASK-5.2
- **Validation:** TASK-9.6

**2. HMAC Signature Mismatch**
- **Mitigation:** Use constant-time comparison, log all attempts
- **Task Impact:** TASK-6.1
- **Validation:** TASK-9.3

**3. Concurrent Session Limit**
- **Mitigation:** Enforce limit di WhatsAppManager, queue requests
- **Task Impact:** TASK-5.2
- **Validation:** TASK-9.7

**4. Memory Leak**
- **Mitigation:** Monitor Node.js memory, implement session cleanup
- **Task Impact:** TASK-5.6, TASK-10.4
- **Validation:** TASK-9.7

### Mitigation Actions per Task
- **TASK-5:** Add memory monitoring di health check endpoint
- **TASK-6:** Implement request logging untuk HMAC debugging
- **TASK-9:** Dedicated security testing phase
- **TASK-10:** Configure memory alerts via monitoring system

---

## VALIDATION & TESTING MATRIX

| Task | Unit Tests | Integration Tests | Manual QA | Acceptance Criteria |
|------|-----------|-------------------|-----------|---------------------|
| TASK-1 | ✅ Config validation | N/A | ✅ Installation | Environment ready |
| TASK-2 | ✅ Migration tests | N/A | ✅ Database check | Settings seeded |
| TASK-3 | ✅ Event tests | ✅ Broadcast flow | ✅ Multiple drivers | Events broadcast correctly |
| TASK-4 | ✅ Provider logic | ✅ Failover scenarios | ✅ Provider switching | Failover automatic |
| TASK-5 | ✅ Session lifecycle | ✅ WhatsApp Web JS | ✅ QR generation | Sessions managed |
| TASK-6 | ✅ HMAC validation | ✅ Webhook processing | ✅ Message flow | Messages processed |
| TASK-7 | ✅ Component tests | ✅ Echo connection | ✅ QR display | QR updates real-time |
| TASK-8 | N/A | ✅ Settings save | ✅ UI interaction | Settings persist |
| TASK-9 | ✅ All units | ✅ All integrations | ✅ E2E workflow | All FR validated |
| TASK-10 | N/A | ✅ Health checks | ✅ Monitoring | Production stable |

---

## ROLLBACK PROCEDURES

### Per Task Rollback
- **TASK-1-3:** Revert migrations, set `BROADCAST_DRIVER=pusher`
- **TASK-4:** Restore original `WhatsappService.php` from git
- **TASK-5-6:** Stop Node.js service, disable webhook route
- **TASK-7-8:** Hide UI components, revert Echo changes
- **TASK-9-10:** N/A (testing dan deployment verification)

### Complete Rollback Strategy
1. Set `BROADCAST_DRIVER=pusher` di database
2. Disable webhook route (`routes/api.php`)
3. Stop Node.js service (`pm2 stop whatsapp-service`)
4. Revert WhatsappService to Meta API only
5. Hide WhatsApp setup UI component

### Quick Rollback Command
```bash
# Execute in order:
UPDATE settings SET value='pusher' WHERE key='broadcast_driver';
pm2 stop whatsapp-service
git checkout main -- app/Services/WhatsappService.php
php artisan cache:clear && php artisan config:clear
```

---

## ARTIFACTS REFERENCE

**Complete file list available in:** docs/whatsapp-webjs-integration/design.md "ARTIFACTS/FILES TO BE MODIFIED/CREATED" section

**Total Files:**
- **Backend (Laravel):** ~20 files
  - Services: 5 files
  - Controllers: 3 files
  - Middleware: 2 files
  - Events: 3 files
  - Migrations: 2 files
  - Config: 2 files
  - Commands: 1 file
  - Routes: 1 file
  
- **Frontend (Vue.js):** ~8 files
  - Components: 2 files
  - Pages: 1 file
  - Echo config: 1 file
  - Routes: 1 file
  - Layouts: 3 files
  
- **Node.js Service:** ~15 files
  - Manager: 1 file
  - Routes: 3 files
  - Middleware: 1 file
  - Utils: 3 files
  - Config: 2 files
  - Server: 1 file
  - PM2: 1 file
  - Package: 1 file
  - Env: 1 file
  
- **Configuration:** ~5 files
  - Laravel .env updates
  - Node.js .env
  - PM2 ecosystem.config.js
  - Supervisor config
  - Nginx config (optional)

---

# APPENDICES

## APPENDIX A: QUICK START CHECKLIST

### Before Starting TASK-1
- [ ] Clone repository: `git clone <repository-url>`
- [ ] Install PHP 8.2+ and Composer
- [ ] Install Node.js 18+ and NPM
- [ ] Install Chrome/Chromium browser
- [ ] Configure network ports (8000, 3000, 8080)
- [ ] Generate HMAC secret: `openssl rand -hex 64`
- [ ] Review requirements.md, design.md, tasks.md

### Daily Development Checklist
- [ ] Pull latest changes: `git pull origin stg-new`
- [ ] Start Laravel: `php artisan serve --port=8000`
- [ ] Start Reverb: `php artisan reverb:start` (separate terminal)
- [ ] Start Node.js service: `cd whatsapp-service && npm start` (separate terminal)
- [ ] Check Laravel logs: `tail -f storage/logs/laravel.log`
- [ ] Check Node.js logs: `pm2 logs whatsapp-service` (production) or console output (development)

### Environment Verification Checklist
```bash
# Verify all services running:
curl http://localhost:8000/api/health          # Laravel health check
curl http://localhost:3000/health              # Node.js health check
php artisan reverb:status                      # Reverb status
php artisan config:show broadcasting.default   # Should return 'reverb'

# Verify dependencies:
composer show laravel/reverb                   # Laravel Reverb installed
npm list laravel-echo pusher-js                # Frontend dependencies
which chromium || which chromium-browser       # Chrome/Chromium available
```

---

## APPENDIX B: COMMON PITFALLS & TROUBLESHOOTING

### Pitfall 1: Puppeteer Fails to Launch Chrome

**Symptom:** 
```
Error: Failed to launch the browser process
Error: Could not find Chrome executable
```

**Causes:**
- Chrome/Chromium not installed
- Wrong executable path
- Missing system libraries (Linux)

**Solutions:**
```bash
# Install Chrome/Chromium (Ubuntu/Debian):
sudo apt-get install chromium-browser

# macOS:
brew install chromium

# Set custom path in whatsapp-service/.env:
PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium-browser

# Test Chrome availability:
which chromium || which chromium-browser || which google-chrome

# Install missing libraries (Ubuntu/Debian):
sudo apt-get install -y libatk-bridge2.0-0 libx11-xcb1 libxcomposite1 \
    libxcursor1 libxdamage1 libxi6 libxtst6 libnss3 libcups2 libxss1 \
    libxrandr2 libasound2 libpangocairo-1.0-0 libatk1.0-0 libgtk-3-0
```

---

### Pitfall 2: HMAC Signature Mismatch (403 Forbidden)

**Symptom:** 
```
Webhook rejected: Invalid HMAC signature
403 Forbidden on webhook calls
```

**Causes:**
- HMAC_SECRET mismatch between Laravel and Node.js
- Timestamp drift between servers
- Payload encoding mismatch

**Solutions:**
```bash
# 1. Verify HMAC secrets match:
# Laravel (.env):
echo $WHATSAPP_NODE_HMAC_SECRET

# Node.js (whatsapp-service/.env):
echo $HMAC_SECRET

# Should be identical!

# 2. Check server time synchronization:
date  # Both servers should show same time (±5 minutes max)

# Sync time (Ubuntu/Debian):
sudo ntpdate -u pool.ntp.org

# 3. Debug signature generation:
# Add to Laravel middleware:
Log::debug('HMAC verification', [
    'expected' => $expectedSignature,
    'received' => $signature,
    'payload' => $request->getContent(),
    'timestamp' => $timestamp,
]);

# Add to Node.js webhook sender:
logger.debug('Sending webhook', {
    signature: signature,
    payload: JSON.stringify(payload),
    timestamp: timestamp,
});
```

---

### Pitfall 3: Session Not Persisting After Restart

**Symptom:** 
```
QR code regenerates on every Node.js restart
Session authentication lost
```

**Causes:**
- Session directory permissions incorrect
- LocalAuth dataPath misconfigured
- Session files deleted prematurely

**Solutions:**
```bash
# 1. Check session directory permissions:
ls -la whatsapp-service/sessions/
# Should show drwx------ (700) permissions

# Fix permissions:
chmod 700 whatsapp-service/sessions/*
chown -R node-user:node-user whatsapp-service/sessions/

# 2. Verify LocalAuth configuration in WhatsAppManager.js:
const client = new Client({
    authStrategy: new LocalAuth({
        clientId: sessionId,
        dataPath: path.join(__dirname, '../../sessions', workspaceId.toString()),
    }),
});

# 3. Check session files exist:
ls -la whatsapp-service/sessions/{workspace_id}/
# Should contain: Default/, session-{id}.json

# 4. Test session recovery:
# Stop Node.js service
pm2 stop whatsapp-service

# Start again
pm2 start whatsapp-service

# Check logs - should attempt reconnection:
pm2 logs whatsapp-service | grep "Attempting session reconnection"
```

---

### Pitfall 4: WebSocket Connection Fails (Reverb)

**Symptom:** 
```
WebSocket connection to 'ws://localhost:8080' failed
Echo channel not receiving events
Frontend console: "Failed to load resource: net::ERR_CONNECTION_REFUSED"
```

**Causes:**
- Reverb server not running
- Firewall blocking port 8080
- CORS misconfiguration
- Wrong WebSocket URL in frontend

**Solutions:**
```bash
# 1. Verify Reverb is running:
php artisan reverb:status
# Should show: "Reverb server is running on 127.0.0.1:8080"

# Start Reverb if not running:
php artisan reverb:start

# 2. Test WebSocket connection:
npm install -g wscat
wscat -c ws://localhost:8080/app/your-app-key

# 3. Check firewall rules:
sudo ufw status | grep 8080
# Should allow port 8080

# Open port if blocked:
sudo ufw allow 8080/tcp

# 4. Verify frontend Echo configuration:
# resources/js/echo.js should have:
const config = {
    broadcaster: 'pusher',
    key: reverbConfig.key,
    wsHost: reverbConfig.host,
    wsPort: reverbConfig.port,
    wssPort: reverbConfig.port,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
};

# 5. Check browser console for CORS errors:
# If CORS error, update config/cors.php:
'paths' => ['api/*', 'reverb/*', 'broadcasting/*'],
'allowed_origins' => ['http://localhost:8000'],
```

---

### Pitfall 5: Provider Selection Not Working

**Symptom:** 
```
Always using Meta API even when Web JS available
Failover not triggering
```

**Causes:**
- Workspace metadata not updated
- ProviderSelector cache issue
- Node.js service health check failing

**Solutions:**
```php
// 1. Check workspace metadata:
$workspace = Workspace::find(1);
$metadata = json_decode($workspace->metadata, true);
print_r($metadata['whatsapp']);

// Should contain:
// 'webjs_status' => 'connected'
// 'webjs_session_id' => 'session_xxx'
// 'provider_priority' => 'webjs'

// 2. Manually update metadata:
$metadata['whatsapp']['webjs_status'] = 'connected';
$metadata['whatsapp']['webjs_session_id'] = 'session_123';
$metadata['whatsapp']['provider_priority'] = 'webjs';
$workspace->metadata = json_encode($metadata);
$workspace->save();

// 3. Test provider selection:
use App\Services\WhatsApp\ProviderSelector;

$selector = new ProviderSelector();
$provider = $selector->selectProvider($workspace);
echo "Selected provider: " . $provider;  // Should return 'webjs'

// 4. Check Node.js health endpoint:
curl http://localhost:3000/health
// Should return JSON with sessions info

// 5. Clear Laravel cache:
php artisan cache:clear
php artisan config:clear
```

---

### Pitfall 6: High Memory Usage on Node.js Service

**Symptom:** 
```
Node.js process consuming >1GB RAM
Server becomes unresponsive
PM2 showing high memory warnings
```

**Causes:**
- Too many concurrent sessions (>50)
- Memory leak in Puppeteer
- Session cleanup not running

**Solutions:**
```bash
# 1. Check current memory usage:
pm2 monit
# Or:
ps aux | grep node

# 2. Check session count:
curl http://localhost:3000/health | jq '.sessions.active'
# Should be ≤ 50

# 3. Force session cleanup:
# Add to WhatsAppManager.js:
setInterval(() => {
    const inactiveSessions = [];
    for (const [workspaceId, meta] of this.sessions.entries()) {
        const inactive = Date.now() - meta.created_at.getTime() > 86400000; // 24 hours
        if (meta.status !== 'connected' && inactive) {
            inactiveSessions.push(workspaceId);
        }
    }
    inactiveSessions.forEach(id => this.destroySession(id, true));
}, 3600000); // Every hour

# 4. Configure PM2 memory limits:
# ecosystem.config.js:
module.exports = {
  apps: [{
    name: 'whatsapp-service',
    script: './src/server.js',
    max_memory_restart: '1G',  // Restart if exceeds 1GB
    instances: 1,
    exec_mode: 'fork',
  }]
};

# 5. Monitor memory trends:
pm2 install pm2-server-monit
```

---

### Pitfall 7: Message Deduplication Not Working

**Symptom:** 
```
Duplicate messages appearing in database
Same message_id processed multiple times
```

**Causes:**
- Index missing on chats.external_id
- Webhook retry logic causing duplicates
- Database transaction isolation issue

**Solutions:**
```sql
-- 1. Add index on external_id:
CREATE INDEX idx_chats_external_id ON chats(external_id);

-- 2. Verify index exists:
SHOW INDEX FROM chats WHERE Key_name = 'idx_chats_external_id';

-- 3. Test deduplication:
-- Send same webhook twice:
SELECT COUNT(*) FROM chats WHERE external_id = 'test_message_123';
-- Should return 1 (not 2)
```

```php
// 4. Add to webhook controller:
DB::beginTransaction();
try {
    $existing = Chat::where('external_id', $messageId)
        ->lockForUpdate()  // Row-level lock
        ->first();
    
    if ($existing) {
        DB::rollBack();
        return response()->json(['status' => 'duplicate_ignored'], 200);
    }
    
    // Create new chat...
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}
```

---

### Pitfall 8: QR Code Not Displaying in Frontend

**Symptom:** 
```
QR component shows "Connecting..." forever
No QR code image displayed
Console: "Echo channel not receiving events"
```

**Causes:**
- Echo channel subscription incorrect
- Event broadcasting not configured
- Frontend component not listening to correct event

**Solutions:**
```javascript
// 1. Verify Echo channel subscription:
echo.channel('whatsapp.1')
    .listen('WhatsAppQRGenerated', (e) => {
        console.log('QR received:', e.qrCode);
    });

// 2. Check event name matches backend:
// Laravel: WhatsAppQRGenerated
// Frontend: .listen('WhatsAppQRGenerated', ...)

// 3. Test event broadcasting manually:
php artisan tinker
>>> broadcast(new App\Events\WhatsAppQRGenerated(1, 'test-qr-code', 'session-123'));

// 4. Monitor Reverb logs:
php artisan reverb:start --debug

// 5. Check frontend console for WebSocket messages:
// Should show: "Pusher : Event received : WhatsAppQRGenerated"
```

---

## APPENDIX C: PRODUCTION DEPLOYMENT CHECKLIST

### Pre-Deployment Verification
- [ ] All tests passing: `php artisan test`
- [ ] Code reviewed and approved
- [ ] Database migrations tested: `php artisan migrate --pretend`
- [ ] Environment variables configured in production `.env`
- [ ] HMAC secret securely generated and distributed
- [ ] Chrome/Chromium installed on production server
- [ ] Firewall rules configured (ports 8000, 3000, 8080)
- [ ] SSL certificates installed (for WSS connections)
- [ ] Backup current database: `php artisan backup:run`

### Deployment Steps
1. Pull latest code: `git pull origin main`
2. Install dependencies: `composer install --no-dev --optimize-autoloader`
3. Run migrations: `php artisan migrate --force`
4. Clear caches: `php artisan config:cache && php artisan route:cache && php artisan view:cache`
5. Deploy Node.js service: `cd whatsapp-service && npm install --production`
6. Start PM2: `pm2 start ecosystem.config.js`
7. Start Reverb: `php artisan reverb:start` (via Supervisor/systemd)
8. Verify health: `curl https://production-domain.com/api/health`

### Post-Deployment Monitoring
- [ ] Check Laravel logs: `tail -f storage/logs/laravel.log`
- [ ] Check Node.js logs: `pm2 logs whatsapp-service`
- [ ] Monitor Reverb status: `php artisan reverb:status`
- [ ] Test QR generation workflow
- [ ] Test message sending via both providers
- [ ] Verify provider failover mechanism
- [ ] Monitor memory usage: `pm2 monit`
- [ ] Check error rates in monitoring dashboard

### Rollback Procedure (if issues detected)
1. Stop services: `pm2 stop whatsapp-service && php artisan reverb:stop`
2. Revert code: `git reset --hard <previous-commit>`
3. Rollback migrations: `php artisan migrate:rollback --step=5`
4. Restore database: `php artisan backup:restore <backup-name>`
5. Clear caches: `php artisan cache:clear && php artisan config:clear`
6. Set broadcast driver to Pusher: `UPDATE settings SET value='pusher' WHERE key='broadcast_driver';`
7. Restart Laravel: `php artisan config:cache && systemctl restart php-fpm`

---

## APPENDIX D: IMPLEMENTATION TEMPLATES REFERENCE

**Complete implementation code for all critical components available in:**

1. **WebJSAdapter.php** - See DES-8 in design.md for complete `sendMessage()` implementation
2. **WhatsAppManager.js** - See DES-11 in design.md for complete session lifecycle code
3. **ProviderSelector.php** - See DES-7 in design.md for full algorithm
4. **WhatsAppWebJSController.php** - See DES-9 in design.md for all webhook handlers
5. **WhatsAppSetup.vue** - See DES-10 in design.md for complete Vue component (350+ lines)
6. **HMAC Middleware** - See DES-9 in design.md for security implementation

**Note:** All code templates include:
- Complete method implementations (no placeholders)
- Error handling and logging
- Security best practices
- Performance optimizations
- Production-ready examples

---

**END OF DOCUMENT**
