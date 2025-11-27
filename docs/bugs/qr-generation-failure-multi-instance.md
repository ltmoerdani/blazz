# Bug Report: QR Generation Failure After Multi-Instance Refactor

**Date**: November 20, 2025  
**Severity**: 🔴 **CRITICAL** - Blocks core functionality  
**Status**: ✅ **FIXED**  
**Branch**: `staging-broadcast-arch-task`

---

## 📋 Executive Summary

After implementing multi-instance architecture refactor, WhatsApp account creation fails immediately with status `'failed'` and no QR code is generated. The root cause is that legacy services are still hardcoded to connect to `http://localhost:3000`, which no longer exists after migration to multi-instance ports (3001-3004).

---

## 🐛 Symptoms

### Console Logs
```javascript
📨 Account Status Changed Event received: {
  account_id: 'webjs_1_1763656640_NF0Bv7Ek',
  status: 'failed',
  workspace_id: 1,
  phone_number: null,
  metadata: {...}
}
```

### Laravel Logs
```
[2025-11-20 16:37:21] local.ERROR: Session initialization failed {
  "workspace_id": 1,
  "session_id": "webjs_1_1763656640_NF0Bv7Ek",
  "error": "cURL error 7: Failed to connect to localhost port 3000: Connection refused"
}
```

### User Experience
1. User clicks "Create WhatsApp Account"
2. Modal opens with loading state
3. Immediately receives "failed" status via WebSocket
4. No QR code is displayed
5. Account stuck in `failed` state in database

---

## 🔍 Root Cause Analysis

### Architecture Context

**Before Multi-Instance:**
- Single Node.js service running on `http://localhost:3000`
- Direct HTTP calls from Laravel to Node.js
- Simple, monolithic architecture

**After Multi-Instance Refactor:**
- 4 separate Node.js instances via PM2 cluster mode
- Instances running on ports: `3001`, `3002`, `3003`, `3004`
- Port `3000` NO LONGER EXISTS
- InstanceRouter determines correct instance based on `workspace_id`
- ProxyController acts as routing layer

### The Bug

**WebJSAdapter** was still using hardcoded config:
```php
// ❌ BEFORE (BROKEN)
$this->nodeServiceUrl = config('whatsapp.node_service_url', 'http://localhost:3000');

$response = Http::timeout(60)->post("{$this->nodeServiceUrl}/api/sessions", [...]);
```

**Result:**
- Laravel tries to connect to `localhost:3000`
- Port 3000 is not listening (Connection refused)
- cURL error 7 thrown
- Session status set to `'failed'`
- No QR code generated

---

## 🛠️ Fix Implementation

### 1. WebJSAdapter Constructor Refactor

**File**: `app/Services/Adapters/WebJSAdapter.php`

**Changes:**
```php
// ✅ AFTER (FIXED)
use App\Services\WhatsApp\InstanceRouter;

private InstanceRouter $router;

public function __construct(int $workspaceId, ?WhatsAppAccount $session = null)
{
    $this->workspaceId = $workspaceId;
    $this->session = $session;
    $this->router = app(InstanceRouter::class);
    
    // MULTI-INSTANCE: Use router to get correct instance URL
    // If session already assigned, use that. Otherwise, route based on workspace.
    if ($this->session && $this->session->assigned_instance_url) {
        $this->nodeServiceUrl = $this->session->assigned_instance_url;
    } else {
        $this->nodeServiceUrl = $this->router->getInstanceForWorkspace($workspaceId);
    }
    
    // Initialize helper classes with correct URL...
}
```

**Key Improvements:**
- ✅ Injects `InstanceRouter` dependency
- ✅ Uses workspace-based routing for NEW sessions
- ✅ Uses assigned URL for EXISTING sessions
- ✅ Maintains consistent hashing (workspace_id % instance_count)

### 2. WebJSAdapter::initializeSession() Refactor

