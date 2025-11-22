# Implementation Guide - WhatsApp QR Integration

**Version:** 2.0  
**Last Updated:** November 22, 2025  

---

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Node.js Service Setup](#nodejs-service-setup)
3. [Laravel Backend Setup](#laravel-backend-setup)
4. [Frontend Setup](#frontend-setup)
5. [Testing](#testing)
6. [Deployment](#deployment)

---

## Prerequisites

### Required Software

```bash
# Node.js
node --version  # v18.x or higher
npm --version   # v9.x or higher

# PHP & Laravel
php --version   # v8.1 or higher
composer --version

# Database
mysql --version  # v8.0 or higher

# Process Manager
pm2 --version   # v5.x or higher
```

### Environment Variables

#### Node.js (whatsapp-service/.env)

```bash
# Server Configuration
PORT=3001
NODE_ENV=production

# Authentication
AUTH_STRATEGY=localauth  # CRITICAL: Use localauth for <1000 users

# Laravel Integration
LARAVEL_URL=http://127.0.0.1:8000
HMAC_SECRET=your-secret-key-here-minimum-32-chars

# Puppeteer Configuration
PUPPETEER_TIMEOUT=30000
PUPPETEER_ARGS=--no-sandbox,--disable-setuid-sandbox,--disable-dev-shm-usage,--single-process

# Logging
LOG_LEVEL=info
LOG_FILE=./logs/whatsapp-service.log
```

#### Laravel (.env)

```bash
# WhatsApp Configuration
WHATSAPP_HMAC_SECRET=your-secret-key-here-minimum-32-chars  # MUST match Node.js
WHATSAPP_NODE_URL=http://127.0.0.1:3001

# Broadcast (Laravel Reverb)
BROADCAST_DRIVER=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

# Queue (for async webhook processing)
QUEUE_CONNECTION=database  # or 'redis' for production
```

---

## Node.js Service Setup

### 1. Install Dependencies

```bash
cd whatsapp-service
npm install
```

**Key Dependencies:**
- `whatsapp-web.js` - WhatsApp Web API wrapper
- `puppeteer` - Headless Chrome automation
- `qrcode` - QR code generation
- `axios` - HTTP client for webhooks
- `winston` - Logging library

### 2. Configure SessionManager.js

**File:** `src/managers/SessionManager.js`

**Critical Configuration:**

```javascript
// Puppeteer configuration for optimal performance
const puppeteerConfig = {
    headless: true,
    args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--single-process',  // Reduces memory usage
        '--no-zygote',
        '--disable-gpu'
    ],
    timeout: 30000,  // 30 seconds (reduced from 90s)
    protocolTimeout: 30000
};

// WhatsApp Client configuration
const clientOptions = {
    authStrategy: new LocalAuth({
        clientId: sessionId,
        dataPath: './.wwebjs_auth/'
    }),
    puppeteer: puppeteerConfig,
    webVersionCache: {
        type: 'local',  // Use local cache instead of remote
        path: './.wwebjs_cache/'
    }
};
```

### 3. Phone Extraction Implementation

**Key Method:** `extractPhoneNumberSafely()`

```javascript
async extractPhoneNumberSafely(client, sessionId) {
    const maxRetries = 15;
    const retryDelay = 500; // ms
    const initialDelay = 2500; // Critical: WhatsApp Web.js init time

    // Wait for library initialization
    await new Promise(resolve => setTimeout(resolve, initialDelay));

    // Retry loop
    for (let i = 0; i < maxRetries; i++) {
        try {
            // Primary method: client.info.wid
            if (client.info?.wid?.user) {
                this.logger.info('✅ Phone extracted', {
                    method: 'client.info.wid',
                    attempt: i + 1,
                    phoneNumber: client.info.wid.user
                });
                return client.info.wid.user;
            }

            // Wait before next attempt
            await new Promise(resolve => setTimeout(resolve, retryDelay));
        } catch (error) {
            this.logger.warn(`Attempt ${i + 1} failed`, { error: error.message });
        }
    }

    // Fallback: Direct Store access
    try {
        const phoneNumber = await client.pupPage.evaluate(() => {
            return window.Store?.Conn?.me?.user || null;
        });

        if (phoneNumber) {
            this.logger.info('✅ Phone extracted via fallback', { phoneNumber });
            return phoneNumber;
        }
    } catch (error) {
        this.logger.error('❌ Fallback extraction failed', { error });
    }

    return null;
}
```

### 4. Webhook Implementation

**File:** `utils/webhookNotifier.js`

```javascript
class WebhookNotifier {
    async notify(endpoint, payload) {
        const timestamp = Math.floor(Date.now() / 1000);
        const signature = this.generateHMAC(timestamp, payload);

        const response = await axios.post(
            `${process.env.LARAVEL_URL}${endpoint}`,
            payload,
            {
                headers: {
                    'Content-Type': 'application/json',
                    'X-HMAC-Signature': signature,
                    'X-Timestamp': timestamp,
                    'Connection': 'close'  // Don't keep-alive
                },
                timeout: 5000,  // 5 second timeout
                maxRedirects: 0
            }
        );

        return response.data;
    }

    generateHMAC(timestamp, payload) {
        const crypto = require('crypto');
        const message = timestamp + JSON.stringify(payload);
        return crypto
            .createHmac('sha256', process.env.HMAC_SECRET)
            .update(message)
            .digest('hex');
    }
}
```

### 5. Start Service

```bash
# Development
npm run dev

# Production
pm2 start ecosystem.config.js
pm2 save
```

---

## Laravel Backend Setup

### 1. Database Migration

**File:** `database/migrations/*_create_whatsapp_accounts_table.php`

```php
Schema::create('whatsapp_accounts', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
    $table->string('session_id')->unique();
    $table->string('phone_number', 50)->nullable();
    $table->enum('status', [
        'qr_scanning', 
        'authenticated', 
        'connected', 
        'disconnected', 
        'failed'
    ])->default('qr_scanning');
    $table->boolean('is_primary')->default(false);
    $table->boolean('is_active')->default(true);
    $table->text('qr_code')->nullable();
    $table->timestamp('last_connected_at')->nullable();
    $table->timestamp('last_activity_at')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();
    $table->softDeletes();

    // CRITICAL: Unique constraint
    $table->unique(
        ['phone_number', 'workspace_id', 'status'], 
        'unique_active_phone_workspace'
    );
});
```

### 2. HMAC Middleware

**File:** `app/Http/Middleware/VerifyWhatsAppHmac.php`

```php
public function handle(Request $request, Closure $next)
{
    $signature = $request->header('X-HMAC-Signature');
    $timestamp = $request->header('X-Timestamp');
    $payload = $request->getContent();

    // Validate headers
    if (!$signature || !$timestamp) {
        throw new HttpException(401, 'Missing security headers');
    }

    // Validate timestamp (prevent replay attacks)
    $requestTime = (int) $timestamp;
    $now = time();
    $maxAge = 300; // 5 minutes

    if (abs($now - $requestTime) > $maxAge) {
        throw new HttpException(401, 'Request expired');
    }

    // Verify signature
    $secret = config('whatsapp.node_api_secret');
    $expectedSignature = hash_hmac('sha256', $timestamp . $payload, $secret);

    if (!hash_equals($expectedSignature, $signature)) {
        throw new HttpException(401, 'Invalid signature');
    }

    return $next($request);
}
```

### 3. Webhook Controller

**File:** `app/Http/Controllers/Api/v1/WhatsApp/WebhookController.php`

```php
public function webhook(Request $request)
{
    $event = $request->input('event');
    $data = $request->input('data');

    Log::info('Webhook received', [
        'event' => $event,
        'session_id' => $data['session_id'] ?? null,
        'phone_number' => $data['phone_number'] ?? null
    ]);

    // CRITICAL: Process session_ready INLINE (synchronously)
    if ($event === 'session_ready') {
        try {
            $this->handleSessionReady($data);
            Log::info('✅ session_ready processed inline');
        } catch (\Exception $e) {
            Log::error('❌ session_ready failed', ['error' => $e->getMessage()]);
        }
        return response()->json(['status' => 'processed_inline']);
    }

    // Queue other events
    if (in_array($event, ['qr_code_generated', 'session_authenticated'])) {
        ProcessWhatsAppWebhookJob::dispatch($event, $data)
            ->onQueue('whatsapp-urgent');
        return response()->json(['status' => 'queued']);
    }

    return response()->json(['status' => 'received']);
}

private function handleSessionReady(array $data): void
{
    $workspaceId = $data['workspace_id'];
    $sessionId = $data['session_id'];
    $phoneNumber = $data['phone_number'] ?? null;

    // Validate phone number
    if (!$phoneNumber || $phoneNumber === 'null') {
        Log::error('Invalid phone number');
        return;
    }

    // Find session
    $session = WhatsAppAccount::where('session_id', $sessionId)
        ->where('workspace_id', $workspaceId)
        ->first();

    if (!$session) {
        Log::error('Session not found');
        return;
    }

    // Check if should be primary (first connected account)
    $hasPrimaryAccount = WhatsAppAccount::where('workspace_id', $workspaceId)
        ->where('is_primary', true)
        ->where('status', 'connected')
        ->where('id', '!=', $session->id)
        ->exists();

    $isPrimary = !$hasPrimaryAccount;

    // Cleanup duplicates (bypass unique constraint)
    DB::table('whatsapp_accounts')
        ->where('workspace_id', $workspaceId)
        ->where('phone_number', $phoneNumber)
        ->where('id', '!=', $session->id)
        ->whereIn('status', ['qr_scanning', 'authenticated', 'connected'])
        ->update([
            'status' => 'failed',
            'phone_number' => null,  // CRITICAL: NULL bypasses constraint
            'deleted_at' => now(),
            'updated_at' => now()
        ]);

    // Update session
    $session->update([
        'status' => 'connected',
        'phone_number' => $phoneNumber,
        'is_primary' => $isPrimary,
        'last_connected_at' => now(),
        'last_activity_at' => now(),
        'metadata' => array_merge($session->metadata ?? [], [
            'extraction_method' => $data['extraction_method'] ?? 'unknown',
            'platform' => $data['platform'] ?? 'unknown',
            'auto_set_primary' => $isPrimary
        ])
    ]);

    // Broadcast event
    $session->refresh();
    broadcast(new WhatsAppAccountStatusChangedEvent(
        $sessionId,
        'connected',
        $workspaceId,
        $phoneNumber,
        [
            'id' => $session->id,
            'uuid' => $session->uuid,
            'phone_number' => $phoneNumber,
            'is_primary' => $session->is_primary
        ]
    ));

    Log::info('✅ Session updated', [
        'phone_number' => $phoneNumber,
        'is_primary' => $isPrimary
    ]);
}
```

### 4. Routes

**File:** `routes/api.php`

```php
// WhatsApp webhooks (HMAC secured, no Bearer token)
Route::prefix('whatsapp')
    ->middleware(['whatsapp.hmac'])
    ->group(function () {
        Route::post('/webhooks/webjs', [
            WebhookController::class, 
            'webhook'
        ]);
    });
```

---

## Frontend Setup

### 1. Vue Component

**File:** `resources/js/Pages/User/Settings/WhatsAppAccounts.vue`

**Key Configuration:**

```javascript
const pollingInterval = ref(null);
const authWithoutPhoneAttempts = ref(0);
const maxAttemptsWithoutPhone = 6; // 18 seconds (6 × 3s)

function pollAccountStatus(sessionId, accountId) {
    let attempts = 0;
    const maxAttempts = 40; // 2 minutes total
    authWithoutPhoneAttempts.value = 0;

    pollingInterval.value = setInterval(async () => {
        attempts++;

        if (attempts > maxAttempts) {
            clearInterval(pollingInterval.value);
            return;
        }

        try {
            const response = await axios.get(
                `/api/whatsapp/accounts/${sessionId}/status`,
                { params: { workspace_id: props.workspaceId } }
            );

            const data = response.data;

            // Success: Phone number received
            if ((data.status === 'connected' || data.status === 'authenticated') 
                && data.phone_number) {
                
                updateAccountInList(data);
                closeAddModal();
                clearInterval(pollingInterval.value);
                return;
            }

            // Waiting: Authenticated but no phone yet
            if (data.status === 'authenticated' || data.status === 'connected') {
                authWithoutPhoneAttempts.value++;

                // Timeout after 18 seconds (6 attempts × 3s)
                if (authWithoutPhoneAttempts.value >= maxAttemptsWithoutPhone) {
                    console.warn('Phone extraction timeout');
                    updateAccountInList({
                        ...data,
                        phone_number: null,
                        name: 'Unknown Number'
                    });
                    closeAddModal();
                    clearInterval(pollingInterval.value);
                }
            }

        } catch (error) {
            if (error.response?.status !== 404) {
                console.error('Polling error:', error);
            }
        }
    }, 3000); // Poll every 3 seconds
}
```

### 2. WebSocket Integration

```javascript
// Subscribe to workspace channel
Echo.private(`workspace.${workspaceId}`)
    .listen('WhatsAppQRGeneratedEvent', (event) => {
        if (event.account_id === currentSessionId) {
            qrCode.value = event.qr_code_base64;
            countdown.value = event.expires_in_seconds;
        }
    })
    .listen('WhatsAppAccountStatusChangedEvent', (event) => {
        updateAccountInList(event);
    });
```

### 3. Build Assets

```bash
npm run build  # Production
# or
npm run dev   # Development with hot reload
```

---

## Testing

### 1. Unit Tests

```bash
# Node.js tests
cd whatsapp-service
npm test

# Laravel tests
php artisan test --filter=WhatsAppWebhookTest
```

### 2. Integration Testing

**Test QR Flow:**

```bash
# 1. Start all services
./start-dev.sh

# 2. Monitor logs
tail -f whatsapp-service/logs/whatsapp-service.log
tail -f storage/logs/laravel.log

# 3. Open browser and create account
open http://127.0.0.1:8000/settings/whatsapp-accounts

# 4. Check timing
grep "Phone number extracted" whatsapp-service/logs/*.log
grep "Session updated successfully" storage/logs/*.log
```

**Expected Timeline:**
- QR appears: 7-9 seconds after click
- Status → authenticated: Instant after scan
- Phone number appears: 3-4 seconds after authenticated
- Modal closes: Immediately after phone received

### 3. Performance Testing

```bash
# Check QR generation time
grep -E "QR.*generated|took.*ms" whatsapp-service/logs/*.log | tail -20

# Check phone extraction time
grep "Phone number extracted" whatsapp-service/logs/*.log | \
    grep -oE "[0-9]+ms" | \
    awk '{sum+=$1; count++} END {print "Average:", sum/count, "ms"}'
```

---

## Deployment

### 1. Production Checklist

- [ ] Update `.env` files with production values
- [ ] Set `NODE_ENV=production`
- [ ] Enable HTTPS for Laravel URL
- [ ] Configure firewall rules (ports 3001, 8080)
- [ ] Set up log rotation
- [ ] Configure PM2 auto-restart
- [ ] Set up monitoring (e.g., PM2 Plus)
- [ ] Enable Redis for queue (optional but recommended)

### 2. PM2 Deployment

```bash
# Install PM2 globally
npm install -g pm2

# Start services
cd whatsapp-service
pm2 start ecosystem.config.js --env production

# Save configuration
pm2 save

# Setup auto-restart on reboot
pm2 startup
```

**PM2 Configuration** (`ecosystem.config.js`):

```javascript
module.exports = {
    apps: [{
        name: 'whatsapp-service',
        script: 'server.js',
        instances: 1,
        exec_mode: 'fork',
        env_production: {
            NODE_ENV: 'production',
            PORT: 3001
        },
        error_file: './logs/pm2-error.log',
        out_file: './logs/pm2-out.log',
        log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
        max_memory_restart: '1G',
        watch: false,
        autorestart: true,
        max_restarts: 10,
        min_uptime: '10s'
    }]
};
```

### 3. Laravel Queue Worker

```bash
# Start queue worker (for async webhooks)
php artisan queue:work --queue=whatsapp-urgent,default --tries=3 --timeout=60

# For production, use supervisor
sudo nano /etc/supervisor/conf.d/laravel-worker.conf
```

**Supervisor Config:**

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/worker.log
```

### 4. Reverb WebSocket Server

```bash
# Start Reverb
php artisan reverb:start --host=0.0.0.0 --port=8080

# For production, use supervisor
sudo nano /etc/supervisor/conf.d/reverb.conf
```

---

## Monitoring

### 1. Health Checks

```bash
# Check service status
pm2 status
php artisan queue:monitor

# Check database
php artisan tinker --execute="
  echo 'Connected: ' . \App\Models\WhatsAppAccount::where('status', 'connected')->count();
  echo 'QR Scanning: ' . \App\Models\WhatsAppAccount::where('status', 'qr_scanning')->count();
"
```

### 2. Log Monitoring

```bash
# Real-time logs
pm2 logs whatsapp-service
tail -f storage/logs/laravel.log

# Error tracking
grep -i "error\|fail" whatsapp-service/logs/*.log | tail -50
grep -i "SQLSTATE\|Exception" storage/logs/laravel.log | tail -50
```

### 3. Performance Metrics

```bash
# QR generation stats
grep "QR.*generated" whatsapp-service/logs/*.log | \
    wc -l  # Total QR codes generated today

# Success rate
grep "session_ready" whatsapp-service/logs/*.log | wc -l
grep "Phone number extracted successfully" whatsapp-service/logs/*.log | wc -l
```

---

## Troubleshooting

See [03-troubleshooting.md](./03-troubleshooting.md) for detailed troubleshooting guide.

---

**Document Version:** 2.0  
**Last Updated:** November 22, 2025  
**Maintainer:** Development Team
