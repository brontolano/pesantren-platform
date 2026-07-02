<?php

return [
    // Default ke webhook n8n yang sama dgn PPDB (pipe ppdb-wa sudah generik: terima to/target).
    // Broadcast & push WA jadi otomatis lewat pipa n8n→WAHA yang terverifikasi.
    'n8n_webhook_url' => env('N8N_NOTIFICATION_WEBHOOK_URL', env('PPDB_WA_WEBHOOK_URL', 'http://n8n:5678/webhook/ppdb-wa')),
    'waha_base_url' => env('WAHA_BASE_URL'),
    'waha_session' => env('WAHA_SESSION', 'default'),
    // API key WAHA (opsional). Bila WAHA dijalankan dgn WAHA_API_KEY, kirim lewat
    // header 'X-Api-Key'. Kosongkan bila instance WAHA tanpa auth.
    'waha_api_key' => env('WAHA_API_KEY'),
    'default_email_from' => env('MAIL_FROM_ADDRESS'),

    // Jeda antar-pesan saat broadcast WA (detik) untuk hindari rate-limit/blokir. Default 10 dtk.
    'broadcast_interval_seconds' => (int) env('BROADCAST_WA_INTERVAL_SECONDS', 10),
];
