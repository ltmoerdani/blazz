# 📅 Laravel Scheduler Research & Implementation Analysis

> **Research Date:** November 19, 2025  
> **Laravel Version:** 12.x  
> **Project:** Blazz WhatsApp Campaign System  
> **Branch:** staging-broadcast

---

## 🎯 Research Objective

Meneliti kelayakan Laravel Task Scheduling sebagai pengganti cronjob tradisional untuk scheduled campaigns di project Blazz, termasuk:
- Konsep Laravel Scheduler
- Current implementation analysis
- Gap analysis
- Rekomendasi implementasi

---

## 📚 Laravel Scheduler Overview

### Konsep Dasar

Laravel Scheduler adalah fitur built-in Laravel yang memungkinkan scheduling tasks langsung di dalam aplikasi, menggantikan banyak cron entries dengan **SATU cron entry saja**.

### Keuntungan vs Traditional Cronjob

| Aspek | Traditional Cronjob | Laravel Scheduler |
|-------|-------------------|-------------------|
| **Configuration** | Di crontab (outside code) | Di code (version controlled) |
| **Maintenance** | SSH ke server untuk edit | Edit via Git/IDE |
| **Testing** | Sulit test locally | `php artisan schedule:work` |
| **Visibility** | Hidden di crontab | Clear di code |
| **Setup** | 1 entry per task | 1 entry untuk semua |
| **Timezone Support** | Complex | Built-in |
| **Overlap Prevention** | Manual locking | `->withoutOverlapping()` |
| **Sub-minute Tasks** | Not supported | `->everySecond()` supported |

### Cron Entry Required

**Hanya 1 baris di crontab:**
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🔍 Current Implementation Analysis

### ✅ Sudah Terimplementasi dengan Baik!

Project ini **SUDAH MENGGUNAKAN** Laravel Scheduler dengan benar!

#### File: `app/Console/Kernel.php`

```php
protected function schedule(Schedule $schedule): void
{
    // Campaign scheduling jobs - SUDAH ADA! ✅
    $schedule->job(new CreateCampaignLogsJob(), 'campaign-logs')
        ->everyMinute()
        ->withoutOverlapping();

    $schedule->job(new ProcessCampaignMessagesJob(), 'campaign-messages')
        ->everyMinute()
        ->withoutOverlapping();
    
    // Queue health monitoring - SUDAH ADA! ✅
    $schedule->command('queue:restart')
        ->hourly()
        ->evenInMaintenanceMode();
    
    // Cleanup tasks - SUDAH ADA! ✅
    $schedule->command('queue:prune-failed --hours=24')
        ->daily()
        ->evenInMaintenanceMode();

    $schedule->command('queue:prune-batches --hours=48 --unfinished=72')
        ->daily();
    
    $schedule->command('model:prune', [
        '--model' => [CampaignLog::class],
        '--hours' => 72,
    ])->daily();

    // Queue monitoring - SUDAH ADA! ✅
    $schedule->command('monitor:queue-size')
        ->everyFiveMinutes();
}
```

**Kesimpulan:** ✅ **Implementation SUDAH SEMPURNA!**

---

## 🏗️ Architecture Analysis

### Campaign Scheduling Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    LARAVEL SCHEDULER                             │
│                    (runs every minute)                           │
└──────────────────────────┬──────────────────────────────────────┘
                           │
           ┌───────────────┴───────────────┐
           │                               │
           ▼                               ▼
┌────────────────────────┐      ┌─────────────────────────┐
│ CreateCampaignLogsJob  │      │ ProcessCampaignMessages │
│                        │      │         Job             │
│ - Check scheduled      │      │                         │
│   campaigns            │      │ - Process pending logs  │
│ - Create campaign_logs │      │ - Dispatch send jobs    │
│ - Update status to     │      │ - Mark completed        │
│   'ongoing'            │      │   campaigns             │
└────────────────────────┘      └─────────────────────────┘
           │                               │
           └───────────────┬───────────────┘
                           ▼
                ┌────────────────────┐
                │   QUEUE WORKERS    │
                │                    │
                │ - SendCampaignJob  │
                │ - ProcessSingle    │
                │   CampaignLogJob   │
                └────────────────────┘
