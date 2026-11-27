<?php

$pgsql = [
    'driver' => 'pgsql',
    'url' => env('DB_URL'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'pesantren_v2_central'),
    'username' => env('DB_USERNAME', 'pesantren_v2'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,
    'search_path' => 'public',
    'sslmode' => env('DB_SSLMODE', 'prefer'),
];

return [
    'default' => env('DB_CONNECTION', 'central'),
    'connections' => [
        'central' => $pgsql,
        'tenant' => $pgsql,
        'provisioner' => [
            ...$pgsql,
            'database' => 'postgres',
            'username' => env('DB_PROVISIONER_USERNAME', 'postgres'),
            'password' => env('DB_PROVISIONER_PASSWORD', ''),
        ],
    ],
    'migrations' => ['table' => 'migrations', 'update_date_on_publish' => true],
    'redis' => ['client' => 'phpredis', 'options' => ['cluster' => env('REDIS_CLUSTER', 'redis'), 'prefix' => env('REDIS_PREFIX', 'pesantren_v2_database_')]],
];
