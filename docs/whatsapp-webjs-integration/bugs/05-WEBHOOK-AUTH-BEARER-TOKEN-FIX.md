# Webhook Authentication Fix - Bearer Token Blocking HMAC

**Date:** 2025-10-13  
**Issue:** QR code generated but never reaches frontend - webhook blocked by Bearer Token middleware  
**Root Cause:** Webhook route inside `AuthenticateBearerToken` middleware group, causing 401 error

---

## 🔍 Problem Analysis

### Error Message from Node.js Logs
```json
{
  "error": "Request failed with status code 401",
  "event": "qr_code_generated",
  "level": "error",
  "message": "Failed to send data to Laravel",
  "response": {"error": "Unauthorized. Bearer Token is missing."}
}
```

### Symptoms
1. ✅ QR code **generated** by Node.js (logged successfully)
2. ❌ Webhook **fails** to reach Laravel (401 Unauthorized)
3. ❌ Frontend **stuck** on loading spinner
4. ❌ No QR code displayed

### Root Cause Investigation

**Node.js successfully generates QR:**
```json
{"level":"info","message":"QR code generated","sessionId":"webjs_1_...","workspaceId":1}
```

**But webhook request fails:**
```json
{"error":"Request failed with status code 401","event":"qr_code_generated"}
{"response":{"error":"Unauthorized. Bearer Token is missing."}}
```

**Problem in routes/api.php (BEFORE):**
```php
// Line 34: Bearer Token middleware wraps everything
Route::middleware([AuthenticateBearerToken::class])->group(function () {
    Route::post('/send', ...);
    Route::get('/contacts', ...);
    ...
    
    // Line 58: WhatsApp webhook INSIDE Bearer Token group!
    Route::prefix('whatsapp')->middleware(['whatsapp.hmac'])->group(function () {
        Route::post('/webhooks/webjs', [...]);  // ❌ Requires Bearer Token!
    });
});
```

**Flow:**
```
Node.js webhook → Laravel API → AuthenticateBearerToken middleware
                                        ↓
                                  No Bearer Token in request
                                        ↓
                                  401 Unauthorized ❌
                                        ↓
                                  Never reaches whatsapp.hmac middleware
                                        ↓
                                  Never reaches controller
```

**Why this happened:**
- Webhook uses **HMAC authentication** (`whatsapp.hmac` middleware)
- But route was **nested inside** Bearer Token authentication
- Laravel processes middlewares **in order**: Bearer Token first, HMAC second
- Bearer Token middleware **blocks** request before HMAC can validate

---

## 🛠️ Fix Applied

### Solution: Move Webhook Routes Outside Bearer Token Group

**File:** `routes/api.php`  
**Lines:** 34-98

**BEFORE (WRONG):**
```php
Route::middleware([AuthenticateBearerToken::class])->group(function () {
    Route::post('/send', ...);
    Route::get('/contacts', ...);
    ...
    
    // ❌ WhatsApp routes INSIDE Bearer Token middleware
    Route::prefix('whatsapp')->middleware(['whatsapp.hmac'])->group(function () {
        Route::post('/webhooks/webjs', [WhatsAppWebJSController::class, 'webhook']);
        Route::get('/sessions/{sessionId}/status', ...);
        Route::post('/broadcast', ...);
    });
});
```

**AFTER (CORRECT):**
```php
// ✅ WhatsApp routes OUTSIDE Bearer Token middleware (HMAC secured instead)
Route::prefix('whatsapp')->middleware(['whatsapp.hmac'])->group(function () {
    // Webhook for Node.js service callbacks (HMAC secured)
    Route::post('/webhooks/webjs', [WhatsAppWebJSController::class, 'webhook']);

    // Account management for Node.js service (HMAC secured)
    Route::get('/sessions/{sessionId}/status', [WhatsAppWebJSController::class, 'getSessionStatus']);

    // Broadcasting events (for testing) - requires HMAC
    Route::post('/broadcast', function (Request $request) {
        // ... broadcast logic
    });
});

// Bearer Token routes (separate group)
Route::middleware([AuthenticateBearerToken::class])->group(function () {
    Route::post('/send', ...);
    Route::get('/contacts', ...);
    Route::get('/contact-groups', ...);
    Route::get('/canned-replies', ...);
    Route::get('/templates', ...);
});
```