```

### Job Responsibilities

#### 1. CreateCampaignLogsJob
- **Frequency:** Every minute
- **Purpose:** Check `scheduled_at` time dan create campaign logs
- **Query:**
  ```php
  Campaign::where('status', 'scheduled')
      ->with('workspace')
      ->whereNull('deleted_at')
      ->cursor();
  ```
- **Logic:**
  - Parse `scheduled_at` dengan workspace timezone
  - Compare dengan current time
  - Create campaign logs untuk contacts
  - Update campaign status → `ongoing`

#### 2. ProcessCampaignMessagesJob
- **Frequency:** Every minute
- **Purpose:** Process pending/failed campaign logs
- **Query:**
  ```php
  CampaignLog::with(['campaign.workspace', 'contact'])
      ->whereIn('status', ['pending', 'failed'])
      ->whereHas('campaign', function ($query) {
          $query->where('status', 'ongoing');
      })
      ->chunk(1000, function ($logs) { ... });
  ```
- **Logic:**
  - Batch process logs (1000 at a time)
  - Dispatch ProcessSingleCampaignLogJob
  - Handle retry logic
  - Mark campaigns as completed

---

## 🔧 Technical Implementation Details

### Timezone Support ✅

**Sudah diimplementasi dengan sempurna:**

```php
protected function getWorkspaceTimezone($workspace)
{
    if (!$workspace) return 'UTC';

    $metadata = $workspace->metadata;
    $metadata = isset($metadata) ? json_decode($metadata, true) : null;

    return $metadata['timezone'] ?? 'UTC';
}

// Usage:
$timezone = $this->getWorkspaceTimezone($campaign->workspace);
$scheduledAt = Carbon::parse($campaign->scheduled_at, 'UTC')->timezone($timezone);

if ($scheduledAt->lte(Carbon::now($timezone))) {
    $this->processCampaign($campaign);
}
```

**Benefit:**
- Multi-timezone support
- Accurate scheduling per workspace
- No timezone confusion

### Overlap Prevention ✅

**Sudah diimplementasi:**

```php
$schedule->job(new CreateCampaignLogsJob())
    ->everyMinute()
    ->withoutOverlapping(); // ← Prevent duplicate runs
```

**Benefit:**
- Prevent duplicate campaign processing
- Safe if scheduler runs slowly
- No race conditions

### Performance Optimization ✅

**Sudah diimplementasi:**

```php
// Cursor untuk memory efficiency
$campaigns = Campaign::where('status', 'scheduled')
    ->cursor(); // ← Memory efficient

// Chunking untuk batch processing
CampaignLog::whereIn('status', ['pending', 'failed'])
    ->chunk(1000, function ($logs) { // ← Process in batches
        // ...
    });
```

**Benefit:**
- Low memory footprint
- Handle large datasets
- No OOM errors

---

## 🚨 Current Gap: NO CRON ENTRY!

### Problem

Laravel Scheduler **SUDAH DIIMPLEMENTASI** dengan sempurna di code, tapi:

```bash
crontab -l
# Output: No crontab found for current user
```

**❌ Scheduler TIDAK BERJALAN** karena tidak ada cron entry!

### Impact

Campaigns dengan `scheduled_at` di masa depan **TIDAK AKAN DIPROSES** karena:
- `CreateCampaignLogsJob` tidak dijalankan setiap menit
- `ProcessCampaignMessagesJob` tidak dijalankan setiap menit
- Scheduled campaigns stuck di status `scheduled`

### Workaround Saat Ini

Development menggunakan **manual trigger** atau queue worker, tapi scheduled campaigns tidak otomatis diproses pada `scheduled_at` time.

---

## ✅ Solution: Setup Cron Entry

### Development Environment

**Option 1: Using `schedule:work` (Recommended for Dev)**

```bash
# Run in terminal (stays in foreground)
php artisan schedule:work
```

**Benefits:**
- ✅ Instant feedback
- ✅ Easy debugging
- ✅ See logs in real-time
- ✅ No crontab needed

**Add to `start-dev.sh`:**

```bash
# Add after queue worker
echo -e "${BLUE}5. Starting Laravel Scheduler...${NC}"
nohup php artisan schedule:work > logs/scheduler.log 2>&1 &
SCHEDULER_PID=$!
echo -e "${GREEN}   ✓ Scheduler started (PID: $SCHEDULER_PID)${NC}"
```

**Option 2: Using System Cron**

```bash
# Edit crontab
crontab -e

