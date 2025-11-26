# 📚 Documentation Index - Queue Worker & Campaign System

> **Last Updated:** November 19, 2025  
> **Project:** Blazz WhatsApp Campaign System  
> **Branch:** staging-broadcast

---

## 📋 Table of Contents

### Quick Start & Reference

| # | File | Description | Type |
|---|------|-------------|------|
| 01 | [queue-worker-readme.md](01-queue-worker-readme.md) | ⚡ Quick answer & checklist untuk queue worker | Quick Start |
| 02 | [queue-worker-cheatsheet.txt](02-queue-worker-cheatsheet.txt) | 📋 ASCII art cheatsheet untuk reference cepat | Reference |
| 03 | [manage-queue-worker.sh](03-manage-queue-worker.sh) | 🔧 Queue worker management script (executable) | Tool |

### Technical Documentation

| # | File | Description | Type |
|---|------|-------------|------|
| 04 | [campaign-skip-schedule-fix.md](04-campaign-skip-schedule-fix.md) | 🔧 Technical details & fixes untuk campaign feature | Technical |
| 05 | [queue-worker-setup.md](05-queue-worker-setup.md) | 📖 Setup guide lengkap & best practices | Guide |
| 06 | [queue-worker-faq.md](06-queue-worker-faq.md) | ❓ FAQ lengkap dengan tutorial & diagrams | FAQ |

---

## 🚀 Quick Navigation

### I. Baru Mulai?
👉 Start here: **[01-queue-worker-readme.md](01-queue-worker-readme.md)**

### II. Butuh Command Reference?
👉 Check: **[02-queue-worker-cheatsheet.txt](02-queue-worker-cheatsheet.txt)**

### III. Manage Queue Worker?
👉 Use: **[03-manage-queue-worker.sh](03-manage-queue-worker.sh)**
```bash
./docs/03-manage-queue-worker.sh status
./docs/03-manage-queue-worker.sh restart
./docs/03-manage-queue-worker.sh monitor
```

### IV. Technical Details?
👉 Read: **[04-campaign-skip-schedule-fix.md](04-campaign-skip-schedule-fix.md)**

### V. Setup Production?
👉 Follow: **[05-queue-worker-setup.md](05-queue-worker-setup.md)**

### VI. Troubleshooting?
👉 See: **[06-queue-worker-faq.md](06-queue-worker-faq.md)**

---

## 📖 Document Overview

### 01. Queue Worker README
**Purpose:** Quick answer untuk pertanyaan umum  
**Target Audience:** Developer & Non-Technical  
**Content:**
- ❓ Apakah queue worker harus start manual?
- ✅ Checklist sebelum create campaign
- 🔧 Management commands
- 🎬 Flow diagram lengkap

**When to use:** First time setup atau butuh quick reference

---

### 02. Queue Worker Cheatsheet
**Purpose:** ASCII art reference card untuk terminal  
**Target Audience:** Developer  
**Content:**
- Command shortcuts
- Queue priorities
- Status checking
- Quick troubleshooting

**When to use:** Daily development, quick command lookup

---

### 03. Management Script
**Purpose:** Queue worker management automation  
**Target Audience:** Developer & DevOps  
**Features:**
- `start` - Start queue worker
- `stop` - Stop queue worker  
- `restart` - Restart queue worker
- `status` - Check status
- `log` - View logs
- `monitor` - Real-time monitoring

**When to use:** Managing queue worker lifecycle

---

### 04. Campaign Skip Schedule Fix
**Purpose:** Technical documentation untuk bug fixes  
**Target Audience:** Senior Developer  
**Content:**
- Problem analysis
- Root cause investigation
- Solution implementation
- Code changes detail
- Testing results

**When to use:** Understanding technical implementation

---

### 05. Queue Worker Setup Guide
**Purpose:** Comprehensive setup & configuration  
**Target Audience:** Developer & DevOps  
**Content:**
- Development setup
- Production setup with Supervisor
- Configuration options
- Best practices
- Performance tuning

