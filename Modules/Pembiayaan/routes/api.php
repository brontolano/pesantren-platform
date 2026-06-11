<?php

use Illuminate\Support\Facades\Route;
use Modules\Pembiayaan\Http\Controllers\PwaGuruSarprasController;

// PWA Guru Sarpras — WP-33: lapor kerusakan + ceklis piket (scan QR area).
Route::middleware(['auth:sanctum'])->prefix('pwa/guru/sarpras')->name('pwa.guru.sarpras.')->group(function () {
    Route::post('lapor-kerusakan', [PwaGuruSarprasController::class, 'laporKerusakan'])->name('lapor-kerusakan');
    Route::get('laporan-saya', [PwaGuruSarprasController::class, 'laporanSaya'])->name('laporan-saya');
    Route::post('piket/scan', [PwaGuruSarprasController::class, 'scanPiket'])->name('piket.scan');
});
