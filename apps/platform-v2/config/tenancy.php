<?php

return [
    'invitation_ttl_minutes' => (int) env('TENANT_INVITATION_TTL_MINUTES', 30),
    'database_prefix' => env('TENANT_DATABASE_PREFIX', 'po_v2_t_'),
    'storage_root' => env('TENANT_STORAGE_ROOT', 'app/private/tenants'),
    'testing_driver' => env('TENANT_TEST_DRIVER', 'sqlite'),
    'demo_domain' => env('TENANT_DEMO_DOMAIN', 'demo-pesantren-v2.test'),
    'demo_operator_email' => env('DEMO_OPERATOR_EMAIL', ''),
    'demo_operator_password' => env('DEMO_OPERATOR_PASSWORD', ''),
];