**Changes:**
1. ✅ Moved WhatsApp webhook routes **before** Bearer Token group
2. ✅ WhatsApp routes use **only** `whatsapp.hmac` middleware
3. ✅ Bearer Token routes remain separate
4. ✅ No authentication conflicts

---

## 🔄 Authentication Flow

### HMAC Authentication (whatsapp.hmac middleware)

**Request from Node.js:**
```javascript
await axios.post(`${LARAVEL_URL}/api/whatsapp/webhooks/webjs`, payload, {
    headers: {
        'Content-Type': 'application/json',
        'X-API-Key': process.env.API_KEY,
        'X-Timestamp': timestamp,
        'X-HMAC-Signature': signature,  // HMAC SHA256
    }
});
```

**Laravel Validation:**
```php
// WhatsAppWebJSController.php
private function validateHmacSignature(Request $request): void
{
    $signature = $request->header('X-HMAC-Signature');
    $timestamp = $request->header('X-Timestamp');
    $payload = $request->getContent();

    // Verify timestamp (prevent replay attacks)
    if (abs(time() - $requestTime) > 300) { // 5 minutes
        abort(401, 'Request expired');
    }

    // Verify HMAC signature
    $expectedSignature = hash_hmac('sha256', $timestamp . $payload, config('whatsapp.node_api_secret'));

    if (!hash_equals($expectedSignature, $signature)) {
        abort(401, 'Invalid signature');
    }
}
```

**Security Benefits:**
- ✅ **Timestamp validation** - prevents replay attacks
- ✅ **HMAC SHA256** - cryptographic signature
- ✅ **Secret key** - only Node.js and Laravel know
- ✅ **No Bearer Token** - simpler for service-to-service

---

## 🧪 Testing After Fix

### Test 1: Check Laravel Logs

```bash
tail -f storage/logs/laravel.log | grep -i "webhook\|qr"
```

**Expected logs (SUCCESS):**
```
[timestamp] WhatsApp WebJS webhook received {event: "qr_code_generated", workspace_id: 1, session_id: "..."}
[timestamp] Broadcasting WhatsAppQRGeneratedEvent {workspace_id: 1, session_id: "...", qr_code_length: 15234}
[timestamp] WhatsAppQRGeneratedEvent broadcasted
```

**Should NOT see:**
```
[timestamp] Unauthorized. Bearer Token is missing.
```

### Test 2: Check Node.js Logs

```bash
tail -f whatsapp-service/logs/whatsapp-service.log | grep -i "qr\|webhook"
```

**Expected logs (SUCCESS):**
```json
{"level":"info","message":"QR code generated","sessionId":"...","workspaceId":1}
{"level":"debug","message":"Data sent to Laravel successfully","event":"qr_code_generated"}
```

**Should NOT see:**
```json
{"error":"Request failed with status code 401"}
{"response":{"error":"Unauthorized. Bearer Token is missing."}}
```

### Test 3: Browser Test (End-to-End)

1. Navigate to: `http://127.0.0.1:8000/settings/whatsapp-accounts`
2. Open DevTools Console (F12)
3. Click "Add WhatsApp Number"

**Expected timeline:**
- `0s` - Request sent
- `7s` - Response: "Session created successfully. QR code will be sent via websocket."
- `15s` - **QR CODE APPEARS** ✅

**Expected console logs:**
```javascript
🔄 Creating new WhatsApp account...
✅ Session created: {success: true, qr_code: null}
⏳ Waiting for QR code via websocket...
📨 QR Code Generated Event received: {qr_code: "data:image/png;base64,..."}
✅ QR code displayed
```

**Expected UI:**
- ✅ Modal opens
- ✅ Loading spinner (7-15 seconds)
- ✅ QR code displays
- ✅ Timer countdown starts
- ✅ Instructions shown

