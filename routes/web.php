<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\CmsPublicApiController;
use App\Http\Controllers\Api\IbadahApiController;
use App\Http\Controllers\Api\KomentarApiController;
use App\Http\Controllers\Api\PpdbPublicApiController;
use App\Http\Controllers\Api\PublicDirectoryApiController;
use App\Http\Controllers\Api\TenantApiController;
use App\Http\Controllers\Auth\StaffLoginController;
use App\Http\Controllers\DonasiPublikController;
use App\Http\Controllers\HapusAkunController;
use App\Http\Controllers\LauncherController;
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

// Root: central landing (pesantren.online) atau PINTU SISTEM tenant (domain sistem).
// Domain sistem tenant (mis. pesantren.asy-syifaa.com) = satu pintu terkunci login:
// guest → login bermerek dirender langsung di '/'; setelah login → diarahkan per
// peran (LauncherController + App\Support\AppLauncher). Website publik = domain
// terpisah (type='website').
Route::get('/', function () {
    if (in_array(request()->getHost(), config('tenancy.central_domains', []), true)) {
        return view('landing');
    }

    $domainType = Domain::query()
        ->where('domain', request()->getHost())
        ->value('type');

    if ($domainType === 'sistem') {
        return app()->call([app(LauncherController::class), '__invoke']);
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

// ── Satu pintu login: GET /masuk (UNIVERSAL, semua domain tenant) ──
// GET /masuk merender form login bermerek LANGSUNG (StaffLoginController::show),
// jadi berlaku di SEMUA domain tenant — termasuk tenant yang '/' nya dipakai
// WEBSITE publik (mis. subdomain {slug}.pesantren.online) di mana '/' bukan
// halaman login. (Domain 'sistem' tetap juga menampilkan login di '/'.) Form
// mem-POST ke /masuk (store); sukses → tujuan per peran (staf → /admin) via
// AppLauncher, jadi tak nyangkut di website publik.
Route::get('/masuk', [StaffLoginController::class, 'show'])->name('tenant.masuk.show');
Route::post('/masuk', [StaffLoginController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('tenant.masuk.store');
Route::post('/keluar', [StaffLoginController::class, 'logout'])->name('tenant.masuk.logout');

// Fallback login route untuk middleware 'auth' (termasuk redirect-guest panel
// Filament tenant) → /masuk (form login universal). Login panel /admin/login
// DISATUKAN ke /masuk lewat App\Filament\Pages\Auth\Login (redirect), BUKAN
// shadow route web — menimpa GET /admin/login via route web pernah 500.
Route::get('/login', fn () => redirect('/masuk'))->name('login');

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

// Penghapusan Akun & Data — WAJIB Google Play (account/data deletion URL).
// DOMAIN TENANT: halaman bermerek + redaksi (PenghapusanAkun, bisa diedit
// pesantren) + kanal kontak tenant. Central: versi generik platform.
// Publik (tanpa login) agar reviewer Play & pengguna bisa mengakses; dipakai
// sebagai URL "Penghapusan Akun" di formulir Data safety Play Console.
Route::get('/hapus-akun', HapusAkunController::class)->name('legal.account-deletion');

Route::get('/syarat-layanan', fn () => view('legal.terms-of-service'))->name('legal.terms');
Route::get('/disclaimer', fn () => view('legal.disclaimer'))->name('legal.disclaimer');
Route::get('/cookie-policy', fn () => view('legal.cookie-policy'))->name('legal.cookies');
Route::get('/kontak', fn () => view('legal.contact'))->name('legal.contact');

// ── API publik konten CMS untuk website tenant (mis. api.asy-syifaa.com) ──
// Tenant di-resolve dari domain oleh middleware tenancy web. CORS: config/cors.php.
// Kontrak: docs/SPEC-API-WEBSITE-ASY-SYIFAA.md
// HARDENING (red-team RATE-002): semua endpoint baca publik di-throttle per
// tenant+IP (limiter 'public-api') agar tak bisa di-scrape/DoS tanpa batas.
Route::middleware('throttle:public-api')->group(function () {
    Route::get('/api/v1/posts', [CmsPublicApiController::class, 'posts']);
    Route::get('/api/v1/posts/{slug}', [CmsPublicApiController::class, 'post']);
    Route::get('/api/public/content/gallery', [CmsPublicApiController::class, 'gallery']);
    Route::get('/api/public/content/gallery/{slug}', [CmsPublicApiController::class, 'galleryItem']);
    Route::get('/api/public/content/profile', [CmsPublicApiController::class, 'profile']);
    Route::get('/api/public/content/settings', [CmsPublicApiController::class, 'settings']);
    // Ibadah publik (1-aplikasi / native Android): jadwal sholat + arah kiblat + donasi.
    Route::get('/api/v1/jadwal-sholat', IbadahApiController::class);
});

// Auth aplikasi NATIVE (token Sanctum). login=jemaah; demo=per-peran (gerbang demo).
// Throttle kasar per-IP di route + RateLimiter per-identifier di controller.
Route::post('/api/v1/auth/login', [AuthApiController::class, 'login'])->middleware('throttle:20,1');
Route::post('/api/v1/auth/demo/{peran}', [AuthApiController::class, 'demoLogin'])->middleware('throttle:30,1');
// Registrasi mandiri jemaah (No.WA + OTP WhatsApp), mirror JemaahAuthController.
Route::post('/api/v1/auth/daftar', [AuthApiController::class, 'daftar'])->middleware('throttle:10,1');
Route::post('/api/v1/auth/aktivasi', [AuthApiController::class, 'aktivasi'])->middleware('throttle:15,1');
Route::post('/api/v1/auth/kirim-ulang', [AuthApiController::class, 'kirimUlang'])->middleware('throttle:6,1');
Route::middleware(['auth:sanctum', 'throttle:public-api'])->group(function () {
    Route::get('/api/v1/auth/me', [AuthApiController::class, 'me']);
    Route::post('/api/v1/auth/logout', [AuthApiController::class, 'logout']);
});

// Komentar artikel (native): baca publik (approved) + kirim (token + izin komentar).
// Gerbang sama dgn web CommentController: auth + can:komentar + throttle:komentar.
Route::get('/api/v1/posts/{slug}/komentar', [KomentarApiController::class, 'index'])
    ->middleware('throttle:public-api');
Route::post('/api/v1/posts/{slug}/komentar', [KomentarApiController::class, 'store'])
    ->middleware(['auth:sanctum', 'can:komentar', 'throttle:komentar']);

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
