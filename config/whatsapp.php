<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Integration Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for WhatsApp integrations including
    | Meta API and WhatsApp Web.js settings.
    |
    */

        /*
    |--------------------------------------------------------------------------
    | WhatsApp Web.js Node Service URL
    |--------------------------------------------------------------------------
    |
    | URL endpoint untuk Node.js service yang menjalankan WhatsApp Web.js
    | Default: http://localhost:3001 (verified running via health check)
    |
    */
    'node_service_url' => env('WHATSAPP_NODE_SERVICE_URL', 'http://localhost:3001'),
    'node_api_key' => env('WHATSAPP_NODE_API_KEY', 'your-node-api-key'),
    'node_api_secret' => env('HMAC_SECRET', env('WHATSAPP_NODE_API_SECRET', 'your-node-api-secret')),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Web.js Settings
    |--------------------------------------------------------------------------
    */

    'webjs' => [
        'session_timeout' => env('WHATSAPP_WEBJS_SESSION_TIMEOUT', 3600), // 1 hour
        'max_sessions_per_workspace' => env('WHATSAPP_WEBJS_MAX_SESSIONS', 5),
        'qr_code_timeout' => env('WHATSAPP_WEBJS_QR_TIMEOUT', 300), // 5 minutes
        'health_check_interval' => env('WHATSAPP_WEBJS_HEALTH_CHECK_INTERVAL', 30), // 30 seconds
        'auto_reconnect' => env('WHATSAPP_WEBJS_AUTO_RECONNECT', true),
        'max_reconnect_attempts' => env('WHATSAPP_WEBJS_MAX_RECONNECT_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */

    'rate_limiting' => [
        'messages_per_minute' => env('WHATSAPP_RATE_LIMIT_MESSAGES_PER_MINUTE', 30),
        'messages_per_hour' => env('WHATSAPP_RATE_LIMIT_MESSAGES_PER_HOUR', 1000),
        'unique_contacts_per_day' => env('WHATSAPP_RATE_LIMIT_CONTACTS_PER_DAY', 500),
        'broadcast_size_limit' => env('WHATSAPP_BROADCAST_SIZE_LIMIT', 256),

        // Sync API rate limiting (TASK-SEC-2)
        'sync_max_requests' => env('WHATSAPP_SYNC_MAX_REQUESTS', 60), // requests per minute per session
        'sync_decay_minutes' => env('WHATSAPP_SYNC_DECAY_MINUTES', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Management
    |--------------------------------------------------------------------------
    */

    'sessions' => [
        'storage_path' => env('WHATSAPP_SESSIONS_PATH', storage_path('app/whatsapp-sessions')),
        'cleanup_interval' => env('WHATSAPP_SESSION_CLEANUP_INTERVAL', 24), // hours
        'max_inactive_time' => env('WHATSAPP_SESSION_MAX_INACTIVE_TIME', 72), // hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */

    'security' => [
        'hmac_algorithm' => env('WHATSAPP_HMAC_ALGORITHM', 'sha256'),
        'signature_ttl' => env('WHATSAPP_SIGNATURE_TTL', 300), // 5 minutes
        'encrypt_session_data' => env('WHATSAPP_ENCRYPT_SESSION_DATA', true),
        'allowed_ips' => env('WHATSAPP_ALLOWED_IPS', ''), // comma-separated IPs for production
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Health Checks
    |--------------------------------------------------------------------------
    */

    'monitoring' => [
        'health_check_enabled' => env('WHATSAPP_HEALTH_CHECK_ENABLED', true),
        'metrics_enabled' => env('WHATSAPP_METRICS_ENABLED', true),
        'alert_thresholds' => [
            'memory_usage' => env('WHATSAPP_ALERT_MEMORY_USAGE', 80), // percentage
            'error_rate' => env('WHATSAPP_ALERT_ERROR_RATE', 10), // percentage
            'response_time' => env('WHATSAPP_ALERT_RESPONSE_TIME', 5000), // milliseconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    */

    'sync' => [
        'initial_window_days' => env('WHATSAPP_SYNC_WINDOW_DAYS', 30),
        'max_chats_per_sync' => env('WHATSAPP_SYNC_MAX_CHATS', 500),
        'batch_size' => env('WHATSAPP_SYNC_BATCH_SIZE', 50),
        'rate_limit_per_second' => env('WHATSAPP_SYNC_RATE_LIMIT', 10),
        'incremental_interval' => env('WHATSAPP_SYNC_INCREMENTAL_INTERVAL', 6), // hours
    ],
];