**Changes:**
```php
public function initializeSession(): array
{
    try {
        // MULTI-INSTANCE: Route to correct instance based on workspace
        $instanceIndex = $this->router->getInstanceIndex($this->workspaceId);
        $targetInstanceUrl = $this->router->getInstanceUrl($instanceIndex);

        Log::info("Initializing session on Instance {$instanceIndex}", [
            'workspace_id' => $this->workspaceId,
            'session_id' => $this->session->session_id,
            'target_instance' => $targetInstanceUrl,
        ]);

        $response = Http::timeout(60)->post("{$targetInstanceUrl}/api/sessions", [
            'workspace_id' => $this->workspaceId,
            'account_id' => $this->session->id,
            'session_id' => $this->session->session_id,
            'api_key' => config('whatsapp.node_api_key'),
        ]);

        if ($response->successful()) {
            $data = $response->json();

            // Update session status AND instance assignment
            $this->session->update([
                'status' => $data['status'] ?? 'qr_scanning',
                'last_activity_at' => now(),
            ]);

            // Assign to instance in database
            $this->session->assignToInstance($instanceIndex, $targetInstanceUrl);

            return [
                'success' => true,
                'message' => 'Session initialized successfully.',
                'session_id' => $data['session_id'],
                'status' => $data['status'] ?? 'qr_scanning',
                'qr_code' => null, // Sent via WebSocket
            ];
        }
        
        // Error handling...
    }
}
```

**Key Improvements:**
- ✅ Uses `InstanceRouter` to determine target instance
- ✅ Logs routing decisions for debugging
- ✅ Assigns instance to database immediately
- ✅ Proper timeout (60s for Puppeteer initialization)
- ✅ Enhanced error logging with context

### 3. ProxyController Endpoint Fix

**File**: `app/Http/Controllers/WhatsApp/ProxyController.php`

**Changes:**
```php
// ❌ BEFORE (WRONG ENDPOINT)
$response = Http::timeout(30)->post("{$targetInstanceUrl}/sessions/create", $request->all());

// ✅ AFTER (CORRECT ENDPOINT)
$response = Http::timeout(60)->post("{$targetInstanceUrl}/api/sessions", [
    'workspace_id' => $workspaceId,
    'session_id' => $sessionId,
    'account_id' => WhatsAppAccount::where('session_id', $sessionId)->first()?->id,
    'api_key' => config('whatsapp.node_api_key'),
]);
```

**Key Improvements:**
- ✅ Corrected endpoint from `/sessions/create` → `/api/sessions`
- ✅ Matches Node.js `SessionController.createSession()` route
- ✅ Includes `account_id` for database tracking
- ✅ Enhanced error logging

---

## 🎯 Testing & Verification

### Pre-Fix State
```bash
# PM2 instances running
pm2 list
# ✅ 4 instances online (ports 3001-3004)

# Test old endpoint (FAILED)
curl http://localhost:3000/health
# ❌ Connection refused

# Laravel logs
tail -f storage/logs/laravel.log
# ❌ cURL error 7: Connection refused to port 3000
```

### Post-Fix State
```bash
# Clear cache
php artisan optimize:clear

# Test routing
php artisan tinker
>>> $router = app(\App\Services\WhatsApp\InstanceRouter::class);
>>> $router->getInstanceForWorkspace(1);
# ✅ "http://localhost:3002"

# Create WhatsApp account via UI
# ✅ Session initialized successfully
# ✅ QR code generated and displayed
# ✅ Status: 'qr_scanning'
# ✅ Instance assigned in database
```

---

## 📊 Impact Assessment

### Affected Components
1. ✅ **WebJSAdapter** - FIXED (uses InstanceRouter)
2. ✅ **ProxyController** - FIXED (corrected endpoint)
3. ⚠️ **AccountStatusService** - NEEDS REVIEW (uses hardcoded URL)
4. ⚠️ **WebhookService** - NEEDS REVIEW (uses hardcoded URL)

### Services Still Using Hardcoded URL
```php
// app/Services/WhatsApp/AccountStatusService.php
$this->nodeServiceUrl = config('whatsapp.node_service_url', 'http://localhost:3000');

// app/Services/WhatsApp/WebhookService.php
$this->nodeServiceUrl = config('whatsapp.node_service_url', 'http://localhost:3000');
```

**Note**: These services typically operate on EXISTING sessions with `assigned_instance_url` in database, so they may not be affected. However, they should be audited for edge cases.

---

## ✅ Resolution Checklist

- [x] Identified root cause (hardcoded localhost:3000)
- [x] Refactored WebJSAdapter to use InstanceRouter
- [x] Fixed ProxyController endpoint mismatch
- [x] Added comprehensive logging
- [x] Updated database instance assignment
- [x] Cleared Laravel cache
- [x] Rebuilt frontend assets
- [x] Documented fix in bug report
- [ ] Test QR generation in production-like environment
- [ ] Audit remaining services for hardcoded URLs
- [ ] Update architecture documentation

