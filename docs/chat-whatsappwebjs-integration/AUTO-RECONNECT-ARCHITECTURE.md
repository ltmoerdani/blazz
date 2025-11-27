# Auto-Reconnect Architecture

**Date:** October 22, 2025
**Feature:** Automatic Session Restoration & Reconnection
**Status:** ✅ **IMPLEMENTED**
**Priority:** 🔴 **CRITICAL** - Production Reliability

---

## 🎯 PROBLEM STATEMENT

### **User Experience Issue:**
User harus manually reconnect dan scan QR code setiap kali Node.js service restart. Ini membuat aplikasi **NOT PRODUCTION-READY**.

### **Technical Root Cause:**
1. Node.js service **stateless** - session data hanya di memory (Map)
2. Ketika service restart → session Map kosong
3. WhatsApp Web.js **LocalAuth** save session ke disk, tapi service tidak restore
4. Laravel database punya session "connected", tapi Node.js tidak tahu
5. Tidak ada health monitoring & auto-recovery mechanism

---

## 🏗️ SOLUTION ARCHITECTURE

Implementasi **3-Phase Auto-Reconnect System**:

### **Phase 1: Session Restoration on Startup** ✅
- Node.js service query Laravel database untuk semua "connected" sessions
- Restore each session menggunakan LocalAuth disk storage
- Update Laravel DB jika restoration failed
- **Result:** Zero-downtime session recovery after restart

### **Phase 2: Auto-Reconnect on Disconnection** ✅
- Deteksi disconnection events dari WhatsApp
- Distinguish user-initiated vs technical disconnects
- Auto-retry dengan exponential backoff strategy
- Notify Laravel of reconnection status
- **Result:** Automatic recovery from network/technical issues

### **Phase 3: Health Monitoring** (Future)
- Periodic health checks on all sessions
- Proactive reconnection jika session unresponsive
- Alert admin jika repeated failures
- **Result:** Predictive maintenance & high availability

---

## 📁 FILE STRUCTURE

### **Node.js Service:**

```
whatsapp-service/
├── src/
│   └── services/
│       ├── SessionRestoration.js  ← NEW: Restore sessions on startup
│       └── AutoReconnect.js       ← NEW: Handle auto-reconnect logic
├── server.js                       ← UPDATED: Integrated new services
└── logs/
    └── whatsapp-service.log        ← Monitor restoration logs
```

### **Laravel Backend:**

```
app/
├── Http/
│   └── Controllers/
│       └── Api/
│           └── WhatsAppWebJSController.php  ← UPDATED: New endpoints
└── Models/
    └── WhatsAppAccount.php

routes/
└── api.php  ← UPDATED: New restoration routes
```

---

## 🔧 IMPLEMENTATION DETAILS

### **1. SessionRestoration Service**

**File:** `whatsapp-service/src/services/SessionRestoration.js`

**Responsibilities:**
- Query Laravel API for active sessions
- Restore sessions from LocalAuth disk storage
- Handle restoration failures gracefully
- Update Laravel DB with restoration status

**Key Methods:**

```javascript
// Called once on Node.js startup
async restoreAllSessions()

// Query Laravel for sessions with status='connected' or 'authenticated'
async getActiveSessions()

// Restore single session from disk
async restoreSession(sessionData)

// Mark failed sessions as disconnected
async markSessionAsDisconnected(sessionId, workspaceId, reason)
```

**Startup Flow:**

```
Node.js Startup
    │
    ├──> Query Laravel: GET /api/whatsapp/sessions/active
    │        └──> Returns: [{ session_id, workspace_id, phone_number }]
    │
    ├──> For each session:
    │        ├──> Check if already in memory (skip if yes)
    │        ├──> Call createSession(session_id, workspace_id)
    │        │        └──> LocalAuth restores from disk
    │        │
    │        ├──> Success? → Log & continue
    │        └──> Failed? → Mark as disconnected in Laravel
    │
    └──> Log final results: X restored, Y failed
```

---

### **2. AutoReconnect Service**

**File:** `whatsapp-service/src/services/AutoReconnect.js`

**Responsibilities:**
- Handle disconnection events
- Distinguish user vs technical disconnects
- Implement exponential backoff retry
- Track reconnection attempts per session
- Notify Laravel of reconnection status

**Key Methods:**

```javascript
// Called when session disconnects
async handleDisconnection(sessionId, workspaceId, reason)

// Check if disconnect was user-initiated
isUserInitiated(reason)

// Schedule reconnection with exponential backoff
async scheduleReconnect(sessionId, workspaceId)

// Attempt to reconnect session
async attemptReconnect(sessionId, workspaceId)

// Notify Laravel
async notifyReconnectSuccess(sessionId, workspaceId)
async notifyReconnectFailed(sessionId, workspaceId)
```

**Reconnection Flow:**

