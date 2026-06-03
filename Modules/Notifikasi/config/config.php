<?php

return [
    'n8n_webhook_url' => env('N8N_NOTIFICATION_WEBHOOK_URL'),
    'waha_base_url' => env('WAHA_BASE_URL'),
    'waha_session' => env('WAHA_SESSION', 'default'),
    'default_email_from' => env('MAIL_FROM_ADDRESS'),
];