# Add this line:
* * * * * cd /Applications/MAMP/htdocs/blazz && php artisan schedule:run >> logs/scheduler-cron.log 2>&1
```

### Production Environment

**Using System Cron (Recommended):**

```bash
# As application user
crontab -e

# Add:
* * * * * cd /var/www/blazz && php artisan schedule:run >> /dev/null 2>&1
```

**Or using Supervisor + schedule:work:**

```ini
[program:blazz-scheduler]
process_name=%(program_name)s
command=php /var/www/blazz/artisan schedule:work
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/blazz/storage/logs/scheduler.log
```

---

## 📊 Comparison: Schedule Methods

### Method 1: `schedule:run` via Cron (Traditional)

```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

**Pros:**
- ✅ Standard Laravel way
- ✅ Battle-tested
- ✅ Low resource usage
- ✅ Works on all servers

**Cons:**
- ❌ Requires crontab access
- ❌ One more system dependency

### Method 2: `schedule:work` (Modern)

```bash
php artisan schedule:work
```

**Pros:**
- ✅ No crontab needed
- ✅ Real-time logs
- ✅ Easier debugging
- ✅ Perfect for containers/Docker

**Cons:**
- ❌ Must stay running (process management needed)
- ❌ Slightly higher resource usage

### Method 3: Hybrid (Recommended for This Project)

**Development:**
```bash
./start-dev.sh  # Includes schedule:work
```

**Production:**
```bash
# Cron entry
* * * * * cd /var/www/blazz && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🎯 Recommended Implementation

### 1. Update `start-dev.sh`

```bash
#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}🚀 Starting Blazz Development Environment...${NC}"
echo "=============================================="

# 1. Start Laravel Backend
echo -e "${BLUE}1. Starting Laravel Backend (Port 8000)...${NC}"
nohup php artisan serve --host=127.0.0.1 --port=8000 > logs/laravel.log 2>&1 &
LARAVEL_PID=$!
echo -e "${GREEN}   ✓ Laravel started (PID: $LARAVEL_PID)${NC}"

# 2. Start Laravel Reverb
echo -e "${BLUE}2. Starting Laravel Reverb (Port 8080)...${NC}"
nohup php artisan reverb:start --host=127.0.0.1 --port=8080 > logs/reverb.log 2>&1 &
REVERB_PID=$!
echo -e "${GREEN}   ✓ Reverb started (PID: $REVERB_PID)${NC}"

# 3. Start WhatsApp Service
echo -e "${BLUE}3. Starting WhatsApp Service (Port 3001)...${NC}"
cd whatsapp-service
nohup node index.js > ../logs/whatsapp-service.log 2>&1 &
WHATSAPP_PID=$!
cd ..
echo -e "${GREEN}   ✓ WhatsApp Service started (PID: $WHATSAPP_PID)${NC}"

# 4. Start Queue Worker
echo -e "${BLUE}4. Starting Queue Worker...${NC}"
nohup php artisan queue:work \
  --queue=whatsapp-urgent,whatsapp-high,whatsapp-normal,whatsapp-campaign \
  --tries=3 --timeout=300 > logs/queue.log 2>&1 &
QUEUE_PID=$!
echo -e "${GREEN}   ✓ Queue Worker started (PID: $QUEUE_PID)${NC}"

# 5. Start Laravel Scheduler ← NEW!
echo -e "${BLUE}5. Starting Laravel Scheduler...${NC}"
nohup php artisan schedule:work > logs/scheduler.log 2>&1 &
SCHEDULER_PID=$!
echo -e "${GREEN}   ✓ Scheduler started (PID: $SCHEDULER_PID)${NC}"

