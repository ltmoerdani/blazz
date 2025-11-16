<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    ],

    'paypal' => [
        'class' => App\Services\PayPalService::class,
    ],

    'stripe' => [
        'class' => App\Services\StripeService::class,
    ],

    'paystack' => [
        'class' => App\Services\PayStackService::class,
    ],

    'flutterwave' => [
        'class' => App\Services\FlutterwaveService::class,
    ],

    // 'clickpay' => [
    //     'class' => Modules\Clickpaysa\Controllers\ProcessPayment::class,
    // ],

    'razorpay' => [
        'class' => Modules\Razorpay\Controllers\ProcessPayment::class,
    ],

    // 'pabbly subscriptions' => [
    //     'class' => Modules\Pabbly\Controllers\ProcessPayment::class,
    // ],

    // WhatsApp Web.js Node.js Service Configuration
    'whatsapp' => [
        'nodejs_url' => env('WHATSAPP_NODE_SERVICE_URL', 'http://localhost:3001'),
        'api_key' => env('WHATSAPP_API_KEY'),
        'hmac_secret' => env('WHATSAPP_HMAC_SECRET'),
        'timeout' => env('WHATSAPP_TIMEOUT', 30),
        'retry_attempts' => env('WHATSAPP_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('WHATSAPP_RETRY_DELAY', 1000),
    ],
];




