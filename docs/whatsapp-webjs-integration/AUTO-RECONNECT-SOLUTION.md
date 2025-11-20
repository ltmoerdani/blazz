# WhatsApp Session Auto-Reconnect & Health Sync

## 🔍 Problem yang Diselesaikan

**Sebelumnya:**
- Server Node.js restart/mati → sessions hilang dari memory
- Database masih menunjukkan status `connected` (100% health)
- Chat tidak masuk karena tidak ada listener aktif di Node.js
- User bingung kenapa nomor terkoneksi tapi chat tidak masuk

**Root Cause:**
- Session restoration gagal karena duplicate session attempt
- Database status tidak sinkron dengan Node.js service
- Tidak ada automatic health check & reconnection

---

## ✅ Solusi yang Diimplementasikan

### 1. **Session Cleanup pada Restoration**
**File:** `whatsapp-service/src/services/AccountRestoration.js`

Menambahkan force cleanup sebelum restore untuk mencegah duplicate session:

```javascript
// Check if session already exists and cleanup if needed
if (this.sessionManager.sessions.has(session_id)) {
    await this.sessionManager.forceCleanupSession(session_id);
    await new Promise(resolve => setTimeout(resolve, 2000));
}
```

**File:** `whatsapp-service/src/managers/SessionManager.js`

Menambahkan method `forceCleanupSession()`:
- Lebih aggressive dari `disconnectSession()`
- Tidak throw error jika client tidak ada
- Timeout protection (5 detik max)
- Force delete dari memory

---

### 2. **Laravel Command: Sync Sessions**
**File:** `app/Console/Commands/SyncWhatsAppSessions.php`

Command untuk sync database dengan Node.js service:

```bash
# Manual sync (hanya update status)
php artisan whatsapp:sync-sessions

# Auto-reconnect sessions yang mismatch
php artisan whatsapp:sync-sessions --auto-reconnect

# Sync workspace tertentu
php artisan whatsapp:sync-sessions --workspace=1 --auto-reconnect
```

**Fitur:**
- ✅ Deteksi mismatch antara database dan Node.js
- ✅ Update status database ke `disconnected` jika session tidak ada di Node.js
- ✅ Auto-reconnect (create session baru) jika flag `--auto-reconnect` aktif
- ✅ Logging lengkap untuk audit trail
- ✅ Summary report

**Output Example:**
```
🔄 Starting WhatsApp session synchronization...
✅ Node.js service is healthy (uptime: 1390s)
   Sessions in Node.js: 0 total, 0 connected

📊 Database Analysis:
   Sessions in database: 1 active

⚠️  Mismatch found:
   Session ID: webjs_1_1763300356_ot6RUaMF
   Phone: 62811801641
   Database status: connected
   Node.js status: not found
   🔄 Attempting auto-reconnect...
   ✅ Reconnection initiated successfully

============================================================
📈 Synchronization Summary:
   Mismatches found: 1
   Sessions reconnected: 1
   Statuses updated: 0
============================================================
```

---

### 3. **Scheduled Auto-Sync (Every 5 Minutes)**
**File:** `app/Console/Kernel.php`

Menambahkan scheduled task:

```php
// Sync WhatsApp sessions with Node.js service (auto-reconnect)
$schedule->command('whatsapp:sync-sessions --auto-reconnect')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
```

**Behavior:**
- ✅ Jalan setiap 5 menit
- ✅ Tidak overlap (jika sync sebelumnya masih jalan)
- ✅ Background execution (tidak block task lain)
- ✅ Otomatis reconnect sessions yang terputus

---

## 🚀 Cara Pakai

### Scenario 1: Server Restart
**Otomatis:**
1. Server restart → sessions hilang dari Node.js
2. Scheduler jalan (max 5 menit)
3. Command deteksi mismatch
4. Auto-reconnect initiated
5. QR code generated (jika perlu)
6. User scan QR → connected

**Manual (lebih cepat):**
```bash
php artisan whatsapp:sync-sessions --auto-reconnect
```

---

### Scenario 2: Session Mati Lama (berhari-hari)
**Otomatis:**
1. Node.js tidak restart tapi session disconnect
2. Event `disconnected` trigger auto-reconnect (via `AutoReconnect.js`)
3. Jika gagal → scheduler akan handle dalam 5 menit
4. Database status tetap sinkron

**Manual:**
User bisa klik tombol "Reconnect" di UI:
- API: `POST /settings/whatsapp-accounts/{uuid}/reconnect`
- Generate QR baru
- User scan → connected

---

### Scenario 3: Health Check & Monitoring
**Monitor status:**
```bash
# Check Node.js health
curl http://localhost:3001/health

# Check all sessions
curl http://localhost:3001/api/sessions?api_key=YOUR_KEY

# Sync status (dry run)
php artisan whatsapp:sync-sessions
```

---

## 🔧 Configuration

### Environment Variables
```env
# Node.js Service
WHATSAPP_NODE_SERVICE_URL=http://localhost:3001
WHATSAPP_NODE_API_KEY=your-api-key

# Auto Reconnect Settings
WHATSAPP_WEBJS_AUTO_RECONNECT=true
WHATSAPP_WEBJS_MAX_RECONNECT_ATTEMPTS=3

# Health Check Interval
WHATSAPP_WEBJS_HEALTH_CHECK_INTERVAL=30
```

