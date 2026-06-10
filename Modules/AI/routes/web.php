<?php

use Illuminate\Support\Facades\Route;
use Modules\AI\Http\Controllers\RingkasanKebijakanController;

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
