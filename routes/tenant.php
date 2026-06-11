<?php

declare(strict_types=1);

use App\Http\Controllers\AkademikExportController;
use App\Http\Controllers\AppSantri\AppSantriController;
use App\Http\Controllers\EmisExportController;
use App\Http\Controllers\KeuanganApiController;
use App\Http\Controllers\KeuanganExportController;
use App\Http\Controllers\KeuanganPwaController;
use App\Http\Controllers\PayrollExportController;
use App\Http\Controllers\PembiayaanExportController;
use App\Http\Controllers\PengadaanExportController;
use App\Http\Controllers\PortalGuru\PortalGuruBerandaController;
use App\Http\Controllers\PortalGuru\PortalGuruDisiplinController;
use App\Http\Controllers\PortalGuru\PortalGuruGerbangController;
use App\Http\Controllers\PortalGuru\PortalGuruIzinController;
use App\Http\Controllers\PortalGuru\PortalGuruJurnalController;
use App\Http\Controllers\PortalGuru\PortalGuruKitabController;
use App\Http\Controllers\PortalGuru\PortalGuruNilaiController;
use App\Http\Controllers\PortalGuru\PortalGuruPresensiController;
use App\Http\Controllers\PortalGuru\PortalGuruPwaController;
use App\Http\Controllers\PortalGuru\PortalGuruSetoranController;
use App\Http\Controllers\PortalGuru\PortalGuruTahfidzLanjutanController;
use App\Http\Controllers\PortalGuru\PortalGuruWaliKelasController;
use App\Http\Controllers\PortalWali\PortalWaliAcademicController;
use App\Http\Controllers\PortalWali\PortalWaliAuthController;
use App\Http\Controllers\PortalWali\PortalWaliDashboardController;
use App\Http\Controllers\PortalWali\PortalWaliFinancialController;
use App\Http\Controllers\PortalWali\PortalWaliHafalanController;
use App\Http\Controllers\PortalWali\PortalWaliIzinController;
use App\Http\Controllers\PortalWali\PortalWaliJengukController;
use App\Http\Controllers\PortalWali\PortalWaliKegiatanController;
use App\Http\Controllers\PortalWali\PortalWaliKesehatanController;
use App\Http\Controllers\PortalWali\PortalWaliNotificationController;
use App\Http\Controllers\PortalWali\PortalWaliPembayaranController;
use App\Http\Controllers\PortalWali\PortalWaliProfileController;
use App\Http\Controllers\PortalWali\PortalWaliPwaController;
use App\Http\Controllers\PortalWali\PortalWaliRaporDiniyahDownloadController;
use App\Http\Controllers\PortalWali\PortalWaliRaporDownloadController;
use App\Http\Controllers\PortalWali\PortalWaliSantriAkunController;
use App\Http\Controllers\PortalWali\PortalWaliTabunganController;
use App\Http\Controllers\PortalWali\PortalWaliTopupController;
use App\Http\Controllers\SantriExportController;
use App\Http\Controllers\TahfidzExportController;
use App\Support\UiMode;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
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
        $disk = Storage::disk('public');
        // Sanitasi path — cegah directory traversal
        $path = ltrim(str_replace('..', '', $path), '/');

        // Direktori dokumen sensitif (arsip HR/kesantrian) WAJIB login —
        // sisanya (logo, galeri landing) tetap publik.
        $direktoriSensitif = ['kontrak-kerja', 'mutasi', 'disiplin', 'sertifikasi'];
        $segmenPertama = explode('/', $path, 2)[0];
        if (in_array($segmenPertama, $direktoriSensitif, true)) {
            abort_unless(auth()->check(), 403);
        }

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
            'ui_mode' => UiMode::simple() ? 'advanced' : 'simple',
        ])->save();

        return back();
    })->middleware('auth')->name('tenant.ui-mode.toggle');

    // App Santri (/app) — login NIS + dashboard ringkas (tahap 1; lanjut: WebAuthn).
    Route::prefix('app')->name('tenant.app-santri.')->group(function () {
        Route::get('/login', [AppSantriController::class, 'showLogin'])->name('login');
        Route::post('/login', [AppSantriController::class, 'login'])
            ->middleware('throttle:10,1')->name('login.attempt');
        Route::middleware('auth')->group(function () {
            Route::get('/', [AppSantriController::class, 'home'])->name('home');
            Route::post('/logout', [AppSantriController::class, 'logout'])->name('logout');
        });
    });

    // F1-PWA — PWA Guru (/guru): aset PWA publik (manifest/SW/ikon, pola
    // PortalWaliPwaController) + layar inti di balik auth + can:akses_pwa_guru.
    Route::prefix('guru')->name('tenant.portal-guru.pwa.')->group(function () {
        Route::get('/manifest.json', [PortalGuruPwaController::class, 'manifest'])->name('manifest');
        Route::get('/icon.svg', [PortalGuruPwaController::class, 'icon'])->name('icon');
        Route::get('/sw.js', [PortalGuruPwaController::class, 'serviceWorker'])->name('service-worker');
    });

    Route::middleware(['auth', 'can:akses_pwa_guru'])->prefix('guru')->name('tenant.portal-guru.')->group(function () {
        Route::get('/', PortalGuruBerandaController::class)->name('beranda');
        Route::get('/presensi/{jadwal}', [PortalGuruPresensiController::class, 'show'])->name('presensi.show');
        Route::post('/presensi/{jadwal}', [PortalGuruPresensiController::class, 'store'])->name('presensi.store');
        Route::get('/setoran', [PortalGuruSetoranController::class, 'index'])->name('setoran.index');
        Route::get('/setoran/{halaqah}', [PortalGuruSetoranController::class, 'form'])->name('setoran.form');
        Route::post('/setoran', [PortalGuruSetoranController::class, 'store'])->name('setoran.store');

        // WP-11 — mode muhaffizh: sima'an & munaqasyah (idempoten client_ref).
        Route::get('/simaan', [PortalGuruTahfidzLanjutanController::class, 'simaanForm'])->name('simaan.form');
        Route::post('/simaan', [PortalGuruTahfidzLanjutanController::class, 'simaanStore'])->name('simaan.store');
        Route::get('/munaqasyah', [PortalGuruTahfidzLanjutanController::class, 'munaqasyahForm'])->name('munaqasyah.form');
        Route::post('/munaqasyah', [PortalGuruTahfidzLanjutanController::class, 'munaqasyahStore'])->name('munaqasyah.store');

        // WP-24 — PWA Guru Disiplin (kepengasuhan mode)
        Route::get('/disiplin/daftar', [PortalGuruDisiplinController::class, 'daftarPelanggaran'])->name('disiplin.daftar');
        Route::get('/disiplin/catat', [PortalGuruDisiplinController::class, 'formCatatPelanggaran'])->name('disiplin.catat');
        Route::get('/disiplin/poin', [PortalGuruDisiplinController::class, 'poinView'])->name('disiplin.poin');

        // WP-17 — Izin Pegawai Harian (ajukan + antrean approver)
        Route::get('/izin', [PortalGuruIzinController::class, 'index'])->name('izin.index');
        Route::post('/izin', [PortalGuruIzinController::class, 'store'])->name('izin.store');
        Route::post('/izin/{cuti}/setujui', [PortalGuruIzinController::class, 'setujui'])->name('izin.setujui');
        Route::post('/izin/{cuti}/tolak', [PortalGuruIzinController::class, 'tolak'])->name('izin.tolak');

        // F2-PWA — Grid nilai massal (autosave per baris, idempoten upsert)
        Route::get('/nilai', [PortalGuruNilaiController::class, 'index'])->name('nilai.index');
        Route::get('/nilai/{kelas}/{pelajaran}', [PortalGuruNilaiController::class, 'grid'])->name('nilai.grid');
        Route::post('/nilai/{kelas}/{pelajaran}', [PortalGuruNilaiController::class, 'simpanBaris'])->name('nilai.simpan');

        // F2-PWA — Jurnal KBM 30-detik (menempel layar presensi)
        Route::post('/jurnal/{jadwal}', [PortalGuruJurnalController::class, 'store'])->name('jurnal.store');

        // F2-PWA — Capaian kitab (sorogan per santri / bandongan kelompok)
        Route::get('/kitab', [PortalGuruKitabController::class, 'index'])->name('kitab.index');
        Route::get('/kitab/{kajian}', [PortalGuruKitabController::class, 'form'])->name('kitab.form');
        Route::post('/kitab/{kajian}', [PortalGuruKitabController::class, 'store'])->name('kitab.store');

        // F2-PWA — Layar wali kelas (rekap binaan + catatan rapor)
        Route::get('/wali-kelas', [PortalGuruWaliKelasController::class, 'index'])->name('wali-kelas.index');
        Route::get('/wali-kelas/{kelas}', [PortalGuruWaliKelasController::class, 'show'])->name('wali-kelas.show');
        Route::post('/wali-kelas/{kelas}/catatan', [PortalGuruWaliKelasController::class, 'simpanCatatan'])->name('wali-kelas.catatan');

        // WP-29/30 — Pos Gerbang (mode keamanan): scan NIS, catat keluar/
        // masuk, cache izin aktif, registrasi tamu penjengukan.
        Route::get('/gerbang', [PortalGuruGerbangController::class, 'index'])->name('gerbang.index');
        Route::post('/gerbang/catat', [PortalGuruGerbangController::class, 'catat'])->name('gerbang.catat');
        Route::get('/gerbang/izin-aktif', [PortalGuruGerbangController::class, 'izinAktif'])->name('gerbang.izin-aktif');
        Route::post('/gerbang/jenguk/{pengajuan}/hadir', [PortalGuruGerbangController::class, 'jengukHadir'])->name('gerbang.jenguk.hadir');
        Route::post('/gerbang/jenguk/walk-in', [PortalGuruGerbangController::class, 'jengukWalkIn'])->name('gerbang.jenguk.walk-in');
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

        // WP-30 — ajukan slot jenguk + lihat status (anak sendiri saja).
        Route::get('/jenguk', [PortalWaliJengukController::class, 'index'])->name('jenguk');
        Route::post('/jenguk', [PortalWaliJengukController::class, 'store'])->name('jenguk.store');
        Route::post('/jenguk/{pengajuan}/batal', [PortalWaliJengukController::class, 'batal'])->name('jenguk.batal');
        Route::get('/santri/{santri}/rapor/{rapor}/download', PortalWaliRaporDownloadController::class)->name('rapor.download');
        // WP-10 — unduh/cetak rapor diniyah anak sendiri (403 untuk anak orang lain).
        Route::get('/santri/{santri}/rapor-diniyah/{raporDiniyah}/download', PortalWaliRaporDiniyahDownloadController::class)->name('rapor-diniyah.download');
        Route::get('/santri/{santri}/hafalan', PortalWaliHafalanController::class)->name('hafalan');
        Route::get('/santri/{santri}/kesehatan', PortalWaliKesehatanController::class)->name('kesehatan');
        Route::get('/santri/{santri}/kegiatan', PortalWaliKegiatanController::class)->name('kegiatan');

        // W2 Pembayaran
        Route::get('/santri/{santri}/tagihan/{tagihan}/bayar', [PortalWaliPembayaranController::class, 'create'])->name('pembayaran.create');
        Route::post('/santri/{santri}/tagihan/{tagihan}/bayar', [PortalWaliPembayaranController::class, 'store'])->name('pembayaran.store');
        Route::get('/pembayaran-saya', [PortalWaliPembayaranController::class, 'index'])->name('pembayaran.index');

        // App Santri: wali atur PIN login anaknya
        Route::post('/santri/{santri}/atur-pin', [PortalWaliSantriAkunController::class, 'aturPin'])->name('santri.atur-pin');

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
        // WP-10 — cetak rapor diniyah; blok tampil mengikuti Setting tenant.
        Route::get('/rapor-diniyah/{raporDiniyah}/print', [AkademikExportController::class, 'raporDiniyahPrint'])->name('rapor-diniyah.print');
        // WP-08 — cetak syahadah (sertifikat internal) per alumni.
        Route::get('/syahadah/{alumni}/print', [AkademikExportController::class, 'syahadahPrint'])->name('syahadah.print');
    });

    // WP-11 — cetak daftar peserta wisuda tahfidz per periode (lulus + ditandai).
    Route::middleware('auth')->prefix('admin/tahfidz/export')->name('tenant.tahfidz.export.')->group(function () {
        Route::get('/wisuda/print', [TahfidzExportController::class, 'wisudaPrint'])->name('wisuda.print');
    });

    Route::middleware('auth')->prefix('admin/keuangan/export')->name('tenant.keuangan.export.')->group(function () {
        Route::get('/tagihan/csv', [KeuanganExportController::class, 'tagihanCsv'])->name('tagihan.csv');
        Route::get('/tagihan/print', [KeuanganExportController::class, 'tagihanPrint'])->name('tagihan.print');
        Route::get('/pembayaran/csv', [KeuanganExportController::class, 'pembayaranCsv'])->name('pembayaran.csv');
        Route::get('/pembayaran/print', [KeuanganExportController::class, 'pembayaranPrint'])->name('pembayaran.print');
    });

    Route::middleware('auth')->prefix('admin/pengadaan')->name('tenant.pengadaan.')->group(function () {
        Route::get('/bkk/{belanja}/print', [PengadaanExportController::class, 'bkkPrint'])->name('bkk.print');
    });

    Route::middleware('auth')->prefix('admin/payroll')->name('tenant.payroll.')->group(function () {
        Route::get('/slip/{slip}/print', [PayrollExportController::class, 'slipPrint'])->name('slip.print');
        // WP-03 — Cetak SK penugasan dari template ber-placeholder.
        Route::get('/penugasan/{penugasan}/sk', [PayrollExportController::class, 'skPenugasan'])->name('penugasan.sk');
    });

    Route::middleware('auth')->prefix('admin/emis')->name('tenant.emis.')->group(function () {
        Route::get('/export/csv', [EmisExportController::class, 'csv'])->name('export.csv');
    });

    // PWA Keuangan Lapangan (Vue/Inertia). Memakai API api/keuangan/* yang sudah ada.
    Route::middleware('auth')->prefix('admin/keuangan/pwa')->name('tenant.keuangan.pwa.')->group(function () {
        Route::get('/', [KeuanganPwaController::class, 'beranda'])->name('beranda');
        Route::get('/catat-belanja', [KeuanganPwaController::class, 'catatBelanja'])->name('belanja');
        Route::get('/catat-pemasukan', [KeuanganPwaController::class, 'catatPemasukan'])->name('pemasukan');
        Route::get('/dasbor', [KeuanganPwaController::class, 'dasbor'])->name('dasbor');
        Route::get('/slip-saya', [KeuanganPwaController::class, 'slipSaya'])->name('slip');
        Route::get('/setor-keliling', [KeuanganPwaController::class, 'setorKeliling'])->name('setor-keliling');
    });

    Route::middleware('auth')->prefix('admin/pembiayaan')->name('tenant.pembiayaan.')->group(function () {
        Route::get('/{pengajuan}/paket-kredit/print', [PembiayaanExportController::class, 'paketKreditPrint'])->name('paket.print');
    });

    // API PWA Keuangan Lapangan (staf). Tenant di-resolve via web group; auth Sanctum.
    Route::middleware(['auth:sanctum'])->prefix('api/keuangan')->name('tenant.api.keuangan.')->group(function () {
        Route::post('/belanja', [KeuanganApiController::class, 'belanja'])->name('belanja');
        Route::post('/pemasukan', [KeuanganApiController::class, 'pemasukan'])->name('pemasukan');
        Route::get('/saldo', [KeuanganApiController::class, 'saldo'])->name('saldo');
        Route::get('/dashboard-pimpinan', [KeuanganApiController::class, 'dashboardPimpinan'])->name('dashboard');
        Route::get('/slip-saya', [KeuanganApiController::class, 'slipSaya'])->name('slip');
        // WP-32: Setor Keliling di PWA (gate setor_tabungan di controller).
        Route::get('/setor-keliling/kamar', [KeuanganApiController::class, 'setorKelilingKamar'])->name('setor-keliling.kamar');
        Route::get('/setor-keliling/kamar/{kamar}/santri', [KeuanganApiController::class, 'setorKelilingSantri'])->name('setor-keliling.santri');
        Route::post('/setor-keliling/setoran', [KeuanganApiController::class, 'setorKelilingSetoran'])->name('setor-keliling.setoran');
        Route::get('/setor-keliling/sesi', [KeuanganApiController::class, 'setorKelilingSesi'])->name('setor-keliling.sesi');
        Route::post('/setor-keliling/ajukan', [KeuanganApiController::class, 'setorKelilingAjukan'])->name('setor-keliling.ajukan');
    });
});
