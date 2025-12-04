# 🛡️ Anti-Ban Tier System Design
## WhatsApp Campaign Speed Selection (User-Controlled)

**Version:** 2.0  
**Date:** November 28, 2025  
**Based On:** [01 - Re-Evaluasi Tier Structure](./01-re-evaluasi-tier-structure.md)  
**Implementation:** [03 - Implementation Guide](./03-implementation-guide.md)  
**Architecture:** Hybrid Service-Oriented Dual-Server (Laravel + Node.js)

---

## 📋 Executive Summary

### Problem Statement
Sistem saat ini **TIDAK memiliki delay/interval** antar kontak saat mengirim campaign message di level Laravel (`SendCampaignJob.php`). Rate limiter di Node.js (`WhatsAppRateLimiter.js`) sudah ada tetapi **BELUM TERINTEGRASI** ke alur pengiriman pesan.

### Solution
Implementasi **User-Selectable Speed Tier System** yang:
1. **User memilih sendiri** kecepatan pengiriman saat create campaign
2. Sistem menerapkan **interval + daily limit** berdasarkan tier yang dipilih
3. **Tanpa sistem upgrade otomatis** - user bebas memilih tier sesuai kebutuhan
4. Menerapkan **human behavior simulation** (typing indicators, variance, breaks)

### Design Philosophy
| Prinsip | Penjelasan |
|---------|------------|
| **User Control** | User memilih kecepatan sendiri, bukan sistem yang menentukan |
| **Simplicity** | Hanya 1 parameter utama: **interval** (tanpa daily limit) |
| **Transparency** | User melihat jelas risk level setiap tier |
| **Responsibility** | User bertanggung jawab atas pilihan tier mereka |

> **Catatan:** Tidak ada automatic tier upgrade berdasarkan metrics (Days Active, Messages Sent, Success Rate, dll). User bebas memilih tier manapun sesuai kebutuhan dan risk appetite mereka.

---

## 🏗️ Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                         LARAVEL LAYER                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────────────┐    ┌──────────────────────────────┐   │
│  │  Campaign Form UI   │───▶│   User Selects Speed Tier    │   │
│  │  (Speed Selection)  │    │   (Dropdown: 5 options)      │   │
│  └─────────────────────┘    └──────────────────────────────┘   │
│            │                              │                     │
│            ▼                              ▼                     │
│  ┌─────────────────────┐    ┌──────────────────────────────┐   │
│  │   SendCampaignJob   │───▶│   CampaignSpeedService       │   │
│  │  (Apply Delay)      │    │   (Get Interval/Limit)       │   │
│  └─────────────────────┘    └──────────────────────────────┘   │
│            │                                                    │
│            ▼                                                    │
│  ┌─────────────────────┐                                       │
│  │WhatsAppServiceClient│                                       │
│  │   (HTTP to Node.js) │                                       │
│  └─────────────────────┘                                       │
│            │                                                    │
└────────────┼────────────────────────────────────────────────────┘
             │ HTTP API
             ▼
┌─────────────────────────────────────────────────────────────────┐
│                         NODE.JS LAYER                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────────────┐    ┌──────────────────────────────┐   │
│  │  MessageController  │───▶│   BehaviorSimulator (NEW)    │   │
│  └─────────────────────┘    │   (Typing, Variance)         │   │
│            │                └──────────────────────────────┘   │
│            ▼                                                    │
│  ┌─────────────────────┐                                       │
│  │   SessionManager    │                                       │
│  │   (sendMessage)     │                                       │
│  └─────────────────────┘                                       │
│            │                                                    │
│            ▼                                                    │
│  ┌─────────────────────┐                                       │
│  │  whatsapp-web.js    │                                       │
│  └─────────────────────┘                                       │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📊 Speed Tier Options (User Selectable)

### Tier Structure - SIMPLIFIED

User memilih salah satu dari **5 Speed Tier** saat membuat campaign:

| Tier | Label | Interval | Risk Level | Recommended For |
|------|-------|----------|------------|-----------------|
| 🐢 **1** | Paranoid | 90-120s | 🟢 Very Low | New accounts, first-time users |
| 🚶 **2** | Safe | 45-60s | 🟢 Low | General use, risk-averse |
| 🚴 **3** | Balanced | 30-45s | 🟡 Medium | Regular campaigns |
| 🚗 **4** | Fast | 20-30s | 🟠 High | Experienced users |
| 🚀 **5** | Aggressive | 10-20s | 🔴 Very High | Expert users, aged accounts |

