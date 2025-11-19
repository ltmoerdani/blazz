# ⚡ Quick Answer: Queue Worker untuk Campaign

## 🎯 Jawaban Singkat

### ❓ Apakah Queue Worker harus di-start manual?

**✅ TIDAK!** Queue worker **OTOMATIS START** ketika menjalankan:

```bash
./start-dev.sh
```

### ❓ Apakah Laravel Scheduler harus di-start manual?

**✅ TIDAK!** Laravel Scheduler **OTOMATIS START** ketika menjalankan:

```bash
./start-dev.sh
```

### ❓ Apakah bisa dijalankan langsung dengan start-dev.sh?

**✅ YA!** Queue worker dan Laravel Scheduler sudah termasuk dalam `start-dev.sh` dan akan otomatis berjalan.

---

## 📚 Dokumentasi Lengkap

- 📖 **[Queue Worker Setup Guide](docs/queue-worker-setup.md)** - Setup lengkap & best practices
- ❓ **[Queue Worker FAQ](docs/QUEUE-WORKER-FAQ.md)** - Tanya jawab lengkap dengan tutorial
- 📋 **[Quick Reference Card](QUEUE-WORKER-CHEATSHEET.txt)** - Cheatsheet untuk reference cepat
- 🔧 **[Campaign Fix Documentation](docs/campaign-skip-schedule-fix.md)** - Technical details & fixes

---

## 🚀 Quick Start

### 1. Start Development Environment

```bash
./start-dev.sh
```

Output:
```
🚀 Starting Blazz Development Environment...
==============================================
1. Starting Laravel Backend (Port 8000)...      ✅
2. Starting Laravel Reverb (Port 8080)...       ✅
3. Starting WhatsApp Service (Port 3001)...     ✅
4. Starting Queue Worker...                     ✅ ← AUTO START!
5. Starting Laravel Scheduler...                ✅ ← AUTO START!
```

### 2. Verify Queue Worker

```bash
./manage-queue-worker.sh status
```

### 3. Create Campaign

1. Go to UI → Campaigns → Create Campaign
2. Fill in details
3. ✅ Check "Skip scheduling & send immediately"
4. Click Create
5. ✅ Message sent automatically!

---

## 🔧 Management Commands

### Queue Worker
```bash
# Check if queue worker is running
./docs/queue-worker/03-manage-queue-worker.sh status

# Restart queue worker (after code changes)
./docs/queue-worker/03-manage-queue-worker.sh restart

# Monitor logs in real-time
./docs/queue-worker/03-manage-queue-worker.sh monitor
```

### Laravel Scheduler
```bash
# Check if scheduler is running
./docs/queue-worker/08-manage-scheduler.sh status

# List all scheduled tasks
./docs/queue-worker/08-manage-scheduler.sh list

# Monitor scheduler logs
./docs/queue-worker/08-manage-scheduler.sh monitor

# Run scheduler manually (test)
./docs/queue-worker/08-manage-scheduler.sh test
```

---

## ⚠️ Kapan Perlu Restart Queue Worker?

Queue worker perlu di-restart **HANYA** jika:

1. ✏️ **Setelah edit code** di Job/Model/Service files
2. 🐛 **Queue worker crash** atau stuck
3. 🔄 **Jobs tidak diproses** (worker hang)

**Cara restart:**
```bash
./manage-queue-worker.sh restart
```

---

## 🎬 Flow Lengkap

### Immediate Campaign (Skip Schedule)
```
./start-dev.sh
    │
    ├─> Laravel Backend      ✅
    ├─> Laravel Reverb       ✅
    ├─> WhatsApp Service     ✅
    ├─> Queue Worker         ✅ (OTOMATIS!)
    └─> Laravel Scheduler    ✅ (OTOMATIS!)
            │
            ▼
    User creates campaign with "Skip schedule" ✅
            │
            ▼
    Job dispatched to queue: whatsapp-campaign
            │
            ▼
    Queue Worker processes job IMMEDIATELY
            │
            ▼
    Message sent via WhatsApp ✅
            │
            ▼
    Campaign status: completed ✅
```

### Scheduled Campaign
```
./start-dev.sh
    │
    └─> Laravel Scheduler    ✅ (Runs every minute)
            │
            ▼
    Check campaigns with scheduled_at <= now
            │
            ▼
    Create campaign logs
            │
            ▼
    Update status: scheduled → ongoing
            │
            ▼
    Queue Worker processes pending logs
            │
            ▼
    Messages sent at scheduled time ✅
```

---

## 🔍 Troubleshooting

### Problem: Campaign tidak diproses

```bash
# 1. Check status
./manage-queue-worker.sh status

# 2. If NOT running
./manage-queue-worker.sh start
```

### Problem: Setelah edit code, masih pakai code lama

```bash
# Restart queue worker
./manage-queue-worker.sh restart
```

### Problem: Queue worker tidak ada di process list

```bash
# Re-run start-dev.sh
./stop-dev.sh
./start-dev.sh
```

---

## 📊 Monitoring

### Check Queue Status

```bash
# Via management script
./manage-queue-worker.sh status

# Via process list
ps aux | grep "queue:work" | grep -v grep

# Via database
mysql -u root -p -e "USE blazz; SELECT COUNT(*) FROM jobs WHERE queue = 'whatsapp-campaign';"
```

### Monitor Logs

```bash
# Queue worker logs
./manage-queue-worker.sh monitor

# Or manually
tail -f logs/queue.log

# Laravel logs
tail -f storage/logs/laravel.log | grep campaign
```

---

## ✅ Checklist Before Creating Campaign

- [ ] `./start-dev.sh` sudah dijalankan
- [ ] Queue worker running (check: `./manage-queue-worker.sh status`)
- [ ] WhatsApp account connected dan status = "connected"
- [ ] Contact group sudah dibuat dengan contacts
- [ ] Ready to send! 🚀

---

## 📝 Summary

| Question | Answer |
|----------|--------|
| Apakah queue worker otomatis start? | ✅ **YA** |
| Apakah scheduler otomatis start? | ✅ **YA** |
| Perlu start manual? | ❌ **TIDAK** (kecuali crash/code changes) |
| Dimana queue worker di-start? | 📄 `start-dev.sh` |
| Dimana scheduler di-start? | 📄 `start-dev.sh` |
| Command untuk check queue status? | `./docs/queue-worker/03-manage-queue-worker.sh status` |
| Command untuk check scheduler status? | `./docs/queue-worker/08-manage-scheduler.sh status` |
| Command untuk list scheduled tasks? | `./docs/queue-worker/08-manage-scheduler.sh list` |

---

## 🔗 Related Files

- `start-dev.sh` - Main startup script (includes queue worker + scheduler)
- `stop-dev.sh` - Stop all services
- `docs/queue-worker/03-manage-queue-worker.sh` - Queue worker management
- `docs/queue-worker/08-manage-scheduler.sh` - Scheduler management
- `app/Console/Kernel.php` - Scheduler configuration
- `app/Jobs/CreateCampaignLogsJob.php` - Check scheduled campaigns
- `app/Jobs/ProcessCampaignMessagesJob.php` - Process campaign messages
- `app/Jobs/SendCampaignJob.php` - Campaign processing job
- `app/Services/CampaignService.php` - Campaign service logic

---

**📌 Remember:** 
- Queue worker **SUDAH OTOMATIS JALAN** ketika `./start-dev.sh`!
- Laravel Scheduler **SUDAH OTOMATIS JALAN** ketika `./start-dev.sh`!

**Last Updated:** 2025-11-19  
**Status:** ✅ Tested & Working (with Scheduler)
