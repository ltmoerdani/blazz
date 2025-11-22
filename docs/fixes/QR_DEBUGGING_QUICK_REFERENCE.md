# QR Scanning Debugging - Quick Reference

**Quick diagnostic commands untuk troubleshoot "Unknown Number" issue**

---

## 🔍 Step 1: Check Services Status

```bash
# Check if Node.js service is running
ps aux | grep "node.*whatsapp-service"

# Check if Laravel queue worker is running
ps aux | grep "artisan queue:work"

# Check if Redis is running (if using queue)
redis-cli ping  # Should return "PONG"
```

**Expected:**
- ✅ Node.js process running on port 3000
- ✅ Laravel queue worker active
- ✅ Redis responding

---

## 🔍 Step 2: Monitor Real-Time Logs

```bash
# Terminal 1: Watch Node.js logs (phone extraction)
tail -f whatsapp-service/logs/whatsapp-service.log | grep -E "Phone|extraction|attempt|client\.info"

# Terminal 2: Watch Laravel logs (webhook processing)
tail -f storage/logs/laravel.log | grep -E "session_ready|phone_number|webhook"

# Terminal 3: Watch Laravel queue logs (if using queue)
tail -f storage/logs/laravel.log | grep -E "ProcessWhatsAppWebhookJob|FAIL|SUCCESS"
```

**What to look for:**

### ✅ SUCCESS Pattern (Node.js)
```
🔍 Starting phone number extraction
✅ Phone number extracted successfully
   method: client.info.wid
   attempt: 3
   phoneNumber: 62811801641
📤 Sending session_ready webhook
```

### ✅ SUCCESS Pattern (Laravel)
```
WhatsApp WebJS webhook received
   event: session_ready
   phone_number: 62811801641
✅ session_ready processed inline successfully
✅ Session updated successfully
```

### ❌ FAILURE Pattern (Node.js)
```
🔍 Starting phone number extraction
⚠️ Primary method failed, trying fallback
❌ All phone extraction methods failed
📤 Sending session_error webhook
```

### ❌ FAILURE Pattern (Laravel)
```
❌ Invalid phone number in session_ready
   phone_number: null
OR
❌ Session not found in database
```

---

## 🔍 Step 3: Check Database State

```sql
-- Check latest session
SELECT 
    id,
    session_id,
    phone_number,
    status,
    last_connected_at,
    created_at,
    updated_at
FROM whatsapp_accounts
WHERE workspace_id = 1
ORDER BY id DESC
LIMIT 5;
```

**Expected Result After QR Scan:**
```
| id | session_id      | phone_number | status    | last_connected_at   |
|----|-----------------|--------------|-----------|---------------------|
| 25 | webjs_1_xxx_xxx | 62811801641  | connected | 2025-11-22 10:15:00 |
```

**Problem Indicators:**
```
❌ phone_number: NULL            → Phone extraction failed
❌ status: qr_scanning           → Stuck in scanning state
❌ status: authenticated         → Stuck after auth, before ready
❌ Multiple rows same phone      → Duplicate sessions (constraint issue)
```

---

## 🔍 Step 4: Check for Duplicate/Stuck Sessions

```sql
-- Find duplicate sessions for same phone
SELECT 
    phone_number,
    COUNT(*) as count,
    GROUP_CONCAT(id) as session_ids,
    GROUP_CONCAT(status) as statuses
FROM whatsapp_accounts
WHERE phone_number IS NOT NULL
  AND deleted_at IS NULL
GROUP BY phone_number
HAVING COUNT(*) > 1;
```

**If duplicates found:**
```sql
-- Clean up old sessions (keep only the latest)
UPDATE whatsapp_accounts
SET status = 'failed', deleted_at = NOW()
WHERE id IN (
    SELECT id FROM (
        SELECT id
        FROM whatsapp_accounts
        WHERE phone_number = '62811801641'
          AND workspace_id = 1
          AND deleted_at IS NULL
        ORDER BY id ASC
        LIMIT 10 OFFSET 1  -- Keep only the latest one
    ) AS old_sessions
);
```

