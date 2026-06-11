<?php

use Illuminate\Support\Facades\Route;
use Modules\Kegiatan\Http\Controllers\KegiatanController;
use Modules\Kegiatan\Http\Controllers\PwaWaliIzinController;
use Modules\Kegiatan\Http\Controllers\PwaGuruIzinController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('kegiatans', KegiatanController::class)->names('kegiatan');
});

// PWA Wali Izin Pulang — GET/POST untuk ajukan, list, tandai kembali
Route::middleware(['auth:sanctum'])->prefix('pwa/wali')->name('pwa.wali.')->group(function () {
    Route::post('{santri_id}/izin-ajukan', [PwaWaliIzinController::class, 'ajukan'])->name('izin.ajukan');
    Route::get('{santri_id}/izin-list', [PwaWaliIzinController::class, 'list'])->name('izin.list');
    Route::post('izin/{izin_id}/tandai-kembali', [PwaWaliIzinController::class, 'tandaiKembali'])->name('izin.tandai-kembali');
});

// PWA Guru Izin Pulang — antrean, setujui, tolak, surat PDF
Route::middleware(['auth:sanctum'])->prefix('pwa/guru')->name('pwa.guru.')->group(function () {
    Route::get('izin/antrean', [PwaGuruIzinController::class, 'antrean'])->name('izin.antrean');
    Route::post('izin/{izin_id}/setujui', [PwaGuruIzinController::class, 'setujui'])->name('izin.setujui');
    Route::post('izin/{izin_id}/tolak', [PwaGuruIzinController::class, 'tolak'])->name('izin.tolak');
    Route::get('izin/{izin_id}/surat-pdf', [PwaGuruIzinController::class, 'suratPdf'])->name('izin.surat-pdf');
});
