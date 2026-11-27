<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'local' => ['driver' => 'local', 'root' => storage_path('app/private'), 'serve' => false, 'throw' => true],
        'public' => ['driver' => 'local', 'root' => storage_path('app/public'), 'url' => env('APP_URL').'/storage', 'visibility' => 'public', 'throw' => true],
        'tenant' => ['driver' => 'local', 'root' => storage_path('app/private/tenants'), 'serve' => false, 'throw' => true],
    ],
    'links' => [public_path('storage') => storage_path('app/public')],
];