```
Session Disconnected
    │
    ├──> Is user-initiated? (LOGOUT, USER_REQUESTED)
    │        └──> YES → Stop (don't reconnect)
    │
    ├──> NO → Technical disconnect detected
    │        │
    │        ├──> Attempt 1: Wait 5 seconds → Retry
    │        ├──> Attempt 2: Wait 10 seconds → Retry
    │        ├──> Attempt 3: Wait 20 seconds → Retry
    │        ├──> Attempt 4: Wait 40 seconds → Retry
    │        ├──> Attempt 5: Wait 80 seconds → Retry (FINAL)
    │        │
    │        ├──> Success? → Notify Laravel: session_reconnected
    │        └──> Failed all 5? → Notify Laravel: session_reconnect_failed
    │
    └──> Laravel broadcasts status to frontend
```

**Exponential Backoff Formula:**

```javascript
delay = min(baseDelay * 2^attempts, maxDelay)

// Example:
baseDelay = 5000ms (5 seconds)
maxDelay = 300000ms (5 minutes)

Attempt 1: 5s
Attempt 2: 10s
Attempt 3: 20s
Attempt 4: 40s
Attempt 5: 80s
```

---

### **3. Laravel API Endpoints**

**File:** `app/Http/Controllers/Api/WhatsAppWebJSController.php`

#### **Endpoint 1: Get Active Sessions**

```http
GET /api/whatsapp/sessions/active
Headers:
  X-API-Key: {node_api_key}
  X-HMAC-Signature: {signature}
  X-Timestamp: {timestamp}

Response:
{
  "success": true,
  "sessions": [
    {
      "id": 36,
      "session_id": "webjs_1_1761120782_cfxRW5fB",
      "workspace_id": 1,
      "phone_number": "62811801641",
      "status": "connected",
      "provider_type": "webjs"
    }
  ],
  "count": 1
}
```

**Query Logic:**
```php
WhatsAppAccount::whereIn('status', ['connected', 'authenticated'])
    ->where('is_active', true)
    ->get()
```

#### **Endpoint 2: Mark Session Disconnected**

```http
POST /api/whatsapp/sessions/{sessionId}/mark-disconnected
Headers:
  X-API-Key: {node_api_key}
  X-HMAC-Signature: {signature}
  X-Timestamp: {timestamp}

Body:
{
  "workspace_id": 1,
  "reason": "Failed to restore session on startup"
}

Response:
{
  "success": true,
  "message": "Session marked as disconnected"
}
```

**Actions:**
1. Update status to "disconnected"
2. Save disconnect reason in metadata
3. Broadcast status change to frontend via WebSocket

---

## 🔐 SECURITY

All endpoints menggunakan **HMAC SHA256 signature** yang sama dengan webhook:

```javascript
// Node.js
const timestamp = Math.floor(Date.now() / 1000).toString();
const payloadString = JSON.stringify(data);
const signature = crypto
    .createHmac('sha256', process.env.HMAC_SECRET)
    .update(timestamp + payloadString)
    .digest('hex');

headers: {
    'X-API-Key': process.env.API_KEY,
    'X-Timestamp': timestamp,
    'X-HMAC-Signature': signature
}
```

```php
// Laravel (VerifyWhatsAppHmac middleware)
$expectedSignature = hash_hmac('sha256', $timestamp . $payload, config('whatsapp.node_api_secret'));

if (!hash_equals($expectedSignature, $signature)) {
    abort(401, 'Invalid signature');
}
```

---

## 🧪 TESTING GUIDE

### **Test 1: Service Restart Auto-Reconnect**

**Goal:** Verify sessions auto-restore after Node.js restart

```bash
# Step 1: Verify session is connected
curl http://localhost:3001/health
# Should show: "total": 1, "connected": 1

# Step 2: Restart Node.js service
cd whatsapp-service
pm2 restart whatsapp-service

# Step 3: Check logs for restoration
tail -f logs/whatsapp-service.log | grep "restoration"

# Expected logs:
# 🔄 Starting session restoration from database...
# Found 1 active session(s) to restore
# Restoring session: webjs_1_1761120782_cfxRW5fB
# ✅ Session restored successfully
# ✅ Session restoration completed: 1 restored, 0 failed, 1 total

# Step 4: Verify session is active
curl http://localhost:3001/health
# Should show: "total": 1, "connected": 1

# Step 5: Send test WhatsApp message
# Message should be received and processed normally
```

**Expected Result:**
- ✅ Session restored automatically
- ✅ No QR code scan required
- ✅ Messages work immediately

---

### **Test 2: Network Disconnect Auto-Reconnect**

**Goal:** Verify auto-reconnect pada technical disconnect

```bash
# Step 1: Simulate network disconnect
# Temporarily disable WiFi/network for 10 seconds

# Step 2: Check Node.js logs
tail -f logs/whatsapp-service.log | grep "disconnect\|reconnect"

# Expected logs:
# WhatsApp account disconnected {"reason": "CONNECTIVITY_ISSUE"}
# Technical disconnect detected, initiating auto-reconnect
# Scheduling reconnection attempt {"attempt": 1, "delayMs": 5000}
# Attempting reconnection {"attempt": 1}
# ✅ Auto-reconnection successful

# Step 3: Verify session status in Laravel
php artisan tinker --execute="
  \$session = \App\Models\WhatsAppAccount::where('session_id', 'webjs_1_...')
    ->first();
  echo 'Status: ' . \$session->status . PHP_EOL;
"

# Should show: Status: connected
```

