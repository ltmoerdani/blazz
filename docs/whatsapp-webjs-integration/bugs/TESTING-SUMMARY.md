# WhatsApp QR Code Fix - Summary untuk User

**Date:** 2025-10-13  
**Status:** ✅ **READY FOR TESTING**

---

## 🎯 Masalah yang Ditemukan

### 1. ❌ HMAC Secret Tidak Cocok
- **Node.js** menggunakan: `3a10ac583f4c83514e089570b88697c96f6ca4f3...` (128 char)
- **Laravel** menggunakan: `your-node-api-secret` (default)
- **Akibat:** Signature validation selalu gagal

### 2. ❌ Format Timestamp Salah
- **Node.js** mengirim: `1760321709078` (milliseconds)
- **Laravel** expect: `1760321709` (seconds)
- **Akibat:** Timestamp selalu expired (selisih ~55,000 tahun!)

### 3. ❌ Route Authentication Salah
- **Webhook routes** di dalam `AuthenticateBearerToken` middleware
- **Akibat:** Webhook butuh Bearer Token, tapi Node.js pakai HMAC

---

## ✅ Fix yang Sudah Diterapkan

### 1. ✅ Sinkronisasi HMAC Secret
**File:** `.env` (Laravel)  
**Action:** Ditambahkan `WHATSAPP_NODE_API_SECRET`

```env
WHATSAPP_NODE_API_SECRET=3a10ac583f4c83514e089570b88697c96f6ca4f3ceaef220eb9b8e50da8e19588c7ea6742b201e2d353f57619a19d05c6b890cd663bf9c5eccc38a86f10c00f6
```

### 2. ✅ Fix Timestamp Format
**File:** `whatsapp-service/server.js`  
**Line:** 360

```javascript
// BEFORE:
const timestamp = Date.now().toString();  // milliseconds

// AFTER:
const timestamp = Math.floor(Date.now() / 1000).toString();  // seconds ✅
```

### 3. ✅ Pindahkan Webhook Routes
**File:** `routes/api.php`  
**Action:** Webhook routes dipindahkan keluar dari Bearer Token middleware

```php
// Webhook routes (HMAC only) - OUTSIDE Bearer Token
Route::prefix('whatsapp')->middleware(['whatsapp.hmac'])->group(function () {
    Route::post('/webhooks/webjs', [WhatsAppWebJSController::class, 'webhook']);
});

// API routes (Bearer Token)
Route::middleware([AuthenticateBearerToken::class])->group(function () {
    Route::post('/send', ...);
    Route::get('/contacts', ...);
});
```

### 4. ✅ Debug Logging
**File:** `app/Http/Middleware/VerifyWhatsAppHmac.php`  
**Action:** Ditambahkan logging untuk debugging

---

## 🚀 Cara Testing

### Step 0: Cleanup Stuck Sessions (REQUIRED!)

```bash
./cleanup-whatsapp-sessions.sh
```

