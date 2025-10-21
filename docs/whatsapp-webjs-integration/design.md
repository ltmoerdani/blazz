# TECHNICAL ARCHITECTURE - WhatsApp Web JS Integration

## 📋 EXECUTIVE SUMMARY

**Document Purpose:** High-level technical architecture and key integration patterns
**Audience:** Developers, technical architects
**Scope:** Architecture overview and key technical decisions (implementation details moved to tasks.md)
**Status:** OPTIMIZED - Streamlined for clean, efficient implementation
**Last Updated:** 12 Oktober 2025 (Optimization Phase)

---

## 🎯 CORE ARCHITECTURE DECISIONS

### ARCH-1: Broadcasting Strategy (UPDATED 2025-10-11)
**Decision:** Laravel Reverb as DEFAULT broadcast driver, Pusher as optional alternative
- **Rationale:** Socket.IO unused in codebase, Laravel Reverb is native Laravel 12 solution
- **Architecture:** Dual-driver support with dynamic configuration
- **Integration:** Laravel Echo client supports both drivers seamlessly
- **Migration:** Zero breaking changes, admin can switch instantly

### ARCH-2: Service Architecture (UPDATED WITH CRITICAL GAPS)
**Decision:** Dedicated Node.js service for WhatsApp Web JS, Laravel for business logic
- **Rationale:** WhatsApp Web JS requires Puppeteer (Chromium) instances
- **Critical Gap:** Database schema `whatsapp_sessions` table MISSING - P0 BLOCKING
- **Architecture:** Microservice pattern with clear separation of concerns
- **Communication:** REST API + HMAC authentication + Webhook callbacks
- **Deployment:** Independent scaling and deployment cycles

### ARCH-3: Provider Abstraction (ENHANCED)
**Decision:** Strategy pattern with automatic provider selection and failover
- **Rationale:** Support both Meta API and WhatsApp Web JS simultaneously
- **Critical Gap:** Missing `whatsapp_session_id` foreign keys in `chats` and `campaign_logs`
- **Architecture:** ProviderSelector service with health monitoring
- **Failover:** Automatic fallback with manual override capability
- **Benefits:** Zero-downtime provider switching, improved reliability

### ARCH-4: Session Management (CRITICAL MITIGATION REQUIRED)
**Decision:** File-based session persistence with workspace isolation
- **Rationale:** WhatsApp Web JS LocalAuth supports persistent sessions
- **Critical Issues:** 8 WhatsApp Web.js issues requiring mitigation (silent disconnect, storage bloat, etc.)
- **Architecture:** `/sessions/{workspace_id}/{session_id}/` directory structure
- **Security:** Encrypted session data with strict file permissions
- **Recovery:** Automatic session restoration after server restart

---

## 🏗️ SYSTEM ARCHITECTURE OVERVIEW

```
┌─────────────────────────────────────────────────────────────────┐
│                    USER INTERFACE                               │
├─────────────────────────────────────────────────────────────────┤
│  Vue.js + Inertia.js              Laravel Broadcasting           │
│  ┌─────────────┐  Echo.js        ┌─────────────┐  WebSocket    │
│  │ QR Display  │◄──────────────► │ Laravel     │◄──────────►   │
│  │ Component   │   (Reverb/     │ Reverb      │   Server      │
│  │             │    Pusher)     │ (Default)   │   (Port 8080) │
│  └─────────────┘                └─────────────┘               │
└─────────────────────────────────────────────────────────────────┘
                                │
                HTTP/REST API   │   Webhook Callbacks
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                    LARAVEL BACKEND                              │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │ Provider    │  │ Webhook     │  │ Database    │              │
│  │ Selector    │  │ Handler     │  │ Schema      │              │
│  │             │  │             │  │             │              │
│  │ • Auto-     │  │ • HMAC      │  │ • whatsapp_ │              │
│  │   failover  │  │   validation│  │   sessions  │              │
│  │ • Health    │  │ • Message   │  │ • Encrypted │              │
│  │   monitoring│  │   processing│  │   session   │              │
│  └─────────────┘  └─────────────┘  └─────────────┘              │
└─────────────────────────────────────────────────────────────────┘
                                │
                HTTP/REST API   │   HMAC Authentication
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                   NODE.JS SERVICE                               │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │ WhatsApp    │  │ Session     │  │ Health      │              │
│  │ Manager     │  │ Manager     │  │ Check       │              │
│  │             │  │             │  │             │              │
│  │ • Puppeteer │  │ • Lifecycle │  │ • Metrics   │              │
│  │ • QR Gen    │  │ • Recovery  │  │ • Alerts    │              │
│  │ • Message   │  │ • Cleanup   │  │ • Logs      │              │
│  └─────────────┘  └─────────────┘  └─────────────┘              │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │              WhatsApp Web JS Client                        │  │
│  │  • Chromium Instances (Headless)                          │  │
│  │  • Session Persistence (LocalAuth)                        │  │
│  │  • Message Sync & Broadcasting                            │  │
│  └─────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🔑 KEY INTEGRATION PATTERNS

### Pattern 1: Provider Selection & Failover
```php
// High-level pattern (implementation in tasks.md)
$provider = ProviderSelector::select($workspace);
if ($provider === 'webjs') {
    $adapter = new WebJSAdapter($workspace);
} else {
    $adapter = new MetaAPIAdapter($credentials);
}
$result = $adapter->sendMessage($contact, $message);
```

### Pattern 2: Broadcasting Driver Abstraction
```javascript
// High-level pattern (implementation in tasks.md)
const echo = getEchoInstance(broadcasterConfig);
echo.channel(`workspace.${workspaceId}`)
    .listen('WhatsAppQRGenerated', handleQR)
    .listen('WhatsAppSessionStatusChanged', handleStatus);
