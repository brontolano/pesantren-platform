<?php

declare(strict_types=1);

use App\Http\Controllers\PortalWali\PortalWaliAcademicController;
use App\Http\Controllers\PortalWali\PortalWaliAuthController;
use App\Http\Controllers\PortalWali\PortalWaliDashboardController;
use App\Http\Controllers\PortalWali\PortalWaliFinancialController;
use App\Http\Controllers\PortalWali\PortalWaliHafalanController;
use App\Http\Controllers\PortalWali\PortalWaliIzinController;
use App\Http\Controllers\PortalWali\PortalWaliJengukController;
use App\Http\Controllers\PortalWali\PortalWaliKegiatanController;
use App\Http\Controllers\PortalWali\PortalWaliKesehatanController;
use App\Http\Controllers\PortalWali\PortalWaliNotificationController;
use App\Http\Controllers\PortalWali\PortalWaliPembayaranController;
use App\Http\Controllers\PortalWali\PortalWaliProfileController;
use App\Http\Controllers\PortalWali\PortalWaliPwaController;
use App\Http\Controllers\PortalWali\PortalWaliRaporDiniyahDownloadController;
use App\Http\Controllers\PortalWali\PortalWaliRaporDownloadController;
use App\Http\Controllers\PortalWali\PortalWaliSantriAkunController;
use App\Http\Controllers\PortalWali\PortalWaliTabunganController;
use App\Http\Controllers\PortalWali\PortalWaliTopupController;
use Illuminate\Support\Facades\Route;

Route::prefix('wali')->name('tenant.portal-wali.pwa.')->group(function () {
    Route::get('/manifest.json', [PortalWaliPwaController::class, 'manifest'])->name('manifest');
    Route::get('/icon.svg', [PortalWaliPwaController::class, 'icon'])->name('icon');
    Route::get('/screenshot.svg', [PortalWaliPwaController::class, 'screenshot'])->name('screenshot');
    Route::get('/sw.js', [PortalWaliPwaController::class, 'serviceWorker'])->name('service-worker');
});

Route::prefix('wali')->name('tenant.portal-wali.auth.')->group(function () {
    Route::get('/login', [PortalWaliAuthController::class, 'showLogin'])->name('login');
    Route::get('/aktivasi', [PortalWaliAuthController::class, 'showAktivasi'])->name('activation');
    Route::get('/lupa-password', [PortalWaliAuthController::class, 'showLupaPassword'])->name('password.forgot');
    Route::post('/login', [PortalWaliAuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
    Route::post('/aktivasi', [PortalWaliAuthController::class, 'aktivasi'])->middleware('throttle:10,15')->name('activation.verify');
    Route::post('/aktivasi/kirim-ulang', [PortalWaliAuthController::class, 'kirimUlang'])->middleware('throttle:3,15')->name('activation.resend');
    Route::post('/lupa-password', [PortalWaliAuthController::class, 'lupaPassword'])->middleware('throttle:5,15')->name('password.request');
    Route::post('/lupa-password/reset', [PortalWaliAuthController::class, 'resetPassword'])->middleware('throttle:10,15')->name('password.reset');
});

Route::middleware('wali')->prefix('wali')->name('tenant.portal-wali.')->group(function () {
    Route::get('/', PortalWaliDashboardController::class)->name('dashboard');
    Route::post('/logout', [PortalWaliAuthController::class, 'logout'])->name('logout');
    Route::get('/santri/{santri}', PortalWaliProfileController::class)->name('profile');
    Route::get('/santri/{santri}/akademik', PortalWaliAcademicController::class)->name('academic');
    Route::get('/santri/{santri}/keuangan', PortalWaliFinancialController::class)->name('financial');
    Route::get('/notifikasi', PortalWaliNotificationController::class)->name('notifications');
    Route::get('/izin', [PortalWaliIzinController::class, 'index'])->name('izin');
    Route::post('/izin', [PortalWaliIzinController::class, 'store'])->name('izin.store');

    // WP-30 — ajukan slot jenguk + lihat status (anak sendiri saja).
    Route::get('/jenguk', [PortalWaliJengukController::class, 'index'])->name('jenguk');
    Route::post('/jenguk', [PortalWaliJengukController::class, 'store'])->name('jenguk.store');
    Route::post('/jenguk/{pengajuan}/batal', [PortalWaliJengukController::class, 'batal'])->name('jenguk.batal');
    Route::get('/santri/{santri}/rapor/{rapor}/download', PortalWaliRaporDownloadController::class)->name('rapor.download');
    // WP-10 — unduh/cetak rapor diniyah anak sendiri (403 untuk anak orang lain).
    Route::get('/santri/{santri}/rapor-diniyah/{raporDiniyah}/download', PortalWaliRaporDiniyahDownloadController::class)->name('rapor-diniyah.download');
    Route::get('/santri/{santri}/hafalan', PortalWaliHafalanController::class)->name('hafalan');
    Route::get('/santri/{santri}/kesehatan', PortalWaliKesehatanController::class)->name('kesehatan');
    Route::get('/santri/{santri}/kegiatan', PortalWaliKegiatanController::class)->name('kegiatan');

    // W2 Pembayaran
    Route::get('/santri/{santri}/tagihan/{tagihan}/bayar', [PortalWaliPembayaranController::class, 'create'])->name('pembayaran.create');
    Route::post('/santri/{santri}/tagihan/{tagihan}/bayar', [PortalWaliPembayaranController::class, 'store'])->name('pembayaran.store');
    Route::get('/pembayaran-saya', [PortalWaliPembayaranController::class, 'index'])->name('pembayaran.index');

    // App Santri: wali atur PIN login anaknya
    Route::post('/santri/{santri}/atur-pin', [PortalWaliSantriAkunController::class, 'aturPin'])->name('santri.atur-pin');

    // W3 Tabungan
    Route::get('/santri/{santri}/tabungan', [PortalWaliTabunganController::class, 'show'])->name('tabungan.show');
    Route::post('/santri/{santri}/tabungan/topup', [PortalWaliTabunganController::class, 'topup'])->name('tabungan.topup');
    Route::post('/santri/{santri}/tabungan/batas-belanja', [PortalWaliTabunganController::class, 'setBatasBelanja'])->name('tabungan.batas-belanja');
    Route::post('/santri/{santri}/tagihan/{tagihan}/bayar-saldo', [PortalWaliTabunganController::class, 'bayarDariSaldo'])->name('tabungan.bayar-saldo');

    // W3+ Top Up Tabungan — checkout marketplace (DEMO simulasi pembayaran)
    Route::get('/santri/{santri}/topup', [PortalWaliTopupController::class, 'checkout'])->name('topup.checkout');
    Route::post('/santri/{santri}/topup', [PortalWaliTopupController::class, 'store'])->name('topup.store');
    Route::get('/santri/{santri}/topup/{topup}/instruksi', [PortalWaliTopupController::class, 'instruksi'])->name('topup.instruksi');
    Route::post('/santri/{santri}/topup/{topup}/konfirmasi', [PortalWaliTopupController::class, 'konfirmasi'])->name('topup.konfirmasi');
    Route::get('/santri/{santri}/topup/{topup}/sukses', [PortalWaliTopupController::class, 'sukses'])->name('topup.sukses');
});
