# 📚 Queue Worker & Campaign System Documentation

> **Project:** Blazz WhatsApp Campaign System  
> **Branch:** staging-broadcast  
> **Last Updated:** November 19, 2025

---

## 📂 Documentation Files

| # | File | Description | Type |
|---|------|-------------|------|
| 00 | [00-index.md](00-index.md) | 📋 Navigation & complete overview | Index |
| 01 | [01-queue-worker-readme.md](01-queue-worker-readme.md) | ⚡ Quick start & checklist | Quick Start |
| 02 | [02-queue-worker-cheatsheet.txt](02-queue-worker-cheatsheet.txt) | 📋 Command reference card | Reference |
| 03 | [03-manage-queue-worker.sh](03-manage-queue-worker.sh) | 🔧 Management script (executable) | Tool |
| 04 | [04-campaign-skip-schedule-fix.md](04-campaign-skip-schedule-fix.md) | 🔧 Technical fixes & details | Technical |
| 05 | [05-queue-worker-setup.md](05-queue-worker-setup.md) | 📖 Complete setup guide | Guide |
| 06 | [06-queue-worker-faq.md](06-queue-worker-faq.md) | ❓ FAQ & troubleshooting | FAQ |

---

## 🚀 Quick Start

### First Time Setup?
👉 Read: **[01-queue-worker-readme.md](01-queue-worker-readme.md)**

### Need Commands?
👉 Check: **[02-queue-worker-cheatsheet.txt](02-queue-worker-cheatsheet.txt)**

### Manage Queue Worker?
```bash
./docs/queue-worker/03-manage-queue-worker.sh status
./docs/queue-worker/03-manage-queue-worker.sh restart
./docs/queue-worker/03-manage-queue-worker.sh monitor
```

### Understanding Technical Details?
👉 Read: **[04-campaign-skip-schedule-fix.md](04-campaign-skip-schedule-fix.md)**

### Production Setup?
👉 Follow: **[05-queue-worker-setup.md](05-queue-worker-setup.md)**

### Having Issues?
👉 Check: **[06-queue-worker-faq.md](06-queue-worker-faq.md)**

---

## 🎯 Quick Answers

### Q: Queue worker otomatis start?
✅ **YA** - Auto start dengan `./start-dev.sh`

### Q: Kapan perlu restart queue worker?
🔄 **Setelah edit code** di Job/Model/Service files

### Q: Command untuk check status?
```bash
./docs/queue-worker/03-manage-queue-worker.sh status
```

### Q: Bagaimana monitor logs?
```bash
./docs/queue-worker/03-manage-queue-worker.sh monitor
```

---

## 📖 Complete Navigation

For complete navigation and detailed overview, see:  
👉 **[00-index.md](00-index.md)**

---

**📌 Start Here:** [01-queue-worker-readme.md](01-queue-worker-readme.md)
