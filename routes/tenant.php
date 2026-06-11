<?php

declare(strict_types=1);

use App\Http\Controllers\KeuanganExportController;
use App\Http\Controllers\AkademikExportController;
use App\Http\Controllers\PortalWali\PortalWaliAcademicController;
use App\Http\Controllers\PortalWali\PortalWaliAuthController;
use App\Http\Controllers\PortalWali\PortalWaliDashboardController;
use App\Http\Controllers\PortalWali\PortalWaliFinancialController;
use App\Http\Controllers\PortalWali\PortalWaliHafalanController;
use App\Http\Controllers\PortalWali\PortalWaliKegiatanController;
use App\Http\Controllers\PortalWali\PortalWaliKesehatanController;
use App\Http\Controllers\PortalWali\PortalWaliIzinController;
use App\Http\Controllers\PortalWali\PortalWaliNotificationController;
use App\Http\Controllers\PortalWali\PortalWaliPembayaranController;
use App\Http\Controllers\PortalWali\PortalWaliPwaController;
use App\Http\Controllers\PortalWali\PortalWaliProfileController;
use App\Http\Controllers\PortalWali\PortalWaliRaporDownloadController;
use App\Http\Controllers\PortalWali\PortalWaliTabunganController;
use App\Http\Controllers\PortalWali\PortalWaliTopupController;
use App\Http\Controllers\SantriExportController;
use Illuminate\Support\Facades\Route;
use Modules\CMS\Http\Controllers\ArticleDetailController;
use Modules\CMS\Http\Controllers\SitemapController;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
 * Tenant routes — resolved by full domain (mis. demo.pesantren.online).
 * PreventAccessFromCentralDomains dijalankan lebih dulu agar domain central
 * (pesantren.online) di-abort 404 sebelum resolver tenant melempar exception.
 * Halaman publik tenant ("/" + "/ppdb") ditangani oleh routes/web.php.
 */
