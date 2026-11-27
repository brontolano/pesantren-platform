<?php

return [
    'defaults' => ['guard' => 'platform', 'passwords' => 'platform-operators'],
    'guards' => [
        'platform' => ['driver' => 'session', 'provider' => 'platform-operators'],
        'tenant' => ['driver' => 'session', 'provider' => 'tenant-admins'],
    ],
    'providers' => [
        'platform-operators' => ['driver' => 'eloquent', 'model' => App\Models\PlatformOperator::class],
        'tenant-admins' => ['driver' => 'eloquent', 'model' => App\Models\TenantAdmin::class],
    ],
    'passwords' => [
        'platform-operators' => ['provider' => 'platform-operators', 'table' => 'password_reset_tokens', 'expire' => 30, 'throttle' => 60],
        'tenant-admins' => ['provider' => 'tenant-admins', 'table' => 'password_reset_tokens', 'expire' => 30, 'throttle' => 60],
    ],
    'password_timeout' => 10800,
];
