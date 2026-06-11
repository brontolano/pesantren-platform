<?php

use App\Http\Controllers\Api\CmsPublicApiController;
use App\Http\Controllers\Api\PpdbPublicApiController;
use App\Http\Controllers\Api\PublicDirectoryApiController;
use App\Http\Controllers\Api\TenantApiController;
use App\Http\Controllers\DonasiPublikController;
use App\Http\Controllers\TenantWebsiteController;
use App\Support\KebijakanPrivasiSistem;
use App\Support\TenantBranding;
use Illuminate\Support\Facades\Route;
use Modules\CMS\Http\Controllers\EmbedWidgetController;
use Modules\CMS\Http\Controllers\TenantLandingPageController;
use Modules\PPDB\Http\Controllers\LandingRegistrationController;
use Modules\PPDB\Http\Controllers\PpdbFileController;
use Modules\PPDB\Http\Middleware\FrameAncestorsPpdb;
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

// Root: central landing (pesantren.online) atau HUB APLIKASI tenant (domain sistem).
// Domain sistem tenant (mis. pesantren.asy-syifaa.com) menampilkan launcher portal
// login bergaya menu aplikasi (icon grid). Website publik = domain terpisah (asy-syifaa.com).
Route::get('/', function () {
    if (in_array(request()->getHost(), config('tenancy.central_domains', []), true)) {
        return view('landing');
    }

    $domainType = Domain::query()
        ->where('domain', request()->getHost())
        ->value('type');

    if ($domainType === 'sistem') {
        return view('tenant.launcher', [
            'pesantren' => TenantBranding::name(tenant()?->name ?? 'Portal Pesantren'),
            'logoUrl' => TenantBranding::logoUrl(),
        ]);
    }

    return app()->call([app(TenantLandingPageController::class), '__invoke']);
})->name('home');

// URL lama /v2 (preview desain AlbaKids) sudah dipromosikan ke root — jaga
// tautan/bookmark lama tetap hidup.
Route::get('/v2', fn () => redirect('/', 301))->name('home.v2-legacy');

// robots.txt & sitemap.xml — tenant-aware (host dinamis) untuk indeksasi mesin pencari.
Route::get('/robots.txt', function () {
    $host = request()->getSchemeAndHttpHost();

    return response("User-agent: *\nAllow: /\n\nSitemap: {$host}/sitemap.xml\n", 200, [
        'Content-Type' => 'text/plain; charset=UTF-8',
    ]);
})->name('seo.robots');

Route::get('/sitemap.xml', function () {
    $host = request()->getSchemeAndHttpHost();
    $now = now()->toAtomString();
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
        ."<url><loc>{$host}/</loc><lastmod>{$now}</lastmod><changefreq>weekly</changefreq><priority>1.0</priority></url>"
        .'</urlset>';

    return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
})->name('seo.sitemap');

// Submit formulir landing page tenant-specific (contoh: Alba Kids).
Route::post('/daftar', [LandingRegistrationController::class, 'store'])
    ->middleware('throttle:ppdb-submit')
    ->name('tenant.landing.register');

// Unduh dokumen pendaftaran (bukti/kk/akta) via signed URL — dipakai di notifikasi
// WhatsApp ke admin agar bisa membuka file langsung tanpa login.
Route::get('/ppdb/dok/{candidate}/{type}', [PpdbFileController::class, 'signedDoc'])
    ->middleware('signed')
    ->whereIn('type', ['bukti', 'kk', 'akta'])
    ->name('tenant.ppdb.signed-doc');

// PPDB publik — hanya aktif di domain tenant (controller 404 di central)
// Iframe-friendly per tenant: CSP frame-ancestors (domain tenant + Setting embed_allowed_origins).
Route::get('/ppdb', [TenantWebsiteController::class, 'ppdbForm'])
    ->middleware(FrameAncestorsPpdb::class)
    ->name('tenant.ppdb.form');
Route::post('/ppdb', [TenantWebsiteController::class, 'ppdbStore'])->name('tenant.ppdb.store');

// 2c Baitul Maal — halaman donasi publik website tenant (konfirmasi transfer
// MANUAL; payment gateway menunggu owner). Form & upload bukti dithrottle
// (anti-spam, per tenant+IP); bukti hanya pdf/jpg/png (anti stored-XSS).
Route::get('/donasi', [DonasiPublikController::class, 'index'])
    ->name('tenant.donasi.index');
Route::post('/donasi', [DonasiPublikController::class, 'store'])
    ->middleware('throttle:ppdb-submit')
    ->name('tenant.donasi.store');
Route::get('/donasi/terima-kasih/{ref}', [DonasiPublikController::class, 'terimaKasih'])
    ->name('tenant.donasi.terima-kasih');
