<?php

use Illuminate\Support\Facades\Route;
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