echo ""
echo -e "${GREEN}✅ All services started successfully!${NC}"
echo ""
echo -e "${YELLOW}📋 Service URLs:${NC}"
echo "   - Laravel Backend:    http://127.0.0.1:8000"
echo "   - Laravel Reverb:     ws://127.0.0.1:8080"
echo "   - WhatsApp Service:   http://127.0.0.1:3001"
echo ""
echo -e "${YELLOW}📊 Process IDs:${NC}"
echo "   - Laravel:            $LARAVEL_PID"
echo "   - Reverb:             $REVERB_PID"
echo "   - WhatsApp Service:   $WHATSAPP_PID"
echo "   - Queue Worker:       $QUEUE_PID"
echo "   - Scheduler:          $SCHEDULER_PID"
echo ""
echo -e "${YELLOW}📝 Log Files:${NC}"
echo "   - Laravel:            logs/laravel.log"
echo "   - Reverb:             logs/reverb.log"
echo "   - WhatsApp:           logs/whatsapp-service.log"
echo "   - Queue:              logs/queue.log"
echo "   - Scheduler:          logs/scheduler.log"
echo ""
echo -e "${BLUE}💡 To stop all services, run: ./stop-dev.sh${NC}"
```

### 2. Update `stop-dev.sh`

```bash
#!/bin/bash

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

echo -e "${RED}🛑 Stopping Blazz Development Environment...${NC}"

# Stop Laravel
pkill -f "php artisan serve"
echo -e "${GREEN}   ✓ Laravel stopped${NC}"

# Stop Reverb
pkill -f "php artisan reverb:start"
echo -e "${GREEN}   ✓ Reverb stopped${NC}"

# Stop WhatsApp Service
pkill -f "node index.js"
echo -e "${GREEN}   ✓ WhatsApp Service stopped${NC}"

# Stop Queue Worker
pkill -f "php artisan queue:work"
echo -e "${GREEN}   ✓ Queue Worker stopped${NC}"

# Stop Scheduler ← NEW!
pkill -f "php artisan schedule:work"
echo -e "${GREEN}   ✓ Scheduler stopped${NC}"

echo -e "${GREEN}✅ All services stopped!${NC}"
```

### 3. Create Scheduler Management Script

```bash
#!/bin/bash
# File: docs/queue-worker/08-manage-scheduler.sh

case "$1" in
    start)
        nohup php artisan schedule:work > logs/scheduler.log 2>&1 &
        echo "✅ Scheduler started"
        ;;
    stop)
        pkill -f "php artisan schedule:work"
        echo "✅ Scheduler stopped"
        ;;
    restart)
        pkill -f "php artisan schedule:work"
        sleep 2
        nohup php artisan schedule:work > logs/scheduler.log 2>&1 &
        echo "✅ Scheduler restarted"
        ;;
    status)
        if pgrep -f "php artisan schedule:work" > /dev/null; then
            echo "✅ Scheduler is running"
            ps aux | grep "schedule:work" | grep -v grep
        else
            echo "❌ Scheduler is not running"
        fi
        ;;
    log)
        tail -50 logs/scheduler.log
        ;;
    monitor)
        tail -f logs/scheduler.log
        ;;
    test)
        php artisan schedule:list
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status|log|monitor|test}"
        exit 1
        ;;
esac
```

### 4. Production Setup

**Using Supervisor (Recommended):**

```ini
[program:blazz-scheduler]
process_name=%(program_name)s
command=php /var/www/blazz/artisan schedule:work
directory=/var/www/blazz
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/blazz/storage/logs/scheduler.log
stopwaitsecs=3600

