<?php

use Illuminate\Support\Facades\Route;
use Modules\Kesantrian\Http\Controllers\PwaGuruDisiplinController;

/**
 * WP-24 — PWA Guru Disiplin API (kepengasuhan mode).
 * Routes: disiplin daftar, catat, verifikasi, poin.
 */
Route::middleware(['auth:sanctum', 'can:akses_pwa_guru'])->prefix('pwa/guru')->name('pwa.guru.')->group(function () {
    Route::prefix('disiplin')->name('disiplin.')->group(function () {
        Route::get('/daftar', [PwaGuruDisiplinController::class, 'daftarPelanggaran'])->name('daftar');
        Route::post('/catatPelanggaran', [PwaGuruDisiplinController::class, 'catatPelanggaran'])->name('catat');
        Route::post('/{id}/verifikasi', [PwaGuruDisiplinController::class, 'verifikasiPelanggaran'])->name('verifikasi');
        Route::get('/poinTerbaru', [PwaGuruDisiplinController::class, 'poinTerbaru'])->name('poin');
    });
});
