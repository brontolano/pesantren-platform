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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Notifikasi WhatsApp PPDB via n8n → WAHA (jaringan internal Docker).
    // Override di .env bila endpoint berubah. Default = service `n8n` di compose.
    'ppdb_wa' => [
        'webhook' => env('PPDB_WA_WEBHOOK_URL', 'http://n8n:5678/webhook/ppdb-wa'),
    ],

    // API publik headless per tenant: batas laju default (per menit).
    // Dipakai App\Support\PublicApiThrottle untuk named limiter 'public-api'.
    'public_api' => [
        'rate_per_minute' => (int) env('PUBLIC_API_RATE_PER_MINUTE', 60),
    ],

    // Anggaran harian OTP WhatsApp jemaah per tenant (pagar biaya/anti-abuse,
    // red-team RATE-001/AUTH-8). Longgar agar tak ganggu pemakaian wajar; 0 = mati.
    'jemaah' => [
        'otp_daily_budget' => (int) env('JEMAAH_OTP_DAILY_BUDGET', 300),
    ],

];
