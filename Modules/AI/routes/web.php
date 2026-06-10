<?php

use Illuminate\Support\Facades\Route;
use Modules\AI\Http\Controllers\DrafIkhtisarCetakController;
use Modules\AI\Http\Controllers\DrafRppCetakController;
use Modules\AI\Http\Controllers\DrafSoalCetakController;
use Modules\AI\Http\Controllers\RatingJawabanController;
use Modules\AI\Http\Controllers\RingkasanKebijakanController;
use Modules\AI\Http\Controllers\TanyaUstadzController;

// Modul AI web routes.
// Filament menangani route admin secara otomatis via Pages (T-A011).

// T-A012 — ringkasan kebijakan untuk kyai/pimpinan via tautan bertanda
// tangan (temporarySignedRoute, umur config ai.settings.tautan_ringkasan_jam).
// BACA-SAJA: tanpa login hanya membaca ringkasan; persetujuan tetap lewat
// panel (login + permission ai.setting.setujui). Tenancy diinisialisasi
// middleware web global (InitializeTenancyForWebRequests) di domain tenant.
Route::get('/ai/ringkasan-kebijakan/{approval}', RingkasanKebijakanController::class)
    ->middleware('signed')
    ->whereNumber('approval')
    ->name('ai.ringkasan.signed');

// T-A090 — route kecil komponen UI AI standar. Keduanya WAJIB login (auth
// eksisting, Hukum #2) + konteks tenant (dicek controller; tenancy datang
// dari middleware web global di domain tenant). Throttle santun anti-spam.

// Tombol "Tanya Ustadz": eskalasi MANUAL — idempoten per user+hash pertanyaan
// (EscalationService::buatManual), pertanyaan diredaksi PII sebelum disimpan.
Route::post('/ai/tanya-ustadz', TanyaUstadzController::class)
    ->middleware(['auth', 'throttle:20,1'])
    ->name('ai.tanya-ustadz.eskalasi');

// Bintang 1–5 kepuasan jawaban → ResearchTelemetry::rekamRating (T-A013;
// no-op senyap bila tenant tidak opt-in riset).
Route::post('/ai/rating-jawaban', RatingJawabanController::class)
    ->middleware(['auth', 'throttle:60,1'])
    ->name('ai.rating-jawaban');

// T-A040 — lembar cetak paket draf soal Generator Soal (print-friendly,
// dibuka tab baru dari halaman GeneratorSoal). WAJIB login; permission
// ai.generator.soal + gating modul dicek controller; tenancy datang dari
// middleware web global di domain tenant.
Route::get('/ai/draf-soal/{draf}/cetak', DrafSoalCetakController::class)
    ->middleware('auth')
    ->whereNumber('draf')
    ->name('ai.draf-soal.cetak');

// T-A041 — lembar cetak & unduhan dokumen draf RPP Generator RPP. WAJIB
// login; permission ai.generator.rpp + gating modul dicek controller;
// tenancy datang dari middleware web global di domain tenant. Unduhan =
// HTML ber-MIME Word (tanpa dependency phpword — lihat docblock controller).
Route::get('/ai/draf-rpp/{draf}/cetak', [DrafRppCetakController::class, 'cetak'])
    ->middleware('auth')
    ->whereNumber('draf')
    ->name('ai.draf-rpp.cetak');

Route::get('/ai/draf-rpp/{draf}/unduh', [DrafRppCetakController::class, 'unduh'])
    ->middleware('auth')
    ->whereNumber('draf')
    ->name('ai.draf-rpp.unduh');

// T-A042 — handout cetak draf ikhtisar materi kitab / kerangka kultum
// (print-friendly, dibuka tab baru dari halaman IkhtisarKitab). WAJIB login;
// permission ai.generator.ikhtisar + gating modul dicek controller; tenancy
// datang dari middleware web global di domain tenant.
Route::get('/ai/draf-ikhtisar/{draf}/cetak', DrafIkhtisarCetakController::class)
    ->middleware('auth')
    ->whereNumber('draf')
    ->name('ai.draf-ikhtisar.cetak');
