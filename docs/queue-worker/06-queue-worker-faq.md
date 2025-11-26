# 🚀 Quick Start Guide - Campaign & Queue Worker

## ❓ Apakah Queue Worker Otomatis Berjalan?

### ✅ **JAWABAN: YA, OTOMATIS!**

Queue worker **SUDAH AUTO-START** ketika Anda menjalankan:

```bash
./start-dev.sh
```

## 🎯 Flow Lengkap

```
┌─────────────────────────────────────────────────────────┐
│  User menjalankan: ./start-dev.sh                       │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│  AUTO-START Services:                                    │
│  1. Laravel Backend (Port 8000)       ✅                │
│  2. Laravel Reverb (Port 8080)        ✅                │
│  3. WhatsApp Service (Port 3001)      ✅                │
│  4. Queue Worker (Background)         ✅ ← OTOMATIS!   │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│  User creates Campaign di UI                             │
│  └─ Centang "Skip scheduling & send immediately"        │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│  Job di-dispatch ke queue: whatsapp-campaign            │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│  Queue Worker (yang sudah jalan) langsung proses        │
│  └─ Kirim pesan via WhatsApp Web.js                    │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────┐
│  ✅ Pesan terkirim!                                     │
│  ✅ Campaign status: completed                          │
└─────────────────────────────────────────────────────────┘
```

## 📋 Checklist - Apakah Queue Worker Berjalan?

### ✅ Test Sederhana

```bash
# 1. Cek apakah queue worker running
ps aux | grep "queue:work" | grep -v grep

# Jika ada output seperti ini, berarti RUNNING:
# user  21768  php artisan queue:work --queue=whatsapp-urgent...
```

### ✅ Test dengan Script

```bash
./manage-queue-worker.sh status

# Output jika running:
# ✅ Queue worker is running
# PID: 21768
```

## 🔄 Kapan TIDAK PERLU Start Manual?

**TIDAK PERLU** start manual queue worker jika:

✅ Sudah menjalankan `./start-dev.sh`  
✅ Service belum di-stop dengan `./stop-dev.sh`  
✅ Queue worker belum crash atau error  

## ⚠️ Kapan PERLU Start Manual?

**PERLU** start manual queue worker jika:

❌ Queue worker crash (cek dengan `ps aux | grep queue:work`)  
❌ Melakukan perubahan code di Job/Model/Service  
❌ Queue worker tidak memproses jobs (stuck)  

### Cara Start Manual:

```bash
# Option 1: Restart semua service
./stop-dev.sh
./start-dev.sh

# Option 2: Restart queue worker saja
./manage-queue-worker.sh restart

# Option 3: Via Laravel artisan
php artisan queue:restart
```

## 🎬 Tutorial Lengkap

### Scenario 1: Fresh Start (Pertama Kali)

```bash
# 1. Start all services
./start-dev.sh

# Output:
# 🚀 Starting Blazz Development Environment...
# ==============================================
# 1. Starting Laravel Backend (Port 8000)...
# 2. Starting Laravel Reverb (Port 8080)...
# 3. Starting WhatsApp Node.js Service (Port 3001)...
# 4. Starting Queue Worker...              ← AUTO START!
# ...
# 🎉 All services are running successfully!

# 2. Verify queue worker
./manage-queue-worker.sh status

# Output:
# ✅ Queue worker is running

# 3. Create campaign via UI
# ✅ Campaign langsung diproses!
```

### Scenario 2: Setelah Code Changes

```bash
# 1. Edit code (misalnya SendCampaignJob.php)
vim app/Jobs/SendCampaignJob.php

# 2. Restart queue worker untuk reload code
./manage-queue-worker.sh restart

# Output:
# Stopping queue worker...
# Queue worker stopped
# Starting queue worker...
# Queue worker started with PID: 22341

# 3. Test campaign lagi
# ✅ Campaign dengan code baru!
```

### Scenario 3: Queue Worker Stuck

```bash
# 1. Check status
./manage-queue-worker.sh status

# Output:
# ❌ Queue worker is NOT running

# 2. Start queue worker
./manage-queue-worker.sh start

# Output:
# Queue worker started with PID: 22567

# 3. Verify
./manage-queue-worker.sh status

# Output:
# ✅ Queue worker is running
```

## 📊 Monitoring

### Monitor Logs Real-time

```bash
# Queue worker logs
./manage-queue-worker.sh monitor

# Laravel application logs (filter campaign)
tail -f storage/logs/laravel.log | grep -i campaign

# All logs
tail -f logs/*.log
```

### Check Database

```sql
-- Check pending jobs
SELECT COUNT(*) FROM jobs WHERE queue = 'whatsapp-campaign';

-- Check campaign status
SELECT id, name, status, messages_sent 
FROM campaigns 
ORDER BY created_at DESC 
LIMIT 10;

-- Check campaign logs
SELECT campaign_id, COUNT(*) as total, status 
FROM campaign_logs 
GROUP BY campaign_id, status;
```

## 🎯 Summary

| Question | Answer |
|----------|--------|
| Apakah queue worker otomatis start? | ✅ **YA**, dengan `./start-dev.sh` |
| Perlu start manual? | ❌ **TIDAK**, kecuali crash atau code changes |
| Bisa dijalankan langsung dengan start-dev.sh? | ✅ **YA**, sudah include queue worker |
| Kapan perlu restart? | ⚠️ Setelah **code changes** atau **worker stuck** |

## 🔗 Related Commands

```bash
# Start all services (termasuk queue worker)
./start-dev.sh

# Stop all services
./stop-dev.sh

# Manage queue worker only
./manage-queue-worker.sh {start|stop|restart|status|log|monitor}

# Laravel queue commands
php artisan queue:work          # Start worker
php artisan queue:restart       # Restart all workers
php artisan queue:monitor       # Monitor queue
php artisan queue:flush         # Clear all jobs
```

---

**🎉 Kesimpulan:**  
Queue worker **SUDAH OTOMATIS BERJALAN** ketika Anda `./start-dev.sh`.  
Campaign dengan "Skip scheduling & send immediately" **LANGSUNG DIPROSES**  tanpa perlu start manual!

**📝 Created**: 2025-11-19  
**✅ Status**: Tested & Working
