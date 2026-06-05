<?php

return [
    // Default ke webhook n8n yang sama dgn PPDB (pipe ppdb-wa sudah generik: terima to/target).
    // Broadcast & push WA jadi otomatis lewat pipa n8n→WAHA yang terverifikasi.
    'n8n_webhook_url' => env('N8N_NOTIFICATION_WEBHOOK_URL', env('PPDB_WA_WEBHOOK_URL', 'http://n8n:5678/webhook/ppdb-wa')),
    'waha_base_url' => env('WAHA_BASE_URL'),
    'waha_session' => env('WAHA_SESSION', 'default'),
    'default_email_from' => env('MAIL_FROM_ADDRESS'),
];
