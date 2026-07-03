<?php

use App\Support\ThemeThumbnail;
use Illuminate\Contracts\Console\Kernel;

/**
 * Generator thumbnail SVG statis untuk tema panel.
 * Menulis public/images/theme-thumbnails/{key}.svg dari palet asli tiap tema.
 *
 * Jalankan: php scripts/generate-theme-thumbnails.php
 * (Idempoten — aman dijalankan ulang; regenerasi bila palet tema berubah.)
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$written = ThemeThumbnail::writeAll();

foreach ($written as $path) {
    echo 'wrote: '.$path.PHP_EOL;
}

echo count($written).' thumbnail SVG dibuat.'.PHP_EOL;
