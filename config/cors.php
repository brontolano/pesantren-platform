<?php

/*
 * CORS untuk API publik (path api/*), dipanggil website tenant lintas-origin.
 *
 * "Per-domain tenant": selain daftar origin eksplisit, tiap subdomain di bawah
 * apex platform (mis. *.asy-syifaa.com, *.pesantren.online) diizinkan via pola —
 * jadi domain tiap tenant baru otomatis tercakup tanpa edit config. Semua
 * dapat di-override lewat env (aman utk config:cache karena nilai dihitung saat
 * cache dibuat saat deploy).
 */

$splitCsv = static fn (string $csv): array => array_values(array_filter(
    array_map('trim', explode(',', $csv))
));

$origins = $splitCsv((string) env(
    'CORS_ALLOWED_ORIGINS',
    'https://asy-syifaa.com,https://www.asy-syifaa.com'
));

// Apex platform → pola regex: izinkan apex itu sendiri + semua subdomain-nya.
// Wajib diakhiri tepat di apex ($), tiap label di depan harus diikuti titik —
// mencegah origin tipuan seperti https://evilasy-syifaa.com.
$apexes = $splitCsv((string) env('CORS_APEX_DOMAINS', 'asy-syifaa.com,pesantren.online'));
$patterns = array_map(
    static fn (string $apex): string => '#^https://([a-z0-9-]+\.)*'.preg_quote($apex, '#').'$#i',
    $apexes
);

// Pola tambahan eksplisit (opsional) dari env.
foreach ($splitCsv((string) env('CORS_ALLOWED_ORIGIN_PATTERNS', '')) as $pattern) {
    $patterns[] = $pattern;
}

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => $patterns,

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,
];
