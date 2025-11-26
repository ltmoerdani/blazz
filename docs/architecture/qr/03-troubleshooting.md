# Troubleshooting Guide - WhatsApp QR Integration

**Version:** 2.0  
**Last Updated:** November 22, 2025  

---

## 📋 Table of Contents

1. [Quick Reference](#quick-reference)
2. [Common Issues](#common-issues)
3. [Error Codes](#error-codes)
4. [Debugging Tools](#debugging-tools)
5. [Resolution Steps](#resolution-steps)

---

## Quick Reference

### Status Flow

```
qr_scanning → authenticated → connected
     ↓              ↓              ↓
  failed        failed          disconnected
```

### Expected Timings

| Phase | Expected Time | Acceptable Range |
|-------|--------------|------------------|
| QR Generation | 7-9 seconds | 5-12 seconds |
| QR Scan (user) | Variable | N/A |
| Status → authenticated | Instant | < 500ms |
| Phone Extraction | 3-4 seconds | 2-8 seconds |
| Database Update | 500ms | 200ms-2s |
| Frontend Poll | 3s intervals | N/A |
| **Total (after scan)** | **4-5 seconds** | **3-10 seconds** |

### Critical Timeouts

```javascript
// Frontend
maxAttemptsWithoutPhone = 6  // 18 seconds
pollingInterval = 3000       // 3 seconds

// Node.js
PUPPETEER_TIMEOUT = 30000    // 30 seconds
extractPhoneRetries = 15     // 7.5 seconds
initialDelay = 2500          // 2.5 seconds

// Laravel
Webhook timeout = N/A        // Processed inline (synchronous)
```

---

## Common Issues

### 1. "Unknown Number" Timeout

**Symptom:**
- QR scan succeeds
- Status shows "authenticated" or "connected"
- Phone number remains NULL
- Frontend times out after 18 seconds

**Root Cause:**
Phone extraction taking > 18 seconds OR webhook not reaching Laravel

**Resolution:**

```bash
# 1. Check Node.js logs
grep "Phone number extracted" whatsapp-service/logs/*.log | tail -20

# 2. Check Laravel webhook logs
grep "session_ready" storage/logs/laravel.log | tail -20

# 3. Check database
php artisan tinker --execute="
  \$session = \App\Models\WhatsAppAccount::where('session_id', 'YOUR_SESSION_ID')->first();
  echo 'Status: ' . \$session->status;
  echo 'Phone: ' . \$session->phone_number;
"

# 4. Verify HMAC secret matches
grep HMAC_SECRET whatsapp-service/.env
grep WHATSAPP_HMAC_SECRET .env
```

**Fix:**

```php
// If webhook not reaching Laravel, check HMAC
// File: app/Http/Middleware/VerifyWhatsAppHmac.php

// Add debug logging
Log::info('HMAC verification', [
    'signature_header' => $request->header('X-HMAC-Signature'),
    'timestamp' => $request->header('X-Timestamp'),
    'expected' => $expectedSignature,
    'received' => $signature
]);
```

---

### 2. Database Unique Constraint Violation

**Symptom:**
```
SQLSTATE[23000]: Integrity constraint violation: 1062 
Duplicate entry '62811801641-1-connected' for key 'unique_active_phone_workspace'
```

**Root Cause:**
Previous session with same phone_number not cleaned up properly

**Resolution:**

```sql
-- Check for duplicates
SELECT 
    id, session_id, phone_number, status, deleted_at, created_at
FROM whatsapp_accounts
WHERE phone_number = '62811801641'
  AND workspace_id = 1
ORDER BY created_at DESC;

-- Manual cleanup (if needed)
UPDATE whatsapp_accounts
SET status = 'failed',
    phone_number = NULL,  -- CRITICAL
    deleted_at = NOW()
WHERE workspace_id = 1
  AND phone_number = '62811801641'
  AND id != 136  -- Keep only the active one
  AND deleted_at IS NULL;
```

**Prevention:**

Ensure `phone_number = NULL` set in cleanup code:

```php
// File: app/Http/Controllers/Api/v1/WhatsApp/WebhookController.php
DB::table('whatsapp_accounts')
    ->where('workspace_id', $workspaceId)
    ->where('phone_number', $phoneNumber)
    ->where('id', '!=', $session->id)
    ->update([
        'status' => 'failed',
        'phone_number' => null,  // ← CRITICAL: Bypass constraint
        'deleted_at' => now()
    ]);
```

---

### 3. QR Code Not Appearing

**Symptom:**
- Click "Add Account" button
- Modal appears but QR stays loading for > 15 seconds
- Browser console shows errors

**Resolution:**

```bash
# 1. Check PM2 status
pm2 status

# 2. Check Node.js service logs
pm2 logs whatsapp-service --lines 50

# 3. Check port availability
lsof -i :3001  # Should show node process

# 4. Test API directly
curl -X POST http://127.0.0.1:3001/api/session/init \
  -H "Content-Type: application/json" \
  -d '{"sessionId": "test-123"}'

# 5. Check Puppeteer Chrome
ps aux | grep chrome  # Should see chromium processes
```

**Common Fixes:**

```bash
# Fix 1: Restart Node service
pm2 restart whatsapp-service

# Fix 2: Clear auth cache
rm -rf whatsapp-service/.wwebjs_auth/*
rm -rf whatsapp-service/.wwebjs_cache/*
pm2 restart whatsapp-service

# Fix 3: Check Puppeteer dependencies (Ubuntu/Debian)
sudo apt-get install -y \
    gconf-service libasound2 libatk1.0-0 libc6 libcairo2 \
    libcups2 libdbus-1-3 libexpat1 libfontconfig1 libgcc1 \
    libgconf-2-4 libgdk-pixbuf2.0-0 libglib2.0-0 libgtk-3-0 \
    libnspr4 libpango-1.0-0 libpangocairo-1.0-0 libstdc++6 \
    libx11-6 libx11-xcb1 libxcb1 libxcomposite1 libxcursor1 \
    libxdamage1 libxext6 libxfixes3 libxi6 libxrandr2 \
    libxrender1 libxss1 libxtst6 ca-certificates fonts-liberation \
    libappindicator1 libnss3 lsb-release xdg-utils wget
```

---

### 4. WebSocket Connection Failed

**Symptom:**
- Browser console: `WebSocket connection to 'ws://127.0.0.1:8080' failed`
- Events not received in real-time
- Must refresh page to see updates

**Resolution:**

```bash
# 1. Check Reverb status
php artisan reverb:ping

# 2. Check port
lsof -i :8080  # Should show artisan process

# 3. Check configuration
php artisan config:cache
php artisan config:clear

# 4. Test WebSocket
wscat -c ws://127.0.0.1:8080/app/your-app-key

# 5. Start Reverb if not running
php artisan reverb:start --host=0.0.0.0 --port=8080
```

**Configuration Check:**

```bash
# Verify .env
grep -E "BROADCAST|REVERB" .env

# Should output:
# BROADCAST_DRIVER=reverb
# REVERB_APP_ID=...
# REVERB_APP_KEY=...
# REVERB_APP_SECRET=...
# REVERB_HOST=127.0.0.1
# REVERB_PORT=8080
```

---

### 5. Auto-Primary Not Working

**Symptom:**
- First account connected but not marked as primary
- `is_primary` stays `false`

**Resolution:**

```sql
-- Check for existing primary accounts
SELECT id, session_id, phone_number, is_primary, status
FROM whatsapp_accounts
WHERE workspace_id = 1
  AND status = 'connected'
  AND deleted_at IS NULL
ORDER BY created_at ASC;

-- Manually set first as primary
UPDATE whatsapp_accounts
SET is_primary = TRUE
WHERE id = (
    SELECT id FROM (
        SELECT id FROM whatsapp_accounts
        WHERE workspace_id = 1
          AND status = 'connected'
          AND deleted_at IS NULL
        ORDER BY created_at ASC
        LIMIT 1
    ) AS first_account
);
```

**Code Verification:**

```php
// File: app/Http/Controllers/Api/v1/WhatsApp/WebhookController.php
// Line ~193-215

// Check if has primary account
$hasPrimaryAccount = WhatsAppAccount::where('workspace_id', $workspaceId)
    ->where('is_primary', true)
    ->where('status', 'connected')
    ->where('id', '!=', $session->id)  // ← Check this line
    ->exists();

$isPrimary = !$hasPrimaryAccount;  // ← Should be true for first account
```

---

### 6. Phone Extraction Timeout

**Symptom:**
- Node.js logs: `⚠️ Phone number extraction FAILED after 15 retries`
- Session stuck in `authenticated` status

**Resolution:**

```bash
# 1. Check WhatsApp Web.js info availability
grep -E "client\.info|wid\.user" whatsapp-service/logs/*.log | tail -20

# 2. Increase retry attempts
# Edit: whatsapp-service/src/managers/SessionManager.js
const maxRetries = 20;  // Was: 15
const retryDelay = 700; // Was: 500

# 3. Restart service
pm2 restart whatsapp-service
```

**Advanced Debug:**

```javascript
// Add to extractPhoneNumberSafely() method
console.log('🔍 Client info structure:', JSON.stringify({
    hasInfo: !!client.info,
    hasWid: !!client.info?.wid,
    hasUser: !!client.info?.wid?.user,
    infoKeys: Object.keys(client.info || {}),
    widKeys: Object.keys(client.info?.wid || {})
}, null, 2));
```

---

### 7. HMAC Signature Mismatch

**Symptom:**
- Laravel logs: `401 Unauthorized - Invalid HMAC signature`
- Node.js logs: `Webhook notification successful (200)`

**Resolution:**

```bash
# 1. Verify secrets match
echo "Node.js:"
grep HMAC_SECRET whatsapp-service/.env

echo "Laravel:"
grep WHATSAPP_HMAC_SECRET .env

# 2. Test signature generation
node -e "
const crypto = require('crypto');
const secret = 'your-secret-here';
const timestamp = Math.floor(Date.now() / 1000);
const payload = JSON.stringify({event: 'test'});
const signature = crypto.createHmac('sha256', secret).update(timestamp + payload).digest('hex');
console.log('Signature:', signature);
console.log('Timestamp:', timestamp);
"

# 3. Test webhook manually
curl -X POST http://127.0.0.1:8000/api/whatsapp/webhooks/webjs \
  -H "Content-Type: application/json" \
  -H "X-HMAC-Signature: GENERATED_SIGNATURE" \
  -H "X-Timestamp: TIMESTAMP" \
  -d '{"event":"test","data":{}}'
```

---

### 8. Multiple Accounts on Same Phone

**Symptom:**
- Same phone number on multiple accounts
- Duplicate primary accounts
- WhatsApp disconnects randomly

**Root Cause:**
WhatsApp allows only ONE active session per phone number

**Resolution:**

```sql
-- Find duplicates
SELECT phone_number, COUNT(*) as count
FROM whatsapp_accounts
WHERE workspace_id = 1
  AND status = 'connected'
  AND deleted_at IS NULL
GROUP BY phone_number
HAVING count > 1;

-- Keep only the most recent
-- (Automatic cleanup should handle this, but manual fix if needed)
DELETE wa1 FROM whatsapp_accounts wa1
INNER JOIN whatsapp_accounts wa2 
WHERE wa1.phone_number = wa2.phone_number
  AND wa1.workspace_id = wa2.workspace_id
  AND wa1.created_at < wa2.created_at
  AND wa1.status = 'connected'
  AND wa2.status = 'connected';
```

---

## Error Codes

### Laravel Errors

| Code | Message | Cause | Solution |
|------|---------|-------|----------|
| 401 | Invalid HMAC signature | Secret mismatch | Verify `.env` files match |
| 401 | Request expired | Timestamp > 5 mins old | Check server time sync |
| 404 | Session not found | Invalid session_id | Verify database record exists |
| 422 | Validation failed | Missing required fields | Check webhook payload |
| 500 | Database error | Constraint violation | Check unique constraint |

### Node.js Errors

| Error | Cause | Solution |
|-------|-------|----------|
| `ECONNREFUSED` | Laravel not running | Start Laravel: `php artisan serve` |
| `Execution context destroyed` | Puppeteer crash | Restart PM2: `pm2 restart whatsapp-service` |
| `Protocol error: Target closed` | WhatsApp disconnected | Normal, will auto-reconnect |
| `Session not found` | Invalid session_id in request | Verify session exists in DB |

---

## Debugging Tools

### 1. Real-Time Monitoring

```bash
# Terminal 1: Node.js logs
pm2 logs whatsapp-service --lines 100 --raw

# Terminal 2: Laravel logs
tail -f storage/logs/laravel.log

# Terminal 3: Database queries (if query log enabled)
tail -f storage/logs/query.log

# Terminal 4: Reverb WebSocket
php artisan reverb:start --debug
```

### 2. Database Inspection

```php
// Laravel Tinker
php artisan tinker

// Check session status
$session = \App\Models\WhatsAppAccount::where('session_id', 'sess_123')->first();
dd([
    'status' => $session->status,
    'phone' => $session->phone_number,
    'is_primary' => $session->is_primary,
    'last_connected' => $session->last_connected_at,
    'metadata' => $session->metadata
]);

// Check workspace accounts
\App\Models\WhatsAppAccount::where('workspace_id', 1)
    ->where('deleted_at', null)
    ->get(['id', 'session_id', 'phone_number', 'status', 'is_primary'])
    ->toArray();
```

### 3. API Testing

```bash
# Test QR generation
curl -X POST http://127.0.0.1:3001/api/session/init \
  -H "Content-Type: application/json" \
  -d '{
    "sessionId": "test-'$(date +%s)'",
    "workspaceId": 1,
    "webhookUrl": "http://127.0.0.1:8000/api/whatsapp/webhooks/webjs"
  }' | jq

# Test session status
curl http://127.0.0.1:3001/api/session/test-123/status | jq

# Test session termination
curl -X DELETE http://127.0.0.1:3001/api/session/test-123
```

### 4. Performance Profiling

```bash
# QR generation time
grep "QR code generated" whatsapp-service/logs/*.log | \
  grep -oE "[0-9]+ms" | \
  awk '{sum+=$1; count++; if(min==""){min=$1}; if($1<min){min=$1}; if($1>max){max=$1}} 
       END {print "Avg:", sum/count, "ms | Min:", min, "ms | Max:", max, "ms"}'

# Phone extraction time
grep "Phone number extracted" whatsapp-service/logs/*.log | \
  grep -oE "[0-9]+ms" | \
  awk '{sum+=$1; count++} END {print "Average:", sum/count, "ms"}'

# Webhook latency
grep "Webhook.*took" whatsapp-service/logs/*.log | tail -20
```

---

## Resolution Steps

### Step-by-Step: Full System Reset

```bash
# 1. Stop all services
pm2 stop whatsapp-service
php artisan queue:restart
# Kill Reverb (Ctrl+C in terminal)

# 2. Clear caches
rm -rf whatsapp-service/.wwebjs_auth/*
rm -rf whatsapp-service/.wwebjs_cache/*
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 3. Reset database (CAUTION: Development only!)
php artisan migrate:fresh --seed

# 4. Restart services
pm2 restart whatsapp-service
php artisan reverb:start &
php artisan queue:work &

# 5. Test
curl http://127.0.0.1:3001/health
curl http://127.0.0.1:8000/api/health
```

### Step-by-Step: Single Session Reset

```bash
# 1. Find session
php artisan tinker --execute="
  \$session = \App\Models\WhatsAppAccount::where('session_id', 'YOUR_SESSION_ID')->first();
  echo 'ID: ' . \$session->id;
"

# 2. Terminate in Node.js
curl -X DELETE http://127.0.0.1:3001/api/session/YOUR_SESSION_ID

# 3. Clean database
php artisan tinker --execute="
  \$session = \App\Models\WhatsAppAccount::find(ACCOUNT_ID);
  \$session->forceDelete();
"

# 4. Remove auth files
rm -rf whatsapp-service/.wwebjs_auth/session-YOUR_SESSION_ID*

# 5. Retry
# Go to frontend and create new account
```

---

## Escalation

If issue persists after following this guide:

1. **Collect Diagnostic Bundle:**
   ```bash
   mkdir /tmp/diagnostic-$(date +%Y%m%d-%H%M%S)
   cd /tmp/diagnostic-*
   
   # Copy logs
   cp -r /path/to/whatsapp-service/logs ./node-logs
   cp /path/to/storage/logs/laravel.log ./laravel.log
   
   # Environment info
   pm2 status > pm2-status.txt
   php artisan about > laravel-about.txt
   node --version > versions.txt
   php --version >> versions.txt
   
   # Database snapshot
   php artisan tinker --execute="
     echo json_encode(\App\Models\WhatsAppAccount::all()->toArray());
   " > database-snapshot.json
   
   tar -czf diagnostic-bundle.tar.gz *
   ```

2. **Report to Development Team** with:
   - Diagnostic bundle
   - Steps to reproduce
   - Expected vs actual behavior
   - Screenshots (if UI issue)

---

**Document Version:** 2.0  
**Last Updated:** November 22, 2025  
**Maintainer:** Development Team