```

### Pattern 3: Session Lifecycle Management
```javascript
// High-level pattern (implementation in tasks.md)
const session = await WhatsAppManager.createSession(workspaceId);
// States: initializing → qr_required → authenticating → connected
// Automatic recovery on restart via LocalAuth
```

### Pattern 4: Secure Inter-Service Communication
```php
// High-level pattern (implementation in tasks.md)
// Laravel → Node.js
$response = Http::withHeaders([
    'X-HMAC-Signature' => generateHmacSignature($payload),
    'X-Timestamp' => time(),
])->post('/api/sessions', $data);
```

---

## 📊 ARCHITECTURE CHARACTERISTICS

| Aspect | Decision | Rationale | Impact |
|--------|----------|-----------|---------|
| **Broadcasting** | Laravel Reverb (default) + Pusher (optional) | Native Laravel 12, zero cost, proven compatibility | Zero external dependencies for basic functionality |
| **Service Separation** | Node.js for WhatsApp, Laravel for business logic | WhatsApp Web JS requires Puppeteer instances | Independent scaling, clear responsibilities |
| **Provider Strategy** | Abstraction with automatic failover | Support both Meta API and Web JS simultaneously | Improved reliability, zero-downtime provider switching |
| **Session Management** | File-based with workspace isolation | WhatsApp Web JS LocalAuth requirement | Persistent sessions, security through isolation |
| **Security** | HMAC authentication + AES encryption | Inter-service security + GDPR compliance | Protection against unauthorized access and data breaches |
| **Deployment** | Zero-downtime with blue-green strategy | Session persistence requirements | No service interruption during deployments |

---

## 🔒 SECURITY ARCHITECTURE

### Authentication & Authorization
- **Inter-Service:** HMAC-SHA256 with timestamp validation
- **Session Isolation:** Workspace-based directory structure with strict permissions
- **Data Protection:** AES-256-CBC encryption for sensitive session data
- **Access Control:** Role-based permissions with audit logging

### Network Security
- **API Communication:** HTTPS-only with certificate validation
- **Webhook Validation:** HMAC signature verification with replay attack prevention
- **Rate Limiting:** Progressive delays based on message volume
- **IP Whitelisting:** Production environment restrictions

---

## ⚡ PERFORMANCE ARCHITECTURE

### Scalability Strategy
- **Horizontal Scaling:** Multiple Node.js instances with load balancer
- **Session Limits:** 50 concurrent sessions per instance (configurable)
- **Queue Management:** Priority-based message processing with Redis
- **Resource Monitoring:** Real-time metrics with auto-scaling triggers

### Optimization Measures
- **Database:** Composite indexes for common query patterns
- **Caching:** Session and configuration caching with TTL
- **Memory Management:** Resource limits per session with auto-cleanup
- **Network:** Connection pooling and timeout management

---

## 🏗️ DEPLOYMENT ARCHITECTURE

### Service Deployment
- **Laravel:** Traditional PHP deployment with process manager
- **Node.js:** PM2 cluster mode with auto-restart capabilities
- **Reverb:** Laravel Reverb server via Supervisor/systemd
- **Database:** Standard MySQL deployment with backup strategy

### Environment Strategy
- **Development:** All services on single machine (localhost)
- **Staging:** Separate instances for testing integration
- **Production:** Load-balanced with monitoring and alerting
- **CI/CD:** Automated deployment with rollback capabilities

---

## 📈 MONITORING & OBSERVABILITY

### Key Metrics
- **Business:** Message delivery rates, session uptime, user satisfaction
- **Technical:** Response times, error rates, resource utilization
- **Security:** Authentication attempts, suspicious activities, compliance
- **Operational:** Deployment success, backup integrity, alert accuracy

### Alerting Strategy
- **Critical:** Session failures, security breaches, data corruption
- **High:** Performance degradation, provider outages, resource exhaustion
- **Medium:** Configuration issues, minor errors, maintenance reminders
- **Low:** Informational events, successful operations, routine activities

---

## 🔄 INTEGRATION PATTERNS

### Pattern A: Real-time QR Broadcasting
```
User Action → Laravel API → Node.js Service → WhatsApp Web JS
                                                           ↓
