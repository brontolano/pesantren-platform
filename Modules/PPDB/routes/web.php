<?php

use Illuminate\Support\Facades\Route;
use Modules\PPDB\Http\Controllers\PesertaAuthController;
use Modules\PPDB\Http\Controllers\PesertaPwaController;
use Modules\PPDB\Http\Controllers\PesertaPortalController;
use Modules\PPDB\Http\Controllers\PublicPpdbController;
use Modules\PPDB\Http\Controllers\PPDBController;
use Modules\PPDB\Http\Middleware\ShareSpmbPageData;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::domain('{tenant}.localhost')->middleware([
    'web',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/ppdb', [PublicPpdbController::class, 'create'])
        ->name('tenant.ppdb.public.create');
    Route::post('/ppdb', [PublicPpdbController::class, 'store'])
        ->name('tenant.ppdb.public.store');
});

Route::domain('{tenant}.localhost')->middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/ppdb/tracking/{token}', [PublicPpdbController::class, 'tracking'])
        ->name('tenant.ppdb.public.tracking');
});

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/ppdb/tracking/{token}', [PublicPpdbController::class, 'tracking'])
        ->name('ppdb.public.tracking');
});

/*
|--------------------------------------------------------------------------
| Portal Peserta SPMB (PWA) — {domain-tenant}/spmb/*
|--------------------------------------------------------------------------
| Tenancy di-resolve dari FULL DOMAIN (Blueprint §3b: InitializeTenancyByDomain,
| sama dengan panel tenant) sebelum auth, sehingga `users` dibaca dari DB tenant.
| Guard `web` existing — Hukum #2: tidak ada sistem auth baru.
|
| Endpoint publik (belum login): aktivasi, login, lupa password.
| Halaman GET (Vue) menyusul di F1.2.x.
|
| Pertahanan berlapis: throttle route = per IP; rate limit per-NOMOR WA +
| cooldown verifikasi (AUTH-4/5) ditegakkan di PesertaOtpService (F1.1.5);
| login dibatasi per identifier+IP di controller (F1.1.3).
*/
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    ShareSpmbPageData::class, // F1.2.1 — branding white-label + flash utk semua halaman /spmb
])->prefix('spmb')->name('spmb.')->group(function () {
    Route::get('/manifest.json', [PesertaPwaController::class, 'manifest'])
        ->name('manifest');
    Route::get('/icon.svg', [PesertaPwaController::class, 'icon'])
        ->name('icon');
    Route::get('/sw.js', [PesertaPwaController::class, 'serviceWorker'])
        ->name('service-worker');

    // Halaman tamu — Inertia + Vue (F1.2.2, F1.2.3)
    Route::get('/login', [PesertaAuthController::class, 'showLogin'])
        ->name('login');
    Route::get('/aktivasi', [PesertaAuthController::class, 'showAktivasi'])
        ->name('aktivasi');
    Route::get('/lupa-password', [PesertaAuthController::class, 'showLupaPassword'])
        ->name('password.forgot');

    // Aktivasi akun (AUTH-2)
    Route::post('/aktivasi', [PesertaAuthController::class, 'aktivasi'])
        ->middleware('throttle:10,15')
        ->name('aktivasi.verify');
    Route::post('/aktivasi/kirim-ulang', [PesertaAuthController::class, 'kirimUlang'])
        ->middleware('throttle:3,15')
        ->name('aktivasi.resend');

    // Login & logout (AUTH-3, F1.1.3)
    Route::post('/login', [PesertaAuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login.attempt');
    Route::post('/logout', [PesertaAuthController::class, 'logout'])
        ->middleware('auth')
        ->name('logout');

    // Lupa password via OTP WA (AUTH-4, F1.1.4)
    Route::post('/lupa-password', [PesertaAuthController::class, 'lupaPassword'])
        ->middleware('throttle:5,15')
        ->name('password.request');
    Route::post('/lupa-password/reset', [PesertaAuthController::class, 'resetPassword'])
        ->middleware('throttle:10,15')
        ->name('password.reset');

    /*
    | Area peserta — WAJIB login + role `peserta` (F1.1.6).
    | Alias `peserta` (EnsureUserIsPeserta) menegakkan keduanya sekaligus:
    | guest → redirect /spmb/login; user non-peserta (staf/wali) → 403.
    | Halaman Vue/Inertia sesungguhnya dibangun di F1.2.x — placeholder
    | di bawah memastikan rute bernama & proteksinya SUDAH teruji sejak kini.
    */
    Route::middleware('peserta')->group(function () {
        Route::get('/', [PesertaPortalController::class, 'dashboard'])
            ->name('dashboard');
        Route::get('/berkas', [PesertaPortalController::class, 'berkas'])
            ->name('berkas');
        Route::put('/biodata', [PesertaPortalController::class, 'updateBiodata'])
            ->name('biodata.update');
    });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('ppdbs', PPDBController::class)->names('ppdb');
});