**When to use:** Initial setup atau production deployment

---

### 06. Queue Worker FAQ
**Purpose:** Common questions & answers  
**Target Audience:** All  
**Content:**
- Konsep dasar queue system
- Troubleshooting guide
- Visual flow diagrams
- Real-world scenarios
- Performance tips

**When to use:** Learning or troubleshooting issues

---

## 🔗 Related Files (Outside docs/)

### Root Files
- `start-dev.sh` - Main development startup script
- `stop-dev.sh` - Stop all development services
- `test-campaign-immediate.php` - Test script untuk campaign

### Application Files
- `app/Jobs/SendCampaignJob.php` - Campaign job processor
- `app/Services/CampaignService.php` - Campaign service logic
- `app/Models/Campaign.php` - Campaign model

---

## 🎯 Quick Answers

### Q: Queue worker otomatis start?
✅ **YA** - Auto start dengan `./start-dev.sh`

### Q: Kapan perlu restart queue worker?
🔄 **Setelah edit code** di Job/Model/Service files

### Q: Command untuk check status?
```bash
./docs/03-manage-queue-worker.sh status
```

### Q: Bagaimana monitor logs?
```bash
./docs/03-manage-queue-worker.sh monitor
```

---

## 📊 Documentation Flow

```
Start
  │
  ▼
01-queue-worker-readme.md ← Start here!
  │
  ├─> Need commands? → 02-queue-worker-cheatsheet.txt
  │
  ├─> Need management? → 03-manage-queue-worker.sh
  │
  ├─> Need technical details? → 04-campaign-skip-schedule-fix.md
  │
  ├─> Setup production? → 05-queue-worker-setup.md
  │
  └─> Have problems? → 06-queue-worker-faq.md
```

---

## 🔍 How to Use This Documentation

### For Developers (First Time)
1. Read **01-queue-worker-readme.md** untuk overview
2. Check **02-queue-worker-cheatsheet.txt** untuk commands
3. Run `./start-dev.sh` dan start coding!

### For Production Setup
1. Read **05-queue-worker-setup.md** untuk setup guide
2. Configure Supervisor menggunakan template provided
3. Use **03-manage-queue-worker.sh** untuk management

### For Troubleshooting
1. Check **06-queue-worker-faq.md** untuk common issues
2. Review **04-campaign-skip-schedule-fix.md** untuk technical context
3. Use **03-manage-queue-worker.sh** untuk debugging

### For Understanding Technical Details
1. Start with **04-campaign-skip-schedule-fix.md** untuk context
2. Deep dive into code files mentioned
3. Reference **05-queue-worker-setup.md** untuk architecture

---

## ✅ Checklist Before Creating Campaign

- [ ] Documentation read: **01-queue-worker-readme.md**
- [ ] `./start-dev.sh` executed successfully
- [ ] Queue worker running (check: `./docs/03-manage-queue-worker.sh status`)
- [ ] WhatsApp account connected
- [ ] Contact group created
- [ ] Ready to send! 🚀

---

## 📝 Maintenance Notes

### Document Updates
- All documents created: November 19, 2025
- Testing completed: Campaign ID 7 ✅
- Production ready: Yes ✅

### Future Improvements
- [ ] Add webhook integration documentation
- [ ] Add performance monitoring guide
- [ ] Add scaling strategies for high-volume campaigns
- [ ] Add disaster recovery procedures

---

## 🤝 Contributing

When adding new documentation:
1. Use lowercase filenames
2. Use numbering prefix: `07-`, `08-`, etc.
3. Update this index file
4. Add entry to Table of Contents
5. Include quick navigation link

---

## 📞 Support

For questions or issues:
1. Check **06-queue-worker-faq.md** first
2. Review logs: `./docs/03-manage-queue-worker.sh log`
3. Contact: Development Team

---

**🎉 Happy Coding!**

*This documentation is maintained by the Blazz Development Team*
