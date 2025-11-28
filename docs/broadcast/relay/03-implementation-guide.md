# 🛠️ Anti-Ban Tier System - Implementation Guide

## Complete Step-by-Step Implementation Reference

**Version:** 1.0  
**Date:** November 28, 2025  
**Related Docs:** 
- [01-re-evaluasi-tier-structure.md](./01-re-evaluasi-tier-structure.md)
- [02-anti-ban-system-design.md](./02-anti-ban-system-design.md)

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Implementation Phases](#implementation-phases)
4. [Phase 1: Configuration](#phase-1-configuration)
5. [Phase 2: Database Migration](#phase-2-database-migration)
6. [Phase 3: Service Layer](#phase-3-service-layer)
7. [Phase 4: Job Integration](#phase-4-job-integration)
8. [Phase 5: Frontend Components](#phase-5-frontend-components)
9. [Phase 6: Controller Updates](#phase-6-controller-updates)
10. [Phase 7: Model Updates](#phase-7-model-updates)
11. [Testing Checklist](#testing-checklist)
12. [Deployment Guide](#deployment-guide)

---

## Overview

Dokumen ini adalah **referensi implementasi lengkap** untuk Anti-Ban Tier System. Setiap section berisi kode yang siap di-copy dan panduan implementasi.

### System Goals
- ✅ User memilih sendiri kecepatan pengiriman campaign
- ✅ Interval-based delay (tanpa daily limit)
- ✅ Human behavior simulation (variance, batch breaks)
- ✅ Transparent risk display

### Speed Tiers

| Tier | Emoji | Label | Interval | Risk | Batch Size | Batch Break |
|------|-------|-------|----------|------|------------|-------------|
| 1 | 🐢 | Paranoid | 90-120s | Very Low | 10 | 5 min |
| 2 | 🚶 | Safe ⭐ | 45-60s | Low | 20 | 3 min |
| 3 | 🚴 | Balanced | 30-45s | Medium | 25 | 2.5 min |
| 4 | 🚗 | Fast | 20-30s | High | 30 | 2 min |
| 5 | 🚀 | Aggressive | 10-20s | Very High | 40 | 1.5 min |

---

## Prerequisites

### Required Knowledge
- Laravel 10+ (PHP 8.1+)
- Vue 3 + Inertia.js
- Existing Campaign system understanding

### Files That Will Be Modified/Created

```
📁 Created (New Files):
├── config/campaign.php
├── app/Services/Campaign/CampaignSpeedService.php
├── database/migrations/xxxx_add_speed_tier_to_campaigns.php
└── resources/js/Components/Campaign/SpeedTierSelector.vue

📝 Modified (Existing Files):
├── app/Jobs/SendCampaignJob.php
├── app/Models/Campaign.php
├── app/Http/Controllers/User/CampaignController.php
├── resources/js/Pages/User/Campaigns/Create.vue
└── resources/js/Pages/User/Campaigns/Edit.vue
```

---

## Implementation Phases

### Overview Timeline

| Phase | Task | Duration | Dependencies |
|-------|------|----------|--------------|
| 1 | Configuration | 1 hour | None |
| 2 | Database Migration | 30 min | Phase 1 |
| 3 | Service Layer | 2 hours | Phase 1 |
| 4 | Job Integration | 2 hours | Phase 2, 3 |
| 5 | Frontend Components | 3 hours | Phase 1 |
| 6 | Controller Updates | 1 hour | Phase 3, 5 |
| 7 | Model Updates | 30 min | Phase 2 |

**Total Estimated Time:** 10-12 hours

---

## Phase 1: Configuration

### Step 1.1: Create Config File

Create file: `config/campaign.php`

```php
<?php

/**
 * Campaign Configuration
 * 
 * Configuration for campaign speed tiers and anti-ban settings.
 * 
 * @see docs/broadcast/relay/02-anti-ban-system-design.md
 * @see docs/broadcast/relay/03-implementation-guide.md
 */

return [
    
    /*
    |--------------------------------------------------------------------------
    | Speed Tier System
    |--------------------------------------------------------------------------
    |
    | User-selectable speed tiers for campaign message sending.
    | Each tier has different intervals to balance speed vs safety.
    |
    */
    
    'speed_tiers' => [
        
        // Enable/disable speed tier system
        'enabled' => env('CAMPAIGN_SPEED_TIERS_ENABLED', true),
        
        // Available tiers
        'tiers' => [
            
            /*
            |------------------------------------------------------------------
            | TIER 1: Paranoid (Safest)
            |------------------------------------------------------------------
            | For: New accounts, first-time users
            | Risk: Very Low - Almost zero ban risk
            | Speed: ~30-40 messages/hour
            */
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
                'batch_break_seconds' => 300, // 5 minutes
                'typing_indicator' => true,
                'is_default' => false,
                'show_warning' => false,
            ],
            
            /*
            |------------------------------------------------------------------
            | TIER 2: Safe (Recommended/Default)
            |------------------------------------------------------------------
            | For: General use, risk-averse users
            | Risk: Low - Minimal ban risk
            | Speed: ~60-80 messages/hour
            */
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
                'batch_break_seconds' => 180, // 3 minutes
                'typing_indicator' => true,
                'is_default' => true,
                'show_warning' => false,
            ],
            
            /*
            |------------------------------------------------------------------
            | TIER 3: Balanced
            |------------------------------------------------------------------
            | For: Regular campaigns, normal operations
            | Risk: Medium - Moderate ban risk
            | Speed: ~80-120 messages/hour
            */
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
                'batch_break_seconds' => 150, // 2.5 minutes
                'typing_indicator' => true,
                'is_default' => false,
                'show_warning' => false,
            ],
            
            /*
            |------------------------------------------------------------------
            | TIER 4: Fast
            |------------------------------------------------------------------
            | For: Experienced users, time-sensitive campaigns
            | Risk: High - Increased ban risk
            | Speed: ~120-180 messages/hour
            */
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
                'batch_break_seconds' => 120, // 2 minutes
                'typing_indicator' => true,
                'is_default' => false,
                'show_warning' => false,
            ],
            
            /*
            |------------------------------------------------------------------
            | TIER 5: Aggressive (Expert Only)
            |------------------------------------------------------------------
            | For: Expert users with aged accounts
            | Risk: Very High - High ban risk
            | Speed: ~180-360 messages/hour
            */
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
                'batch_break_seconds' => 90, // 1.5 minutes
                'typing_indicator' => false,
                'is_default' => false,
                'show_warning' => true,
            ],
            
        ],
        
        // Default tier when not specified
        'default_tier' => 2,
        
        // Interval variance percentage (adds randomness)
        // Example: 25% variance on 60s = 45s to 75s actual delay
        'interval_variance_percent' => 25,
        
    ],
    
];
```

### Step 1.2: Add Environment Variable (Optional)

Add to `.env`:

```env
# Campaign Speed Tiers
CAMPAIGN_SPEED_TIERS_ENABLED=true
```

### Step 1.3: Clear Config Cache

```bash
php artisan config:clear
php artisan config:cache
```

---

## Phase 2: Database Migration

### Step 2.1: Create Migration

```bash
php artisan make:migration add_speed_tier_to_campaigns_table
```

### Step 2.2: Migration Content

File: `database/migrations/2025_11_28_000001_add_speed_tier_to_campaigns_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add speed_tier column to campaigns table
 * 
 * @see docs/broadcast/relay/03-implementation-guide.md
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // Speed tier selection (1-5)
            // Default: 2 (Safe/Recommended)
            $table->unsignedTinyInteger('speed_tier')
                  ->default(2)
                  ->after('status')
                  ->comment('User-selected speed tier: 1=Paranoid, 2=Safe, 3=Balanced, 4=Fast, 5=Aggressive');
            
            // Index for filtering/reporting
            $table->index('speed_tier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex(['speed_tier']);
            $table->dropColumn('speed_tier');
        });
    }
};
```

### Step 2.3: Run Migration

```bash
php artisan migrate
```

### Step 2.4: (Optional) Rate Limit Logs Migration

For analytics and debugging, create a logs table:

```bash
php artisan make:migration create_campaign_rate_limit_logs_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rate limit logs for analytics and debugging
 * 
 * @see docs/broadcast/relay/03-implementation-guide.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_rate_limit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->foreignId('whatsapp_account_id')->nullable()->constrained()->onDelete('set null');
            
            // Tier & Delay Applied
            $table->unsignedTinyInteger('speed_tier')->default(2);
            $table->unsignedInteger('delay_applied_ms')->default(0);
            
            // Batch Info
            $table->unsignedInteger('batch_sequence')->default(1);
            $table->boolean('batch_break_applied')->default(false);
            
            // Result
            $table->enum('result', ['sent', 'delayed', 'error'])->default('sent');
            
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes for querying
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

## Phase 3: Service Layer

### Step 3.1: Create Service Directory (If Not Exists)

```bash
mkdir -p app/Services/Campaign
```

### Step 3.2: Create CampaignSpeedService

File: `app/Services/Campaign/CampaignSpeedService.php`

```php
<?php

namespace App\Services\Campaign;

use App\Models\Campaign;
use Illuminate\Support\Facades\Log;

/**
 * Campaign Speed Service
 * 
 * Handles speed tier logic for campaign message sending.
 * User selects tier, service provides delay calculations.
 * 
 * @see docs/broadcast/relay/02-anti-ban-system-design.md
 * @see docs/broadcast/relay/03-implementation-guide.md
 */
class CampaignSpeedService
{
    /**
     * Tier configuration from config file
     */
    private array $tierConfig;
    
    /**
     * Default tier if not specified
     */
    private int $defaultTier;
    
    /**
     * Variance percentage for randomization
     */
    private int $variancePercent;
    
    public function __construct()
    {
        $this->tierConfig = config('campaign.speed_tiers.tiers', []);
        $this->defaultTier = config('campaign.speed_tiers.default_tier', 2);
        $this->variancePercent = config('campaign.speed_tiers.interval_variance_percent', 25);
    }
    
    // =========================================================================
    // PUBLIC METHODS
    // =========================================================================
    
    /**
     * Get delay configuration for a campaign
     * 
     * @param Campaign $campaign
     * @return array{
     *   tier: int,
     *   tier_name: string,
     *   tier_label: string,
     *   tier_emoji: string,
     *   interval_min_ms: int,
     *   interval_max_ms: int,
     *   batch_size: int,
     *   batch_break_ms: int,
     *   typing_indicator: bool,
     *   risk_level: string
     * }
     */
    public function getDelayConfig(Campaign $campaign): array
    {
        $tier = $campaign->speed_tier ?? $this->defaultTier;
        $tierData = $this->tierConfig[$tier] ?? $this->tierConfig[$this->defaultTier];
        
        return [
            'tier' => $tier,
            'tier_name' => $tierData['name'],
            'tier_label' => $tierData['label'],
            'tier_emoji' => $tierData['emoji'],
            'interval_min_ms' => $tierData['interval_min_seconds'] * 1000,
            'interval_max_ms' => $tierData['interval_max_seconds'] * 1000,
            'batch_size' => $tierData['batch_size'],
            'batch_break_ms' => $tierData['batch_break_seconds'] * 1000,
            'typing_indicator' => $tierData['typing_indicator'] ?? false,
            'risk_level' => $tierData['risk_level'],
        ];
    }
    
    /**
     * Calculate random delay with variance
     * 
     * Formula:
     * 1. Pick random base delay between min and max
     * 2. Apply +/- variance percentage
     * 3. Ensure minimum 1 second
     * 
     * @param Campaign $campaign
     * @return int Delay in milliseconds
     */
    public function calculateDelay(Campaign $campaign): int
    {
        $config = $this->getDelayConfig($campaign);
        
        // Random base delay between min and max
        $baseDelay = random_int($config['interval_min_ms'], $config['interval_max_ms']);
        
        // Apply variance for human-like behavior
        $maxVariance = (int)($baseDelay * ($this->variancePercent / 100));
        $varianceValue = random_int(-$maxVariance, $maxVariance);
        
        $finalDelay = $baseDelay + $varianceValue;
        
        // Minimum 1 second (1000ms)
        return max(1000, $finalDelay);
    }
    
    /**
     * Check if batch break is needed
     * 
     * @param Campaign $campaign
     * @param int $currentBatchCount Messages sent in current batch
     * @return bool
     */
    public function needsBatchBreak(Campaign $campaign, int $currentBatchCount): bool
    {
        $config = $this->getDelayConfig($campaign);
        return $currentBatchCount >= $config['batch_size'];
    }
    
    /**
     * Get batch break duration in milliseconds
     * 
     * @param Campaign $campaign
     * @return int
     */
    public function getBatchBreakDuration(Campaign $campaign): int
    {
        $config = $this->getDelayConfig($campaign);
        return $config['batch_break_ms'];
    }
    
    /**
     * Check if typing indicator should be shown
     * 
     * @param Campaign $campaign
     * @return bool
     */
    public function shouldShowTypingIndicator(Campaign $campaign): bool
    {
        $config = $this->getDelayConfig($campaign);
        return $config['typing_indicator'];
    }
    
    /**
     * Get all available tiers for UI dropdown
     * 
     * @return array
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
                'interval_min' => $tierData['interval_min_seconds'],
                'interval_max' => $tierData['interval_max_seconds'],
                'risk_level' => $tierData['risk_level'],
                'risk_color' => $tierData['risk_color'],
                'description' => $tierData['description'],
                'is_default' => $tierData['is_default'] ?? false,
                'show_warning' => $tierData['show_warning'] ?? false,
                'batch_size' => $tierData['batch_size'],
                'batch_break_seconds' => $tierData['batch_break_seconds'],
            ];
        }
        
        return $tiers;
    }
    
    /**
     * Get default tier number
     * 
     * @return int
     */
    public function getDefaultTier(): int
    {
        return $this->defaultTier;
    }
    
    /**
     * Get tier info by number
     * 
     * @param int $tierNumber
     * @return array|null
     */
    public function getTierInfo(int $tierNumber): ?array
    {
        $tierData = $this->tierConfig[$tierNumber] ?? null;
        
        if (!$tierData) {
            return null;
        }
        
        return [
            'value' => $tierNumber,
            'label' => $tierData['label'],
            'emoji' => $tierData['emoji'],
            'interval' => $this->formatInterval($tierData),
            'risk_level' => $tierData['risk_level'],
            'risk_color' => $tierData['risk_color'],
            'description' => $tierData['description'],
        ];
    }
    
    /**
     * Estimate messages per hour for a tier
     * 
     * @param int $tierNumber
     * @return array{min: int, max: int, avg: int}
     */
    public function estimateMessagesPerHour(int $tierNumber): array
    {
        $tierData = $this->tierConfig[$tierNumber] ?? $this->tierConfig[$this->defaultTier];
        
        // Calculate messages per hour based on interval
        // 3600 seconds / interval = messages per hour
        $minPerHour = (int)(3600 / $tierData['interval_max_seconds']);
        $maxPerHour = (int)(3600 / $tierData['interval_min_seconds']);
        $avgPerHour = (int)(($minPerHour + $maxPerHour) / 2);
        
        return [
            'min' => $minPerHour,
            'max' => $maxPerHour,
            'avg' => $avgPerHour,
        ];
    }
    
    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================
    
    /**
     * Format interval for display
     * 
     * @param array $tierData
     * @return string
     */
    private function formatInterval(array $tierData): string
    {
        $min = $tierData['interval_min_seconds'];
        $max = $tierData['interval_max_seconds'];
        
        // Format as minutes if >= 60 seconds
        if ($min >= 60) {
            $minDisplay = $min >= 60 ? ($min / 60) . ' menit' : $min . ' detik';
            $maxDisplay = $max >= 60 ? ($max / 60) . ' menit' : $max . ' detik';
            
            if ($min >= 60 && $max >= 60) {
                return ($min / 60) . '-' . ($max / 60) . ' menit';
            }
        }
        
        return "{$min}-{$max} detik";
    }
}
```

### Step 3.3: Register Service (Optional - Auto-Discovery)

If you want explicit binding, add to `AppServiceProvider`:

```php
// app/Providers/AppServiceProvider.php

use App\Services\Campaign\CampaignSpeedService;

public function register(): void
{
    $this->app->singleton(CampaignSpeedService::class, function ($app) {
        return new CampaignSpeedService();
    });
}
```

---

## Phase 4: Job Integration

### Step 4.1: Update SendCampaignJob

Modify file: `app/Jobs/SendCampaignJob.php`

**Add imports at top:**

```php
use App\Services\Campaign\CampaignSpeedService;
```

**Add properties:**

```php
/**
 * Speed service for tier-based delays
 */
private CampaignSpeedService $speedService;

/**
 * Counter for batch messages
 */
private int $batchMessageCount = 0;
```

**Update constructor:**

```php
public function __construct(
    private Campaign|int $campaign,
    ?ProviderSelectionService $providerService = null,
    ?CampaignSpeedService $speedService = null
) {
    $this->providerService = $providerService ?? app(ProviderSelectionService::class);
    $this->speedService = $speedService ?? app(CampaignSpeedService::class);
    $this->onQueue('whatsapp-campaign');
}
```

**Add delay method:**

```php
/**
 * Apply speed tier delay before sending
 * 
 * @param Campaign $campaign
 * @return void
 */
protected function applySpeedDelay(Campaign $campaign): void
{
    if (!config('campaign.speed_tiers.enabled', true)) {
        return;
    }
    
    // Check if batch break is needed
    if ($this->speedService->needsBatchBreak($campaign, $this->batchMessageCount)) {
        $breakDuration = $this->speedService->getBatchBreakDuration($campaign);
        
        Log::info('Campaign speed: Applying batch break', [
            'campaign_id' => $campaign->id,
            'break_ms' => $breakDuration,
            'batch_count' => $this->batchMessageCount,
        ]);
        
        // Sleep for batch break duration (convert ms to microseconds)
        usleep($breakDuration * 1000);
        
        // Reset batch counter
        $this->batchMessageCount = 0;
    }
    
    // Calculate and apply interval delay
    $delayMs = $this->speedService->calculateDelay($campaign);
    
    Log::debug('Campaign speed: Applying interval delay', [
        'campaign_id' => $campaign->id,
        'delay_ms' => $delayMs,
        'tier' => $campaign->speed_tier,
    ]);
    
    // Sleep for delay duration (convert ms to microseconds)
    usleep($delayMs * 1000);
}
```

**Modify sendTemplateMessage method:**

```php
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
        
        // ========== SPEED TIER DELAY ==========
        $this->applySpeedDelay($campaign);
        // ======================================

        // Mark log as ongoing
        $log->status = 'ongoing';
        $log->save();

        try {
            // ... existing message sending logic ...
            
            // ========== INCREMENT BATCH COUNTER ==========
            if (config('campaign.speed_tiers.enabled', true)) {
                $this->batchMessageCount++;
            }
            // ==============================================
            
        } catch (\Exception $e) {
            // ... existing error handling ...
        }
    });
}
```

---

## Phase 5: Frontend Components

### Step 5.1: Create SpeedTierSelector Component

File: `resources/js/Components/Campaign/SpeedTierSelector.vue`

```vue
<template>
  <div class="space-y-3">
    <!-- Label -->
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
      Kecepatan Pengiriman
      <span class="text-gray-400 font-normal ml-1">(Speed Tier)</span>
    </label>
    
    <!-- Tier Options -->
    <div class="space-y-2">
      <div 
        v-for="tier in tiers" 
        :key="tier.value"
        @click="selectTier(tier.value)"
        :class="[
          'p-4 rounded-lg border-2 cursor-pointer transition-all duration-200',
          selectedTier === tier.value 
            ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 ring-2 ring-primary-200' 
            : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600',
        ]"
      >
        <!-- Tier Header -->
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <!-- Emoji -->
            <span class="text-2xl">{{ tier.emoji }}</span>
            
            <!-- Label & Description -->
            <div>
              <div class="font-medium text-gray-900 dark:text-white flex items-center gap-2">
                {{ tier.label }}
                
                <!-- Recommended Badge -->
                <span 
                  v-if="tier.is_default" 
                  class="text-xs px-2 py-0.5 bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 rounded-full"
                >
                  ⭐ Recommended
                </span>
                
                <!-- Selected Check -->
                <svg 
                  v-if="selectedTier === tier.value"
                  class="w-5 h-5 text-primary-500" 
                  fill="currentColor" 
                  viewBox="0 0 20 20"
                >
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
              </div>
              
              <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                {{ tier.description }}
              </div>
            </div>
          </div>
          
          <!-- Interval Display -->
          <div class="text-right">
            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
              {{ tier.interval }}
            </div>
            <div class="text-xs text-gray-400">
              per message
            </div>
          </div>
        </div>
        
        <!-- Risk & Stats Row -->
        <div class="mt-3 flex items-center justify-between">
          <!-- Risk Badge -->
          <span 
            :class="[
              'inline-flex items-center px-2.5 py-0.5 text-xs font-medium rounded-full',
              getRiskBadgeClass(tier.risk_level)
            ]"
          >
            <span class="mr-1">{{ getRiskIcon(tier.risk_level) }}</span>
            Risk: {{ formatRiskLevel(tier.risk_level) }}
          </span>
          
          <!-- Estimated Speed -->
          <span class="text-xs text-gray-400">
            ~{{ estimateMessagesPerHour(tier) }} msg/hour
          </span>
        </div>
        
        <!-- Warning for Aggressive Tier -->
        <div 
          v-if="tier.show_warning && selectedTier === tier.value" 
          class="mt-3 p-3 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800"
        >
          <div class="flex items-start gap-2">
            <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <div>
              <p class="text-sm font-medium text-red-800 dark:text-red-300">
                Warning: High Ban Risk
              </p>
              <p class="text-xs text-red-600 dark:text-red-400 mt-1">
                Kecepatan ini dapat menyebabkan akun WhatsApp ter-ban. 
                Gunakan hanya jika Anda memahami risikonya.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Help Text -->
    <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">
      💡 Tier dengan interval lebih lama lebih aman tetapi lebih lambat.
      Pilih sesuai kebutuhan dan toleransi risiko Anda.
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

// Props
const props = defineProps({
  modelValue: { 
    type: Number, 
    default: 2 
  },
  tiers: { 
    type: Array, 
    required: true 
  },
})

// Emits
const emit = defineEmits(['update:modelValue'])

// Computed
const selectedTier = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

// Methods
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

const getRiskIcon = (riskLevel) => {
  const icons = {
    'very_low': '🟢',
    'low': '🟢',
    'medium': '🟡',
    'high': '🟠',
    'very_high': '🔴',
  }
  return icons[riskLevel] || '🟡'
}

const formatRiskLevel = (riskLevel) => {
  return riskLevel.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
}

const estimateMessagesPerHour = (tier) => {
  // Calculate based on average interval
  const avgInterval = (tier.interval_min + tier.interval_max) / 2
  const messagesPerHour = Math.floor(3600 / avgInterval)
  return messagesPerHour
}
</script>
```

### Step 5.2: Update Campaign Create Form

Modify: `resources/js/Pages/User/Campaigns/Create.vue`

**Add import:**

```vue
<script setup>
import SpeedTierSelector from '@/Components/Campaign/SpeedTierSelector.vue'
// ... other imports
</script>
```

**Add prop:**

```javascript
const props = defineProps({
  // ... existing props
  speedTiers: { type: Array, default: () => [] },
  defaultSpeedTier: { type: Number, default: 2 },
})
```

**Add to form data:**

```javascript
const form = useForm({
  // ... existing fields
  speed_tier: props.defaultSpeedTier,
})
```

**Add component in template:**

```vue
<template>
  <!-- ... existing form fields ... -->
  
  <!-- Speed Tier Selection -->
  <div class="mt-6">
    <SpeedTierSelector 
      v-model="form.speed_tier"
      :tiers="speedTiers"
    />
    <InputError :message="form.errors.speed_tier" class="mt-2" />
  </div>
  
  <!-- ... rest of form ... -->
</template>
```

### Step 5.3: Update Campaign Edit Form

Same pattern as Create, add to `Edit.vue`:

```javascript
const form = useForm({
  // ... existing fields
  speed_tier: props.campaign.speed_tier || props.defaultSpeedTier,
})
```

---

## Phase 6: Controller Updates

### Step 6.1: Update CampaignController

File: `app/Http/Controllers/User/CampaignController.php`

**Add import:**

```php
use App\Services\Campaign\CampaignSpeedService;
```

**Update create method:**

```php
public function create(CampaignSpeedService $speedService)
{
    return Inertia::render('User/Campaigns/Create', [
        // ... existing props
        'speedTiers' => $speedService->getAvailableTiers(),
        'defaultSpeedTier' => $speedService->getDefaultTier(),
    ]);
}
```

**Update edit method:**

```php
public function edit(Campaign $campaign, CampaignSpeedService $speedService)
{
    return Inertia::render('User/Campaigns/Edit', [
        'campaign' => $campaign,
        // ... existing props
        'speedTiers' => $speedService->getAvailableTiers(),
        'defaultSpeedTier' => $speedService->getDefaultTier(),
    ]);
}
```

**Update store/update validation:**

```php
public function store(Request $request)
{
    $validated = $request->validate([
        // ... existing validation
        'speed_tier' => 'nullable|integer|min:1|max:5',
    ]);
    
    // Set default if not provided
    $validated['speed_tier'] = $validated['speed_tier'] ?? config('campaign.speed_tiers.default_tier', 2);
    
    // ... create campaign
}

public function update(Request $request, Campaign $campaign)
{
    $validated = $request->validate([
        // ... existing validation
        'speed_tier' => 'nullable|integer|min:1|max:5',
    ]);
    
    // ... update campaign
}
```

---

## Phase 7: Model Updates

### Step 7.1: Update Campaign Model

File: `app/Models/Campaign.php`

**Add to fillable:**

```php
protected $fillable = [
    // ... existing fields
    'speed_tier',
];
```

**Add cast:**

```php
protected $casts = [
    // ... existing casts
    'speed_tier' => 'integer',
];
```

**Add accessor for tier info (optional):**

```php
/**
 * Get speed tier info
 */
public function getSpeedTierInfoAttribute(): ?array
{
    $speedService = app(\App\Services\Campaign\CampaignSpeedService::class);
    return $speedService->getTierInfo($this->speed_tier ?? 2);
}
```

---

## Testing Checklist

### Unit Tests

```php
// tests/Unit/Services/CampaignSpeedServiceTest.php

<?php

namespace Tests\Unit\Services;

use App\Services\Campaign\CampaignSpeedService;
use App\Models\Campaign;
use Tests\TestCase;

class CampaignSpeedServiceTest extends TestCase
{
    private CampaignSpeedService $service;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CampaignSpeedService();
    }
    
    /** @test */
    public function it_returns_all_available_tiers()
    {
        $tiers = $this->service->getAvailableTiers();
        
        $this->assertCount(5, $tiers);
        $this->assertEquals(1, $tiers[0]['value']);
        $this->assertEquals(5, $tiers[4]['value']);
    }
    
    /** @test */
    public function it_returns_default_tier()
    {
        $defaultTier = $this->service->getDefaultTier();
        
        $this->assertEquals(2, $defaultTier);
    }
    
    /** @test */
    public function it_calculates_delay_within_tier_range()
    {
        $campaign = new Campaign(['speed_tier' => 2]);
        
        $delay = $this->service->calculateDelay($campaign);
        
        // Tier 2: 45-60s = 45000-60000ms + variance
        $this->assertGreaterThanOrEqual(30000, $delay); // Min with variance
        $this->assertLessThanOrEqual(80000, $delay);    // Max with variance
    }
    
    /** @test */
    public function it_detects_batch_break_needed()
    {
        $campaign = new Campaign(['speed_tier' => 2]); // Batch size: 20
        
        $this->assertFalse($this->service->needsBatchBreak($campaign, 10));
        $this->assertFalse($this->service->needsBatchBreak($campaign, 19));
        $this->assertTrue($this->service->needsBatchBreak($campaign, 20));
        $this->assertTrue($this->service->needsBatchBreak($campaign, 25));
    }
    
    /** @test */
    public function it_returns_batch_break_duration()
    {
        $campaign = new Campaign(['speed_tier' => 2]); // Break: 180s = 180000ms
        
        $duration = $this->service->getBatchBreakDuration($campaign);
        
        $this->assertEquals(180000, $duration);
    }
}
```

### Feature Tests

```php
// tests/Feature/CampaignSpeedTierTest.php

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Campaign;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignSpeedTierTest extends TestCase
{
    use RefreshDatabase;
    
    /** @test */
    public function user_can_select_speed_tier_when_creating_campaign()
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        
        $response = $this->actingAs($user)
            ->post(route('user.campaigns.store'), [
                'name' => 'Test Campaign',
                'speed_tier' => 3,
                // ... other required fields
            ]);
        
        $this->assertDatabaseHas('campaigns', [
            'name' => 'Test Campaign',
            'speed_tier' => 3,
        ]);
    }
    
    /** @test */
    public function speed_tier_defaults_to_2_if_not_provided()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->post(route('user.campaigns.store'), [
                'name' => 'Test Campaign',
                // speed_tier not provided
            ]);
        
        $this->assertDatabaseHas('campaigns', [
            'name' => 'Test Campaign',
            'speed_tier' => 2,
        ]);
    }
    
    /** @test */
    public function speed_tier_must_be_between_1_and_5()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->post(route('user.campaigns.store'), [
                'name' => 'Test Campaign',
                'speed_tier' => 6, // Invalid
            ]);
        
        $response->assertSessionHasErrors('speed_tier');
    }
}
```

### Manual Testing Checklist

- [ ] Create campaign with Tier 1 (Paranoid) - verify 90-120s delay
- [ ] Create campaign with Tier 2 (Safe) - verify 45-60s delay
- [ ] Create campaign with Tier 3 (Balanced) - verify 30-45s delay
- [ ] Create campaign with Tier 4 (Fast) - verify 20-30s delay
- [ ] Create campaign with Tier 5 (Aggressive) - verify 10-20s delay + warning
- [ ] Verify batch break happens after batch_size messages
- [ ] Verify interval variance (delays should vary)
- [ ] Edit campaign and change speed tier
- [ ] Check logs show correct tier and delay values

---

## Deployment Guide

### Pre-Deployment Checklist

```bash
# 1. Run migrations
php artisan migrate

# 2. Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# 3. Rebuild assets
npm run build

# 4. Restart queue workers
php artisan queue:restart
```

### Rollback Plan

```bash
# If issues occur, rollback migration
php artisan migrate:rollback --step=1

# Disable feature via .env
CAMPAIGN_SPEED_TIERS_ENABLED=false
```

### Monitoring

Watch for these log entries:

```
# Successful delay application
Campaign speed: Applying interval delay {campaign_id, delay_ms, tier}

# Batch break
Campaign speed: Applying batch break {campaign_id, break_ms, batch_count}

# Errors
Failed to apply speed delay {campaign_id, error}
```

---

## Quick Reference Card

### Tier Summary Table

| Tier | Name | Interval | Batch | Break | Risk |
|------|------|----------|-------|-------|------|
| 1 | Paranoid | 90-120s | 10 | 5m | 🟢 |
| 2 | Safe ⭐ | 45-60s | 20 | 3m | 🟢 |
| 3 | Balanced | 30-45s | 25 | 2.5m | 🟡 |
| 4 | Fast | 20-30s | 30 | 2m | 🟠 |
| 5 | Aggressive | 10-20s | 40 | 1.5m | 🔴 |

### Key Files

| Purpose | File Path |
|---------|-----------|
| Config | `config/campaign.php` |
| Service | `app/Services/Campaign/CampaignSpeedService.php` |
| Job | `app/Jobs/SendCampaignJob.php` |
| Model | `app/Models/Campaign.php` |
| Vue Component | `resources/js/Components/Campaign/SpeedTierSelector.vue` |
| Controller | `app/Http/Controllers/User/CampaignController.php` |

### Useful Commands

```bash
# Check config values
php artisan tinker
>>> config('campaign.speed_tiers.tiers')

# Test delay calculation
>>> $service = app(App\Services\Campaign\CampaignSpeedService::class)
>>> $campaign = App\Models\Campaign::find(1)
>>> $service->calculateDelay($campaign)
```

---

## References

- [01 - Re-Evaluasi Tier Structure](./01-re-evaluasi-tier-structure.md)
- [02 - Anti-Ban System Design](./02-anti-ban-system-design.md)
- [Development Patterns](../../architecture/06-development-patterns-guidelines.md)

---

**Document Version:** 1.0  
**Created:** November 28, 2025  
**Author:** AI Assistant  
**Status:** Ready for Implementation