### UI Display di Campaign Form

```
Speed Tier Selection:
┌────────────────────────────────────────────────────────────────┐
│  🐢 Paranoid (Safest)                                          │
│     Interval: 90-120 detik per message                         │
│     Risk: Very Low | Best for new accounts                     │
├────────────────────────────────────────────────────────────────┤
│  🚶 Safe (Recommended)                              ⭐ DEFAULT │
│     Interval: 45-60 detik per message                          │
│     Risk: Low | Best for general use                           │
├────────────────────────────────────────────────────────────────┤
│  🚴 Balanced                                                    │
│     Interval: 30-45 detik per message                          │
│     Risk: Medium | For regular campaigns                       │
├────────────────────────────────────────────────────────────────┤
│  🚗 Fast                                                        │
│     Interval: 20-30 detik per message                          │
│     Risk: High | For experienced users                         │
├────────────────────────────────────────────────────────────────┤
│  🚀 Aggressive (Expert Only)                                    │
│     Interval: 10-20 detik per message                          │
│     Risk: Very High | For expert users, aged accounts          │
└────────────────────────────────────────────────────────────────┘
```

---

## ⚙️ Config Implementation

```php
// config/campaign.php

return [
    'speed_tiers' => [
        'enabled' => env('CAMPAIGN_SPEED_TIERS_ENABLED', true),
        
        'tiers' => [
            // TIER 1: Paranoid (Safest)
            1 => [
                'name' => 'paranoid',
                'label' => 'Paranoid (Safest)',
                'emoji' => '🐢',
                'interval_min_seconds' => 90,
                'interval_max_seconds' => 120,
                'risk_level' => 'very_low',
                'risk_color' => 'green',
                'description' => 'Best for new accounts or first-time users',
                'batch_size' => 10,
                'batch_break_seconds' => 300,
                'typing_indicator' => true,
            ],
            
            // TIER 2: Safe (Default/Recommended)
            2 => [
                'name' => 'safe',
                'label' => 'Safe (Recommended)',
                'emoji' => '🚶',
                'interval_min_seconds' => 45,
                'interval_max_seconds' => 60,
                'risk_level' => 'low',
                'risk_color' => 'green',
                'description' => 'Best for general use, risk-averse users',
                'batch_size' => 20,
                'batch_break_seconds' => 180,
                'typing_indicator' => true,
                'is_default' => true,
            ],
            
            // TIER 3: Balanced
            3 => [
                'name' => 'balanced',
                'label' => 'Balanced',
                'emoji' => '🚴',
                'interval_min_seconds' => 30,
                'interval_max_seconds' => 45,
                'risk_level' => 'medium',
                'risk_color' => 'yellow',
                'description' => 'For regular campaigns',
                'batch_size' => 25,
                'batch_break_seconds' => 150,
                'typing_indicator' => true,
            ],
            
            // TIER 4: Fast
            4 => [
                'name' => 'fast',
                'label' => 'Fast',
                'emoji' => '🚗',
                'interval_min_seconds' => 20,
                'interval_max_seconds' => 30,
                'risk_level' => 'high',
                'risk_color' => 'orange',
                'description' => 'For experienced users',
                'batch_size' => 30,
                'batch_break_seconds' => 120,
                'typing_indicator' => true,
            ],
            
            // TIER 5: Aggressive (Expert Only)
            5 => [
                'name' => 'aggressive',
                'label' => 'Aggressive (Expert Only)',
                'emoji' => '🚀',
                'interval_min_seconds' => 10,
                'interval_max_seconds' => 20,
                'risk_level' => 'very_high',
                'risk_color' => 'red',
                'description' => 'For expert users with aged accounts. Higher ban risk.',
                'batch_size' => 40,
                'batch_break_seconds' => 90,
                'typing_indicator' => false,
                'show_warning' => true,
            ],
        ],
        
        // Default tier if not selected
        'default_tier' => 2,
        
        // Interval variance (randomization)
        'interval_variance_percent' => 25,
    ],
];
```

---

## 🗄️ Database Schema

### Migration: Add Speed Tier to Campaigns