Route::post('/donasi/{ref}/bukti', [DonasiPublikController::class, 'uploadBukti'])
    ->middleware('throttle:ppdb-submit')
    ->name('tenant.donasi.bukti');

// Widget <script> embed utk situs eksternal — data publik saja, CORS terbuka.
Route::get('/embed/widget.js', [EmbedWidgetController::class, 'script'])->name('tenant.embed.script');
Route::get('/embed/data.json', [EmbedWidgetController::class, 'data'])->name('tenant.embed.data');

// Fallback login route untuk middleware 'auth' pada route non-panel (mis. export tenant).
// Pada domain tenant, /admin/login adalah halaman login panel tenant.
Route::get('/login', fn () => redirect('/admin/login'))->name('login');

// Portal peserta LAMA (/portal, panel Filament) sudah disatukan ke PWA /spmb.
// Redirect semua /portal/* ke /spmb/login agar tautan/bookmark lama tetap berfungsi
// dan pengalaman "portal" = "PWA" identik.
Route::get('/portal/{any?}', fn () => redirect('/spmb/login'))
    ->where('any', '.*')
    ->name('portal.legacy.redirect');

// Halaman legal — central domain; di DOMAIN TENANT menampilkan Kebijakan
// Privasi Sistem Pondok (redaksi default platform, bisa diedit pesantren).
Route::get('/kebijakan-privasi', function () {
    if (tenancy()->initialized && tenant() !== null) {
        return view('tenant.kebijakan-privasi', [
            'nama' => TenantBranding::name(tenant('name') ?? 'Pesantren'),
            'isi' => KebijakanPrivasiSistem::isi(),
        ]);
    }

    return view('legal.privacy-policy');
})->name('legal.privacy');
Route::get('/syarat-layanan', fn () => view('legal.terms-of-service'))->name('legal.terms');
Route::get('/disclaimer', fn () => view('legal.disclaimer'))->name('legal.disclaimer');
Route::get('/cookie-policy', fn () => view('legal.cookie-policy'))->name('legal.cookies');
Route::get('/kontak', fn () => view('legal.contact'))->name('legal.contact');

// ── API publik konten CMS untuk website tenant (mis. api.asy-syifaa.com) ──
// Tenant di-resolve dari domain oleh middleware tenancy web. CORS: config/cors.php.
// Kontrak: docs/SPEC-API-WEBSITE-ASY-SYIFAA.md
Route::get('/api/v1/posts', [CmsPublicApiController::class, 'posts']);
Route::get('/api/v1/posts/{slug}', [CmsPublicApiController::class, 'post']);
Route::get('/api/public/content/gallery', [CmsPublicApiController::class, 'gallery']);
Route::get('/api/public/content/gallery/{slug}', [CmsPublicApiController::class, 'galleryItem']);
Route::get('/api/public/content/profile', [CmsPublicApiController::class, 'profile']);
Route::get('/api/public/content/settings', [CmsPublicApiController::class, 'settings']);

// PPDB publik — terima pendaftaran dari form website (Fase minimal: simpan + e-tiket).
// Anti-spam: rate-limit ketat per tenant+IP (honeypot ditangani di controller).
Route::post('/api/v1/spmb/register', [PpdbPublicApiController::class, 'register'])
    ->middleware('throttle:ppdb-submit');

// API direktori publik — kompatibel kontrak legacy api.asy-syifaa.com
// (hasil-seleksi.php, direktori-santri/alumni/pengajar.php). Data publik saja.
Route::middleware('throttle:public-api')->group(function () {
    Route::get('/api/v1/ppdb/selection-results', [PublicDirectoryApiController::class, 'selectionResults']);
    Route::get('/api/public/students', [PublicDirectoryApiController::class, 'students']);
    Route::get('/api/public/staff', [PublicDirectoryApiController::class, 'staff']);
    Route::get('/api/public/guardians', [PublicDirectoryApiController::class, 'guardians']);
});

// API publik headless TERPROTEKSI (X-Api-Key per tenant + rate-limit). Kontrak:
// docs/INTEGRASI-API.md. Aditif — tak mengubah endpoint publik di atas.
Route::middleware(['api.key', 'throttle:public-api'])->prefix('api/v1')->group(function () {
    Route::get('/profil', [TenantApiController::class, 'profile'])->name('api.tenant.profil');
    Route::get('/statistik', [TenantApiController::class, 'statistics'])->name('api.tenant.statistik');
    Route::get('/jadwal-ppdb', [TenantApiController::class, 'jadwalPpdb'])->name('api.tenant.jadwal-ppdb');
});
