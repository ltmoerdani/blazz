# Reconnect Session Fix

**Date**: November 16, 2025  
**Issue**: Error saat klik reconnect - "Failed to reconnect session: Failed to reconnect account: Node.js service reconnect failed: {"error":"Session not found"}"

## Problem Analysis

### Root Cause
Previous reconnect flow mencoba me-restart session yang sudah tidak ada di Node.js service:

```
User clicks "Reconnect"
    ↓
Laravel calls Node.js: POST /api/sessions/{old_session_id}/reconnect
    ↓
Node.js tries to disconnect old_session_id (doesn't exist)
    ↓
ERROR: Session not found
```

### Why Session Not Found?
1. Session crashed dan Puppeteer browser terminated
2. Database masih show "connected" (webhook disconnect event tidak sampai)
3. Node.js service sudah tidak punya session tersebut
4. Reconnect endpoint expects existing session

## Solution Implemented

### Strategy
Untuk **disconnected/failed sessions**, jangan reconnect - **create new session** instead:

```javascript
// Old Flow (BROKEN)
reconnect(old_session_id) → disconnect(doesn't exist) → ERROR

// New Flow (FIXED)
if (session disconnected || no session_id):
    generate_new_session_id()
    create_new_session(new_id)
else:
    disconnect_existing() → create_new_session()
```

### Code Changes

#### 1. AccountStatusService::reconnect() - `/app/Services/WhatsApp/AccountStatusService.php`

**Before**:
```php
// Always try to reconnect with old session_id
$response = Http::post("{$nodeUrl}/api/sessions/{$account->session_id}/reconnect", [...]);
```

**After**:
```php
// Generate new session_id for disconnected sessions
$isDisconnected = in_array($account->status, ['disconnected', 'failed', 'error']);

if ($isDisconnected || !$account->session_id) {
    // Create completely new session
    $account->session_id = 'webjs_' . $workspaceId . '_' . time() . '_' . substr(md5(uniqid()), 0, 8);
    $account->save();
} else {
    // Disconnect existing connected session first
    $this->disconnect($uuid);
}

// Call create session endpoint (not reconnect)
$response = Http::post("{$nodeUrl}/api/sessions", [
    'session_id' => $account->session_id,
    'workspace_id' => $workspaceId,
    'account_id' => $account->id,
    'priority' => 'normal',
]);
```

#### 2. AccountStatusService::regenerateQR() - Same file

Applied same logic untuk regenerate QR code flow:
- Check if disconnected/failed
- Generate new session_id if needed
- Call create session endpoint

## Testing Steps

### 1. Check Current State
```bash
# Check database
php artisan tinker --execute="
    WhatsAppAccount::where('status', 'disconnected')
        ->get(['id', 'phone_number', 'status', 'session_id'])
        ->toArray()
"

# Check Node.js sessions
curl http://localhost:3001/health
```

### 2. Test Reconnect via Browser
1. Navigate to: `http://localhost:8000/settings/whatsapp-accounts`
2. Find disconnected session
3. Click "Reconnect" button
4. Should see QR code modal
5. Scan with WhatsApp
6. Wait for "Connected" status

### 3. Test Reconnect via API
```bash
# Get account UUID
UUID="578b01b2-461d-4fa2-8d28-bba5b1b846e1"

# Call reconnect endpoint
curl -X POST http://127.0.0.1:8000/settings/whatsapp-accounts/${UUID}/reconnect \
  -H "Cookie: laravel_session=YOUR_SESSION" \
  -H "Content-Type: application/json"

# Should return:
# {"success":true,"message":"Session reconnection initiated","qr_code":"..."}
```

### 4. Verify Session Created
```bash
# Check Node.js has new session
curl http://localhost:3001/api/sessions \
  -H "X-API-Key: YOUR_API_KEY"

# Check database updated
php artisan tinker --execute="
    WhatsAppAccount::where('uuid', '578b01b2-461d-4fa2-8d28-bba5b1b846e1')
        ->first(['status', 'session_id', 'updated_at'])
"
```

## Expected Results

### Before Fix
```
❌ Error: Session not found
❌ Database status: disconnected
❌ Node.js sessions: 1 (old crashed session still showing)
❌ User cannot reconnect
```

### After Fix
```
✅ New session_id generated
✅ QR code displayed
✅ User can scan and connect
✅ Database status: qr_scanning → connected
✅ Node.js sessions: 1 (new healthy session)
✅ Health monitoring active
```

## Benefits

1. **Automatic Recovery**: Users can reconnect disconnected sessions without manual intervention
2. **Clean State**: Each reconnect gets fresh session_id, no conflicts with old sessions
3. **Better UX**: No confusing error messages, reconnect "just works"
4. **Health Monitoring**: New sessions automatically tracked by health monitoring system

## Related Files

### Modified
- `app/Services/WhatsApp/AccountStatusService.php` (lines 182-220, 268-305)

### Related Systems
- `whatsapp-service/src/controllers/SessionController.js` - Node.js create session
- `app/Jobs/MonitorWhatsAppSessionHealthJob.php` - Health monitoring
- `app/Services/WhatsApp/SessionHealthService.php` - Auto-reconnect trigger

## Deployment Checklist

- [x] Code changes implemented
- [x] Cache cleared (`php artisan optimize:clear`)
- [x] Database migrations up to date
- [x] Node.js service running
- [ ] Test reconnect in browser
- [ ] Verify QR code display
- [ ] Test complete connection flow
- [ ] Monitor health scores after reconnect

## Troubleshooting

### Issue: Still getting "Session not found"
**Check**:
```bash
# Verify Node.js service running
curl http://localhost:3001/health

# Check if API key configured
grep NODE_API_KEY .env
grep NODE_API_KEY whatsapp-service/.env
```

### Issue: QR code not displayed
**Check**:
```bash
# Check Laravel logs
tail -f storage/logs/laravel.log | grep "QR"

# Check Node.js logs
cd whatsapp-service && npm run logs
```

### Issue: Session connects but immediately disconnects
**Check**:
```bash
# Run health check
php artisan whatsapp:health-summary

# Check webhook connectivity
curl -X POST http://127.0.0.1:8000/api/whatsapp/webhooks/webjs \
  -H "X-Timestamp: $(date +%s)" \
  -H "X-HMAC-Signature: test" \
  -d '{"event":"test"}'
```

## Success Criteria

- [ ] User clicks "Reconnect" on disconnected session
- [ ] QR code modal appears within 5 seconds
- [ ] User scans QR with WhatsApp
- [ ] Status changes: qr_scanning → connected
- [ ] Test message sent successfully
- [ ] Test message received successfully
- [ ] Health score shows 100
- [ ] No errors in logs

---

**Status**: ✅ FIXED - Ready for testing  
**Next**: User to test reconnect flow and verify messages work