```php
// database/migrations/2025_11_28_000001_add_speed_tier_to_campaigns.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // Speed tier selection (1-5)
            $table->tinyInteger('speed_tier')->default(2)->after('status');
            
            // Index
            $table->index('speed_tier');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['speed_tier']);
        });
    }
};
```

### Optional: Rate Limit Logs (For Analytics)

```php
// database/migrations/2025_11_28_000002_create_campaign_rate_limit_logs_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_rate_limit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->foreignId('whatsapp_account_id')->nullable()->constrained()->onDelete('set null');
            
            // Tier & Delay Applied
            $table->tinyInteger('speed_tier')->default(2);
            $table->integer('delay_applied_ms')->default(0);
            
            // Batch Info
            $table->integer('batch_sequence')->default(1);
            $table->boolean('batch_break_applied')->default(false);
            
            // Result
            $table->enum('result', ['sent', 'delayed', 'limit_reached', 'error'])->default('sent');
            
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes
            $table->index(['campaign_id', 'created_at']);
            $table->index('speed_tier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_rate_limit_logs');
    }
};
```

---

## 📦 Laravel Service Layer

### CampaignSpeedService (Simple)

```php
// app/Services/Campaign/CampaignSpeedService.php

<?php

namespace App\Services\Campaign;

use App\Models\Campaign;
use Illuminate\Support\Facades\Log;

/**
 * Campaign Speed Service
 * 
 * Simple service to get interval and daily limit based on user-selected tier
 * No automatic tier calculation - user chooses their own speed
 */
class CampaignSpeedService
{
    private array $tierConfig;
    
    public function __construct()
    {
        $this->tierConfig = config('campaign.speed_tiers.tiers', []);
    }
    
    /**
     * Get delay configuration for a campaign
     */
    public function getDelayConfig(Campaign $campaign): array
    {
        $tier = $campaign->speed_tier ?? config('campaign.speed_tiers.default_tier', 2);
        $tierData = $this->tierConfig[$tier] ?? $this->tierConfig[2];
        
        return [
            'tier' => $tier,
            'tier_name' => $tierData['name'],
            'interval_min_ms' => $tierData['interval_min_seconds'] * 1000,
            'interval_max_ms' => $tierData['interval_max_seconds'] * 1000,
            'batch_size' => $tierData['batch_size'],
            'batch_break_ms' => $tierData['batch_break_seconds'] * 1000,
            'typing_indicator' => $tierData['typing_indicator'] ?? false,
        ];
    }
    
    /**
     * Calculate random delay with variance
     */
    public function calculateDelay(Campaign $campaign): int
    {
        $config = $this->getDelayConfig($campaign);
        $variance = config('campaign.speed_tiers.interval_variance_percent', 25);
        
        // Random base delay
        $baseDelay = random_int($config['interval_min_ms'], $config['interval_max_ms']);
        
        // Apply variance
        $maxVariance = (int)($baseDelay * ($variance / 100));
        $varianceValue = random_int(-$maxVariance, $maxVariance);
        
        return max(1000, $baseDelay + $varianceValue); // Minimum 1 second
    }
    
    /**
     * Check if batch break is needed
     */
    public function needsBatchBreak(Campaign $campaign, int $currentBatchCount): bool
    {
        $config = $this->getDelayConfig($campaign);
        return $currentBatchCount >= $config['batch_size'];
    }
    
    /**
     * Get batch break duration in milliseconds
     */
    public function getBatchBreakDuration(Campaign $campaign): int
    {
        $config = $this->getDelayConfig($campaign);
        return $config['batch_break_ms'];
    }
    
    /**
     * Get all available tiers for UI dropdown
     */
    public function getAvailableTiers(): array
    {
        $tiers = [];
        
        foreach ($this->tierConfig as $tierNum => $tierData) {
            $tiers[] = [
                'value' => $tierNum,
                'label' => $tierData['label'],
                'emoji' => $tierData['emoji'],
                'interval' => $this->formatInterval($tierData),
                'risk_level' => $tierData['risk_level'],
                'risk_color' => $tierData['risk_color'],
                'description' => $tierData['description'],
                'is_default' => $tierData['is_default'] ?? false,
                'show_warning' => $tierData['show_warning'] ?? false,
            ];
        }
        
        return $tiers;
    }
    
    // ========== Private Helpers ==========
    
    private function formatInterval(array $tierData): string
    {
        $min = $tierData['interval_min_seconds'];
        $max = $tierData['interval_max_seconds'];
        
        if ($min >= 60) {
            return ($min / 60) . '-' . ($max / 60) . ' menit';
        }
        
        return "{$min}-{$max} detik";
    }
}
```

