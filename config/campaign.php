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

    /*
    |--------------------------------------------------------------------------
    | Mobile Conflict Detection Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for detecting conflicts between campaign messaging
    | and mobile WhatsApp activity.
    |
    */

    'mobile_conflict' => [
        /*
        |--------------------------------------------------------------------------
        | Enable Mobile Conflict Detection
        |--------------------------------------------------------------------------
        |
        | Enable/disable the mobile conflict detection system.
        |
        */
        'enabled' => env('CAMPAIGN_CONFLICT_ENABLED', true),

        /*
        |--------------------------------------------------------------------------
        | Queue Name
        |--------------------------------------------------------------------------
        |
        | Queue name for conflict resolution jobs.
        |
        */
        'queue' => env('CAMPAIGN_CONFLICT_QUEUE', 'campaign-conflict'),

        /*
        |--------------------------------------------------------------------------
        | Default Cooldown Period
        |--------------------------------------------------------------------------
        |
        | Default cooldown period in seconds if tier not determined.
        |
        */
        'default_cooldown_seconds' => env('CAMPAIGN_CONFLICT_DEFAULT_COOLDOWN', 30),

        /*
        |--------------------------------------------------------------------------
        | Maximum Resume Attempts
        |--------------------------------------------------------------------------
        |
        | Maximum attempts to auto-resume before forcing resume.
        |
        */
        'max_resume_attempts' => env('CAMPAIGN_CONFLICT_MAX_ATTEMPTS', 5),

        /*
        |--------------------------------------------------------------------------
        | Tier-Based Cooldown Periods
        |--------------------------------------------------------------------------
        |
        | Cooldown periods in seconds based on WhatsApp account tier.
        | Higher tier (more trusted) = faster resume.
        |
        */
        'tier_cooldown' => [
            1 => 60,  // Tier 1: New account - conservative
            2 => 45,  // Tier 2: Warming up
            3 => 30,  // Tier 3: Established
            4 => 20,  // Tier 4: Trusted
        ],

        /*
        |--------------------------------------------------------------------------
        | Trigger Device Types
        |--------------------------------------------------------------------------
        |
        | Device types that trigger campaign pause.
        | 'web' is excluded because it's our own client.
        |
        */
        'trigger_device_types' => ['android', 'ios', 'unknown'],
    ],

];
