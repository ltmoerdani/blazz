# Assigned Instance URL Auto-Sync Fix

**Date**: November 22, 2024  
**Issue**: Database-Node.js instance URL mismatch causing message send failures  
**Root Cause**: `assigned_instance_url` tidak update otomatis saat session reconnect ke instance berbeda  
**Severity**: CRITICAL - Mencegah pengiriman pesan

---

## 🔴 Problem Statement

### Symptoms
- Frontend menunjukkan WhatsApp account "connected" dengan Health Score 100%
- User tidak bisa kirim pesan dari frontend
- Laravel logs menunjukkan error: `404 Not Found` saat POST ke `/api/messages/send`
- Database `assigned_instance_url` mengarah ke instance yang salah

### Root Cause Analysis

**Database State:**
```sql
SELECT id, session_id, phone_number, status, assigned_instance_url 
FROM whatsapp_accounts WHERE id = 142;

-- Result:
-- id: 142
-- session_id: webjs_1_1763798326_U9tUyl0b
-- phone_number: 62811801641
-- status: connected
-- assigned_instance_url: http://localhost:3001  ❌ WRONG!
```

**Node.js Reality:**
```bash
curl http://localhost:3001/health/detailed
# Result: {"sessions":{"total":0,"connected":0}} ❌ No session!

curl http://localhost:3002/health/detailed
# Result: {"sessions":{"total":1,"connected":1}} ✅ Session ada di sini!
```

**Timeline:**
1. Session awalnya di-assign ke port 3001
2. Session restart/reconnect dan pindah ke port 3002
3. Database `assigned_instance_url` TIDAK update otomatis
4. Laravel POST ke port 3001 (database value) → 404 Not Found
5. Session sebenarnya ada di port 3002

---

## ✅ Solution Implemented

### 1. Auto-Sync `assigned_instance_url` pada Webhook

**File**: `app/Http/Controllers/Api/v1/WhatsApp/WebhookController.php`

```php
// ✅ PREVENTION FIX: Extract instance URL from request header
$requestInstanceUrl = request()->header('X-Instance-Url');

$updateData = [
    'status' => 'connected',
    'phone_number' => $phoneNumber,
    // ... other fields
];

// ✅ AUTO-SYNC: Update assigned_instance_url if provided
if ($requestInstanceUrl) {
    $updateData['assigned_instance_url'] = $requestInstanceUrl;
    Log::info('🔄 Auto-syncing assigned_instance_url from webhook header', [
        'session_id' => $sessionId,
        'old_url' => $session->assigned_instance_url,
        'new_url' => $requestInstanceUrl
    ]);
}

$session->update($updateData);
```

**When Triggered:**
- `session_ready` event (saat session pertama kali connect)
- `session_reconnected` event (saat session reconnect setelah disconnect)

---

### 2. Session Verification Before Send

**File**: `app/Services/WhatsApp/MessageService.php`

```php
// ✅ PREVENTION FIX: Verify session exists sebelum kirim
$sessionExists = $this->verifySessionExists($whatsappAccount);

if (!$sessionExists) {
    Log::warning('Session not found in assigned instance, attempting sync...');
    
    // Try to find session in other instances and update database
    $syncResult = $this->attemptInstanceSync($whatsappAccount);
    
    if (!$syncResult) {
        throw new \Exception('Session not found in any Node.js instance.');
    }
}

// Safe to send message now
$result = $this->whatsappClient->sendMessage(...);
```

**Helper Methods:**

1. **`verifySessionExists()`** - Check if session ada di assigned instance:
```php
protected function verifySessionExists(WhatsAppAccount $account): bool
{
    $client = new \GuzzleHttp\Client(['timeout' => 3]);
    $response = $client->get($account->assigned_instance_url . '/health/detailed');
    $data = json_decode($response->getBody()->getContents(), true);
    
    // Search for session in instance
    foreach ($data['sessions']['details'] as $session) {
        if ($session['session_id'] === $account->session_id) {
            return true; // ✅ Found!
        }
    }
    
    return false; // ❌ Not found
}
```

2. **`attemptInstanceSync()`** - Scan all instances and update database:
```php
protected function attemptInstanceSync(WhatsAppAccount $account): bool
{
    $instancePorts = [3001, 3002, 3003, 3004];
    
    foreach ($instancePorts as $port) {
        $instanceUrl = "http://localhost:{$port}";
        
        // Check if session exists in this instance
        $response = $client->get($instanceUrl . '/health/detailed');
        
        // If found, update database
        if ($sessionFoundInThisInstance) {
            $account->update(['assigned_instance_url' => $instanceUrl]);
            
            Log::info('🔄 Instance sync successful! Database updated', [
                'old_url' => $account->assigned_instance_url,
                'new_url' => $instanceUrl
            ]);
            
            return true; // ✅ Sync successful
        }
    }
    
    return false; // ❌ Session not found anywhere
}
```

---

### 3. Node.js Instance URL Header

**File**: `whatsapp-service/utils/webhookNotifier.js`

```javascript
// ✅ PREVENTION FIX: Add instance URL to headers
const instancePort = process.env.PORT || 3001;
const instanceUrl = `http://localhost:${instancePort}`;

const response = await axios.post(url, body, {
    headers: {
        'Content-Type': 'application/json',
        'X-HMAC-Signature': signature,
        'X-Timestamp': timestamp,
        'X-Instance-Url': instanceUrl, // ✅ Send instance URL
        'User-Agent': 'WhatsApp-WebJS-Service/1.0',
    },
    // ... other options
});
```

**Environment Variable:**
```bash
# Instance 1
PORT=3001

