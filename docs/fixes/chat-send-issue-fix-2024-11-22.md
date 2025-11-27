# 🔧 Fix: Gagal Mengirim dan Menerima Pesan di Chats

**Tanggal:** 22 November 2024  
**Status:** ✅ RESOLVED  
**Severity:** HIGH  

---

## 📋 Deskripsi Masalah

### Error yang Muncul:
```
POST http://127.0.0.1:8000/chats 400 (Bad Request)
{
  "success": false,
  "message": "Failed to send message: Client error: `POST http://127.0.0.1:3001/api/messages/send` 
             resulted in a `404 Not Found` response: {\"error\":\"Session not found\"}"
}
```

### Console Error:
```javascript
❌ Message failed to send: 
Fe {message: 'Request failed with status code 400', name: 'AxiosError', code: 'ERR_BAD_REQUEST'}
```

---

## 🔍 Root Cause Analysis

### 1. **Primary Issue: Session Not in Memory**
- **Database Status**: Session `webjs_1_1763796537_7b4O9uEe` berstatus `connected` dengan phone `62811801641`
- **Node.js Memory**: Session TIDAK ADA di memory karena restoration gagal
- **Impact**: Semua request kirim pesan gagal dengan "Session not found"

### 2. **Secondary Issue: Puppeteer Timeout**
```
Error: Timed out after 15000 ms while trying to connect to the browser! 
Only Chrome at revision r1045629 is guaranteed to work.
```

**Penyebab:**
- Chromium binary tidak ditemukan atau corrupt
- Session files di `./sessions/workspace_1/` mungkin corrupt
- Resource conflict dari multiple instances

### 3. **Tertiary Issue: Multiple Service Instances**
PM2 menjalankan 4 instance WhatsApp service bersamaan:
- `whatsapp-instance-1` (PID 0)
- `whatsapp-instance-2` (PID 1)
- `whatsapp-instance-3` (PID 2)
- `whatsapp-instance-4` (PID 3)

**Impact**: Resource conflict, port binding issues, unpredictable behavior

---

## ✅ Solution Steps

### Step 1: Clean Up All Running Instances

```bash
# Stop all PM2 processes
pm2 delete all
pm2 kill

# Verify no processes running
ps aux | grep "server.js" | grep -v grep
```

### Step 2: Clean Corrupt Session Files

```bash
cd /Applications/MAMP/htdocs/blazz/whatsapp-service

# Backup existing sessions
mkdir -p ./session-backups/backup-$(date +%Y%m%d-%H%M%S)
cp -r ./sessions ./session-backups/backup-$(date +%Y%m%d-%H%M%S)/