### Test 4: Network Tab

In DevTools Network → WS (WebSocket):

```
WebSocket connection: ws://127.0.0.1:8080
Status: 101 Switching Protocols

Messages received:
{
  "event": "WhatsAppQRGeneratedEvent",
  "data": {
    "qr_code": "data:image/png;base64,iVBORw0KG...",
    "expires_in": 300,
    "workspace_id": 1,
    "session_id": "webjs_1_..."
  }
}
```

---

## 📋 Impact Analysis

### Files Modified
1. `routes/api.php` - Restructured middleware groups

### Breaking Changes
- **None** - Only affects internal webhook routing
- External API routes unchanged
- Bearer Token authentication still works for other endpoints

### Security Improvements
- ✅ Proper separation of authentication methods
- ✅ HMAC for service-to-service
- ✅ Bearer Token for external API
- ✅ No authentication bypass

---

## ✅ Verification Checklist

After applying fix:

- [x] Webhook routes moved outside Bearer Token middleware
- [x] WhatsApp routes use only `whatsapp.hmac` middleware
- [x] No syntax errors in routes/api.php
- [ ] Test via browser - QR code appears
- [ ] Check Laravel logs - webhook received
- [ ] Check Node.js logs - no 401 errors
- [ ] Verify HMAC validation still works

---

## 🔗 Related Issues

### This Fix Resolves:
- ❌ Webhook 401 Unauthorized error
- ❌ QR code not reaching frontend
- ❌ Loading spinner stuck forever
- ❌ "Failed to send data to Laravel" in Node.js logs

### This Fix Enables:
- ✅ Webhook successfully reaches Laravel
- ✅ QR code broadcasted via Reverb
- ✅ Frontend receives QR code
- ✅ Complete session creation flow

### Dependencies:
- Requires:
  - ✅ 04-LARAVEL-TIMEOUT-QR-WEBHOOK-FIX.md (completed)
  - ✅ 03-OPTIONS-PRIORITY-UNDEFINED-FIX.md (completed)
- Depends on:
  - ✅ `whatsapp.hmac` middleware exists
  - ✅ HMAC secret configured in .env
  - ✅ Reverb server running

---

## 📝 Notes

### Why HMAC Instead of Bearer Token?

**HMAC Advantages for Webhooks:**
1. **Timestamp-based** - prevents replay attacks
2. **Stateless** - no need to store tokens
3. **Cryptographic** - stronger than simple tokens
4. **Standard** - used by Stripe, GitHub, etc.
5. **No expiration** - secret doesn't expire

**Bearer Token Issues:**
- Needs to be generated and managed
- Can expire
- Needs to be stored securely
- Less standard for webhooks

### Middleware Order Matters!

Laravel processes middlewares **in order**:
```php
// WRONG:
Route::middleware(['auth', 'hmac'])->group(function () {
    // auth runs first, blocks if no Bearer Token
});

// CORRECT:
Route::middleware(['hmac'])->group(function () {
    // Only HMAC validation, no Bearer Token check
});
```

### Other Services Using Bearer Token

These routes **still require** Bearer Token:
- `/api/send` - Send message
- `/api/contacts` - Contact management
- `/api/contact-groups` - Group management
- `/api/canned-replies` - Canned replies
- `/api/templates` - Templates

Only **WhatsApp webhooks** use HMAC.

---

## 📌 Summary

**Problem:** Webhook route inside Bearer Token middleware group  
**Solution:** Move webhook routes outside Bearer Token, use HMAC only  
**Impact:** Critical - Blocks QR code delivery  
**Risk:** Low - No breaking changes  
**Test Time:** Immediate - QR appears in ~15 seconds  

**Status:** ✅ Fixed - Ready for testing

---

**Fixed by:** AI Assistant  
**Date:** 2025-10-13  
**Related:** 04-LARAVEL-TIMEOUT-QR-WEBHOOK-FIX.md, 03-OPTIONS-PRIORITY-UNDEFINED-FIX.md