Route::middleware([
    'web',
    PreventAccessFromCentralDomains::class,
    InitializeTenancyByDomain::class,
])->group(function () {

    /*
     * Tenant storage file serve — dipakai blade Settings & Branding untuk
     * menampilkan preview logo. Storage::url('public') menghasilkan URL
     * APP_URL/storage/{path} yang tidak ter-resolve ke path tenant di Docker
     * (tidak ada symlink tenant-specific). Route ini men-serve file langsung
     * dari PHP menggunakan disk 'public' yang sudah ter-bootstrap untuk tenant,
     * sehingga path-nya selalu benar tanpa memerlukan symlink.
     *
     * Akses hanya untuk pengguna yang sudah login (middleware 'auth' via
     * Filament panel — request dari blade Livewire sudah terautentikasi).
     * Fallback: 404 jika file tidak ada.
     */
    Route::get('/_tenant-storage/{path}', function (string $path) {
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        // Sanitasi path — cegah directory traversal
        $path = ltrim(str_replace('..', '', $path), '/');
        abort_unless($disk->exists($path), 404);
        return response()->file(
            $disk->path($path),
            ['Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream'],
        );
    })->where('path', '.*')->name('tenant.storage.file');

    Route::get('/artikel/{slug}', ArticleDetailController::class)->name('tenant.cms.articles.show');
    Route::get('/sitemap.xml', SitemapController::class)->name('tenant.cms.sitemap');

    // Switch mode tampilan panel (simple <-> advanced) — preferensi per user.
    Route::post('/ui-mode/toggle', function () {
        $user = auth()->user();
        abort_unless($user !== null, 403);
        $user->forceFill([
            'ui_mode' => \App\Support\UiMode::simple() ? 'advanced' : 'simple',
        ])->save();

        return back();
    })->middleware('auth')->name('tenant.ui-mode.toggle');

    // App Santri (/app) — login NIS + dashboard ringkas (tahap 1; lanjut: WebAuthn).
    Route::prefix('app')->name('tenant.app-santri.')->group(function () {
        Route::get('/login', [\App\Http\Controllers\AppSantri\AppSantriController::class, 'showLogin'])->name('login');
        Route::post('/login', [\App\Http\Controllers\AppSantri\AppSantriController::class, 'login'])
            ->middleware('throttle:10,1')->name('login.attempt');
        Route::middleware('auth')->group(function () {
            Route::get('/', [\App\Http\Controllers\AppSantri\AppSantriController::class, 'home'])->name('home');
            Route::post('/logout', [\App\Http\Controllers\AppSantri\AppSantriController::class, 'logout'])->name('logout');
        });
    });

    Route::prefix('wali')->name('tenant.portal-wali.pwa.')->group(function () {
        Route::get('/manifest.json', [PortalWaliPwaController::class, 'manifest'])->name('manifest');
        Route::get('/icon.svg', [PortalWaliPwaController::class, 'icon'])->name('icon');
        Route::get('/screenshot.svg', [PortalWaliPwaController::class, 'screenshot'])->name('screenshot');
        Route::get('/sw.js', [PortalWaliPwaController::class, 'serviceWorker'])->name('service-worker');
    });

    Route::prefix('wali')->name('tenant.portal-wali.auth.')->group(function () {
        Route::get('/login', [PortalWaliAuthController::class, 'showLogin'])->name('login');
        Route::get('/aktivasi', [PortalWaliAuthController::class, 'showAktivasi'])->name('activation');
        Route::get('/lupa-password', [PortalWaliAuthController::class, 'showLupaPassword'])->name('password.forgot');
        Route::post('/login', [PortalWaliAuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
        Route::post('/aktivasi', [PortalWaliAuthController::class, 'aktivasi'])->middleware('throttle:10,15')->name('activation.verify');
        Route::post('/aktivasi/kirim-ulang', [PortalWaliAuthController::class, 'kirimUlang'])->middleware('throttle:3,15')->name('activation.resend');
        Route::post('/lupa-password', [PortalWaliAuthController::class, 'lupaPassword'])->middleware('throttle:5,15')->name('password.request');
        Route::post('/lupa-password/reset', [PortalWaliAuthController::class, 'resetPassword'])->middleware('throttle:10,15')->name('password.reset');
    });

    Route::middleware('wali')->prefix('wali')->name('tenant.portal-wali.')->group(function () {
        Route::get('/', PortalWaliDashboardController::class)->name('dashboard');
        Route::post('/logout', [PortalWaliAuthController::class, 'logout'])->name('logout');
        Route::get('/santri/{santri}', PortalWaliProfileController::class)->name('profile');
        Route::get('/santri/{santri}/akademik', PortalWaliAcademicController::class)->name('academic');
        Route::get('/santri/{santri}/keuangan', PortalWaliFinancialController::class)->name('financial');
        Route::get('/notifikasi', PortalWaliNotificationController::class)->name('notifications');
        Route::get('/izin', [PortalWaliIzinController::class, 'index'])->name('izin');
        Route::post('/izin', [PortalWaliIzinController::class, 'store'])->name('izin.store');
        Route::get('/santri/{santri}/rapor/{rapor}/download', PortalWaliRaporDownloadController::class)->name('rapor.download');
        Route::get('/santri/{santri}/hafalan', PortalWaliHafalanController::class)->name('hafalan');
        Route::get('/santri/{santri}/kesehatan', PortalWaliKesehatanController::class)->name('kesehatan');
        Route::get('/santri/{santri}/kegiatan', PortalWaliKegiatanController::class)->name('kegiatan');

        // W2 Pembayaran
        Route::get('/santri/{santri}/tagihan/{tagihan}/bayar', [PortalWaliPembayaranController::class, 'create'])->name('pembayaran.create');
        Route::post('/santri/{santri}/tagihan/{tagihan}/bayar', [PortalWaliPembayaranController::class, 'store'])->name('pembayaran.store');
        Route::get('/pembayaran-saya', [PortalWaliPembayaranController::class, 'index'])->name('pembayaran.index');

        // App Santri: wali atur PIN login anaknya
        Route::post('/santri/{santri}/atur-pin', [\App\Http\Controllers\PortalWali\PortalWaliSantriAkunController::class, 'aturPin'])->name('santri.atur-pin');

        // W3 Tabungan
        Route::get('/santri/{santri}/tabungan', [PortalWaliTabunganController::class, 'show'])->name('tabungan.show');
        Route::post('/santri/{santri}/tabungan/topup', [PortalWaliTabunganController::class, 'topup'])->name('tabungan.topup');
        Route::post('/santri/{santri}/tagihan/{tagihan}/bayar-saldo', [PortalWaliTabunganController::class, 'bayarDariSaldo'])->name('tabungan.bayar-saldo');

        // W3+ Top Up Tabungan — checkout marketplace (DEMO simulasi pembayaran)
        Route::get('/santri/{santri}/topup', [PortalWaliTopupController::class, 'checkout'])->name('topup.checkout');
        Route::post('/santri/{santri}/topup', [PortalWaliTopupController::class, 'store'])->name('topup.store');
        Route::get('/santri/{santri}/topup/{topup}/instruksi', [PortalWaliTopupController::class, 'instruksi'])->name('topup.instruksi');
        Route::post('/santri/{santri}/topup/{topup}/konfirmasi', [PortalWaliTopupController::class, 'konfirmasi'])->name('topup.konfirmasi');
        Route::get('/santri/{santri}/topup/{topup}/sukses', [PortalWaliTopupController::class, 'sukses'])->name('topup.sukses');
    });

    Route::middleware('auth')->prefix('admin/kesantrian/santri/export')->name('tenant.kesantrian.santri.export.')->group(function () {
        Route::get('/csv', [SantriExportController::class, 'csv'])->name('csv');
        Route::get('/print', [SantriExportController::class, 'print'])->name('print');
    });

    Route::middleware('auth')->prefix('admin/akademik/export')->name('tenant.akademik.export.')->group(function () {
        Route::get('/rapor/{rapor}/print', [AkademikExportController::class, 'raporPrint'])->name('rapor.print');
    });

    Route::middleware('auth')->prefix('admin/keuangan/export')->name('tenant.keuangan.export.')->group(function () {
        Route::get('/tagihan/csv', [KeuanganExportController::class, 'tagihanCsv'])->name('tagihan.csv');
        Route::get('/tagihan/print', [KeuanganExportController::class, 'tagihanPrint'])->name('tagihan.print');
        Route::get('/pembayaran/csv', [KeuanganExportController::class, 'pembayaranCsv'])->name('pembayaran.csv');
        Route::get('/pembayaran/print', [KeuanganExportController::class, 'pembayaranPrint'])->name('pembayaran.print');
    });

    Route::middleware('auth')->prefix('admin/pengadaan')->name('tenant.pengadaan.')->group(function () {
        Route::get('/bkk/{belanja}/print', [\App\Http\Controllers\PengadaanExportController::class, 'bkkPrint'])->name('bkk.print');
    });

    Route::middleware('auth')->prefix('admin/payroll')->name('tenant.payroll.')->group(function () {
        Route::get('/slip/{slip}/print', [\App\Http\Controllers\PayrollExportController::class, 'slipPrint'])->name('slip.print');
        // WP-03 — Cetak SK penugasan dari template ber-placeholder.
        Route::get('/penugasan/{penugasan}/sk', [\App\Http\Controllers\PayrollExportController::class, 'skPenugasan'])->name('penugasan.sk');
    });

    Route::middleware('auth')->prefix('admin/emis')->name('tenant.emis.')->group(function () {
        Route::get('/export/csv', [\App\Http\Controllers\EmisExportController::class, 'csv'])->name('export.csv');
    });

    // PWA Keuangan Lapangan (Vue/Inertia). Memakai API api/keuangan/* yang sudah ada.
    Route::middleware('auth')->prefix('admin/keuangan/pwa')->name('tenant.keuangan.pwa.')->group(function () {
        Route::get('/', [\App\Http\Controllers\KeuanganPwaController::class, 'beranda'])->name('beranda');
        Route::get('/catat-belanja', [\App\Http\Controllers\KeuanganPwaController::class, 'catatBelanja'])->name('belanja');
        Route::get('/catat-pemasukan', [\App\Http\Controllers\KeuanganPwaController::class, 'catatPemasukan'])->name('pemasukan');
        Route::get('/dasbor', [\App\Http\Controllers\KeuanganPwaController::class, 'dasbor'])->name('dasbor');
        Route::get('/slip-saya', [\App\Http\Controllers\KeuanganPwaController::class, 'slipSaya'])->name('slip');
        Route::get('/setor-keliling', [\App\Http\Controllers\KeuanganPwaController::class, 'setorKeliling'])->name('setor-keliling');
    });

    Route::middleware('auth')->prefix('admin/pembiayaan')->name('tenant.pembiayaan.')->group(function () {
        Route::get('/{pengajuan}/paket-kredit/print', [\App\Http\Controllers\PembiayaanExportController::class, 'paketKreditPrint'])->name('paket.print');
    });

    // API PWA Keuangan Lapangan (staf). Tenant di-resolve via web group; auth Sanctum.
    Route::middleware(['auth:sanctum'])->prefix('api/keuangan')->name('tenant.api.keuangan.')->group(function () {
        Route::post('/belanja', [\App\Http\Controllers\KeuanganApiController::class, 'belanja'])->name('belanja');
        Route::post('/pemasukan', [\App\Http\Controllers\KeuanganApiController::class, 'pemasukan'])->name('pemasukan');
        Route::get('/saldo', [\App\Http\Controllers\KeuanganApiController::class, 'saldo'])->name('saldo');
        Route::get('/dashboard-pimpinan', [\App\Http\Controllers\KeuanganApiController::class, 'dashboardPimpinan'])->name('dashboard');
        Route::get('/slip-saya', [\App\Http\Controllers\KeuanganApiController::class, 'slipSaya'])->name('slip');
        // WP-32: Setor Keliling di PWA (gate setor_tabungan di controller).
        Route::get('/setor-keliling/kamar', [\App\Http\Controllers\KeuanganApiController::class, 'setorKelilingKamar'])->name('setor-keliling.kamar');
        Route::get('/setor-keliling/kamar/{kamar}/santri', [\App\Http\Controllers\KeuanganApiController::class, 'setorKelilingSantri'])->name('setor-keliling.santri');
        Route::post('/setor-keliling/setoran', [\App\Http\Controllers\KeuanganApiController::class, 'setorKelilingSetoran'])->name('setor-keliling.setoran');
        Route::get('/setor-keliling/sesi', [\App\Http\Controllers\KeuanganApiController::class, 'setorKelilingSesi'])->name('setor-keliling.sesi');
        Route::post('/setor-keliling/ajukan', [\App\Http\Controllers\KeuanganApiController::class, 'setorKelilingAjukan'])->name('setor-keliling.ajukan');
    });
});
