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
    
    /**
     * Check if speed tier system is enabled
     * 
     * @return bool
     */
    public function isEnabled(): bool
    {
        return config('campaign.speed_tiers.enabled', true);
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
        if ($min >= 60 && $max >= 60) {
            return ($min / 60) . '-' . ($max / 60) . ' menit';
        }
        
        return "{$min}-{$max} detik";
    }
}
