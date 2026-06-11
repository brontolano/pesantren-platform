<?php

use Illuminate\Support\Facades\Route;
use Modules\Kesehatan\Http\Controllers\KesehatanController;
use Modules\Kesehatan\Http\Controllers\PwaGuruUksController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('kesehatans', KesehatanController::class)->names('kesehatan');
});

// PWA Guru UKS — Register kunjungan santri (WP-27)
Route::middleware(['auth:sanctum'])->prefix('pwa/guru')->name('pwa.guru.')->group(function () {
    Route::get('uks/istirahat', [PwaGuruUksController::class, 'daftarIstirahat'])->name('uks.istirahat');
    Route::get('uks/rujukan-terbuka', [PwaGuruUksController::class, 'daftarRujukanTerbuka'])->name('uks.rujukan-terbuka');
    Route::post('uks/catat-kunjungan', [PwaGuruUksController::class, 'catatKunjungan'])->name('uks.catat-kunjungan');
    Route::post('uks/{kunjungan_id}/tutup', [PwaGuruUksController::class, 'tutupKunjungan'])->name('uks.tutup');
    Route::get('uks/{santri_id}/riwayat', [PwaGuruUksController::class, 'riwayat'])->name('uks.riwayat');
});
