<?php

use App\Http\Controllers\TenantWebsiteController;
use Illuminate\Support\Facades\Route;

// Root: central landing (pesantren.online) atau website pesantren (tenant domain)
Route::get('/', function () {
    if (in_array(request()->getHost(), config('tenancy.central_domains', []), true)) {
        return view('landing');
    }
    return app(TenantWebsiteController::class)->home(request());
})->name('home');

// PPDB publik — hanya aktif di domain tenant (controller 404 di central)
Route::get('/ppdb', [TenantWebsiteController::class, 'ppdbForm'])->name('tenant.ppdb.form');
Route::post('/ppdb', [TenantWebsiteController::class, 'ppdbStore'])->name('tenant.ppdb.store');

// Halaman legal — central domain
Route::get('/kebijakan-privasi', fn () => view('legal.privacy-policy'))->name('legal.privacy');
Route::get('/syarat-layanan', fn () => view('legal.terms-of-service'))->name('legal.terms');
Route::get('/disclaimer', fn () => view('legal.disclaimer'))->name('legal.disclaimer');
Route::get('/cookie-policy', fn () => view('legal.cookie-policy'))->name('legal.cookies');
Route::get('/kontak', fn () => view('legal.contact'))->name('legal.contact');
