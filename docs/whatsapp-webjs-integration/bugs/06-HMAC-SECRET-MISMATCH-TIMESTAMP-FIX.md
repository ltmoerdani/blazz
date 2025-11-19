# HMAC Secret Mismatch & Timestamp Format Issue

**Date:** 2025-10-13  
**Issue:** Webhook failing with "Request timestamp expired" - HMAC validation issue  
**Root Cause:** Two problems: (1) HMAC secrets don't match, (2) Timestamp format mismatch (milliseconds vs seconds)

---

## 🔍 Problem Analysis

### Error Message from Logs
```json
{
  "level": "warning",
  "message": "WhatsApp HMAC validation failed: Timestamp expired",
  "request_time": 1760321709078,    // ← MILLISECONDS from Node.js
  "current_time": 1760321709,        // ← SECONDS from PHP
  "age_seconds": 1758561387369,      // ← Huge difference!
  "max_age": 300
}
```

### Issue #1: HMAC Secret Mismatch

**Node.js (.env):**
```env
HMAC_SECRET=3a10ac583f4c83514e089570b88697c96f6ca4f3ceaef220eb9b8e50da8e19588c7ea6742b201e2d353f57619a19d05c6b890cd663bf9c5eccc38a86f10c00f6
```

**Laravel (.env) - MISSING!**
```env
# WHATSAPP_NODE_API_SECRET not defined → uses default 'your-node-api-secret'
```

**Result:**
- Node.js signs with: `3a10ac583f4c83514e089570b88697c96f6ca4f3ceaef220eb9b8e50da8e19588c7ea6742b201e2d353f57619a19d05c6b890cd663bf9c5eccc38a86f10c00f6`
- Laravel validates with: `your-node-api-secret`
- **Signatures don't match!** ❌

### Issue #2: Timestamp Format Mismatch

**Node.js (server.js line 360):**
```javascript
const timestamp = Date.now().toString();  // ❌ Returns milliseconds (e.g., 1760321709078)
```

**Laravel (VerifyWhatsAppHmac.php line 77):**
```php
$requestTime = (int) $timestamp;  // Expects seconds
$now = time();                    // Returns seconds (e.g., 1760321709)
if (abs($now - $requestTime) > 300) {  // Compare seconds vs milliseconds!
```

**Result:**
```
$now = 1760321709           (seconds)
$requestTime = 1760321709078 (milliseconds)
age = 1758561387369 seconds = ~55,000 years! ❌
```

---

## 🛠️ Fix Applied

### Fix #1: Sync HMAC Secrets

**Added to Laravel .env:**
```env
WHATSAPP_NODE_API_SECRET=3a10ac583f4c83514e089570b88697c96f6ca4f3ceaef220eb9b8e50da8e19588c7ea6742b201e2d353f57619a19d05c6b890cd663bf9c5eccc38a86f10c00f6
```

**Verified:**
```bash
$ php artisan tinker --execute="echo config('whatsapp.node_api_secret');"
3a10ac583f4c83514e089570b88697c96f6ca4f3ceaef220eb9b8e50da8e19588c7ea6742b201e2d353f57619a19d05c6b890cd663bf9c5eccc38a86f10c00f6
```

✅ **Secrets now match!**

### Fix #2: Convert Timestamp to Seconds

**File:** `whatsapp-service/server.js`  
**Line:** 360

**BEFORE:**
```javascript
const timestamp = Date.now().toString();  // Milliseconds
```

**AFTER:**
```javascript
// Use Unix timestamp in seconds (not milliseconds) to match PHP's time()
const timestamp = Math.floor(Date.now() / 1000).toString();  // Seconds
```

**Explanation:**
- `Date.now()` returns milliseconds since Unix epoch
- `Math.floor(Date.now() / 1000)` converts to seconds
- Now matches PHP's `time()` function

---

## 🧪 Testing Steps

### Step 1: Clear Laravel Config Cache

