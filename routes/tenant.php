<?php

declare(strict_types=1);

use App\Http\Controllers\KeuanganExportController;
use App\Http\Controllers\AkademikExportController;
use App\Http\Controllers\PortalWali\PortalWaliAcademicController;
use App\Http\Controllers\PortalWali\PortalWaliDashboardController;
use App\Http\Controllers\PortalWali\PortalWaliFinancialController;
use App\Http\Controllers\PortalWali\PortalWaliHafalanController;
use App\Http\Controllers\PortalWali\PortalWaliKegiatanController;
use App\Http\Controllers\PortalWali\PortalWaliKesehatanController;
use App\Http\Controllers\PortalWali\PortalWaliIzinController;
use App\Http\Controllers\PortalWali\PortalWaliNotificationController;
use App\Http\Controllers\PortalWali\PortalWaliProfileController;
use App\Http\Controllers\PortalWali\PortalWaliRaporDownloadController;
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

    Route::middleware('auth')->prefix('wali')->name('tenant.portal-wali.')->group(function () {
        Route::get('/', PortalWaliDashboardController::class)->name('dashboard');
        Route::get('/santri/{santri}', PortalWaliProfileController::class)->name('profile');
        Route::get('/santri/{santri}/akademik', PortalWaliAcademicController::class)->name('academic');
        Route::get('/santri/{santri}/keuangan', PortalWaliFinancialController::class)->name('financial');
        Route::get('/notifikasi', PortalWaliNotificationController::class)->name('notifications');
        Route::get('/izin', [PortalWaliIzinController::class, 'index'])->name('izin');
        Route::post('/izin', [PortalWaliIzinController::class, 'store'])->name('izin.store');
        Route::get('/santri/{santri}/rapor/{rapor}/download', PortalWaliRaporDownloadController::class)->name('rapor.download');
        Route::get('/santri/{santri}/hafalan', PortalWaliHafalanController::class)->name('hafalan');
        Route::get('/santri/{santri}/kesehatan', PortalWaliKesehatanController::class)->name('kesehatan');
        Route::get('/santri/{santri}/kegiatan', PortalWaliKegiatanController::class)->name('kegiatan');
    });

    Route::middleware('auth')->prefix('admin/kesantrian/santri/export')->name('tenant.kesantrian.santri.export.')->group(function () {
        Route::get('/csv', [SantriExportController::class, 'csv'])->name('csv');
        Route::get('/print', [SantriExportController::class, 'print'])->name('print');
    });

    Route::middleware('auth')->prefix('admin/akademik/export')->name('tenant.akademik.export.')->group(function () {
        Route::get('/rapor/{rapor}/print', [AkademikExportController::class, 'raporPrint'])->name('rapor.print');
    });

    Route::middleware('auth')->prefix('admin/keuangan/export')->name('tenant.keuangan.export.')->group(function () {
        Route::get('/tagihan/csv', [KeuanganExportController::class, 'tagihanCsv'])->name('tagihan.csv');
        Route::get('/tagihan/print', [KeuanganExportController::class, 'tagihanPrint'])->name('tagihan.print');
        Route::get('/pembayaran/csv', [KeuanganExportController::class, 'pembayaranCsv'])->name('pembayaran.csv');
        Route::get('/pembayaran/print', [KeuanganExportController::class, 'pembayaranPrint'])->name('pembayaran.print');
    });
});