---

## 🔄 SendCampaignJob Integration

### Updated SendCampaignJob with Speed Tier

```php
// app/Jobs/SendCampaignJob.php - Key modifications

/**
 * Add to use statements:
 */
use App\Services\Campaign\CampaignSpeedService;

/**
 * Add property:
 */
private CampaignSpeedService $speedService;
private int $batchMessageCount = 0;

/**
 * Update constructor:
 */
public function __construct(
    private Campaign|int $campaign,
    ?ProviderSelectionService $providerService = null,
    ?CampaignSpeedService $speedService = null
) {
    $this->providerService = $providerService ?? app(ProviderSelectionService::class);
    $this->speedService = $speedService ?? app(CampaignSpeedService::class);
    $this->onQueue('whatsapp-campaign');
}

/**
 * Update sendTemplateMessage method to include speed delay:
 */
protected function sendTemplateMessage(CampaignLog $campaignLog)
{
    DB::transaction(function() use ($campaignLog) {
        $log = CampaignLog::where('id', $campaignLog->id)
                          ->where('status', 'pending')
                          ->lockForUpdate()
                          ->first();

        if (!$log) {
            return;
        }

        $campaign = $campaignLog->campaign;
        
        // ========== Speed Tier Check ==========
        if (config('campaign.speed_tiers.enabled', true)) {
            
            // Check batch break
            if ($this->speedService->needsBatchBreak($campaign, $this->batchMessageCount)) {
                $breakDuration = $this->speedService->getBatchBreakDuration($campaign);
                Log::info('Applying batch break', [
                    'campaign_id' => $campaign->id,
                    'break_ms' => $breakDuration,
                ]);
                usleep($breakDuration * 1000);
                $this->batchMessageCount = 0;
            }
            
            // Apply interval delay
            $delayMs = $this->speedService->calculateDelay($campaign);
            Log::debug('Applying speed delay', [
                'campaign_id' => $campaign->id,
                'delay_ms' => $delayMs,
                'tier' => $campaign->speed_tier,
            ]);
            usleep($delayMs * 1000);
        }
        // ========== End Speed Tier Check ==========

        // Mark log as ongoing
        $log->status = 'ongoing';
        $log->save();

        try {
            // ... existing message sending logic ...
            
            // Increment batch counter
            if (config('campaign.speed_tiers.enabled', true)) {
                $this->batchMessageCount++;
            }
            
        } catch (\Exception $e) {
            // ... existing error handling ...
        }
    });
}
```

---

## 🎨 Frontend Integration

### Campaign Form - Speed Tier Selector

```vue
<!-- resources/js/Pages/User/Campaigns/Partials/SpeedTierSelector.vue -->

<template>
  <div class="space-y-3">
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
      Kecepatan Pengiriman
    </label>
    
    <div class="space-y-2">
      <div 
        v-for="tier in tiers" 
        :key="tier.value"
        @click="selectTier(tier.value)"
        :class="[
          'p-4 rounded-lg border-2 cursor-pointer transition-all',
          selectedTier === tier.value 
            ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' 
            : 'border-gray-200 dark:border-gray-700 hover:border-gray-300',
        ]"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="text-2xl">{{ tier.emoji }}</span>
            <div>
              <div class="font-medium text-gray-900 dark:text-white">
                {{ tier.label }}
                <span 
                  v-if="tier.is_default" 
                  class="ml-2 text-xs px-2 py-0.5 bg-green-100 text-green-800 rounded-full"
                >
                  Recommended
                </span>
              </div>
              <div class="text-sm text-gray-500 dark:text-gray-400">
                {{ tier.description }}
              </div>
            </div>
          </div>
          
          <div class="text-right">
            <div class="text-sm font-medium">{{ tier.interval }}</div>
          </div>
        </div>
        
        <!-- Risk Badge -->
        <div class="mt-2 flex items-center gap-2">
          <span 
            :class="[
              'px-2 py-0.5 text-xs rounded-full',
              getRiskBadgeClass(tier.risk_level)
            ]"
          >
            Risk: {{ tier.risk_level.replace('_', ' ') }}
          </span>
        </div>
        
        <!-- Warning for aggressive tier -->
        <div 
          v-if="tier.show_warning && selectedTier === tier.value" 
          class="mt-3 p-2 bg-red-50 dark:bg-red-900/20 rounded text-sm text-red-600 dark:text-red-400"
        >
          ⚠️ Warning: This speed may cause account ban. Use at your own risk.
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  modelValue: { type: Number, default: 2 },
  tiers: { type: Array, required: true },
})

const emit = defineEmits(['update:modelValue'])

const selectedTier = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

const selectTier = (tierValue) => {
  selectedTier.value = tierValue
}

const getRiskBadgeClass = (riskLevel) => {
  const classes = {
    'very_low': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    'low': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    'medium': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    'high': 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
    'very_high': 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
  }
  return classes[riskLevel] || classes['medium']
}
</script>
```