---

## 🚀 Deployment Notes

### Required Steps
1. **Deploy code changes**:
   ```bash
   git add app/Services/Adapters/WebJSAdapter.php
   git add app/Http/Controllers/WhatsApp/ProxyController.php
   git commit -m "fix: QR generation with multi-instance routing"
   ```

2. **Clear cache on production**:
   ```bash
   php artisan optimize:clear
   ```

3. **Verify PM2 instances**:
   ```bash
   pm2 list
   # Ensure 4 instances running on ports 3001-3004
   ```

4. **Monitor logs**:
   ```bash
   tail -f storage/logs/laravel.log
   pm2 logs --lines 100
   ```

### Rollback Plan
If issues occur:
1. Revert commits: `git revert <commit-hash>`
2. Redeploy previous version
3. Restart PM2: `pm2 restart all`
4. Clear cache: `php artisan optimize:clear`

---

## 📚 Related Documentation

- [Multi-Instance Architecture](../architecture/09-scalable-architecture.md)
- [InstanceRouter Implementation](../architecture/10-implementation-checklist.md)
- [Database Schema Audit](../architecture/13-database-schema-audit-multi-instance.md)
- [Architecture Compliance Report](../architecture/architecture-audit-final-evidence-based.md)

---

## 🎓 Lessons Learned

### What Went Wrong
1. **Incomplete refactor**: Multi-instance infrastructure was deployed without updating all consumers
2. **Missing integration tests**: No automated tests caught the hardcoded URL issue
3. **Insufficient logging**: Initial error didn't clearly indicate routing problem

### Best Practices Going Forward
1. ✅ **Dependency Injection**: Always inject InstanceRouter instead of hardcoding URLs
2. ✅ **Comprehensive logging**: Log routing decisions at INFO level
3. ✅ **Integration tests**: Test full flow from Laravel → ProxyController → Node.js
4. ✅ **Architecture documentation**: Update docs immediately after infrastructure changes
5. ✅ **Gradual rollout**: Use feature flags for major architectural changes

---

## 👥 Credits

**Reported by**: User (console logs analysis)  
**Investigated by**: AI Assistant  
**Fixed by**: AI Assistant  
**Reviewed by**: Pending  
**Date Fixed**: November 20, 2025

---

## 📝 Additional Notes

### Configuration Changes Required

**Before** (`config/whatsapp.php`):
```php
'node_service_url' => env('WHATSAPP_NODE_URL', 'http://localhost:3000'),
```

**After** (DEPRECATED - use InstanceRouter):
```php
// Multi-instance configuration
'instance_count' => env('WHATSAPP_INSTANCE_COUNT', 4),
'instances' => [
    env('WHATSAPP_INSTANCE_1', 'http://localhost:3001'),
    env('WHATSAPP_INSTANCE_2', 'http://localhost:3002'),
    env('WHATSAPP_INSTANCE_3', 'http://localhost:3003'),
    env('WHATSAPP_INSTANCE_4', 'http://localhost:3004'),
],
```

**Environment Variables** (`.env`):
```ini
WHATSAPP_INSTANCE_COUNT=4
WHATSAPP_INSTANCE_1=http://localhost:3001
WHATSAPP_INSTANCE_2=http://localhost:3002
WHATSAPP_INSTANCE_3=http://localhost:3003
WHATSAPP_INSTANCE_4=http://localhost:3004
```

### Node.js Service Configuration

Each PM2 instance must be configured with unique port:

**PM2 Ecosystem** (`ecosystem.config.js`):
```javascript
module.exports = {
  apps: [
    {
      name: 'whatsapp-instance-1',
      script: './server.js',
      instances: 1,
      exec_mode: 'cluster',
      env: {
        PORT: 3001,
        INSTANCE_ID: 0,
      },
    },
    {
      name: 'whatsapp-instance-2',
      script: './server.js',
      instances: 1,
      exec_mode: 'cluster',
      env: {
        PORT: 3002,
        INSTANCE_ID: 1,
      },
    },
    // ... instances 3 and 4
  ],
};
```

---

**End of Bug Report**
