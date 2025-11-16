# 🏥 WhatsApp Session Health Monitoring - Quick Start Guide

## Overview
System monitoring otomatis untuk menjaga kesehatan WhatsApp sessions Anda tetap optimal.

## ✨ Fitur Utama

### 1. **Automatic Health Checks** (Setiap 2 menit)
- Cek status session di Node.js service
- Monitor aktivitas terakhir
- Deteksi failed messages
- Sinkronisasi status DB ↔ Node.js
- Pattern detection untuk crash

### 2. **Health Score (0-100%)**

| Score | Status | Keterangan |
|-------|--------|------------|
| 90-100% | 🟢 Excellent | Session sangat sehat |
| 70-89% | 🔵 Good | Session sehat, normal |
| 50-69% | 🟡 Warning | Ada beberapa masalah minor |
| 30-49% | 🟠 Critical | Butuh perhatian |
| 0-29% | 🔴 Failed | Perlu reconnect segera |

### 3. **Auto-Reconnect**
- Otomatis trigger reconnect jika health score < 40%
- Disconnect session lama
- Buat session baru dengan QR code
- Notifikasi user untuk scan ulang

### 4. **Real-time Monitoring Dashboard**
- Health status badge di setiap WhatsApp account
- Tooltip menampilkan issues detail
- Last check timestamp
- Visual indicators (color-coded)

---

## 🚀 Quick Commands

### Check Health Summary
```bash
php artisan whatsapp:health-summary
```

Output:
```
🏥 WhatsApp Session Health Summary

┌────────────────────────┬─────────┐
│ Total Sessions         │ 5       │
│ Average Health Score   │ 78.4%   │
│ Excellent (90-100%)    │ 2       │
│ Good (70-89%)          │ 2       │
│ Warning (50-69%)       │ 1       │
└────────────────────────┴─────────┘
```

### Manual Health Check
```bash
php artisan whatsapp:monitor-sessions
```

### Force Monitoring (bypass throttle)
```bash
php artisan whatsapp:monitor-sessions --force
```

---

## 📊 Monitoring Dashboard

### UI Location
**Settings → WhatsApp Accounts**

Setiap account menampilkan:
- ✅ Status badge (Connected/QR Scanning/Disconnected)
- 🏥 Health status badge dengan score
- 📊 Hover untuk lihat detail issues
- ⏰ Last health check time

### Health Badge Colors
- **Green** (Excellent): Semua OK
- **Cyan** (Good): Normal operation
- **Yellow** (Warning): Ada masalah minor
- **Orange** (Critical): Perlu perhatian
- **Red** (Failed): Perlu action segera

---

## 🔧 Troubleshooting

### Problem: Session health score rendah

**Diagnosa:**
```bash
php artisan whatsapp:health-summary
```

**Penyebab umum:**
1. Session tidak ditemukan di Node.js
2. Idle terlalu lama (> 30 menit)
3. Banyak failed messages (> 5 dalam 1 jam)
4. Status mismatch antara DB dan Node.js
5. Session crash (Puppeteer browser closed)

**Solusi:**
1. **Manual reconnect via UI:**
   - Buka Settings → WhatsApp Accounts
   - Klik "Reconnect" pada account bermasalah
   - Scan QR code baru

2. **Check Node.js service:**
   ```bash
   curl http://localhost:3001/health
   ```

3. **Restart Node.js service:**
   ```bash
   cd whatsapp-service
   pm2 restart whatsapp-service
   ```

### Problem: Auto-reconnect tidak jalan

**Check scheduler:**
```bash
php artisan schedule:list
```

**Start scheduler (development):**
```bash
php artisan schedule:work
```

**Production (via cron):**
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

### Problem: Queue jobs tidak process

**Start queue worker:**
```bash
php artisan queue:work --queue=whatsapp-health,default
```

**Check queue:**
```bash
php artisan queue:monitor whatsapp-health
```

---

## ⚙️ Configuration

### Adjust Health Thresholds

**File:** `app/Services/WhatsApp/SessionHealthService.php`

```php
// Health score thresholds
const HEALTH_SCORE_EXCELLENT = 90;
const HEALTH_SCORE_GOOD = 70;
const HEALTH_SCORE_WARNING = 50;
const HEALTH_SCORE_CRITICAL = 30;

// Auto-reconnect threshold
const AUTO_RECONNECT_THRESHOLD = 40;

// Timeouts
const MAX_IDLE_MINUTES = 30;
const MAX_NO_MESSAGES_HOURS = 24;
const MAX_FAILED_MESSAGES = 5;
```

### Adjust Monitoring Interval

**File:** `app/Console/Kernel.php`

```php
// Default: every 2 minutes
$schedule->job(MonitorWhatsAppSessionHealthJob::class)
    ->everyTwoMinutes();

// Options:
// ->everyMinute()
// ->everyFiveMinutes()
// ->everyTenMinutes()
```

---

## 📈 Best Practices

### 1. **Monitor Regularly**
- Check health summary setiap hari
- Set alert jika banyak session unhealthy
- Review health trends weekly

### 2. **Proactive Maintenance**
- Reconnect sessions sebelum health critical
- Keep Node.js service running stable
- Monitor server resources (memory, CPU)

### 3. **User Education**
- Inform users tentang health status
- Encourage reconnect on warning status
- Provide clear reconnect instructions

### 4. **Performance Optimization**
- Keep idle time < 30 minutes with activity
- Minimize failed messages
- Monitor Puppeteer browser memory usage

---

## 🎯 Success Metrics

**Target KPIs:**
- Session Uptime: > 99.5%
- Auto-Reconnect Success: > 90%
- Mean Time to Detection: < 2 minutes
- Mean Time to Recovery: < 5 minutes

**Current Implementation:**
- ✅ Health monitoring every 2 minutes
- ✅ Auto-reconnect on score < 40%
- ✅ Real-time UI health badges
- ✅ Comprehensive logging
- ✅ Manual commands for admin

---

## 📚 Additional Resources

- **Full Documentation:** `docs/chats/17-session-health-monitoring.md`
- **Architecture:** `docs/architecture/06-dual-server-architecture.md`
- **Development Guide:** `docs/architecture/07-development-patterns-guidelines.md`

---

## 🔔 Getting Help

**Check logs:**
```bash
tail -f storage/logs/laravel.log | grep "WhatsApp Session Health"
```

**Check Node.js logs:**
```bash
pm2 logs whatsapp-service
```

**Contact Support:**
- Slack: #whatsapp-support
- Email: support@yourcompany.com

---

**Last Updated:** November 16, 2025  
**Status:** ✅ Production Ready  
**Version:** 1.0.0