### Controller - Pass Tiers to View

```php
// app/Http/Controllers/User/CampaignController.php

use App\Services\Campaign\CampaignSpeedService;

public function create(CampaignSpeedService $speedService)
{
    return Inertia::render('User/Campaigns/Create', [
        'speedTiers' => $speedService->getAvailableTiers(),
        'defaultSpeedTier' => config('campaign.speed_tiers.default_tier', 2),
        // ... other props
    ]);
}
```

---

## 📊 Campaign Status Display

### Show Current Speed & Progress

```vue
<!-- In Campaign Detail/Status View -->

<div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow">
  <h3 class="font-medium mb-3">Sending Speed</h3>
  
  <div class="flex items-center gap-3">
    <span class="text-2xl">{{ currentTier.emoji }}</span>
    <div>
      <div class="font-medium">{{ currentTier.label }}</div>
      <div class="text-sm text-gray-500">
        {{ currentTier.interval }} interval
      </div>
    </div>
  </div>
  
  <!-- No daily limit - interval based only -->
</div>
```

---

## 🔧 Implementation Checklist

### Phase 1: Foundation (3-4 days)
- [ ] Create `config/campaign.php` with tier configuration
- [ ] Run migration to add `speed_tier` to campaigns table
- [ ] Create `CampaignSpeedService` class
- [ ] Add unit tests for delay calculation

### Phase 2: Backend Integration (2-3 days)
- [ ] Update `SendCampaignJob` to use speed service
- [ ] Add daily limit checking
- [ ] Add batch break logic
- [ ] Test with different tiers

### Phase 3: Frontend (2-3 days)
- [ ] Create `SpeedTierSelector.vue` component
- [ ] Update Campaign create/edit forms
- [ ] Add speed status display
- [ ] Test UI/UX flow

### Phase 4: Testing & Deployment (2 days)
- [ ] Integration testing
- [ ] Performance testing with different tiers
- [ ] Deploy to staging
- [ ] Deploy to production

---

## 📝 Summary

### What Changed from V1 to V2

| Aspect | V1 (Complex) | V2 (Simple) |
|--------|--------------|-------------|
| Tier Selection | Auto-calculated by system | User selects manually |
| Upgrade Criteria | Days Active, Messages Sent, Success Rate, Ban History, Block Rate | **None** - user chooses freely |
| Number of Tiers | 7 tiers | 5 tiers |
| Daily Limit | Per-tier limits enforced | **No daily limit** |
| Account Age Tracking | Required | Not needed |
| Engagement Metrics | Required | Not needed |
| Database Changes | Many new columns on whatsapp_accounts | Only 1 column on campaigns (`speed_tier`) |
| Complexity | High | Low |

### Key Principles

1. **User Freedom**: User pilih sendiri kecepatan, tidak ada pembatasan sistem
2. **Transparency**: User melihat jelas risk level setiap tier
3. **Simplicity**: Hanya **interval** saja, tanpa daily limit atau metrics lain
4. **Responsibility**: User bertanggung jawab atas pilihan mereka

---

## 📚 References

1. [01 - Re-Evaluasi Tier Structure](./01-re-evaluasi-tier-structure.md)
2. [03 - Implementation Guide](./03-implementation-guide.md)
3. [Development Patterns & Guidelines](../../architecture/06-development-patterns-guidelines.md)
4. [Dual-Server Architecture](../../architecture/05-dual-server-architecture.md)

---

**Author:** AI Assistant  
**Review Required:** Development Team Lead  
**Status:** Design Complete - Ready for Implementation  
**Version:** 2.0 (Simplified User-Controlled)