[program:blazz-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/blazz/artisan queue:work --queue=whatsapp-urgent,whatsapp-high,whatsapp-normal,whatsapp-campaign --tries=3 --timeout=300
directory=/var/www/blazz
autostart=true
autorestart=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/var/www/blazz/storage/logs/queue.log
stopwaitsecs=3600
```

---

## 🧪 Testing

### Test 1: Check Scheduled Tasks

```bash
php artisan schedule:list
```

**Expected output:**
```
  0 * * * * *  App\Jobs\CreateCampaignLogsJob ............ Next Due: 1 minute from now
  0 * * * * *  App\Jobs\ProcessCampaignMessagesJob ...... Next Due: 1 minute from now
  0 0 * * * *  queue:restart ............................... Next Due: 23 hours from now
```

### Test 2: Run Scheduler Manually

```bash
php artisan schedule:run
```

**Expected output:**
```
Running scheduled command: App\Jobs\CreateCampaignLogsJob
Running scheduled command: App\Jobs\ProcessCampaignMessagesJob
```

### Test 3: Monitor Scheduler Logs

```bash
tail -f logs/scheduler.log
```

**Expected logs:**
```
[2025-11-19 10:00:00] Running scheduled command: App\Jobs\CreateCampaignLogsJob
[2025-11-19 10:00:00] Running scheduled command: App\Jobs\ProcessCampaignMessagesJob
[2025-11-19 10:01:00] Running scheduled command: App\Jobs\CreateCampaignLogsJob
[2025-11-19 10:01:00] Running scheduled command: App\Jobs\ProcessCampaignMessagesJob
```

### Test 4: Create Scheduled Campaign

```bash
# Via UI or API
POST /api/campaigns
{
  "name": "Test Scheduled Campaign",
  "scheduled_at": "2025-11-19 15:30:00",
  "skip_schedule": false
}
```

**Verify:**
```bash
# Check database
mysql -u root -proot blazz -e "SELECT id, name, status, scheduled_at FROM campaigns ORDER BY id DESC LIMIT 1;"

# Wait until scheduled_at time
# Then check logs
tail -f logs/scheduler.log

# Should see:
# [timestamp] CreateCampaignLogsJob processing campaign ID: X
```

---

## 📝 Implementation Checklist

### Development Setup

- [ ] Update `start-dev.sh` to include scheduler
- [ ] Update `stop-dev.sh` to stop scheduler
- [ ] Create `docs/queue-worker/08-manage-scheduler.sh`
- [ ] Test `php artisan schedule:list`
- [ ] Test `php artisan schedule:run`
- [ ] Test scheduled campaign creation
- [ ] Monitor logs to verify scheduler runs every minute

### Production Setup

- [ ] Choose deployment method (cron vs supervisor)
- [ ] Setup cron entry OR supervisor config
- [ ] Test scheduler on production
- [ ] Setup monitoring/alerting for scheduler
- [ ] Document in deployment guide

### Documentation

- [ ] Update README with scheduler info
- [ ] Update deployment guide
- [ ] Create troubleshooting guide
- [ ] Update architecture diagrams

---

## 🎯 Summary & Recommendations

### Current State: ✅ EXCELLENT IMPLEMENTATION

**Strengths:**
1. ✅ Laravel Scheduler properly configured in `Kernel.php`
2. ✅ Jobs well-structured and efficient
3. ✅ Timezone support implemented
4. ✅ Overlap prevention configured
5. ✅ Performance optimizations in place
6. ✅ Retry logic implemented
7. ✅ Cleanup tasks scheduled

**Gap:**
- ❌ **Scheduler not running** (no cron entry / no `schedule:work`)

### Recommended Actions

#### Immediate (Priority: HIGH)

1. **Add scheduler to `start-dev.sh`**
   - Enables automatic campaign scheduling in development
   - No manual intervention needed

2. **Test scheduled campaigns**
   - Create campaign with future `scheduled_at`
   - Verify auto-processing at scheduled time

#### Short-term (Priority: MEDIUM)

3. **Setup production scheduler**
   - Use Supervisor for reliability
   - Setup monitoring/alerting

4. **Update documentation**
   - Document scheduler setup
   - Add to deployment guide

#### Long-term (Priority: LOW)

5. **Consider enhancements**
   - Sub-minute scheduling (if needed)
   - More granular frequency options
   - Campaign throttling

### Final Verdict

**Question:** "Apakah Laravel Scheduler bisa diterapkan ke project ini?"

**Answer:** ✅ **SUDAH DITERAPKAN!** Tinggal dijalankan saja!

**Implementation Status:**
- Code: ✅ 100% Complete
- Testing: ⚠️ Needs scheduler running
- Production: ❌ Not deployed yet

**Next Steps:**
1. Add scheduler to `start-dev.sh` 
2. Test scheduled campaigns
3. Deploy to production with Supervisor

---

**📌 Conclusion:**

Laravel Scheduler adalah solusi **SEMPURNA** untuk project ini dan **SUDAH DIIMPLEMENTASI** dengan baik. Tidak perlu perubahan code. **Hanya perlu menjalankan scheduler** dengan salah satu metode:
- Development: `schedule:work` via `start-dev.sh`
- Production: Cron entry atau Supervisor

**Benefit vs Traditional Cronjob:**
- ✅ Version controlled
- ✅ Easy testing
- ✅ Built-in features (timezone, overlap prevention)
- ✅ Single cron entry
- ✅ Better visibility

---

**Research by:** AI Assistant  
**Date:** November 19, 2025  
**Status:** ✅ Research Complete - Ready for Implementation
