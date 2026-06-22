<?php

declare(strict_types=1);

use App\Http\Controllers\AkademikExportController;
use App\Http\Controllers\EmisExportController;
use App\Http\Controllers\KeuanganExportController;
use App\Http\Controllers\PayrollExportController;
use App\Http\Controllers\PembiayaanExportController;
use App\Http\Controllers\PengadaanExportController;
use App\Http\Controllers\SantriExportController;
use App\Http\Controllers\TahfidzExportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('admin/kesantrian/santri/export')->name('tenant.kesantrian.santri.export.')->group(function () {
    Route::get('/csv', [SantriExportController::class, 'csv'])->name('csv');
    Route::get('/print', [SantriExportController::class, 'print'])->name('print');
    Route::get('/template', [SantriExportController::class, 'template'])->name('template');
});

Route::middleware('auth')->prefix('admin/akademik/export')->name('tenant.akademik.export.')->group(function () {
    Route::get('/rapor/{rapor}/print', [AkademikExportController::class, 'raporPrint'])->name('rapor.print');
    // WP-10 — cetak rapor diniyah; blok tampil mengikuti Setting tenant.
    Route::get('/rapor-diniyah/{raporDiniyah}/print', [AkademikExportController::class, 'raporDiniyahPrint'])->name('rapor-diniyah.print');
    // WP-08 — cetak syahadah (sertifikat internal) per alumni.
    Route::get('/syahadah/{alumni}/print', [AkademikExportController::class, 'syahadahPrint'])->name('syahadah.print');
});

// WP-11 — cetak daftar peserta wisuda tahfidz per periode (lulus + ditandai).
Route::middleware('auth')->prefix('admin/tahfidz/export')->name('tenant.tahfidz.export.')->group(function () {
    Route::get('/wisuda/print', [TahfidzExportController::class, 'wisudaPrint'])->name('wisuda.print');
});

Route::middleware('auth')->prefix('admin/keuangan/export')->name('tenant.keuangan.export.')->group(function () {
    Route::get('/tagihan/csv', [KeuanganExportController::class, 'tagihanCsv'])->name('tagihan.csv');
    Route::get('/tagihan/print', [KeuanganExportController::class, 'tagihanPrint'])->name('tagihan.print');
    Route::get('/pembayaran/csv', [KeuanganExportController::class, 'pembayaranCsv'])->name('pembayaran.csv');
    Route::get('/pembayaran/print', [KeuanganExportController::class, 'pembayaranPrint'])->name('pembayaran.print');
});

Route::middleware('auth')->prefix('admin/pengadaan')->name('tenant.pengadaan.')->group(function () {
    Route::get('/bkk/{belanja}/print', [PengadaanExportController::class, 'bkkPrint'])->name('bkk.print');
});

Route::middleware('auth')->prefix('admin/payroll')->name('tenant.payroll.')->group(function () {
    Route::get('/slip/{slip}/print', [PayrollExportController::class, 'slipPrint'])->name('slip.print');
    // WP-03 — Cetak SK penugasan dari template ber-placeholder.
    Route::get('/penugasan/{penugasan}/sk', [PayrollExportController::class, 'skPenugasan'])->name('penugasan.sk');
});

Route::middleware('auth')->prefix('admin/emis')->name('tenant.emis.')->group(function () {
    Route::get('/export/csv', [EmisExportController::class, 'csv'])->name('export.csv');
});

Route::middleware('auth')->prefix('admin/pembiayaan')->name('tenant.pembiayaan.')->group(function () {
    Route::get('/{pengajuan}/paket-kredit/print', [PembiayaanExportController::class, 'paketKreditPrint'])->name('paket.print');
});
