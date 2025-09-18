<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | PHASE-3 Performance optimization settings untuk monitoring,
    | caching, dan database query optimization
    |
    */

    'query_monitoring' => [
        'enabled' => env('QUERY_MONITORING_ENABLED', true),
        'slow_query_threshold' => env('SLOW_QUERY_THRESHOLD', 100), // milliseconds
        'max_queries_warning' => env('MAX_QUERIES_WARNING', 20),
        'log_slow_queries' => env('LOG_SLOW_QUERIES', true),
        'log_performance_metrics' => env('LOG_PERFORMANCE_METRICS', true),
    ],

    'caching' => [
        'default_ttl' => [
            'short' => env('CACHE_TTL_SHORT', 300),      // 5 minutes
            'medium' => env('CACHE_TTL_MEDIUM', 1800),   // 30 minutes
            'long' => env('CACHE_TTL_LONG', 3600),       // 1 hour
            'extended' => env('CACHE_TTL_EXTENDED', 86400), // 24 hours
        ],

        'tags' => [
            'enabled' => env('CACHE_TAGS_ENABLED', true),
            'chat_prefix' => 'chat',
            'user_prefix' => 'user',
            'organization_prefix' => 'org',
            'contact_prefix' => 'contact',
        ],

        'invalidation' => [
            'auto_invalidate' => env('CACHE_AUTO_INVALIDATE', true),
            'batch_invalidation' => env('CACHE_BATCH_INVALIDATION', true),
        ],
    ],

    'database_optimization' => [
        'enable_indexes' => env('DB_ENABLE_PERFORMANCE_INDEXES', true),
        'query_analysis' => env('DB_QUERY_ANALYSIS', true),
        'connection_pooling' => env('DB_CONNECTION_POOLING', false),
    ],

    'redis' => [
        'clustering' => [
            'enabled' => env('REDIS_CLUSTERING_ENABLED', false),
            'nodes' => env('REDIS_CLUSTER_NODES', '127.0.0.1:7000,127.0.0.1:7001,127.0.0.1:7002'),
        ],
        'performance' => [
            'pipeline_enabled' => env('REDIS_PIPELINE_ENABLED', true),
            'compression' => env('REDIS_COMPRESSION', true),
            'serialization' => env('REDIS_SERIALIZATION', 'igbinary'),
        ],
    ],

    'asset_optimization' => [
        'enabled' => env('ASSET_OPTIMIZATION_ENABLED', true),
        'minification' => env('ASSET_MINIFICATION', true),
        'compression' => env('ASSET_COMPRESSION', true),
        'cdn_enabled' => env('CDN_ENABLED', false),
        'cdn_url' => env('CDN_URL', ''),
    ],

    'monitoring' => [
        'metrics_collection' => env('METRICS_COLLECTION_ENABLED', true),
        'performance_alerts' => env('PERFORMANCE_ALERTS_ENABLED', true),
        'dashboard_enabled' => env('PERFORMANCE_DASHBOARD_ENABLED', true),
        'export_metrics' => env('EXPORT_METRICS_ENABLED', false),
        'request_logging' => env('REQUEST_LOGGING_ENABLED', false),
    ],

    'rate_limiting' => [
        'enabled' => env('RATE_LIMITING_ENABLED', true),
        'redis_based' => env('RATE_LIMITING_REDIS', true),
        'aggressive_mode' => env('RATE_LIMITING_AGGRESSIVE', false),
        'whitelist_ips' => env('RATE_LIMITING_WHITELIST', '127.0.0.1,::1'),
    ],

    'security' => [
        'audit_logging' => env('AUDIT_LOGGING_ENABLED', true),
        'security_headers' => env('SECURITY_HEADERS_ENABLED', true),
        'csrf_enhanced' => env('CSRF_ENHANCED_ENABLED', true),
        'suspicious_activity_detection' => env('SUSPICIOUS_ACTIVITY_DETECTION', true),
        'auto_block_threats' => env('AUTO_BLOCK_THREATS', false),
    ],

];
