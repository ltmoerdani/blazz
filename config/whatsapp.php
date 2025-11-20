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

    // Internal API token for secure communication between Laravel and Node.js
    'internal_token' => env('WHATSAPP_INTERNAL_TOKEN', 'secret-internal-token'),

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
