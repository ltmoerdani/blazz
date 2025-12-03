# 📡 Relay System Documentation

## Overview

Dokumentasi lengkap untuk **Relay System** - sistem pengiriman pesan WhatsApp yang fokus pada **timing dan interval** untuk menghindari ban.

> **📌 Note:** Dokumentasi tentang **IP/Proxy Anti-Detection** dan **Infrastructure Architecture** telah dipindahkan ke:  
> **[/docs/architecture/anti-ban-infrastructure/](../../architecture/anti-ban-infrastructure/readme.md)**

---

## 📚 Document Index

| No | Document | Description | Status |
|----|----------|-------------|--------|
| 01 | [Re-Evaluasi Tier Structure](./01-re-evaluasi-tier-structure.md) | Analisis struktur tier berdasarkan data real | ✅ Complete |
| 02 | [Anti-Ban System Design](./02-anti-ban-system-design.md) | Desain sistem dengan user-controlled speed tiers | ✅ Complete |
| 03 | [Implementation Guide](./03-implementation-guide.md) | Panduan implementasi step-by-step | ✅ Complete |
| 04 | [Testing & QA Guide](./04-testing-guide.md) | Panduan testing untuk anti-ban system | 📋 Planned |

---

## 🎯 Quick Reference

### Speed Tier Summary

| Tier | Emoji | Label | Interval | Risk Level |
|------|-------|-------|----------|------------|
| 1 | 🐢 | Paranoid | 90-120s | 🟢 Very Low |
| 2 | 🚶 | Safe ⭐ | 45-60s | 🟢 Low |
| 3 | 🚴 | Balanced | 30-45s | 🟡 Medium |
| 4 | 🚗 | Fast | 20-30s | 🟠 High |
| 5 | 🚀 | Aggressive | 10-20s | 🔴 Very High |

### Key Files

```
Laravel (Backend):
├── config/campaign.php                    # Tier configuration
├── app/Services/Campaign/CampaignSpeedService.php
├── app/Jobs/SendCampaignJob.php           # Modified
└── database/migrations/xxx_add_speed_tier_to_campaigns.php

Vue (Frontend):
├── resources/js/Pages/User/Campaigns/Create.vue
├── resources/js/Pages/User/Campaigns/Edit.vue
└── resources/js/Components/Campaign/SpeedTierSelector.vue
```

---

## 📖 Reading Order

1. **Start Here**: [01-re-evaluasi-tier-structure.md](./01-re-evaluasi-tier-structure.md) - Understand the problem and analysis
2. **Design**: [02-anti-ban-system-design.md](./02-anti-ban-system-design.md) - System design and architecture
3. **Implement**: [03-implementation-guide.md](./03-implementation-guide.md) - Step-by-step implementation

---

## ⚠️ Important Warning

> **Speed Tier saja TIDAK CUKUP untuk anti-ban!**
> 
> WhatsApp detection tidak hanya berdasarkan timing/interval, tapi juga:
> - **IP Address** - Semua session dari IP sama = terdeteksi
> - **Device Fingerprint** - Browser/Puppeteer signature
> - **Network Pattern** - Warm-up antar akun di server sama = terdeteksi
>
> **📚 Baca dokumentasi lengkap di:**  
> **[/docs/architecture/anti-ban-infrastructure/](../../architecture/anti-ban-infrastructure/readme.md)**

---

## 🔗 Related Documentation

### Infrastructure & IP Anti-Detection (Moved)

Dokumentasi berikut telah dipindahkan ke `/docs/architecture/anti-ban-infrastructure/`:

| Old Location | New Location | Description |
|-------------|--------------|-------------|
| Doc 05-14 | [anti-ban-infrastructure/](../../architecture/anti-ban-infrastructure/) | IP, Proxy, Deployment Architecture |

### Quick Links

- **[Anti-Ban Infrastructure README](../../architecture/anti-ban-infrastructure/readme.md)** - Index lengkap
- **[Deployment Architecture](../../architecture/anti-ban-infrastructure/10-deployment-architecture-ip-sync.md)** - 🏆 RECOMMENDED
- **[IP/Proxy Analysis](../../architecture/anti-ban-infrastructure/01-ip-proxy-anti-detection-analysis.md)** - Root cause analysis

### Other Architecture Docs

- [Architecture Overview](../../architecture/01-arsitektur-overview.md)
- [Development Patterns](../../architecture/06-development-patterns-guidelines.md)
- [Dual-Server Architecture](../../architecture/05-dual-server-architecture.md)
- [Scalable Architecture](../../architecture/08-scalable-architecture.md)

---

**Last Updated:** December 3, 2025  
**Version:** 3.0 (Restructured - IP/Infra docs moved to architecture)