Node.js Webhook → Laravel Event → Laravel Broadcasting → Frontend Echo
```

### Pattern B: Message Flow with Failover
```
Message Send → ProviderSelector → Primary Provider (Web JS)
                                                           ↓ (if fails)
Fallback Provider (Meta API) → Message Delivered → Response Processing
```

### Pattern C: Session Lifecycle Management
```
Session Create → QR Generation → User Authentication → Session Ready
                                                           ↓ (on disconnect)
Auto-Reconnect → Session Recovery → Status Broadcast → UI Update
```

### Pattern D: Health Monitoring & Alerting
```
Service Health → Metrics Collection → Threshold Check → Alert Trigger
                                                           ↓ (if threshold exceeded)
Notification → Admin Dashboard → Manual Intervention → Issue Resolution
```

---

## 🎯 ARCHITECTURE PRINCIPLES

1. **Simplicity First:** Choose the simplest solution that meets requirements
2. **Progressive Enhancement:** Start with basic functionality, add complexity as needed
3. **Clear Separation:** Each service has a single, well-defined responsibility
4. **Security by Design:** Security considerations built into every component
5. **Operational Excellence:** Monitoring, logging, and alerting as first-class concerns
6. **Developer Experience:** Easy to understand, test, and maintain

---

## 🚨 ARCHITECTURE RISKS & MITIGATIONS

### Critical Architecture Risks
- **Service Coupling:** Tight coupling between Laravel and Node.js
  - **Mitigation:** Well-defined API contracts with version management
- **State Synchronization:** Inconsistent state between services
  - **Mitigation:** Event-driven architecture with clear ownership
- **Security Vulnerabilities:** Authentication bypass or data exposure
  - **Mitigation:** Defense in depth with multiple security layers

### Performance Risks
- **Resource Exhaustion:** Memory leaks or CPU spikes
  - **Mitigation:** Resource limits, monitoring, and auto-recovery
- **Scalability Limits:** Inability to handle growth
  - **Mitigation:** Horizontal scaling design from day one
- **Network Bottlenecks:** Slow inter-service communication
  - **Mitigation:** Connection pooling, caching, and optimization

---

## 🚨 CRITICAL GAPS MITIGATION ARCHITECTURE

### Database Schema Gap Resolution (P0 BLOCKING)
**Problem:** `whatsapp_sessions` table MISSING dari existing schema
**Solution Architecture:**
- **Migration Strategy:** Create dedicated migration untuk `whatsapp_sessions` table
- **Data Migration:** Migrate existing Meta API credentials dari `workspaces.metadata`
- **Foreign Keys:** Add `whatsapp_session_id` ke `chats` dan `campaign_logs` tables
- **Junction Table:** Create `contact_sessions` untuk multi-number contact tracking

### WhatsApp Web.js Issues Mitigation (8 Critical Issues)
**Problem:** 8 critical issues dari GitHub research dapat menyebabkan production failure
**Solution Architecture:**

#### Issue #1: Silent Disconnection (CRITICAL - SHOWSTOPPER)
- **Component:** SessionHealthMonitor service
- **Strategy:** Periodic health checks dengan test messages
- **Detection:** Monitor `lastMessageReceivedAt` timestamp
- **Recovery:** Auto-reconnect dengan LocalAuth restoration

#### Issue #2: Storage Bloat (SCALABILITY ISSUE)
- **Component:** SessionStorageOptimizer service
- **Strategy:** Enforce 100MB quota per session
- **Cleanup:** Remove cache folders (Default/Cache, GPUCache, dll)
- **Schedule:** Daily cleanup job untuk inactive sessions

#### Issue #3: Destroy Hangs (RESOURCE LEAK)
- **Component:** SessionManager dengan timeout handling
- **Strategy:** Promise.race dengan 30s timeout
- **Fallback:** Force kill Puppeteer process jika timeout
- **Cleanup:** Always remove dari memory regardless of destroy success

#### Issue #4: File Descriptor Exhaustion (PRODUCTION CRASH)
- **Component:** SessionPool dengan capacity management
- **Strategy:** Max 50 concurrent sessions per instance
- **System Tuning:** Increase ulimit -n ke 65536
- **Monitoring:** Track file descriptor usage setiap 5 menit

#### Issue #5: Chrome Profile Locked (RECOVERY ISSUE)
- **Component:** ProfileLockCleaner service
- **Strategy:** Clean lock files sebelum session initialization
- **Files:** Remove SingletonLock, SingletonCookie, SingletonSocket
- **Schedule:** Cleanup on startup untuk orphaned locks

#### Issue #6: QR Infinite Loop (UX BLOCKER)
- **Component:** Stealth mode configuration + rate limiting
- **Strategy:** Puppeteer stealth args untuk avoid detection
- **Rate Limit:** Max 5 QR generations per workspace per hour
- **User-Agent:** Real Chrome user-agent untuk avoid bot detection

#### Issue #7: Memory Leaks (STABILITY ISSUE)
- **Component:** MemoryManager dengan monitoring
- **Strategy:** Resource limits dengan auto-kill pada threshold
- **GC:** Manual garbage collection dengan --expose-gc flag
- **Cleanup:** Remove inactive sessions (>1 hour disconnected)

#### Issue #8: Anti-Ban Protection (BUSINESS IMPACT)
- **Component:** WhatsAppRateLimiter service
- **Strategy:** Progressive delays berdasarkan message volume
- **Limits:** Max 20 msg/min, 500 msg/hour, 5000 msg/day
- **Smart Distribution:** Weighted round-robin untuk campaign messages

---

## 🔒 ENHANCED SECURITY ARCHITECTURE

### Session Data Protection
- **Encryption:** AES-256-CBC untuk session data (5-10MB per session)
- **Key Management:** Rotatable encryption keys tanpa data loss
- **Access Control:** Workspace isolation dengan strict file permissions (700)
- **Audit Trail:** Complete logs untuk semua session activities

### Inter-Service Authentication
- **HMAC-SHA256:** Signature validation dengan timestamp
- **Replay Prevention:** 5-minute timestamp window untuk prevent replay attacks
- **Rate Limiting:** Max 100 requests/minute per API key
- **IP Whitelisting:** Production environment dengan Laravel IP restrictions

### Session Isolation Strategy
- **File Structure:** `/sessions/{workspace_id}/{session_id}/` directory isolation
- **Process Isolation:** Separate Chromium instances per session
- **Network Isolation:** HMAC-authenticated API communication
- **Data Isolation:** Encrypted session data dengan workspace-scoped access

---

## ⚡ PERFORMANCE OPTIMIZATION ARCHITECTURE

### Session Health Optimization
- **Health Score Algorithm:** 0-100 score berdasarkan connection stability
- **Proactive Monitoring:** Real-time metrics collection setiap 30 detik
- **Predictive Recovery:** Auto-reconnect sebelum user notice disconnection
- **Resource Optimization:** Memory limits dengan auto-cleanup pada threshold

### Message Throughput Optimization
- **Priority Queues:** 4-tier priority system (urgent, high, normal, campaign)
- **Smart Batching:** 50 chats per batch untuk sync operations
- **Rate Limiting:** Progressive delays untuk avoid WhatsApp throttling
- **Caching Strategy:** Session metadata caching dengan TTL optimization

### Database Query Optimization
- **Composite Indexes:** Multi-column indexes untuk common query patterns
- **Eager Loading:** Prevent N+1 queries dengan proper relationship loading
- **Query Caching:** Session dan configuration caching untuk reduce DB load
- **Pagination:** Max 50 items per page untuk large datasets

---

## 🏗️ PRODUCTION HARDENING ARCHITECTURE

### Zero-Downtime Deployment
- **Blue-Green Strategy:** Session migration antara Node.js instances
- **Load Balancer:** Nginx upstream switching tanpa service interruption
- **Health Checks:** Pre dan post-deployment health verification
- **Rollback:** 5-minute rollback capability jika deployment failed

### Monitoring & Alerting Architecture
- **Metrics Collection:** Prometheus-compatible metrics export
- **Grafana Dashboards:** Real-time visualization untuk semua key metrics
- **Alert Manager:** Multi-channel alerting (email, Slack, dashboard)
- **Log Aggregation:** Structured JSON logging untuk easy parsing

### Backup & Disaster Recovery
- **Automated Backups:** Daily encrypted backups untuk database dan session files
- **Retention Policy:** 30-day retention dengan S3/cloud storage rotation
- **Integrity Verification:** Daily backup verification dengan decryption tests
- **Restore Procedures:** Documented dan tested restore procedures

### System Limits & Resource Management
- **File Descriptors:** ulimit -n 65536 untuk handle 50+ concurrent sessions
- **Memory Limits:** PM2 max-memory-restart 2GB untuk Node.js instances
- **Storage Quotas:** 100MB per session dengan auto-cleanup enforcement
- **CPU Limits:** Process monitoring dengan auto-restart pada high CPU

---

## 📊 CRITICAL PATH ARCHITECTURE

### Week 1: Foundation (BLOCKING)
```
Database Migration → Schema Updates → Data Migration → Basic Testing
     ↓                    ↓              ↓              ↓
