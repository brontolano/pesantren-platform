<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
 * Tenant routes — resolved by full domain (mis. demo.pesantren.online).
 * PreventAccessFromCentralDomains dijalankan lebih dulu agar domain central
 * (pesantren.online) di-abort 404 sebelum resolver tenant melempar exception.
 * Halaman publik tenant ("/" + "/ppdb") ditangani oleh routes/web.php.
 *
 * Definisi rute dipecah per-portal ke routes/tenant/*.php (KONVENSI 5-lapisan,
 * < 150 baris/file). Tiap file di-require DI DALAM grup ini sehingga mewarisi
 * middleware tenancy. Urutan dipertahankan; prefix antar-file tidak tumpang-tindih.
 */
Route::middleware([
    'web',
    PreventAccessFromCentralDomains::class,
    InitializeTenancyByDomain::class,
])->group(function () {
    require __DIR__.'/tenant/public.php';
    require __DIR__.'/tenant/demo-apps.php';
    require __DIR__.'/tenant/app-santri.php';
    require __DIR__.'/tenant/portal-guru.php';
    require __DIR__.'/tenant/portal-wali.php';
    require __DIR__.'/tenant/exports.php';
    require __DIR__.'/tenant/keuangan.php';
});
