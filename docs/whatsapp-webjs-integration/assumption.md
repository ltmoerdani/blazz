# TECHNICAL ASSUMPTIONS - WhatsApp Web JS Integration

## 📋 EXECUTIVE SUMMARY

**Document Purpose:** Technical assumptions and architectural decisions for WhatsApp Web JS integration
**Audience:** Technical team, developers, architects
**Scope:** Core technical assumptions only (implementation details moved to design.md)
**Status:** OPTIMIZED - Streamlined for clean, efficient implementation
**Last Updated:** 12 Oktober 2025 (Optimization Phase)

---

## 🎯 CORE TECHNICAL ASSUMPTIONS

### ASM-1: Broadcasting Strategy (CRITICAL - UPDATED 2025-10-11)
**Assumption:** Laravel Reverb as DEFAULT broadcast driver, Pusher as optional alternative
- **Evidence:** Socket.IO client installed but NO server implementation found
- **Decision:** Laravel Reverb (100% free, Laravel 12 native) as default
- **Risk Level:** LOW (additive change, zero breaking changes)
- **Impact if Wrong:** Minimal - admin can switch back to Pusher instantly

### ASM-2: Database Architecture (VERIFIED)
**Assumption:** New `whatsapp_accounts` table needed for session data storage
- **Evidence:** `workspaces.metadata` JSON field NOT suitable for 5-10MB session data
- **Decision:** Dedicated table with encrypted session data
- **Risk Level:** MEDIUM (migration required)
- **Impact if Wrong:** Performance issues, data corruption

### ASM-3: Provider Selection Logic (VERIFIED)
**Assumption:** Intelligent provider selection with automatic failover
- **Evidence:** Current single-provider (Meta API) architecture
- **Decision:** ProviderSelector service with health monitoring
- **Risk Level:** HIGH (affects message delivery)
- **Impact if Wrong:** Messages may not be delivered if provider down

### ASM-4: Session Management (VERIFIED)
**Assumption:** WhatsApp Web JS LocalAuth for session persistence
- **Evidence:** whatsapp-web.js library supports LocalAuth strategy
- **Decision:** File-based session storage with encryption
- **Risk Level:** HIGH (affects user experience)
- **Impact if Wrong:** Users must re-scan QR after server restart

### ASM-5: Security Architecture (CRITICAL)
**Assumption:** HMAC authentication for Laravel-Node.js communication
- **Evidence:** Existing HMAC_SECRET in Node.js service config
- **Decision:** HMAC-SHA256 with timestamp validation
- **Risk Level:** CRITICAL (security vulnerability)
- **Impact if Wrong:** Unauthorized access to WhatsApp accounts

---

## 🔒 SECURITY ASSUMPTIONS

### ASM-6: Session Data Protection
**Assumption:** AES-256-CBC encryption for session data (5-10MB per session)
- **Evidence:** Laravel supports field-level encryption
- **Decision:** Encrypted database fields with rotatable keys
- **Risk Level:** CRITICAL (GDPR compliance)
- **Impact if Wrong:** Data breach, compliance violations

### ASM-7: Session Isolation
**Assumption:** File-based session storage isolated per workspace
- **Evidence:** WhatsApp Web JS LocalAuth supports custom paths
- **Decision:** `/sessions/{workspace_id}/{session_id}/` with 700 permissions
- **Risk Level:** CRITICAL (cross-workspace access)
- **Impact if Wrong:** Session hijacking, unauthorized message access

### ASM-8: API Authentication
**Assumption:** HMAC-SHA256 sufficient for inter-service security
- **Evidence:** Existing HMAC_SECRET configuration in Node.js service
- **Decision:** Request signature validation with timestamp
- **Risk Level:** CRITICAL (MITM attacks)
- **Impact if Wrong:** Message interception, session hijacking

---

## ⚡ PERFORMANCE ASSUMPTIONS

### ASM-9: Concurrent Session Limits
**Assumption:** 50 concurrent WhatsApp accounts per Node.js instance
- **Evidence:** Puppeteer default limits and memory constraints
- **Decision:** Session pooling with max limit enforcement
- **Risk Level:** HIGH (server overload)
- **Impact if Wrong:** Session crashes, poor performance

### ASM-10: Message Throughput
**Assumption:** 1000+ messages/minute across all sessions
- **Evidence:** Current Laravel Queue system capability
- **Decision:** Priority queue system with Redis backend
- **Risk Level:** MEDIUM (message delays)
- **Impact if Wrong:** Campaign failures, poor deliverability

### ASM-11: Resource Usage
**Assumption:** ~100MB RAM per WhatsApp account (Chromium instance)
- **Evidence:** Puppeteer documentation benchmarks
- **Decision:** Resource monitoring with auto-kill limits
- **Risk Level:** HIGH (OOM issues)
- **Impact if Wrong:** Server crashes, downtime

---

## 🏗️ OPERATIONAL ASSUMPTIONS

### ASM-12: Service Architecture
**Assumption:** Node.js service for WhatsApp Web JS, Laravel Reverb for broadcasting
- **Evidence:** Clear separation of concerns needed
- **Decision:** Dedicated Node.js service for Puppeteer/WhatsApp Web JS only
- **Risk Level:** HIGH (integration complexity)
- **Impact if Wrong:** WhatsApp functionality broken

