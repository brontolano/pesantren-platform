<?php

declare(strict_types=1);

use App\Http\Controllers\AppSantri\AppSantriController;
use App\Http\Controllers\AppSantri\AppSantriPengurusController;
use App\Http\Controllers\AppSantri\AppSantriUjianController;
use App\Http\Controllers\PimpinanPwaController;
use Illuminate\Support\Facades\Route;

// App Santri (/app) — login NIS + dashboard ringkas (tahap 1; lanjut: WebAuthn).
Route::prefix('app')->name('tenant.app-santri.')->group(function () {
    Route::get('/login', [AppSantriController::class, 'showLogin'])->name('login');
    Route::post('/login', [AppSantriController::class, 'login'])
        ->middleware('throttle:10,1')->name('login.attempt');
    Route::middleware('auth')->group(function () {
        Route::get('/', [AppSantriController::class, 'home'])->name('home');
        Route::post('/logout', [AppSantriController::class, 'logout'])->name('logout');

        // F4-PWA — Ujian Semester Online (timer server-side, satu attempt/sesi).
        Route::get('/ujian', [AppSantriUjianController::class, 'index'])->name('ujian.index');
        Route::post('/ujian/{sesi}/mulai', [AppSantriUjianController::class, 'mulai'])->name('ujian.mulai');
        Route::get('/ujian/{sesi}', [AppSantriUjianController::class, 'kerjakan'])->name('ujian.kerjakan');
        Route::post('/ujian/{sesi}/jawab', [AppSantriUjianController::class, 'jawab'])->name('ujian.jawab');
        Route::post('/ujian/{sesi}/selesai', [AppSantriUjianController::class, 'selesai'])->name('ujian.selesai');

        // WP-21 — Mode Pengurus-Santri: gerbang ganda dicek per-request di
        // controller (baris pengurus_santri BERLAKU hari ini; habis = 403).
        Route::get('/pengurus', [AppSantriPengurusController::class, 'home'])->name('pengurus.home');
        Route::get('/pengurus/pelanggaran', [AppSantriPengurusController::class, 'formPelanggaran'])->name('pengurus.pelanggaran.form');
        Route::post('/pengurus/pelanggaran', [AppSantriPengurusController::class, 'simpanPelanggaran'])->name('pengurus.pelanggaran.simpan');
        Route::get('/pengurus/kegiatan', [AppSantriPengurusController::class, 'kegiatan'])->name('pengurus.kegiatan');
        Route::post('/pengurus/kegiatan/{kegiatan}/presensi', [AppSantriPengurusController::class, 'simpanPresensi'])->name('pengurus.presensi.simpan');
    });
});

// WP-35 — Dasbor & Approval Pimpinan dari HP (/pimpinan): baca-saja
// kecuali setujui/tolak ApprovalRequest (HANYA via ApprovalService F1).
// CATATAN deploy: permission akses_pimpinan baru → butuh tenant:reprovision.
Route::middleware(['auth', 'can:akses_pimpinan'])->prefix('pimpinan')->name('tenant.pimpinan.')->group(function () {
    Route::get('/', [PimpinanPwaController::class, 'beranda'])->name('beranda');
    Route::get('/api/ringkasan', [PimpinanPwaController::class, 'ringkasan'])->name('ringkasan');
    Route::post('/api/approval/{approval}/setujui', [PimpinanPwaController::class, 'setujui'])->name('approval.setujui');
    Route::post('/api/approval/{approval}/tolak', [PimpinanPwaController::class, 'tolak'])->name('approval.tolak');
});