---

## 🔍 Step 5: Check Webhook Endpoint

```bash
# Test webhook endpoint from Node.js service
curl -X POST http://localhost:8000/api/whatsapp/webhooks/webjs \
  -H "Content-Type: application/json" \
  -H "X-WhatsApp-HMAC: test-signature" \
  -d '{
    "event": "session_ready",
    "data": {
      "workspace_id": 1,
      "session_id": "test_session_123",
      "phone_number": "628118016411"
    }
  }'
```

**Expected Response:**
```json
{
  "status": "processed_inline"
}
```

**Problem Indicators:**
```
❌ 404 Not Found           → Route not configured
❌ 500 Internal Error      → Check Laravel logs
❌ 401 Unauthorized        → HMAC signature issue
❌ Timeout                 → Laravel not responding
```

---

## 🔍 Step 6: Verify Phone Extraction Code

```bash
# Check if extractPhoneNumberSafely method exists
grep -A 50 "extractPhoneNumberSafely" whatsapp-service/src/managers/SessionManager.js | head -60
```

**Should contain:**
```javascript
async extractPhoneNumberSafely(client, sessionId) {
    const extractionStart = Date.now();
    
    // Initial 2.5s delay
    await new Promise(resolve => setTimeout(resolve, 2500));
    
    // 15 retries × 500ms
    for (let i = 0; i < 15; i++) {
        if (client.info?.wid?.user) {
            return client.info.wid.user;
        }
        await new Promise(resolve => setTimeout(resolve, 500));
    }
    
    // Fallback to Store.Conn.me
    // ...
}
```

**If method NOT found:**
```bash
# Code belum diupdate - apply fix
git pull origin staging-broadcast-arch-task-bug
# OR manually apply changes dari QR_PHONE_NUMBER_EXTRACTION_FIX.md
```

---

## 🔍 Step 7: Test Frontend Polling

```javascript
// Run in Browser Console (Frontend)
async function debugPolling(sessionId) {
    const response = await fetch(`/api/v1/whatsapp/accounts/${sessionId}/status`);
    const data = await response.json();
    
    console.log('=== SESSION STATUS ===');
    console.log('Status:', data.status);
    console.log('Phone:', data.phone_number);
    console.log('Session ID:', data.session_id);
    console.log('Last Activity:', data.last_activity_at);
    console.log('=====================');
    
    return data;
}

// Get sessionId from QR scan response
const sessionId = 'webjs_1_1732260000_abc123';

// Poll every 2 seconds
const pollInterval = setInterval(async () => {
    const data = await debugPolling(sessionId);
    
    if (data.phone_number && data.phone_number !== 'null') {
        console.log('✅ PHONE NUMBER RETRIEVED:', data.phone_number);
        clearInterval(pollInterval);
    }
}, 2000);

// Stop polling after 30 seconds
setTimeout(() => {
    clearInterval(pollInterval);
    console.log('⏱️ Polling stopped (30s timeout)');
}, 30000);
```

---

## 🔍 Step 8: Check Protocol Error Handler

```bash
# Check if global error handler exists
grep -A 20 "unhandledRejection" whatsapp-service/server.js
```

**Should contain:**
```javascript
process.on('unhandledRejection', (error, promise) => {
    if (error.message?.includes('Protocol error')) {
        logger.error('🛡️ Suppressed Protocol error');
        return;
    }
    // ...
});
```

**Test it:**
```bash
# Watch for suppressed errors in logs
tail -f whatsapp-service/logs/whatsapp-service.log | grep "Suppressed"
```

---

## 🚨 Common Issues & Quick Fixes

### Issue 1: Phone Number Always NULL

**Symptoms:**
- Node.js logs show extraction success
- Laravel logs show `phone_number: null`

**Diagnosis:**
```bash
# Check webhook payload
tail -f whatsapp-service/logs/whatsapp-service.log | grep -A 10 "session_ready"
```

**Fix:**
```bash
# Restart Node.js service
cd whatsapp-service
pm2 restart whatsapp-service
# OR
./stop-dev.sh && ./start-dev.sh
```

