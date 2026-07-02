<?php

// Konfigurasi DNS custom domain tenant (Refs #350 poin 4).
// Dua opsi: manual (pesantren set A record sendiri) + otomasi (API Hostinger DNS).
return [

    // IP publik VPS platform — target A record custom domain.
    // Jangan hardcode di blade/UI; selalu baca dari sini.
    'server_ip' => env('PLATFORM_SERVER_IP', '187.77.116.167'),

    // Otomasi pembuatan A record via API Hostinger DNS
    // (https://developers.hostinger.com). Hanya berlaku bila zona domain
    // dikelola di akun Hostinger owner. Token opsional — tanpa token,
    // opsi otomasi tampil nonaktif di UI.
    'hostinger' => [
        'token' => env('HOSTINGER_API_TOKEN'),
        'base_url' => env('HOSTINGER_API_BASE_URL', 'https://developers.hostinger.com'),
        'timeout' => (int) env('HOSTINGER_API_TIMEOUT', 8),
        'ttl' => (int) env('HOSTINGER_DNS_TTL', 3600),
    ],

];