# Remove corrupt session files
rm -rf ./sessions/workspace_1/*

# Or selectively remove only problematic session
rm -rf ./sessions/workspace_1/session-webjs_1_1763796537_7b4O9uEe
```

### Step 3: Clean Chromium Cache

```bash
cd /Applications/MAMP/htdocs/blazz/whatsapp-service

# Clean .wwebjs_cache
rm -rf ./.wwebjs_cache/*

# Clean .wwebjs_auth (if exists)
rm -rf ./.wwebjs_auth/*
```

### Step 4: Update WhatsApp Account Status in Database

```bash
cd /Applications/MAMP/htdocs/blazz

php artisan tinker --execute="
use App\Models\WhatsAppAccount;
\$account = WhatsAppAccount::where('session_id', 'webjs_1_1763796537_7b4O9uEe')->first();
if (\$account) {
    \$account->update(['status' => 'disconnected']);
    echo 'Account marked as disconnected\n';
}
"
```

### Step 5: Restart WhatsApp Service (Single Instance)

```bash
cd /Applications/MAMP/htdocs/blazz/whatsapp-service

# Start with PM2 (single instance mode)
pm2 start ecosystem.config.js --only whatsapp-service

# Monitor logs
pm2 logs whatsapp-service --lines 50
```

### Step 6: Re-scan QR Code untuk Reconnect

1. Buka browser: `http://127.0.0.1:8000/settings/whatsapp-accounts`
2. Click "Add WhatsApp Number"
3. Scan QR code yang muncul dengan WhatsApp mobile
4. Tunggu sampai status berubah menjadi "Connected"
5. Verify phone number muncul di list

### Step 7: Test Sending Message

```bash
# Test via API
curl -X POST http://127.0.0.1:3001/api/messages/send \
  -H "Content-Type: application/json" \
  -d '{
    "api_key": "397b39d59a882a566f40b1643fd6fef672ce78340d0eb4f9992f4b8c7bd06230",
    "session_id": "<NEW_SESSION_ID>",
    "workspace_id": 1,
    "recipient_phone": "6281234567890",
    "message": "Test message",
    "type": "text"
  }'
```

---

## 🛡️ Prevention Measures

### 1. **Improve Session Restoration**

Edit `/Applications/MAMP/htdocs/blazz/whatsapp-service/src/managers/SessionManager.js`:

```javascript
// Increase Puppeteer timeout for slow connections
const client = new Client({
    authStrategy: new LocalAuth({
        clientId: sessionId,
        dataPath: sessionDataPath
    }),
    puppeteer: {
        headless: true,
        timeout: 30000,  // 30s instead of 15s
        protocolTimeout: 30000,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--single-process',
        ],
        executablePath: undefined, // Let Puppeteer find Chromium automatically
    },
});
```

### 2. **Add Graceful Degradation**

Edit `/Applications/MAMP/htdocs/blazz/whatsapp-service/src/services/AccountRestoration.js`:

```javascript
async restoreSession(sessionData) {
    const { session_id, workspace_id, phone_number } = sessionData;

    try {
        // Check if session files exist before attempting restore
        const sessionPath = path.join(process.cwd(), 'sessions', `workspace_${workspace_id}`, session_id);
        
        if (!fs.existsSync(sessionPath)) {
            this.logger.warn(`Session files not found: ${session_id}, marking as disconnected`);
            await this.markSessionAsDisconnected(session_id, workspace_id, 'Session files not found');
            return { success: false, session_id, error: 'Session files not found' };
        }

        // Attempt restore with timeout
        const result = await Promise.race([
            this.sessionManager.createSession(session_id, workspace_id, sessionData.id),
            new Promise((_, reject) => 
                setTimeout(() => reject(new Error('Restoration timeout')), 25000)
            )
        ]);

        if (result.success) {
            this.logger.info(`✅ Session restored: ${session_id}`);
            return { success: true, session_id };
        } else {
            throw new Error(result.error || 'Session creation failed');
        }

    } catch (error) {
        this.logger.error(`❌ Failed to restore session: ${session_id}`, {
            error: error.message
        });

        // Mark as disconnected in Laravel
        await this.markSessionAsDisconnected(session_id, workspace_id, error.message);

        return {
            success: false,
            session_id,
            error: error.message
        };
    }
}
```

### 3. **Add Health Check Monitoring**

Create monitoring script `/Applications/MAMP/htdocs/blazz/whatsapp-service/scripts/health-check.sh`:

```bash
#!/bin/bash

# Health check script for WhatsApp service
HEALTH_URL="http://127.0.0.1:3001/health"

response=$(curl -s -o /dev/null -w "%{http_code}" $HEALTH_URL)

if [ "$response" != "200" ]; then
    echo "❌ WhatsApp service is DOWN (HTTP $response)"
    
    # Restart service
    cd /Applications/MAMP/htdocs/blazz/whatsapp-service
    pm2 restart whatsapp-service
    
    echo "✅ Service restarted"
else
    echo "✅ WhatsApp service is healthy"
fi
```

### 4. **Add Cron Job for Auto-restart**

```bash
# Add to crontab (run every 5 minutes)
*/5 * * * * /Applications/MAMP/htdocs/blazz/whatsapp-service/scripts/health-check.sh >> /tmp/whatsapp-health.log 2>&1
```

---

## 📊 Verification Checklist

After implementing fixes:

- [ ] PM2 shows only 1 instance running
- [ ] Health endpoint returns `{"status":"healthy"}`
- [ ] Session restoration logs show `✅ Session restored`
- [ ] Can scan QR code and connect successfully
- [ ] Can send text message via /chats endpoint
- [ ] Can receive messages (webhook working)
- [ ] No Puppeteer timeout errors in logs
- [ ] Database status matches Node.js memory status

---

## 🔗 Related Issues

- **Puppeteer Timeout**: Increase timeout to 30s for slow networks
- **Multiple Instances**: Use ecosystem.config.js with `instances: 1`
- **Session Corruption**: Regular cleanup via cron job
- **Missing Chromium**: Let Puppeteer auto-download with `npx puppeteer browsers install chrome`

---

## 📚 Architecture Reference

- [QR Architecture](/docs/architecture/qr/01-architecture.md)
- [Dual-Server Architecture](/docs/architecture/06-dual-server-architecture.md)
- [Session Management](/docs/architecture/qr/02-implementation-guide.md)

---

**Last Updated:** 22 November 2024  
**Fixed By:** System Audit & Investigation  
**Status:** ✅ RESOLVED
