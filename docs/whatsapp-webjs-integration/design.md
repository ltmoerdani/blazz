# TECHNICAL ARCHITECTURE - WhatsApp Web JS Integration

## 📋 EXECUTIVE SUMMARY

**Document Purpose:** High-level technical architecture and key integration patterns  
**Audience:** Developers, technical architects  
**Scope:** Architecture overview and key technical decisions (implementation details moved to tasks.md)  
**Status:** FOCUSED - Simplified from 500+ lines to essential architecture only

---

## 🎯 CORE ARCHITECTURE DECISIONS

### ARCH-1: Broadcasting Strategy (UPDATED 2025-10-11)
**Decision:** Laravel Reverb as DEFAULT broadcast driver, Pusher as optional alternative
- **Rationale:** Socket.IO unused in codebase, Laravel Reverb is native Laravel 12 solution
- **Architecture:** Dual-driver support with dynamic configuration
- **Integration:** Laravel Echo client supports both drivers seamlessly
- **Migration:** Zero breaking changes, admin can switch instantly

### ARCH-2: Service Architecture
**Decision:** Dedicated Node.js service for WhatsApp Web JS, Laravel for business logic
- **Rationale:** WhatsApp Web JS requires Puppeteer (Chromium) instances
- **Architecture:** Microservice pattern with clear separation of concerns
- **Communication:** REST API + HMAC authentication + Webhook callbacks
- **Deployment:** Independent scaling and deployment cycles

### ARCH-3: Provider Abstraction
**Decision:** Strategy pattern with automatic provider selection and failover
- **Rationale:** Support both Meta API and WhatsApp Web JS simultaneously
- **Architecture:** ProviderSelector service with health monitoring
- **Failover:** Automatic fallback with manual override capability
- **Benefits:** Zero-downtime provider switching, improved reliability

### ARCH-4: Session Management
**Decision:** File-based session persistence with workspace isolation
- **Rationale:** WhatsApp Web JS LocalAuth supports persistent sessions
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

## ✅ ARCHITECTURE VALIDATION

### Verification Criteria
- [ ] **Functionality:** All requirements met with proposed architecture
- [ ] **Performance:** Benchmarks achieved (50 sessions, 1000 msg/min)
- [ ] **Security:** Penetration testing passed, compliance achieved
- [ ] **Operability:** Monitoring effective, deployment reliable
- [ ] **Maintainability:** Code organized, documentation complete

### Success Metrics
- **Reliability:** 99.5% uptime for all services
- **Performance:** < 2s message delivery, < 3s QR generation
- **Security:** Zero critical vulnerabilities, 100% encryption coverage
- **Scalability:** Support 100+ concurrent sessions without degradation
- **Operability:** < 5min MTTR (mean time to recovery)

---

**Document Status:** ARCHITECTURE COMPLETE  
**Ready for Implementation:** ✅ YES  
**Next Document:** tasks.md (detailed implementation steps)