---

### Issue 2: Queue Job Stuck/Failing

**Symptoms:**
- Webhook queued but never processed
- `ProcessWhatsAppWebhookJob ... FAIL` in logs

**Diagnosis:**
```bash
# Check queue status
php artisan queue:failed
```

**Fix:**
```bash
# Retry failed jobs
php artisan queue:retry all

# OR clear failed jobs and restart worker
php artisan queue:flush
php artisan queue:work --daemon
```

---

### Issue 3: Duplicate Session Constraint Error

**Symptoms:**
```
SQLSTATE[23000]: Integrity constraint violation
Duplicate entry 'phone_number-workspace_id-status'
```

**Diagnosis:**
```sql
-- Find duplicates
SELECT phone_number, COUNT(*) as count
FROM whatsapp_accounts
WHERE deleted_at IS NULL
GROUP BY phone_number, workspace_id
HAVING COUNT(*) > 1;
```

**Fix:**
```sql
-- Auto-cleanup (already in handleSessionReady)
UPDATE whatsapp_accounts
SET status = 'failed', deleted_at = NOW()
WHERE workspace_id = 1
  AND phone_number = '62811801641'
  AND status IN ('qr_scanning', 'authenticated', 'disconnected')
  AND id != (SELECT MAX(id) FROM whatsapp_accounts WHERE phone_number = '62811801641');
```

---

### Issue 4: Protocol Errors Crashing Process

**Symptoms:**
```
Error: Protocol error (Runtime.callFunctionOn): Session closed
[Process exits]
```

**Diagnosis:**
```bash
# Check if error handler exists
grep "unhandledRejection" whatsapp-service/server.js
```

**Fix:**
```bash
# If not exists, apply fix from QR_PHONE_NUMBER_EXTRACTION_FIX.md
# Then restart
pm2 restart whatsapp-service
```

---

### Issue 5: Extraction Takes Too Long

**Symptoms:**
- Extraction logs show >15 seconds
- Multiple QR regenerations

**Diagnosis:**
```bash
# Check extraction timing
tail -f whatsapp-service/logs/whatsapp-service.log | grep "totalTimeMs"
```

**Fix:**
```bash
# Check WhatsApp Web.js version
cd whatsapp-service
npm list whatsapp-web.js

# Should be v1.33.2+
# If older:
npm install whatsapp-web.js@1.33.2
pm2 restart whatsapp-service
```

---

## 📊 Performance Benchmarks

**Normal Performance (After Fix):**
```
Phone extraction time:     2.5 - 10 seconds
Webhook processing:        < 500ms
Database update:           < 200ms
Frontend polling detect:   < 3 seconds total
```

**If slower than this:**
1. Check server resources (CPU, memory)
2. Check database indexes
3. Check network latency to WhatsApp servers
4. Consider upgrade to v1.33.2+

---

## 🔧 Quick Recovery Commands

```bash
# Full restart (nuclear option)
./stop-dev.sh
sleep 5
./start-dev.sh

# Clear all stuck sessions
mysql -u root -p blazz -e "
UPDATE whatsapp_accounts 
SET status = 'failed', deleted_at = NOW()
WHERE status IN ('qr_scanning', 'authenticated')
  AND updated_at < NOW() - INTERVAL 1 HOUR;
"

# Restart queue workers
php artisan queue:restart

# Clear logs for fresh start
> whatsapp-service/logs/whatsapp-service.log
> storage/logs/laravel.log
```

---

## 📞 Escalation Path

**Level 1 (Self-Service):**
1. Check this guide
2. Check logs (Node.js + Laravel)
3. Check database state

**Level 2 (Team Lead):**
1. Review QR_PHONE_NUMBER_EXTRACTION_FIX.md
2. Check WhatsApp Web.js version
3. Verify code implementation

**Level 3 (DevOps/Senior Dev):**
1. Review infrastructure (server resources)
2. Check network connectivity
3. Consider library upgrade or migration

---

**Last Updated:** November 22, 2025  
**Maintained By:** Development Team
