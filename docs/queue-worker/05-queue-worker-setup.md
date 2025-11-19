# Queue Worker Setup - Campaign Processing

## 📋 Overview

Queue worker adalah service yang memproses background jobs untuk campaign WhatsApp. **Queue worker SUDAH AUTO-START** ketika menjalankan `./start-dev.sh`.

## 🚀 Auto-Start dengan start-dev.sh

### ✅ Yang Otomatis Berjalan

Ketika Anda menjalankan `./start-dev.sh`, service berikut akan otomatis start:

1. **Laravel Backend** (Port 8000)
2. **Laravel Reverb** - Broadcasting (Port 8080)
3. **WhatsApp Node.js Service** (Port 3001)
4. **Queue Worker** ✅ - **OTOMATIS START!**

```bash
./start-dev.sh
```

Output:
```
🚀 Starting Blazz Development Environment...
==============================================
1. Starting Laravel Backend (Port 8000)...
2. Starting Laravel Reverb (Port 8080)...
3. Starting WhatsApp Node.js Service (Port 3001)...
4. Starting Queue Worker...                          ← OTOMATIS!
```

### Queue Configuration

Queue worker yang di-start otomatis menggunakan konfigurasi:
- **Queues**: `whatsapp-urgent`, `whatsapp-high`, `whatsapp-normal`, `whatsapp-campaign`
- **Max Tries**: 3 attempts
- **Timeout**: 300 seconds (5 menit)
- **Log File**: `logs/queue.log`

## 🔍 Cara Cek Status Queue Worker

### Option 1: Via Process List
```bash
ps aux | grep "queue:work" | grep -v grep
```

Output jika running:
```
user  21768  0.0  0.2  php artisan queue:work --queue=whatsapp-urgent...
```

### Option 2: Via Management Script
```bash
./manage-queue-worker.sh status
```

Output:
```
✅ Queue worker is running
PID: 21768
```

## 🎯 Kapan Perlu Start Manual?

Queue worker **TIDAK PERLU** di-start manual jika:
- ✅ Anda sudah menjalankan `./start-dev.sh`
- ✅ Service belum di-stop dengan `./stop-dev.sh`

Queue worker **PERLU** di-start manual jika:
- ❌ Queue worker crash atau error
- ❌ Anda restart Laravel tapi tidak pakai `start-dev.sh`
- ❌ Anda melakukan perubahan code dan perlu reload worker

## 🔧 Management Commands

### Using Management Script (Recommended)

```bash
# Check status
./manage-queue-worker.sh status

# Restart queue worker
./manage-queue-worker.sh restart

# View logs (last 50 lines)
./manage-queue-worker.sh log

# Monitor logs in real-time
./manage-queue-worker.sh monitor

# Stop queue worker
./manage-queue-worker.sh stop

# Start queue worker
./manage-queue-worker.sh start
```

### Using Laravel Artisan

```bash
# Restart all queue workers gracefully
php artisan queue:restart

# Start queue worker manually
php artisan queue:work \
  --queue=whatsapp-urgent,whatsapp-high,whatsapp-normal,whatsapp-campaign \
  --tries=3 \
  --timeout=300

# Monitor queue jobs
php artisan queue:monitor

# Clear failed jobs
php artisan queue:flush
```

## 📊 Monitoring Queue

### Check Jobs in Queue (Database)
```sql
-- Check pending jobs
SELECT COUNT(*) as pending_jobs 
FROM jobs 
WHERE queue = 'whatsapp-campaign';

-- Check failed jobs
SELECT * FROM failed_jobs 
ORDER BY failed_at DESC 
LIMIT 10;
```

### View Logs
```bash
# Queue worker logs
tail -f logs/queue.log

# Laravel application logs
tail -f storage/logs/laravel.log | grep "campaign\|SendCampaignJob"

# All logs
tail -f logs/*.log
```

## 🔄 Restart Queue Worker (Kapan Diperlukan?)

### Harus Restart Ketika:

1. **Code Changes**
   - ✅ Update Job files (`SendCampaignJob.php`)
   - ✅ Update Model files (`Campaign.php`, `Contact.php`)
   - ✅ Update Service files (`CampaignService.php`)
   - ✅ Update configuration

2. **Queue Worker Issues**
   - ❌ Worker stuck atau hanging
   - ❌ Jobs tidak diproses
   - ❌ Memory leak

