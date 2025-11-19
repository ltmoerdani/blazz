# WhatsApp QR Code - Manual Testing Guide

**Date:** 2025-10-13  
**Purpose:** Step-by-step manual testing untuk verify QR code generation fix  
**Prerequisites:** All services running (Laravel, Reverb, Node.js)

---

## 🚀 Pre-Test Checklist

### Step 1: Verify All Services Running

Jalankan script test infrastructure:
```bash
cd /Applications/MAMP/htdocs/blazz
./test-whatsapp-qr.sh
```

**Expected Output:**
```
✅ Node.js service is healthy
✅ Laravel app is running
✅ Reverb is listening on port 8080
✅ WHATSAPP_NODE_SERVICE_URL is correctly set
✅ REVERB_PORT is correctly set
```

### Step 2: Open Monitoring Terminals

**Terminal 1 - Laravel Logs:**
```bash
cd /Applications/MAMP/htdocs/blazz
tail -f storage/logs/laravel.log
```

**Terminal 2 - WhatsApp Service Logs:**
```bash
cd /Applications/MAMP/htdocs/blazz
tail -f whatsapp-service/whatsapp-service.log
```

Keep these terminals visible selama testing!

### Step 3: Open Browser dengan DevTools

1. Buka browser (Chrome/Safari/Firefox)
2. Tekan **F12** atau **Cmd+Option+I** untuk buka DevTools
3. Pilih tab **Console**
4. Navigate ke: `http://127.0.0.1:8000/settings/whatsapp-accounts`
5. Login jika diperlukan

---

## 🧪 Test Scenario 1: Create New WhatsApp account (Happy Path)

### Test Steps

1. **Clear Console Logs**
   - Di DevTools Console, klik "Clear console" icon (🚫)
   - Pastikan console bersih sebelum mulai

2. **Click "Add WhatsApp Number" Button**
   - Klik button hijau "Add WhatsApp Number" di kanan atas
   - Atau klik button di tengah halaman (jika belum ada session)

### Expected Behavior

#### 1. Modal Opens Immediately
- Modal muncul dengan title "Add WhatsApp Number"
- Loading spinner "Generating QR code..." terlihat **briefly** (1-2 detik)

#### 2. Console Logs (dalam urutan)

**Harus muncul log ini:**
```javascript
📡 Subscribing to Echo channel: workspace.1
✅ Echo channel subscribed successfully
🔄 Creating new WhatsApp account...
```

**Setelah 1-2 detik:**
```javascript
✅ Session created: {
  success: true,
  session: {
    uuid: "webjs_1_1734042000_abc123",
    status: "initializing",
    workspace_id: 1,
    ...
  },
  qr_code: "iVBORw0KGgoAAAANSUhEUgAA..." // Base64 string
}
```

**Setelah QR generated di Node.js:**
```javascript
📨 QR Code Generated Event received: {
  qr_code_base64: "iVBORw0KGgoAAAANSUhEUgAA...",
  expires_in_seconds: 300,
  workspace_id: 1,
  session_id: "webjs_1_1734042000_abc123",
  timestamp: "2025-10-13T00:40:15.789Z"
}
```

#### 3. Network Tab (DevTools)

**Check tab "Network" di DevTools:**

1. **POST Request ke `/settings/whatsapp-accounts`**
   - Status: **200 OK**
   - Request Payload:
     ```json
     {
       "provider_type": "webjs"
     }
     ```
   - Response:
     ```json
     {
       "success": true,
       "session": { ... },
       "qr_code": "iVBORw0KGgo..."
     }
     ```

#### 4. Terminal 1 - Laravel Logs

**Harus muncul log ini:**
```
[2025-10-13 07:40:15] local.INFO: 📥 WhatsApp account creation request
[2025-10-13 07:40:15] local.INFO: workspace_id: 1
[2025-10-13 07:40:15] local.INFO: provider_type: webjs
[2025-10-13 07:40:15] local.INFO: 🔄 Calling Node.js service to initialize session
[2025-10-13 07:40:15] local.INFO: Node.js URL: http://127.0.0.1:3001/api/sessions
[2025-10-13 07:40:15] local.INFO: ✅ Session initialized successfully
[2025-10-13 07:40:16] local.INFO: 📨 WhatsApp webhook received
[2025-10-13 07:40:16] local.INFO: Event: qr_code_generated
[2025-10-13 07:40:16] local.INFO: Session ID: webjs_1_1734042000_abc123
[2025-10-13 07:40:16] local.INFO: 📡 Broadcasting WhatsAppQRGeneratedEvent
```

#### 5. Terminal 2 - WhatsApp Service Logs

**Harus muncul log ini:**
```
[2025-10-13 07:40:15] [POST /api/sessions] Creating new WhatsApp account
[2025-10-13 07:40:15] workspace_id: 1
[2025-10-13 07:40:15] session_id: webjs_1_1734042000_abc123
[2025-10-13 07:40:15] Initializing whatsapp-web.js client...
[2025-10-13 07:40:16] ✅ QR code generated successfully
[2025-10-13 07:40:16] Sending webhook to Laravel: POST /api/whatsapp/webhooks/webjs
[2025-10-13 07:40:16] Webhook sent successfully (status: 200)
```