whatsapp_sessions    chats FK       existing      provider
table creation    campaign_logs FK  credentials   selection
```

### Week 2-3: Core Implementation
```
Node.js Service → Mitigation Services → Laravel Integration → Testing
     ↓                ↓                     ↓                ↓
Session Manager  8 Critical Issues    Webhook Handlers  Integration
Health Monitor   Rate Limiter         Provider Selector  Tests
Storage Optimizer Memory Manager      Security Layer
```

### Week 4: Production Readiness
```
Frontend Integration → Load Testing → Security Audit → Deployment
     ↓                     ↓              ↓              ↓
QR Components        50 Sessions    Penetration   PM2 Setup
Real-time Updates    1000 msg/min   Testing       Monitoring
Admin Settings       Memory Usage   Compliance    Backup Strategy
```

---

## ✅ ARCHITECTURE VALIDATION

### Verification Criteria
- [ ] **Functionality:** All requirements met dengan proposed architecture
- [ ] **Critical Gaps:** Database schema dan foreign keys implemented
- [ ] **WhatsApp Issues:** All 8 critical issues memiliki mitigation strategy
- [ ] **Performance:** Benchmarks achieved (50 sessions, 1000 msg/min)
- [ ] **Security:** Penetration testing passed, compliance achieved
- [ ] **Operability:** Monitoring effective, deployment reliable
- [ ] **Maintainability:** Code organized, documentation complete

### Success Metrics
- **Reliability:** 99.5% uptime untuk semua services
- **Performance:** < 2s message delivery, < 3s QR generation
- **Security:** Zero critical vulnerabilities, 100% encryption coverage
- **Scalability:** Support 100+ concurrent sessions tanpa degradation
- **Operability:** < 5min MTTR (mean time to recovery)
- **Critical Gaps:** Zero P0 blocking issues untuk implementation

### Risk Mitigation Validation
- **Database Schema:** Migration scripts tested dan rollback plan ready
- **WhatsApp Issues:** All mitigation strategies implemented dan tested
- **Security:** HMAC authentication dan encryption validated
- **Performance:** Load testing dengan production-scale metrics
- **Monitoring:** All critical metrics memiliki alerting rules

---

**Document Status:** ARCHITECTURE COMPLETE WITH CRITICAL GAPS RESOLUTION
**Ready for Implementation:** ✅ YES
**Critical Gaps Status:** ⚠️ REQUIRES IMMEDIATE ACTION (Database migration P0)
**Next Document:** tasks.md (detailed implementation steps dengan mitigation tasks)