Script ini akan:
1. Show current sessions in database
2. Stop Node.js service
3. Delete Node.js session files (.wwebjs_auth/*)
4. Delete all database records (whatsapp_sessions table)
5. Verify cleanup successful

**⚠️ IMPORTANT:** Jalankan ini dulu sebelum testing untuk menghapus sessions yang stuck!

### Step 1: Restart Services

```bash
./restart-whatsapp-services.sh
```

Script ini akan:
1. Kill semua Node.js processes
2. Clear Laravel config cache
3. Start Node.js service baru
4. Verify semua services running

### Step 2: Run Testing Script

```bash
./test-whatsapp-qr-fix.sh
```

Script ini akan mengecek:
1. ✅ Node.js running di port 3001
2. ✅ HMAC secrets cocok
3. ✅ Timestamp fix ada di server.js
4. ✅ Laravel & Reverb running
5. ✅ Webhook HMAC validation (manual test)

### Step 3: Test via Browser

1. Buka: http://127.0.0.1:8000/settings/whatsapp-sessions
2. Klik tombol **"Add WhatsApp Number"**
3. Tunggu ~7-15 detik
4. **QR code harus muncul!** ✅

### Step 4: Monitor Logs (Optional)

**Terminal 1 - Laravel logs:**
```bash
tail -f storage/logs/laravel.log | grep -i "whatsapp\|hmac\|qr"
```

**Terminal 2 - Node.js logs:**
```bash
tail -f whatsapp-service/logs/whatsapp-service.log | grep -i "qr\|webhook\|success"
```

---

## ✅ Expected Results (Sukses)

### Laravel Logs:
```
[timestamp] local.DEBUG: WhatsApp HMAC timestamp validation {
  "timestamp_received": "1760322500",
  "request_time_int": 1760322500,
  "current_time": 1760322500,
  "age_seconds": 0,              ← Harus kecil!
  "is_valid": true               ← Harus true!
}
[timestamp] local.INFO: WhatsApp WebJS webhook received
[timestamp] local.INFO: Broadcasting WhatsAppQRGeneratedEvent
```

### Node.js Logs:
```json
{"level":"info","message":"QR code generated","sessionId":"webjs_1_..."}
{"level":"debug","message":"Data sent to Laravel successfully","event":"qr_code_generated"}
```

### Browser Console:
```
✅ Session created successfully
📨 QR Code Generated Event received
✅ QR code displayed
```

### Browser UI:
- ✅ Modal terbuka
- ✅ QR code muncul (bukan loading terus)
- ✅ Timer countdown: 5:00 → 4:59 → ...
- ✅ Instructions ditampilkan
- ✅ **TIDAK ADA error popup**

---

## ❌ Error Indicators (Gagal)

Jika masih gagal, cek:

### 1. Timestamp Expired
```
"age_seconds": 1758561387369  ← Angka HUGE = timestamp masih milliseconds!
```
**Solution:** Node.js belum restart dengan code baru

### 2. Invalid Signature
```
"message": "Invalid signature"
```
**Solution:** HMAC secrets tidak cocok, cek dengan:
```bash
grep "HMAC_SECRET" whatsapp-service/.env
php artisan tinker --execute="echo config('whatsapp.node_api_secret');"
```

### 3. 401 Bearer Token Missing
```
"error": "Unauthorized. Bearer Token is missing."
```
**Solution:** Webhook routes masih di dalam Bearer Token middleware

---

## 📁 Files Modified

1. **`.env`** - Added WHATSAPP_NODE_API_SECRET
2. **`whatsapp-service/server.js`** - Fixed timestamp (line 360)
3. **`routes/api.php`** - Moved webhook routes outside Bearer Token
4. **`app/Http/Middleware/VerifyWhatsAppHmac.php`** - Added debug logging

---

## 📚 Documentation

1. **`docs/whatsapp-webjs-integration/bugs/03-OPTIONS-PRIORITY-UNDEFINED-FIX.md`**
   - Fix SessionPool options.priority error

2. **`docs/whatsapp-webjs-integration/bugs/04-LARAVEL-TIMEOUT-QR-WEBHOOK-FIX.md`**
   - Fix Laravel timeout + clarify webhook architecture

3. **`docs/whatsapp-webjs-integration/bugs/05-WEBHOOK-AUTH-BEARER-TOKEN-FIX.md`**
   - Fix webhook routes authentication

4. **`docs/whatsapp-webjs-integration/bugs/06-HMAC-SECRET-MISMATCH-TIMESTAMP-FIX.md`**
   - Fix HMAC secret mismatch + timestamp format

---

## 🎯 Quick Commands

```bash
# Cleanup stuck sessions (run first!)
./cleanup-whatsapp-sessions.sh

# Restart everything
./restart-whatsapp-services.sh

# Run tests
./test-whatsapp-qr-fix.sh

# Monitor Laravel logs
tail -f storage/logs/laravel.log | grep -i "whatsapp\|qr"

# Monitor Node.js logs  
tail -f whatsapp-service/logs/whatsapp-service.log | grep -i "qr\|webhook"

# Check services
lsof -i :8000  # Laravel
lsof -i :3001  # Node.js
lsof -i :8080  # Reverb

# Verify secrets match
grep "HMAC_SECRET" whatsapp-service/.env
php artisan tinker --execute="echo config('whatsapp.node_api_secret');"
```

---

## ✅ Success Checklist

Setelah testing, pastikan:

- [x] Script `cleanup-whatsapp-sessions.sh` berhasil (7 sessions deleted)
- [ ] Script `restart-whatsapp-services.sh` berhasil tanpa error
- [ ] Script `test-whatsapp-qr-fix.sh` semua checks ✅ passed
- [ ] Browser: Klik "Add WhatsApp Number" → QR code muncul
- [ ] Browser: Tidak ada error popup
- [ ] Browser Console: "QR Code Generated Event received"
- [ ] Laravel logs: "Broadcasting WhatsAppQRGeneratedEvent"
- [ ] Node.js logs: "Data sent to Laravel successfully"

---

**Status:** ✅ **SIAP TESTING**  
**Next Action:** Run `./cleanup-whatsapp-sessions.sh` → `./restart-whatsapp-services.sh` → test via browser