#### 6. UI Display

**Modal harus menampilkan:**
- ✅ QR Code image terlihat jelas
- ✅ Timer countdown "5:00" mulai berjalan (4:59, 4:58, ...)
- ✅ Instructions:
  - "Scan the QR code using WhatsApp mobile app"
  - "Open WhatsApp on your phone"
  - "Tap Menu or Settings and select Linked Devices"
  - "Tap Link a Device"
  - "Point your phone to this screen to capture the code"
- ✅ Button "Regenerate QR Code" visible
- ✅ Button "Cancel" visible

---

## 🧪 Test Scenario 2: Scan QR Code with WhatsApp Mobile

### Test Steps

1. **Ambil smartphone dengan WhatsApp installed**
2. **Buka WhatsApp**
3. **Tap Menu (3 dots) → Linked Devices**
4. **Tap "Link a Device"**
5. **Scan QR code** yang muncul di browser

### Expected Behavior

#### 1. Console Logs
```javascript
📨 Session Status Changed Event received: {
  status: "authenticated",
  workspace_id: 1,
  session_id: "webjs_1_1734042000_abc123",
  timestamp: "2025-10-13T00:40:30.123Z"
}
```

#### 2. Laravel Logs
```
[2025-10-13 07:40:30] local.INFO: 📨 WhatsApp webhook received
[2025-10-13 07:40:30] local.INFO: Event: session_status_changed
[2025-10-13 07:40:30] local.INFO: Status: authenticated
[2025-10-13 07:40:30] local.INFO: 📡 Broadcasting WhatsAppAccountStatusChangedEvent
```

#### 3. WhatsApp Service Logs
```
[2025-10-13 07:40:30] Client authenticated successfully
[2025-10-13 07:40:30] Session ID: webjs_1_1734042000_abc123
[2025-10-13 07:40:30] Sending webhook: session_status_changed (authenticated)
```

#### 4. UI Changes
- ✅ Modal menutup otomatis
- ✅ Session muncul di list dengan status "Connected" (hijau)
- ✅ WhatsApp number terlihat
- ✅ Badge "WebJS" visible
- ✅ Actions: Send Test, Disconnect, Delete

---

## 🧪 Test Scenario 3: Regenerate QR Code

### Test Steps

1. **Click "Add WhatsApp Number"** (create new session)
2. **Wait untuk QR code muncul**
3. **Click button "Regenerate QR Code"** (di dalam modal)

### Expected Behavior

#### 1. Console Logs
```javascript
🔄 Regenerating QR code for session: webjs_1_1734042000_abc123
```

#### 2. Network Tab
- POST `/settings/whatsapp-accounts/{uuid}/regenerate-qr`
- Status: **200 OK**
- Response: `{ success: true, qr_code: "..." }`

#### 3. UI Changes
- ✅ Loading spinner muncul **briefly**
- ✅ QR code **berubah** (different pattern)
- ✅ Timer **reset ke 5:00**

---

## 🧪 Test Scenario 4: QR Code Expiration

### Test Steps

1. **Create new session**
2. **Wait selama 5 menit** (atau edit countdown untuk testing cepat)
3. **Jangan scan QR code**

### Expected Behavior

#### 1. UI Changes
- ✅ Timer mencapai 0:00
- ✅ Message "QR code expired" muncul
- ✅ Button "Generate New QR" muncul

#### 2. Console Logs
```javascript
⏰ QR code expired
```

---

## 🧪 Test Scenario 5: Error Handling - Node.js Service Down

### Test Steps

1. **Stop Node.js service:**
   ```bash
   pkill -f "node.*server.js"
   ```

2. **Click "Add WhatsApp Number"**

### Expected Behavior

#### 1. Console Logs
```javascript
🔄 Creating new WhatsApp account...
❌ Failed to create session: Error: Node.js service not responding
```

#### 2. UI Changes
- ✅ Alert popup: "Failed to create WhatsApp account: Node.js service not responding"
- ✅ Modal closes
- ✅ No session created in database

#### 3. Recovery Test
1. **Restart Node.js service:**
   ```bash
   cd /Applications/MAMP/htdocs/blazz/whatsapp-service
   npm run dev
   ```
2. **Click "Add WhatsApp Number" again**
3. ✅ Should work normally

---

## 🧪 Test Scenario 6: Multiple Sessions

### Test Steps

1. **Create first session** and scan QR
2. **Click "Add WhatsApp Number" again**
3. **Create second session** (jika limit allows)

### Expected Behavior

#### 1. Behavior
- ✅ First session tetap "Connected"
- ✅ Second session modal opens
- ✅ Second QR code generates successfully
- ✅ Both sessions visible di list

#### 2. Limit Check
- If limit reached, button "Add WhatsApp Number" disabled
- Tooltip shows: "Maximum sessions reached"

---

## 🧪 Test Scenario 7: Disconnect Session

### Test Steps

1. **Create and authenticate session**
2. **Click "Disconnect" button** di session card
3. **Confirm disconnect**

### Expected Behavior

