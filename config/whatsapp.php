<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Instance Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration controls the multi-instance architecture for WhatsApp
    | services. The instance count determines how workspaces are sharded
    | across available Node.js instances.
    |
    */

    // Number of active WhatsApp Node.js instances
    'instance_count' => env('WHATSAPP_INSTANCE_COUNT', 1),

    // Instance URLs mapped by index (0-based)
    'instances' => [
        0 => env('WHATSAPP_INSTANCE_1', 'http://localhost:3001'),
        1 => env('WHATSAPP_INSTANCE_2', 'http://localhost:3002'),
        2 => env('WHATSAPP_INSTANCE_3', 'http://localhost:3003'),
        3 => env('WHATSAPP_INSTANCE_4', 'http://localhost:3004'),
    ],

    /*
    |--------------------------------------------------------------------------
    | DEPRECATED: Single Instance Configuration
    |--------------------------------------------------------------------------
    |
    | The following configuration is DEPRECATED as of Nov 20, 2025.
    | Use the multi-instance configuration above instead.
    |
    | Legacy code should use:
    | - WhatsAppAccount->assigned_instance_url (for existing sessions)
    | - InstanceRouter->getInstanceForWorkspace() (for new sessions)
    |
    */

    'node_service_url' => env('WHATSAPP_NODE_URL', null), // DEPRECATED - DO NOT USE

    // Internal API token for secure communication between Laravel and Node.js
    'internal_token' => env('WHATSAPP_INTERNAL_TOKEN', 'secret-internal-token'),
    
    // Node.js API key for authenticated requests
    'node_api_key' => env('WHATSAPP_NODE_API_KEY', env('LARAVEL_API_TOKEN', 'default-api-key')),
    
    // Webhook secret for HMAC verification
    'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET', env('LARAVEL_API_TOKEN', 'default-webhook-secret')),
    
    // HMAC secret for webhook signature verification (must match whatsapp-service HMAC_SECRET)
    'node_api_secret' => env('WHATSAPP_HMAC_SECRET', env('HMAC_SECRET', null)),

    // Security configuration
    'security' => [
        'hmac_algorithm' => 'sha256',
        'timestamp_max_age' => 300, // seconds (5 minutes)
    ],

    // Health check configuration
    'health_check' => [
        'interval' => 60, // seconds
        'timeout' => 5, // seconds
    ],

    // Session capacity limits
    'limits' => [
        'max_sessions_per_instance' => env('WHATSAPP_MAX_SESSIONS_PER_INSTANCE', 500),
    ],
];