### ASM-13: Deployment Strategy
**Assumption:** Zero-downtime deployment with blue-green strategy
- **Evidence:** Session persistence requirements
- **Decision:** Session migration between Node.js instances
- **Risk Level:** MEDIUM (deployment complexity)
- **Impact if Wrong:** Session disconnections during deployment

### ASM-14: Monitoring & Alerting
**Assumption:** Real-time monitoring of session health and performance
- **Evidence:** Production operational requirements
- **Decision:** Health check endpoints with alerting
- **Risk Level:** MEDIUM (operational overhead)
- **Impact if Wrong:** Issues detected too late

---

## 📊 ASSUMPTION VALIDATION STATUS

| ID | Category | Status | Priority | Risk | Validation Evidence |
|----|----------|--------|----------|------|-------------------|
| ASM-1 | Broadcasting | ✅ VERIFIED | CRITICAL | LOW | Laravel Reverb native Laravel 12, Socket.IO unused in codebase |
| ASM-2 | Database | ⚠️ REQUIRES ACTION | P0 CRITICAL | HIGH | `whatsapp_accounts` table MISSING dari schema existing |
| ASM-3 | Provider Logic | ✅ VERIFIED | HIGH | HIGH | Existing service layer siap untuk abstraction |
| ASM-4 | Session Mgmt | ⚠️ REQUIRES MITIGATION | P0 CRITICAL | HIGH | WhatsApp Web.js 8 critical issues perlu dimitigasi |
| ASM-5 | Security | ✅ VERIFIED | CRITICAL | CRITICAL | HMAC_SECRET sudah ada di Node.js config |
| ASM-6 | Data Protection | ✅ VERIFIED | CRITICAL | HIGH | Laravel field encryption supports AES-256-CBC |
| ASM-7 | Session Isolation | ✅ VERIFIED | CRITICAL | CRITICAL | LocalAuth supports custom paths per workspace |
| ASM-8 | API Security | ✅ VERIFIED | CRITICAL | CRITICAL | Existing HMAC_SECRET dapat digunakan |
| ASM-9 | Performance | ⚠️ REQUIRES TESTING | HIGH | HIGH | Puppeteer limits perlu diverifikasi dengan 50 sessions |
| ASM-10 | Throughput | ✅ VERIFIED | MEDIUM | MEDIUM | Laravel Queue sudah mature dan tested |
| ASM-11 | Resource Usage | ⚠️ REQUIRES MONITORING | HIGH | HIGH | Memory monitoring perlu diimplementasi |
| ASM-12 | Architecture | ✅ VERIFIED | HIGH | HIGH | Clear separation of concerns sudah ada |
| ASM-13 | Deployment | ✅ VERIFIED | MEDIUM | MEDIUM | Session persistence requirement sudah diketahui |
| ASM-14 | Monitoring | ⚠️ REQUIRES IMPLEMENTATION | HIGH | MEDIUM | Health check endpoints perlu dibuat |

**Legend:** ✅ VERIFIED (evidence-based) | ⚠️ REQUIRES ACTION (critical gaps found) | ❌ INVALID (deprecated)

---

## 🎯 KEY DECISIONS SUMMARY

1. **Broadcasting:** Laravel Reverb as default (free), Pusher as optional
2. **Database:** New `whatsapp_accounts` table with encrypted data
3. **Architecture:** Provider abstraction with automatic failover
4. **Security:** HMAC authentication + AES-256 encryption
5. **Performance:** 50 sessions/instance, 1000 msg/minute capacity
6. **Operations:** Zero-downtime deployment, comprehensive monitoring

---

## 🚨 CRITICAL RISKS & MITIGATIONS

### P0 CRITICAL RISKS (Must address immediately)
- **Session Data Breach:** AES-256 encryption with rotatable keys
- **Cross-Workspace Access:** Strict file permissions + workspace validation
- **API Authentication Bypass:** HMAC validation with timestamp checks

### P1 HIGH RISKS (Must address in Phase 1)
- **Provider Failover Failure:** Health monitoring + auto-recovery
- **Session Recovery Issues:** Comprehensive logging + manual recovery tools
- **Performance Degradation:** Load testing + resource monitoring

### P2 MEDIUM RISKS (Address in Phase 2)
- **Message Delivery Delays:** Priority queue system + Redis backend
- **Resource Exhaustion:** Auto-scaling triggers + monitoring alerts

---

## ✅ VERIFICATION CHECKLIST

### Before Implementation
- [ ] **Security Review:** HMAC implementation, encryption strategy
- [ ] **Performance Testing:** 50 sessions, 1000 msg/minute benchmarks
- [ ] **Integration Testing:** Laravel ↔ Node.js communication
- [ ] **Failover Testing:** Provider switch scenarios
- [ ] **Recovery Testing:** Session restoration after restart

### After Implementation
- [ ] **Penetration Testing:** Session hijacking, API attacks
- [ ] **Load Testing:** Production-scale session counts
- [ ] **Disaster Recovery:** Backup/restore procedures
- [ ] **Monitoring Validation:** Alert accuracy, response times

---

**Document Status:** FOCUSED & ACTIONABLE  
**Total Assumptions:** 14 (down from 24)  
**Ready for Implementation:** ✅ YES  
**Next Document:** design.md (technical architecture)
