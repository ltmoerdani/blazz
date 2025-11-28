# 📡 Relay System Documentation

## Overview

Dokumentasi lengkap untuk **Relay System** - sistem pengiriman pesan WhatsApp yang aman dan terukur dengan anti-ban tier system.

---

## 📚 Document Index

| No | Document | Description | Status |
|----|----------|-------------|--------|
| 01 | [Re-Evaluasi Tier Structure](./01-re-evaluasi-tier-structure.md) | Analisis dan evaluasi struktur tier berdasarkan data real | ✅ Complete |
| 02 | [Anti-Ban System Design](./02-anti-ban-system-design.md) | Desain sistem anti-ban dengan user-controlled speed tiers | ✅ Complete |
| 03 | [Implementation Guide](./03-implementation-guide.md) | Panduan implementasi lengkap step-by-step | ✅ Complete |
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

## 🔗 Related Documentation

- [Architecture Overview](../../architecture/01-arsitektur-overview.md)
- [Development Patterns](../../architecture/06-development-patterns-guidelines.md)
- [Dual-Server Architecture](../../architecture/05-dual-server-architecture.md)

---

**Last Updated:** November 28, 2025  
**Version:** 2.0
