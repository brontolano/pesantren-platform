<?php

return [
    /*
     * CORS untuk API publik konten CMS (dipanggil website tenant lintas-subdomain,
     * mis. asy-syifaa.com → api.asy-syifaa.com). Hanya path api/* + origin website.
     */
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'OPTIONS'],

    'allowed_origins' => [
        'https://asy-syifaa.com',
        'https://www.asy-syifaa.com',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,
];