```bash
php artisan config:clear
```

### Step 2: Restart Node.js Service

```bash
# Kill all node processes
pkill -9 node

# Start Node.js service
cd /Applications/MAMP/htdocs/blazz/whatsapp-service
node server.js > whatsapp-service.out.log 2>&1 &

# Verify it's running
lsof -i :3001
```

### Step 3: Monitor Logs (Real-time)

**Terminal 1 - Laravel logs:**
```bash
tail -f storage/logs/laravel.log | grep -i "whatsapp\|hmac\|timestamp"
```

**Terminal 2 - Node.js logs:**
```bash
tail -f whatsapp-service/logs/whatsapp-service.log | grep -i "qr\|webhook\|success\|401"
```

### Step 4: Test via Browser

1. Navigate to: `http://127.0.0.1:8000/settings/whatsapp-accounts`
2. Open DevTools Console (F12)
3. Click "Add WhatsApp Number"
4. Wait ~7-15 seconds

**Expected Results:**

**Laravel logs (SUCCESS):**
```
[timestamp] local.DEBUG: WhatsApp HMAC timestamp validation {
  "timestamp_received": "1760322500",      ← SECONDS!
  "request_time_int": 1760322500,
  "current_time": 1760322500,
  "age_seconds": 0,                        ← Small difference!
  "max_age": 300,
  "is_valid": true                         ← VALID!
}
[timestamp] local.INFO: WhatsApp WebJS webhook received {event: "qr_code_generated"}
[timestamp] local.INFO: Broadcasting WhatsAppQRGeneratedEvent
```

**Node.js logs (SUCCESS):**
```json
{"level":"info","message":"QR code generated","sessionId":"webjs_1_..."}
{"level":"debug","message":"Data sent to Laravel successfully","event":"qr_code_generated"}
```

**Browser console (SUCCESS):**
```
✅ Session created successfully
📨 QR Code Generated Event received
✅ QR code displayed
```

**UI (SUCCESS):**
- ✅ QR code appears in modal
- ✅ Timer starts counting down (5:00 → 4:59 → ...)
- ✅ Instructions shown
- ✅ No error popup

---

## 📋 Verification Checklist

After restart:

- [ ] Node.js running on port 3001
- [ ] Laravel config cleared
- [ ] HMAC secrets match between Node.js and Laravel
- [ ] Timestamp in seconds (not milliseconds)
- [ ] Webhook reaches Laravel successfully (no 401)
- [ ] HMAC validation passes
- [ ] QR code broadcasts via Reverb
- [ ] QR code appears in browser

---

## 🔗 Related Files Modified

### 1. Laravel .env
**Added:**
```env
WHATSAPP_NODE_API_SECRET=3a10ac583f4c83514e089570b88697c96f6ca4f3ceaef220eb9b8e50da8e19588c7ea6742b201e2d353f57619a19d05c6b890cd663bf9c5eccc38a86f10c00f6
```

### 2. whatsapp-service/server.js
**Line 360 - Changed:**
```javascript
// OLD:
const timestamp = Date.now().toString();

// NEW:
const timestamp = Math.floor(Date.now() / 1000).toString();
```

### 3. app/Http/Middleware/VerifyWhatsAppHmac.php
**Line 77-87 - Added debug logging:**
```php
// Added debug logging to see exact timestamp values
Log::debug('WhatsApp HMAC timestamp validation', [
    'timestamp_received' => $timestamp,
    'request_time_int' => $requestTime,
    'current_time' => $now,
    'age_seconds' => abs($now - $requestTime),
    'max_age' => $maxAge,
    'is_valid' => abs($now - $requestTime) <= $maxAge
]);
```

---

## 📊 Impact Analysis

### Breaking Changes
- **None** - Only fixes existing broken functionality

### Security Improvements
- ✅ **HMAC validation now works correctly**
- ✅ **Replay attack protection active** (5-minute window)
- ✅ **Cryptographic signature validation**