# Instance 2
PORT=3002

# Instance 3
PORT=3003

# Instance 4
PORT=3004
```

---

## 🧪 Testing

### Manual Test Scenario

1. **Setup Initial State:**
```bash
# Pastikan session ada di port 3002
curl http://localhost:3002/health/detailed | jq '.sessions'

# Check database
php artisan tinker --execute="
\$acc = \DB::table('whatsapp_accounts')->where('id', 142)->first(['assigned_instance_url']);
echo \$acc->assigned_instance_url;
"
```

2. **Force Instance Mismatch (Simulate Bug):**
```bash
# Manually set wrong instance URL
php artisan tinker --execute="
\DB::table('whatsapp_accounts')
    ->where('id', 142)
    ->update(['assigned_instance_url' => 'http://localhost:3001']);
echo 'Set to wrong instance';
"
```

3. **Attempt Send Message:**
- Buka WhatsApp Blaze frontend
- Kirim pesan ke contact

4. **Verify Auto-Sync:**
```bash
# Check Laravel logs
tail -n 50 storage/logs/laravel.log | grep "Instance sync"

# Expected output:
# ✅ Session verified in assigned instance
# OR
# 🔄 Instance sync successful! Database updated
```

5. **Verify Database Updated:**
```bash
php artisan tinker --execute="
\$acc = \DB::table('whatsapp_accounts')->where('id', 142)->first(['assigned_instance_url']);
echo \$acc->assigned_instance_url; // Should be http://localhost:3002
"
```

---

## 📊 Impact

### Before Fix
- ❌ Message send failures saat session pindah instance
- ❌ Manual intervention required (update database manually)
- ❌ User experience terganggu (pesan tidak terkirim)
- ❌ Debugging sulit (database vs reality mismatch)

### After Fix
- ✅ Auto-sync instance URL pada webhook
- ✅ Pre-send verification dengan fallback sync
- ✅ Zero downtime untuk user
- ✅ Self-healing system (auto-detect dan fix)

---

## 🔒 Prevention Measures

### 1. Webhook-Based Sync
- **When**: Session ready, session reconnected
- **How**: Node.js sends `X-Instance-Url` header
- **Action**: Laravel updates `assigned_instance_url` automatically

### 2. Pre-Send Verification
- **When**: Before every message send attempt
- **Check**: Verify session exists in assigned instance
- **Fallback**: Scan all instances and sync database if needed

### 3. Health Check Integration (Future Enhancement)
```php
// app/Console/Commands/WhatsAppHealthCheck.php

foreach ($accounts as $account) {
    $currentInstance = $this->verifySessionLocation($account);
    
    if ($currentInstance !== $account->assigned_instance_url) {
        $account->update(['assigned_instance_url' => $currentInstance]);
        
        Log::info('Health check corrected instance URL', [
            'account_id' => $account->id,
            'old_url' => $account->assigned_instance_url,
            'new_url' => $currentInstance
        ]);
    }
}
```

---

## 📝 Monitoring

### Key Metrics to Watch

1. **Instance Sync Events:**
```bash
grep "Instance sync successful" storage/logs/laravel.log | wc -l
```

2. **Session Verification Failures:**
```bash
grep "Session not found in assigned instance" storage/logs/laravel.log
```

3. **Auto-Sync from Webhook:**
```bash
grep "Auto-syncing assigned_instance_url" storage/logs/laravel.log
```

### Alerting Thresholds

- ⚠️ WARNING: > 5 instance sync events per hour
- 🔴 CRITICAL: > 10 consecutive verification failures
- 📊 METRIC: Track sync success rate (target: > 99%)

---

## 🚀 Deployment

### Prerequisites
1. Node.js instances running with proper `PORT` env variable
2. Laravel routes configured for webhook endpoint
3. HMAC authentication enabled

### Deployment Steps

1. **Deploy Laravel Changes:**
```bash
# Pull changes
git pull origin staging-broadcast-arch-task

# No migration needed (uses existing columns)

# Clear cache
php artisan cache:clear
php artisan config:clear
```

2. **Deploy Node.js Changes:**
```bash
# Restart all instances with correct PORT
pm2 restart whatsapp-instance-1 --update-env
pm2 restart whatsapp-instance-2 --update-env
pm2 restart whatsapp-instance-3 --update-env
pm2 restart whatsapp-instance-4 --update-env

# Verify instances running
pm2 list
```

3. **Verify Fix Working:**
```bash
# Send test message
# Check logs for auto-sync activity
tail -f storage/logs/laravel.log | grep -E "Instance sync|Auto-syncing"
```

---

## 📚 Related Issues

- Session Restoration Timing Fix (2024-11-22)
- Multi-Instance Load Balancing
- Health Check Optimization

---

## 🔗 References

- Laravel Middleware: `VerifyWhatsAppHmac`
- Node.js Webhook: `utils/webhookNotifier.js`
- Session Manager: `managers/SessionManager.js`
- Message Service: `Services/WhatsApp/MessageService.php`

---

## ✅ Checklist

- [x] Auto-sync pada webhook implemented
- [x] Pre-send verification implemented
- [x] Instance sync fallback implemented
- [x] Node.js header integration
- [x] Documentation completed
- [ ] Monitoring alerts configured
- [ ] Health check enhancement (future)

---

**Status**: ✅ IMPLEMENTED & TESTED  
**Version**: 1.0  
**Author**: Development Team  
**Last Updated**: November 22, 2024