### Config File
**File:** `config/whatsapp.php`

```php
'webjs' => [
    'auto_reconnect' => env('WHATSAPP_WEBJS_AUTO_RECONNECT', true),
    'max_reconnect_attempts' => env('WHATSAPP_WEBJS_MAX_RECONNECT_ATTEMPTS', 3),
    'health_check_interval' => env('WHATSAPP_WEBJS_HEALTH_CHECK_INTERVAL', 30),
],
```

---

## 📊 Database Changes

### Metadata yang Ditambahkan
Saat sync/reconnect, metadata session diupdate:

```json
{
  "auto_disconnected_at": "2025-11-20T02:13:52.907Z",
  "reason": "Node.js session not found during sync",
  "auto_reconnect_at": "2025-11-20T02:15:00.000Z",
  "reconnect_triggered_by": "sync_command"
}
```

---

## 🧪 Testing

### Test Manual
```bash
# 1. Restart Node.js service
pm2 restart whatsapp-service

# 2. Check mismatch
php artisan whatsapp:sync-sessions

# 3. Auto-reconnect
php artisan whatsapp:sync-sessions --auto-reconnect

# 4. Verify Node.js has sessions
curl http://localhost:3001/health
```

### Test Scheduler
```bash
# Run scheduler manually (for testing)
php artisan schedule:run

# Monitor logs
tail -f storage/logs/laravel.log | grep -i "whatsapp\|sync"
```

---

## 📝 Logs & Audit Trail

### Laravel Logs
**Location:** `storage/logs/laravel.log`

```
[2025-11-20 02:13:52] production.INFO: Session reconnection initiated
{"session_id":"webjs_1_xxx","workspace_id":1,"response":{"success":true}}

[2025-11-20 02:13:52] production.WARNING: Session reconnection failed
{"session_id":"webjs_1_xxx","status":500,"response":"..."}
```

### Node.js Logs
**Location:** `whatsapp-service/logs/whatsapp-service.log`

```json
{"level":"info","message":"Force cleanup session","sessionId":"webjs_1_xxx"}
{"level":"info","message":"Creating WhatsApp session","sessionId":"webjs_1_xxx"}
{"level":"info","message":"✅ Session restored: webjs_1_xxx"}
```

---

## 🎯 Benefits

### Untuk User
✅ **Seamless experience** - tidak perlu manual reconnect setelah server restart  
✅ **Real-time status** - database selalu sinkron dengan actual status  
✅ **Self-healing** - sistem otomatis reconnect dalam 5 menit  
✅ **Transparency** - health score akurat (bukan false positive)

### Untuk Developer
✅ **Monitoring** - mudah detect session yang bermasalah  
✅ **Debugging** - log lengkap untuk troubleshooting  
✅ **Maintenance** - command manual untuk force sync  
✅ **Scalability** - per-workspace sync support

### Untuk System
✅ **Reliability** - automatic recovery  
✅ **Consistency** - database always in sync  
✅ **Performance** - background execution, no blocking  
✅ **Audit trail** - complete logging

---

## 🔮 Future Improvements

1. **Webhook Notification**
   - Notify user via email/WhatsApp when session disconnected
   - Alert admin when auto-reconnect fails

2. **Dashboard Widget**
   - Real-time session health monitoring
   - Reconnect button with one-click

3. **Advanced Health Metrics**
   - Message delivery rate
   - Connection uptime percentage
   - Auto-scale based on load

4. **Session Pooling**
   - Multiple sessions per workspace
   - Load balancing
   - Failover mechanism

---

## ✅ Checklist Implementation

- [x] Fix session restoration duplicate issue
- [x] Add `forceCleanupSession()` method
- [x] Create `SyncWhatsAppSessions` command
- [x] Setup scheduled auto-sync (5 minutes)
- [x] Test manual sync
- [x] Test auto-reconnect
- [x] Documentation
- [ ] Webhook notification (future)
- [ ] Dashboard widget (future)

---

## 🆘 Troubleshooting

### Problem: Command gagal connect ke Node.js
**Solution:**
```bash
# Check Node.js running
pm2 list

# Check port
curl http://localhost:3001/health

# Check env
php artisan tinker
>>> config('whatsapp.node_service_url')
```

### Problem: Auto-reconnect gagal terus
**Solution:**
```bash
# Check logs
tail -f whatsapp-service/logs/whatsapp-service.log

# Manual reconnect via UI
# Check session folder
ls -la whatsapp-service/sessions/1/

# Delete corrupted session
rm -rf whatsapp-service/sessions/1/webjs_1_xxx
```

### Problem: Scheduler tidak jalan
**Solution:**
```bash
# Make sure cron is running
crontab -l

# Add to crontab if missing
* * * * * cd /path/to/blazz && php artisan schedule:run >> /dev/null 2>&1

# Test manually
php artisan schedule:run
```

---

**Last Updated:** 2025-11-20  
**Version:** 1.0.0  
**Status:** ✅ Production Ready