**Expected Result:**
- ✅ Auto-reconnection triggered
- ✅ Exponential backoff applied
- ✅ Laravel DB updated
- ✅ Frontend notified via WebSocket

---

### **Test 3: User Logout (Should NOT Reconnect)**

**Goal:** Verify user-initiated logouts tidak trigger auto-reconnect

```bash
# Step 1: User clicks "Disconnect" in UI
# → POST /api/user/whatsapp-accounts/{uuid}/disconnect

# Step 2: Check Node.js logs
tail -f logs/whatsapp-service.log | grep "disconnect"

# Expected logs:
# WhatsApp account disconnected {"reason": "USER_LOGOUT"}
# User-initiated disconnect, not reconnecting

# Step 3: Verify no reconnection attempts
sleep 30
tail -f logs/whatsapp-service.log | grep "reconnect"
# Should see: NO reconnection logs
```

**Expected Result:**
- ✅ Session disconnected
- ❌ NO auto-reconnect triggered
- ✅ Status remains "disconnected"

---

## 📊 MONITORING & OBSERVABILITY

### **Key Metrics to Monitor:**

1. **Session Restoration Success Rate**
   ```
   (restored_count / total_sessions) * 100
   ```

2. **Auto-Reconnect Success Rate**
   ```
   (successful_reconnects / total_disconnects) * 100
   ```

3. **Average Time to Reconnect**
   ```
   time_from_disconnect_to_reconnected
   ```

4. **Failed Reconnection Rate**
   ```
   (failed_after_max_retries / total_reconnect_attempts) * 100
   ```

### **Log Monitoring:**

```bash
# Monitor restoration events
tail -f logs/whatsapp-service.log | grep "restoration"

# Monitor reconnection attempts
tail -f logs/whatsapp-service.log | grep "reconnect"

# Monitor failures
tail -f logs/whatsapp-service.log | grep "ERROR\|Failed"
```

### **Health Check:**

```bash
curl http://localhost:3001/health
```

```json
{
  "status": "healthy",
  "uptime": 3600.5,
  "sessions": {
    "total": 3,
    "connected": 3,
    "disconnected": 0
  },
  "memory": {
    "used": 45,
    "total": 50,
    "unit": "MB"
  }
}
```

---

## 🚀 DEPLOYMENT CHECKLIST

### **Before Deploying:**

- [x] SessionRestoration.js created
- [x] AutoReconnect.js created
- [x] server.js updated with integrations
- [x] Laravel routes added
- [x] WhatsAppWebJSController methods added
- [x] HMAC security verified
- [ ] Test on staging environment
- [ ] Document deployment steps
- [ ] Update monitoring dashboards

### **Deployment Steps:**

```bash
# 1. Deploy Laravel changes
cd /path/to/blazz
git pull origin main
php artisan config:clear
php artisan route:clear

# 2. Deploy Node.js service
cd whatsapp-service
git pull origin main
npm install  # If new dependencies
pm2 reload whatsapp-service

# 3. Monitor logs
pm2 logs whatsapp-service
tail -f storage/logs/laravel.log | grep "WhatsApp"

# 4. Verify restoration
# Check logs for: "Session restoration completed"
```

---

## 🔮 FUTURE ENHANCEMENTS

### **Phase 3: Proactive Health Monitoring**

```javascript
// Periodic health check every 5 minutes
setInterval(async () => {
    for (const [sessionId, client] of sessions) {
        const health = await checkSessionHealth(client);

        if (health.score < 50) {
            logger.warning('Unhealthy session detected', {
                sessionId,
                healthScore: health.score
            });

            // Proactive reconnection
            await reconnectSession(sessionId);
        }
    }
}, 300000); // 5 minutes
```

### **Phase 4: Multi-Region Failover**

- Session replication across regions
- Automatic failover to backup region
- Geo-distributed account management
- Sub-second recovery time

### **Phase 5: Machine Learning Prediction**

- Predict disconnections before they happen
- Optimize retry strategies based on patterns
- Auto-tune backoff parameters
- Anomaly detection

---

## 📝 NOTES

### **Why LocalAuth Works:**

LocalAuth saves session data ke disk di:
```
whatsapp-service/sessions/{workspace_id}/{session_id}/
```

Data includes:
- Authentication tokens
- Encryption keys
- Message history (if multidevice)
- Profile information

Saat `createSession()` dipanggil dengan **sama session_id**, LocalAuth automatically reads dari disk dan restore session tanpa QR scan.

### **Why This is Production-Ready:**

✅ **Zero Manual Intervention:** Sessions auto-restore on restart
✅ **Resilient to Network Issues:** Auto-reconnect dengan retry logic
✅ **User-Friendly:** User hanya scan QR **once**, not every restart
✅ **Scalable:** Works dengan multiple sessions dan workspaces
✅ **Secure:** HMAC-secured communication antara services
✅ **Observable:** Comprehensive logging untuk debugging

---

**Implementation Date:** October 22, 2025
**Implemented By:** Claude Code
**Review Status:** ⏳ **Awaiting Production Testing**
**Documentation Status:** ✅ **Complete**
