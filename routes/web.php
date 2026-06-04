<?php

use App\Http\Controllers\TenantWebsiteController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Database\Models\Domain;

// Caddy on-demand TLS "ask" endpoint — izinkan terbitkan sertifikat hanya
// untuk central domain atau domain tenant yang terdaftar. Mencegah penyalahgunaan.
Route::get('/caddy/ssl-check', function () {
    $domain = request()->query('domain');
    if (! $domain) {
        abort(400);
    }
    if (in_array($domain, config('tenancy.central_domains', []), true)) {
        return response('ok');
    }
    abort_unless(Domain::where('domain', $domain)->exists(), 403);
    return response('ok');
})->name('caddy.ssl-check');

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

// Fallback login route untuk middleware 'auth' pada route non-panel (mis. export tenant).
// Pada domain tenant, /admin/login adalah halaman login panel tenant.
Route::get('/login', fn () => redirect('/admin/login'))->name('login');

// Halaman legal — central domain
Route::get('/kebijakan-privasi', fn () => view('legal.privacy-policy'))->name('legal.privacy');
Route::get('/syarat-layanan', fn () => view('legal.terms-of-service'))->name('legal.terms');
Route::get('/disclaimer', fn () => view('legal.disclaimer'))->name('legal.disclaimer');
Route::get('/cookie-policy', fn () => view('legal.cookie-policy'))->name('legal.cookies');
Route::get('/kontak', fn () => view('legal.contact'))->name('legal.contact');