### Cara Restart:

```bash
# Option 1: Via start-dev.sh (restart all services)
./stop-dev.sh
./start-dev.sh

# Option 2: Via management script (restart queue only)
./manage-queue-worker.sh restart

# Option 3: Via Laravel artisan (graceful restart)
php artisan queue:restart
```

## 🚨 Troubleshooting

### Problem: Queue Worker Tidak Berjalan

**Check:**
```bash
./manage-queue-worker.sh status
```

**Solution:**
```bash
./manage-queue-worker.sh start
```

### Problem: Jobs Tidak Diproses

**Check jobs in database:**
```sql
SELECT COUNT(*) FROM jobs;
```

**Check failed jobs:**
```sql
SELECT * FROM failed_jobs ORDER BY failed_at DESC LIMIT 5;
```

**Solution:**
```bash
# Restart queue worker
php artisan queue:restart

# Clear stuck jobs (HATI-HATI!)
# php artisan queue:flush
```

### Problem: Queue Worker Memory Leak

**Symptoms:**
- Worker consuming too much memory
- Worker becomes slow over time

**Solution:**
Add memory limit to queue worker:
```bash
php artisan queue:work \
  --queue=whatsapp-urgent,whatsapp-high,whatsapp-normal,whatsapp-campaign \
  --tries=3 \
  --timeout=300 \
  --memory=512  # Restart when memory reaches 512MB
```

Update di `start-dev.sh`:
```bash
nohup php artisan queue:work \
  --queue=whatsapp-urgent,whatsapp-high,whatsapp-normal,whatsapp-campaign \
  --tries=3 \
  --timeout=300 \
  --memory=512 \
  > logs/queue.log 2>&1 &
```

## 📝 Production Setup (Supervisor)

Untuk production, gunakan Supervisor untuk auto-restart queue worker:

### Install Supervisor
```bash
# macOS
brew install supervisor

# Ubuntu/Debian
sudo apt-get install supervisor
```

### Configuration File
Create `/etc/supervisor/conf.d/blazz-queue.conf`:

```ini
[program:blazz-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /Applications/MAMP/htdocs/blazz/artisan queue:work --queue=whatsapp-urgent,whatsapp-high,whatsapp-normal,whatsapp-campaign --tries=3 --timeout=300 --memory=512
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/Applications/MAMP/htdocs/blazz/storage/logs/queue-worker.log
stopwaitsecs=3600
```

### Start Supervisor
```bash
# macOS
brew services start supervisor

# Ubuntu/Debian
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start blazz-queue:*
```

## 🎯 Best Practices

### Development
1. ✅ Use `./start-dev.sh` untuk start all services
2. ✅ Monitor logs dengan `tail -f logs/queue.log`
3. ✅ Restart worker setelah code changes
4. ✅ Check status dengan `./manage-queue-worker.sh status`

### Production
1. ✅ Use Supervisor untuk auto-restart
2. ✅ Setup log rotation
3. ✅ Monitor queue size dan processing time
4. ✅ Setup alerts untuk failed jobs
5. ✅ Use multiple queue workers untuk high load

## 📈 Queue Priorities

Queue diproses berdasarkan priority (dari tertinggi ke terendah):

1. **whatsapp-urgent** - Critical messages (highest priority)
2. **whatsapp-high** - Important messages
3. **whatsapp-normal** - Regular messages
4. **whatsapp-campaign** - Campaign broadcast (lowest priority)

Campaign dengan "Skip scheduling & send immediately" masuk ke queue `whatsapp-campaign` dan akan diproses setelah queue dengan priority lebih tinggi selesai.

## ✅ Summary

### Jawaban Singkat:
**Queue worker SUDAH OTOMATIS START** ketika menjalankan `./start-dev.sh`. Anda **TIDAK PERLU** start manual kecuali:
- Worker crash
- Melakukan code changes
- Worker perlu di-restart

### Quick Commands:
```bash
# Start all services (including queue worker)
./start-dev.sh

# Check if queue worker is running
./manage-queue-worker.sh status

# Restart queue worker only
./manage-queue-worker.sh restart

# Monitor queue logs
./manage-queue-worker.sh monitor

# Stop all services
./stop-dev.sh
```

---

**Created**: 2025-11-19
**Last Updated**: 2025-11-19
**Status**: ✅ Documented & Tested
