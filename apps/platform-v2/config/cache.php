<?php

return [
    'default' => env('CACHE_STORE', 'database'),
    'stores' => [
        'array' => ['driver' => 'array', 'serialize' => false],
        'database' => ['driver' => 'database', 'connection' => env('DB_CONNECTION', 'central'), 'table' => 'cache', 'lock_connection' => env('DB_CONNECTION', 'central'), 'lock_table' => 'cache_locks'],
    ],
    'prefix' => env('CACHE_PREFIX', 'pesantren_v2_cache'),
];
