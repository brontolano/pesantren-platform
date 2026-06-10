<?php

use Illuminate\Support\Facades\Route;
use Modules\PPDB\Http\Controllers\PesertaAuthController;
use Modules\PPDB\Http\Controllers\PesertaPwaController;
use Modules\PPDB\Http\Controllers\PesertaPortalController;
use Modules\PPDB\Http\Controllers\PpdbFileController;
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
        ->middleware(\Modules\PPDB\Http\Middleware\FrameAncestorsPpdb::class)
        ->name('tenant.ppdb.public.create');
    Route::post('/ppdb', [PublicPpdbController::class, 'store'])
        ->middleware(\Modules\PPDB\Http\Middleware\FrameAncestorsPpdb::class)
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
        Route::post('/berkas/upload', [PesertaPortalController::class, 'uploadDokumen'])
            ->name('berkas.upload');
        Route::get('/pengumuman', [PesertaPortalController::class, 'pengumuman'])
            ->name('pengumuman');
        Route::get('/kartu-ujian', [PesertaPortalController::class, 'kartuUjian'])
            ->name('kartu-ujian');
        Route::get('/daftar-ulang', [PesertaPortalController::class, 'daftarUlang'])
            ->name('daftar-ulang');
        Route::post('/daftar-ulang', [PesertaPortalController::class, 'prosesDaftarUlang'])
            ->name('daftar-ulang.proses');
        Route::get('/tagihan', [PesertaPortalController::class, 'tagihan'])
            ->name('tagihan');
        Route::post('/tagihan/{tagihan}/bukti', [PesertaPortalController::class, 'uploadBukti'])
            ->whereNumber('tagihan')
            ->name('tagihan.bukti');
        Route::put('/biodata', [PesertaPortalController::class, 'updateBiodata'])
            ->name('biodata.update');
    });
});

// Serving file PPDB (dokumen & bukti) dari disk privat. Tenancy by domain + auth
// web (peserta & panitia sama-sama web guard). Otorisasi halus di controller:
// panitia (manage_santri) semua; peserta hanya miliknya.
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'auth',
])->prefix('ppdb/file')->name('ppdb.file.')->group(function () {
    Route::get('/dokumen/{dokumen}', [PpdbFileController::class, 'dokumen'])
        ->whereNumber('dokumen')
        ->name('dokumen');
    Route::get('/bukti/{tagihan}', [PpdbFileController::class, 'bukti'])
        ->whereNumber('tagihan')
        ->name('bukti');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('ppdbs', PPDBController::class)->names('ppdb');
});
