<?php

declare(strict_types=1);

use App\Http\Controllers\KeuanganExportController;
use App\Http\Controllers\SantriExportController;
use Illuminate\Support\Facades\Route;
use Modules\CMS\Http\Controllers\ArticleDetailController;
use Modules\CMS\Http\Controllers\SitemapController;
use Modules\CMS\Http\Controllers\TenantLandingPageController;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::domain('{tenant}.localhost')->middleware([
    'web',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/', TenantLandingPageController::class)->name('tenant.home');
    Route::get('/tenant', TenantLandingPageController::class);
    Route::get('/artikel/{slug}', ArticleDetailController::class)->name('tenant.cms.articles.show');
    Route::get('/sitemap.xml', SitemapController::class)->name('tenant.cms.sitemap');

    Route::middleware('auth')->prefix('admin/kesantrian/santri/export')->name('tenant.kesantrian.santri.export.')->group(function () {
        Route::get('/csv', [SantriExportController::class, 'csv'])->name('csv');
        Route::get('/print', [SantriExportController::class, 'print'])->name('print');
    });

    Route::middleware('auth')->prefix('admin/keuangan/export')->name('tenant.keuangan.export.')->group(function () {
        Route::get('/tagihan/csv', [KeuanganExportController::class, 'tagihanCsv'])->name('tagihan.csv');
        Route::get('/tagihan/print', [KeuanganExportController::class, 'tagihanPrint'])->name('tagihan.print');
        Route::get('/pembayaran/csv', [KeuanganExportController::class, 'pembayaranCsv'])->name('pembayaran.csv');
        Route::get('/pembayaran/print', [KeuanganExportController::class, 'pembayaranPrint'])->name('pembayaran.print');
    });
});