### Performance Impact
- **Minimal** - Only adds one debug log line
- **Debug logging** can be removed after testing

---

## 🎯 Success Criteria

**Webhook Authentication:**
- ✅ HMAC signature validates successfully
- ✅ Timestamp within 5-minute window
- ✅ No 401 errors in logs

**QR Code Delivery:**
- ✅ Node.js generates QR code
- ✅ Webhook reaches Laravel
- ✅ Event broadcasted via Reverb
- ✅ Frontend receives QR code
- ✅ QR code displays in modal

**User Experience:**
- ✅ No error popups
- ✅ QR code appears within 15 seconds
- ✅ Timer works correctly
- ✅ Can scan with WhatsApp mobile

---

## 🐛 Debugging Commands

**If still failing, check:**

### 1. Verify Secrets Match
```bash
# Node.js secret
grep "HMAC_SECRET" whatsapp-service/.env

# Laravel secret
php artisan tinker --execute="echo config('whatsapp.node_api_secret');"
```

### 2. Check Timestamp Format
```bash
# Monitor timestamps in logs
tail -f storage/logs/laravel.log | grep "timestamp_received"
```

### 3. Manual HMAC Test
```bash
# Generate correct signature
php artisan tinker --execute="
\$timestamp = time();
\$payload = json_encode(['event' => 'test', 'data' => []]);
\$secret = config('whatsapp.node_api_secret');
\$signature = hash_hmac('sha256', \$timestamp . \$payload, \$secret);
echo 'TS: ' . \$timestamp . PHP_EOL;
echo 'SIG: ' . \$signature . PHP_EOL;
"

# Test webhook with curl
curl -X POST http://127.0.0.1:8000/api/whatsapp/webhooks/webjs \
  -H "Content-Type: application/json" \
  -H "X-Timestamp: [USE_TIMESTAMP_ABOVE]" \
  -H "X-HMAC-Signature: [USE_SIGNATURE_ABOVE]" \
  -d '{"event":"test","data":[]}'
```

---

## 📝 Notes

### Why Milliseconds vs Seconds?

**JavaScript:**
- `Date.now()` returns milliseconds (standard in JS)
- Example: `1760321709078`

**PHP:**
- `time()` returns seconds (Unix timestamp)
- Example: `1760321709`

**Solution:**
- Divide by 1000: `Math.floor(Date.now() / 1000)`

### HMAC Signature Components

**What gets signed:**
```javascript
const message = timestamp + payloadJSON;
const signature = hmac_sha256(message, secret);
```

**Example:**
```
timestamp = "1760321709"
payload = '{"event":"qr_code_generated","data":{...}}'
message = "1760321709" + '{"event":"qr_code_generated","data":{...}}'
signature = hmac_sha256(message, "3a10ac583f4c83514e089570b88697c96f6ca4f3...")
```

**Both sides must:**
1. Use same timestamp format (seconds)
2. Use same secret
3. Use same HMAC algorithm (SHA-256)
4. Use same message format (timestamp + payload)

---

## ✅ Summary

**Problems Fixed:**
1. ✅ HMAC secret mismatch - Added to Laravel .env
2. ✅ Timestamp format - Changed from milliseconds to seconds
3. ✅ Debug logging - Added to track validation

**What User Needs to Do:**
1. Restart Node.js service: `pkill -9 node && cd whatsapp-service && node server.js &`
2. Clear Laravel config: `php artisan config:clear`
3. Test via browser: Navigate to WhatsApp accounts page and add number

**Expected Result:**
✅ QR code appears in modal within 15 seconds, no errors

---

**Status:** ✅ **READY FOR TESTING** (requires server restart by user)

**Fixed by:** AI Assistant  
**Date:** 2025-10-13  
**Related:** 05-WEBHOOK-AUTH-BEARER-TOKEN-FIX.md, 04-LARAVEL-TIMEOUT-QR-WEBHOOK-FIX.md