#### 1. Console Logs
```javascript
📨 Session Status Changed Event received: {
  status: "disconnected",
  ...
}
```

#### 2. Laravel Logs
```
[timestamp] local.INFO: Session disconnect request
[timestamp] local.INFO: session_id: webjs_1_...
[timestamp] local.INFO: ✅ Session disconnected successfully
```

#### 3. WhatsApp Service Logs
```
[timestamp] [DELETE /api/sessions/{id}] Disconnecting session
[timestamp] Client destroyed successfully
```

#### 4. UI Changes
- ✅ Session status berubah menjadi "Disconnected" (merah)
- ✅ Actions change: Reconnect, Delete
- ✅ WhatsApp mobile app shows "Logged out"

---

## ✅ Test Results Checklist

Print checklist ini dan centang saat testing:

### Infrastructure
- [ ] Node.js service healthy (port 3001)
- [ ] Laravel app running (port 8000)
- [ ] Reverb listening (port 8080)
- [ ] Config variables correct

### Scenario 1: Create Session (Happy Path)
- [ ] Modal opens
- [ ] Console logs correct sequence
- [ ] Network POST request successful
- [ ] Laravel logs show flow
- [ ] WhatsApp service logs show QR generation
- [ ] QR code displays in UI
- [ ] Timer starts counting down
- [ ] Instructions visible

### Scenario 2: Scan QR Code
- [ ] WhatsApp mobile scans successfully
- [ ] Session status changes to "authenticated"
- [ ] Modal closes automatically
- [ ] Session appears in list as "Connected"

### Scenario 3: Regenerate QR
- [ ] Button works
- [ ] New QR code generated
- [ ] Timer resets

### Scenario 4: QR Expiration
- [ ] Timer counts to 0:00
- [ ] Expiration message shows
- [ ] Generate new QR button appears

### Scenario 5: Error Handling
- [ ] Service down shows error
- [ ] Alert message displayed
- [ ] No broken database state
- [ ] Recovery works after restart

### Scenario 6: Multiple Sessions
- [ ] Can create multiple sessions
- [ ] Each session independent
- [ ] Limit enforcement works

### Scenario 7: Disconnect
- [ ] Disconnect button works
- [ ] Status updates correctly
- [ ] WhatsApp mobile shows logged out

---

## 🐛 Common Issues & Solutions

### Issue 1: QR Code Tidak Muncul
**Check:**
1. Console logs - Ada error?
2. Network tab - POST request berhasil?
3. Laravel logs - Webhook received?
4. Reverb - Broadcasting working?

**Solution:**
```bash
# Clear cache
php artisan config:clear
php artisan cache:clear

# Restart services
pkill -f "reverb:start"
php artisan reverb:start --host=0.0.0.0 --port=8080
```

### Issue 2: Echo Not Receiving Events
**Check:**
1. Console: "Subscribing to Echo channel: workspace.X"
2. Reverb logs: "Client connected"

**Solution:**
- Check `.env` REVERB settings
- Verify `resources/js/echo.js` config
- Check browser blocks WebSocket?

### Issue 3: Node.js Timeout
**Check:**
1. WhatsApp service logs
2. Port 3001 accessible?

**Solution:**
```bash
cd whatsapp-service
npm run dev
# Check logs
tail -f whatsapp-service.log
```

---

## 📊 Testing Report Template

Copy template ini untuk report hasil testing:

```markdown
# WhatsApp QR Testing Report

**Tester:** [Your Name]
**Date:** [Date]
**Branch:** stg-new-whatsapp
**Commit:** [Git commit hash]

## Environment
- Laravel Version: 12.x
- Node.js Version: [version]
- Browser: [Chrome/Safari/Firefox + version]
- OS: [macOS/Windows/Linux]

## Test Results

### Infrastructure ✅/❌
- [ ] All services running

### Scenario 1: Create Session ✅/❌
- [ ] QR code generates successfully
- **Time to QR:** [X seconds]
- **Issues:** [None/describe]

### Scenario 2: Scan QR ✅/❌
- [ ] WhatsApp scans successfully
- **Connection time:** [X seconds]
- **Issues:** [None/describe]

[Continue for all scenarios...]

## Bugs Found
1. [Description]
   - Steps to reproduce
   - Expected vs Actual
   - Severity: Critical/High/Medium/Low

## Overall Assessment
✅ PASS / ❌ FAIL

**Recommendation:** [Deploy to production / Need fixes]

**Tested by:** [Name]
**Reviewed by:** [Name]
```

---

## 🎯 Success Criteria

Testing dianggap **PASS** jika:

1. ✅ QR code muncul dalam **< 3 seconds**
2. ✅ QR code dapat di-scan dengan WhatsApp mobile
3. ✅ Session status update real-time (via Echo)
4. ✅ Multiple sessions work independently
5. ✅ Error handling works (graceful failures)
6. ✅ No JavaScript errors di console
7. ✅ No PHP errors di Laravel logs
8. ✅ No crashes di WhatsApp service

---

**Created by:** AI Assistant  
**Last Updated:** 2025-10-13  
**Related Docs:** WHATSAPP-QR-FIX-REPORT.md
