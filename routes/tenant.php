<?php

declare(strict_types=1);

use App\Http\Controllers\AkademikExportController;
use App\Http\Controllers\AppSantri\AppSantriController;
use App\Http\Controllers\AppSantri\AppSantriPengurusController;
use App\Http\Controllers\AppSantri\AppSantriUjianController;
use App\Http\Controllers\CetakController;
use App\Http\Controllers\DemoApps\HumasDemoController;
use App\Http\Controllers\DemoApps\KeamananDemoController;
use App\Http\Controllers\DemoApps\KepegawaianDemoController;
use App\Http\Controllers\DemoApps\PengabdianDemoController;
use App\Http\Controllers\DemoApps\PengasuhanDemoController;
use App\Http\Controllers\DemoApps\PosKantinDemoController;
use App\Http\Controllers\DemoApps\PpdbPanitiaDemoController;
use App\Http\Controllers\DemoApps\SantriDemoController;
use App\Http\Controllers\DemoApps\TuDemoController;
use App\Http\Controllers\DemoApps\UksDemoController;
use App\Http\Controllers\DemoAutoLoginController;
use App\Http\Controllers\DemoPickerController;
use App\Http\Controllers\EmisExportController;
use App\Http\Controllers\Jemaah\JemaahAuthController;
use App\Http\Controllers\KepalaKeuangan\KepalaKeuanganApiController;
use App\Http\Controllers\KepalaKeuangan\KepalaKeuanganPwaBrandingController;
use App\Http\Controllers\KepalaKeuangan\KepalaKeuanganPwaController as KepalaKeuanganCtrl;
use App\Http\Controllers\KeuanganApiController;
use App\Http\Controllers\KeuanganExportController;
use App\Http\Controllers\KeuanganPwaController;
use App\Http\Controllers\PayrollExportController;
use App\Http\Controllers\PembiayaanExportController;
use App\Http\Controllers\PengadaanExportController;
use App\Http\Controllers\PimpinanPwaController;
use App\Http\Controllers\PortalGuru\PortalGuruBerandaController;
use App\Http\Controllers\PortalGuru\PortalGuruDisiplinController;
use App\Http\Controllers\PortalGuru\PortalGuruGerbangController;
use App\Http\Controllers\PortalGuru\PortalGuruIzinController;
use App\Http\Controllers\PortalGuru\PortalGuruJurnalController;
use App\Http\Controllers\PortalGuru\PortalGuruKitabController;
use App\Http\Controllers\PortalGuru\PortalGuruNilaiController;
use App\Http\Controllers\PortalGuru\PortalGuruPesanWaliController;
use App\Http\Controllers\PortalGuru\PortalGuruPresensiController;
use App\Http\Controllers\PortalGuru\PortalGuruPwaController;
use App\Http\Controllers\PortalGuru\PortalGuruSetoranController;
use App\Http\Controllers\PortalGuru\PortalGuruSlipController;
use App\Http\Controllers\PortalGuru\PortalGuruTahfidzLanjutanController;
use App\Http\Controllers\PortalGuru\PortalGuruUjianController;
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
use App\Http\Controllers\RootPwaController;
use App\Http\Controllers\SantriExportController;
use App\Http\Controllers\TahfidzExportController;
use App\Support\TenantBranding;
use App\Support\UiMode;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Modules\CMS\Http\Controllers\ArticleDetailController;
use Modules\CMS\Http\Controllers\CommentController;
use Modules\CMS\Http\Controllers\SitemapController;
use Modules\Keuangan\Models\Donasi;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\KontrakKerja;
use Modules\Payroll\Services\RaporUstadzService;
use Modules\Pembiayaan\Models\AreaPiket;
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
        // 'donasi' = bukti transfer unggahan publik — hanya admin yang perlu
        // melihat balik (anti stored-XSS / anti enumerasi bukti orang lain).
        $direktoriSensitif = ['kontrak-kerja', 'mutasi', 'disiplin', 'sertifikasi', 'donasi', 'kerusakan', 'pelamar'];
        $segmenPertama = explode('/', $path, 2)[0];
        if (in_array($segmenPertama, $direktoriSensitif, true)) {
            abort_unless(auth()->check(), 403);
        }

        // Berkas HR (CV/ijazah pelamar, kontrak/SK) lebih ketat: akun santri/
        // wali ikut "login" — wajib hak payroll/keuangan, bukan sekadar authed.
        if (in_array($segmenPertama, ['pelamar', 'kontrak-kerja'], true)) {
            abort_unless(
                auth()->user()?->canAny(['run_payroll', 'manage_payroll', 'manage_keuangan']) ?? false,
                403,
            );
        }

        abort_unless($disk->exists($path), 404);

        return response()->file(
            $disk->path($path),
            ['Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream'],
        );
    })->where('path', '.*')->name('tenant.storage.file');

    /*
     * Auto-login demo untuk PWA/TWA showcase — melogin user demo lalu redirect
     * ke PWA terkait TANPA kredensial. Dikunci ganda di controller: flag
     * DEMO_AUTOLOGIN + tenant harus 'demo' (selain itu 404). Lihat config/demo.php.
     * peran: guru | wali | keuangan | keuangan-lapangan | pimpinan
     */
    Route::get('/demo-masuk/{peran}', DemoAutoLoginController::class)
        ->name('tenant.demo.autologin');

    // Etalase demo — pemilih peran (satu klik login per peran). Gerbang sama:
    // hanya tenant demo + DEMO_AUTOLOGIN (selain itu 404).
    Route::get('/coba', [DemoPickerController::class, 'show'])->name('tenant.demo.picker');
    Route::get('/demo', [DemoPickerController::class, 'show'])->name('tenant.demo.picker.alias');

    /*
     * ── App PWA demo peran (controller-dummy, gate auth saja) ──
     * 9 aplikasi showcase: UKS, TU, Humas, Kepegawaian, PPDB Panitia,
     * POS Kantin, Keamanan, Pengasuhan, Pengabdian. Data dummy inline di
     * controller (nol query) → layar selalu penuh & aman. Auto-login persona
     * via /demo-masuk/{key}. Semua pakai UI kit @/Shared.
     */
    Route::middleware('auth')->prefix('uks')->name('tenant.uks.')->group(function () {
        Route::get('/', [UksDemoController::class, 'beranda'])->name('beranda');
        Route::get('/kunjungan', [UksDemoController::class, 'kunjungan'])->name('kunjungan');
        Route::get('/kunjungan/catat', [UksDemoController::class, 'catatKunjungan'])->name('kunjungan.catat');
        Route::get('/obat', [UksDemoController::class, 'obat'])->name('obat');
        Route::get('/rujukan', [UksDemoController::class, 'rujukan'])->name('rujukan');
        Route::get('/riwayat', [UksDemoController::class, 'riwayat'])->name('riwayat');
    });

    Route::middleware('auth')->prefix('tu')->name('tenant.tu.')->group(function () {
        Route::get('/', [TuDemoController::class, 'beranda'])->name('beranda');
        Route::get('/santri', [TuDemoController::class, 'santri'])->name('santri');
        Route::get('/santri/detail', [TuDemoController::class, 'detailSantri'])->name('detail-santri');
        Route::get('/surat', [TuDemoController::class, 'surat'])->name('surat');
        Route::get('/catat-surat', [TuDemoController::class, 'catatSurat'])->name('catat-surat');
        Route::get('/arsip', [TuDemoController::class, 'arsip'])->name('arsip');
    });

    Route::middleware('auth')->prefix('humas')->name('tenant.humas.')->group(function () {
        Route::get('/', [HumasDemoController::class, 'beranda'])->name('beranda');
        Route::get('/berita', [HumasDemoController::class, 'berita'])->name('berita');
        Route::get('/berita/{artikel}', [HumasDemoController::class, 'detailArtikel'])->name('detail-artikel');
        Route::get('/galeri', [HumasDemoController::class, 'galeri'])->name('galeri');
        Route::get('/agenda', [HumasDemoController::class, 'agenda'])->name('agenda');
    });

    Route::middleware('auth')->prefix('kepegawaian')->name('tenant.kepegawaian.')->group(function () {
        Route::get('/', [KepegawaianDemoController::class, 'beranda'])->name('beranda');
        Route::get('/pegawai', [KepegawaianDemoController::class, 'pegawai'])->name('pegawai');
        Route::get('/pegawai/{id}', [KepegawaianDemoController::class, 'detailPegawai'])->name('detail-pegawai');
        Route::get('/absensi', [KepegawaianDemoController::class, 'absensi'])->name('absensi');
        Route::get('/slip', [KepegawaianDemoController::class, 'slip'])->name('slip');
        Route::get('/cuti', [KepegawaianDemoController::class, 'cuti'])->name('cuti');
    });

    Route::middleware('auth')->prefix('ppdb-panitia')->name('tenant.ppdb-panitia.')->group(function () {
        Route::get('/', [PpdbPanitiaDemoController::class, 'beranda'])->name('beranda');
        Route::get('/pendaftar', [PpdbPanitiaDemoController::class, 'pendaftar'])->name('pendaftar');
        Route::get('/detail', [PpdbPanitiaDemoController::class, 'detail'])->name('detail');
        Route::get('/verifikasi', [PpdbPanitiaDemoController::class, 'verifikasi'])->name('verifikasi');
        Route::get('/keputusan', [PpdbPanitiaDemoController::class, 'keputusan'])->name('keputusan');
        Route::get('/hasil', [PpdbPanitiaDemoController::class, 'hasil'])->name('hasil');
    });

    Route::middleware('auth')->prefix('pos-kantin')->name('tenant.pos-kantin.')->group(function () {
        Route::get('/', [PosKantinDemoController::class, 'beranda'])->name('beranda');
        Route::get('/kasir', [PosKantinDemoController::class, 'kasir'])->name('kasir');
        Route::get('/tabungan', [PosKantinDemoController::class, 'tabungan'])->name('tabungan');
        Route::get('/stok', [PosKantinDemoController::class, 'stok'])->name('stok');
        Route::get('/riwayat', [PosKantinDemoController::class, 'riwayat'])->name('riwayat');
    });

    Route::middleware('auth')->prefix('keamanan')->name('tenant.keamanan.')->group(function () {
        Route::get('/', [KeamananDemoController::class, 'beranda'])->name('beranda');
        Route::get('/gerbang', [KeamananDemoController::class, 'gerbang'])->name('gerbang');
        Route::get('/tamu', [KeamananDemoController::class, 'tamu'])->name('tamu');
        Route::get('/log', [KeamananDemoController::class, 'log'])->name('log');
        Route::get('/ronda', [KeamananDemoController::class, 'ronda'])->name('ronda');
    });

    Route::middleware('auth')->prefix('pengasuhan')->name('tenant.pengasuhan.')->group(function () {
        Route::get('/', [PengasuhanDemoController::class, 'beranda'])->name('beranda');
        Route::get('/presensi', [PengasuhanDemoController::class, 'presensiAsrama'])->name('presensi');
        Route::get('/disiplin', [PengasuhanDemoController::class, 'disiplin'])->name('disiplin');
        Route::get('/disiplin/catat', [PengasuhanDemoController::class, 'catatPelanggaran'])->name('catat');
        Route::get('/izin', [PengasuhanDemoController::class, 'izin'])->name('izin');
    });

    Route::middleware('auth')->prefix('pengabdian')->name('tenant.pengabdian.')->group(function () {
        Route::get('/', [PengabdianDemoController::class, 'beranda'])->name('beranda');
        Route::get('/jadwal', [PengabdianDemoController::class, 'jadwal'])->name('jadwal');
        Route::get('/presensi', [PengabdianDemoController::class, 'presensi'])->name('presensi');
        Route::get('/laporan', [PengabdianDemoController::class, 'laporan'])->name('laporan');
        Route::get('/profil', [PengabdianDemoController::class, 'profil'])->name('profil');
    });

    Route::middleware('auth')->prefix('santri-app')->name('tenant.santri-app.')->group(function () {
        Route::get('/', [SantriDemoController::class, 'beranda'])->name('beranda');
        Route::get('/jadwal', [SantriDemoController::class, 'jadwal'])->name('jadwal');
        Route::get('/hafalan', [SantriDemoController::class, 'hafalan'])->name('hafalan');
        Route::get('/keuangan', [SantriDemoController::class, 'keuangan'])->name('keuangan');
        Route::get('/izin', [SantriDemoController::class, 'izin'])->name('izin');
        Route::get('/pengumuman', [SantriDemoController::class, 'pengumuman'])->name('pengumuman');
    });

    // 2c Baitul Maal — kuitansi donasi (pola print blade). HANYA donasi yang
    // sudah dikonfirmasi bendahara; akses wajib login (dipanggil dari panel).
    Route::get('/keuangan/donasi/{donasi}/kuitansi', function (int $donasi) {
        // Gate sama dengan DonasiResource::canAccess — kuitansi memuat PII
        // donatur dan id sekuensial; user tanpa hak keuangan (santri/wali)
        // tidak boleh bisa enumerasi kuitansi orang lain.
        abort_unless(
            auth()->user()?->canAny(['view_keuangan', 'manage_keuangan']) ?? false,
            403,
        );

        $record = Donasi::query()
            ->with(['program.sumberDana', 'konfirmator'])
            ->findOrFail($donasi);

        abort_unless($record->status === Donasi::STATUS_DIKONFIRMASI, 404);

        return view('exports.kuitansi-donasi-print', [
            'donasi' => $record,
            'namaPesantren' => TenantBranding::name(tenant('name') ?? 'Pesantren'),
        ]);
    })->middleware('auth')->name('tenant.donasi.kuitansi');

    // WP-20 — Rapor Ustadz Tahunan (pola print blade). Gate: mudir/atasan
    // (manage_payroll/run_payroll) ATAU pegawai ybs (lihat rapornya sendiri,
    // read-only). Selain itu 403 — rapor memuat penilaian & rekap kinerja.
    Route::get('/payroll/rapor-ustadz/{employee}/{tahun}', function (int $employee, int $tahun) {
        abort_unless($tahun >= 2000 && $tahun <= 2100, 404);

        $record = Employee::query()->findOrFail($employee);
        $user = auth()->user();

        abort_unless($user !== null && app(RaporUstadzService::class)->bolehLihat($record, $user), 403);

        return view('exports.rapor-ustadz-print', [
            'employee' => $record,
            'rekap' => app(RaporUstadzService::class)->rekap($record, $tahun),
            // Tautan arsip kontrak/SK (WP-16) — konteks masa kerja.
            'kontrak' => KontrakKerja::query()
                ->where('employee_id', $record->id)
                ->orderByDesc('mulai')
                ->get(),
            'namaPesantren' => TenantBranding::name(tenant('name') ?? 'Pesantren'),
        ]);
    })->middleware('auth')->name('tenant.payroll.rapor-ustadz');

    // gap-121 — Cetak stiker QR Area Piket (print blade; QR di-scan via
    // keyboard-wedge di PWA Guru). ?area={id} = satu area; tanpa query = semua aktif.
    Route::get('/sarpras/area-piket/cetak-qr', function () {
        $query = AreaPiket::query()->where('aktif', true)->orderBy('nama');
        if (($id = request()->integer('area')) > 0) {
            $query->whereKey($id);
        }
        $areas = $query->get();
        abort_if($areas->isEmpty(), 404);

        $qr = new QRCode(new QROptions([
            'outputBase64' => true, // data-URI → langsung dipakai <img src>
            'scale' => 6,
        ]));

        return view('exports.area-piket-qr-print', [
            'namaPesantren' => TenantBranding::name(tenant('name') ?? 'Pesantren'),
            'areas' => $areas->map(fn (AreaPiket $a): array => [
                'nama' => (string) $a->nama,
                'kode' => (string) $a->kode_qr,
                'qr' => $qr->render((string) $a->kode_qr),
            ])->all(),
        ]);
    })->middleware('auth')->name('tenant.sarpras.area-piket.cetak-qr');

    Route::get('/artikel/{slug}', ArticleDetailController::class)->name('tenant.cms.articles.show');
    Route::get('/sitemap.xml', SitemapController::class)->name('tenant.cms.sitemap');

    // 1-aplikasi (Fase 4 fondasi) — PWA scope ROOT "/": manifest + ikon untuk
    // SELURUH aplikasi (Beranda publik/Jemaah) agar installable & jadi dasar TWA
    // root per tenant. Publik, pasif (JSON/SVG). Service worker root menyusul
    // (Fase 3, butuh uji-perangkat sebelum deploy).
    Route::get('/manifest.webmanifest', [RootPwaController::class, 'manifest'])->name('tenant.pwa.manifest');
    Route::get('/icon.svg', [RootPwaController::class, 'icon'])->name('tenant.pwa.icon');
    // Service worker scope "/" (Fase 3) — shell-only allow-list. Endpoint selalu
    // tersedia, TAPI registrasi di app.blade.php digerbang flag pwa.root_sw_enabled
    // (default OFF) → aktif hanya setelah owner uji-perangkat.
    Route::get('/sw.js', [RootPwaController::class, 'serviceWorker'])->name('tenant.pwa.sw');

    // 1-aplikasi (Fase 2c) — kirim komentar artikel. Gerbang izin 'komentar'
    // (jemaah + semua peran internal/komunitas; calon santri 'peserta' eksternal
    // TIDAK punya izin ini); tamu read-only. Limiter ber-prefix tenant (anti-spam).
    Route::post('/artikel/{slug}/komentar', [CommentController::class, 'store'])
        ->middleware(['auth', 'can:komentar', 'throttle:komentar'])
        ->name('tenant.cms.comments.store');

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

            // F4-PWA — Ujian Semester Online (timer server-side, satu attempt/sesi).
            Route::get('/ujian', [AppSantriUjianController::class, 'index'])->name('ujian.index');
            Route::post('/ujian/{sesi}/mulai', [AppSantriUjianController::class, 'mulai'])->name('ujian.mulai');
            Route::get('/ujian/{sesi}', [AppSantriUjianController::class, 'kerjakan'])->name('ujian.kerjakan');
            Route::post('/ujian/{sesi}/jawab', [AppSantriUjianController::class, 'jawab'])->name('ujian.jawab');
            Route::post('/ujian/{sesi}/selesai', [AppSantriUjianController::class, 'selesai'])->name('ujian.selesai');

            // WP-21 — Mode Pengurus-Santri: gerbang ganda dicek per-request di
            // controller (baris pengurus_santri BERLAKU hari ini; habis = 403).
            Route::get('/pengurus', [AppSantriPengurusController::class, 'home'])->name('pengurus.home');
            Route::get('/pengurus/pelanggaran', [AppSantriPengurusController::class, 'formPelanggaran'])->name('pengurus.pelanggaran.form');
            Route::post('/pengurus/pelanggaran', [AppSantriPengurusController::class, 'simpanPelanggaran'])->name('pengurus.pelanggaran.simpan');
            Route::get('/pengurus/kegiatan', [AppSantriPengurusController::class, 'kegiatan'])->name('pengurus.kegiatan');
            Route::post('/pengurus/kegiatan/{kegiatan}/presensi', [AppSantriPengurusController::class, 'simpanPresensi'])->name('pengurus.presensi.simpan');
        });
    });

    // WP-35 — Dasbor & Approval Pimpinan dari HP (/pimpinan): baca-saja
    // kecuali setujui/tolak ApprovalRequest (HANYA via ApprovalService F1).
    // CATATAN deploy: permission akses_pimpinan baru → butuh tenant:reprovision.
    Route::middleware(['auth', 'can:akses_pimpinan'])->prefix('pimpinan')->name('tenant.pimpinan.')->group(function () {
        Route::get('/', [PimpinanPwaController::class, 'beranda'])->name('beranda');
        Route::get('/api/ringkasan', [PimpinanPwaController::class, 'ringkasan'])->name('ringkasan');
        Route::post('/api/approval/{approval}/setujui', [PimpinanPwaController::class, 'setujui'])->name('approval.setujui');
        Route::post('/api/approval/{approval}/tolak', [PimpinanPwaController::class, 'tolak'])->name('approval.tolak');
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

        // F3-PWA — Pesan ke wali santri (binaan saja, idempoten client_ref)
        Route::get('/pesan', [PortalGuruPesanWaliController::class, 'index'])->name('pesan.index');
        Route::post('/pesan', [PortalGuruPesanWaliController::class, 'store'])->name('pesan.store');

        // F3-PWA — Slip gaji milik sendiri (cetak reuse tenant.payroll.slip.print)
        Route::get('/slip-saya', [PortalGuruSlipController::class, 'index'])->name('slip.index');

        // F4-PWA — Ujian: daftar sesi, mode pengawas (polling), koreksi essay,
        // tarik nilai final → komponen nilai (idempoten).
        Route::get('/ujian', [PortalGuruUjianController::class, 'index'])->name('ujian.index');
        Route::get('/ujian/koreksi', [PortalGuruUjianController::class, 'koreksi'])->name('ujian.koreksi');
        Route::post('/ujian/koreksi/{attempt}', [PortalGuruUjianController::class, 'koreksiSimpan'])->name('ujian.koreksi.simpan');
        Route::get('/ujian/{sesi}/pengawas', [PortalGuruUjianController::class, 'pengawas'])->name('ujian.pengawas');
        Route::get('/ujian/{sesi}/pengawas/data', [PortalGuruUjianController::class, 'pengawasData'])->name('ujian.pengawas.data');
        Route::post('/ujian/{sesi}/tarik-nilai', [PortalGuruUjianController::class, 'tarikNilai'])->name('ujian.tarik-nilai');
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

    // 1-aplikasi (Fase 2b) — auth peran publik "jemaah" (registrasi mandiri
    // No. WA + password via OTP). Publik (tanpa middleware role); berlabuh ke
    // Beranda '/' setelah masuk. Throttle a la /wali & /spmb.
    Route::prefix('jemaah')->name('tenant.jemaah.auth.')->group(function () {
        Route::get('/login', [JemaahAuthController::class, 'showLogin'])->name('login');
        Route::get('/daftar', [JemaahAuthController::class, 'showDaftar'])->name('register');
        Route::get('/aktivasi', [JemaahAuthController::class, 'showAktivasi'])->name('activation');
        Route::get('/lupa-password', [JemaahAuthController::class, 'showLupaPassword'])->name('password.forgot');
        Route::post('/daftar', [JemaahAuthController::class, 'daftar'])->middleware('throttle:5,15')->name('register.submit');
        Route::post('/login', [JemaahAuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
        Route::post('/aktivasi', [JemaahAuthController::class, 'aktivasi'])->middleware('throttle:10,15')->name('activation.verify');
        Route::post('/aktivasi/kirim-ulang', [JemaahAuthController::class, 'kirimUlang'])->middleware('throttle:3,15')->name('activation.resend');
        Route::post('/lupa-password', [JemaahAuthController::class, 'lupaPassword'])->middleware('throttle:5,15')->name('password.request');
        Route::post('/lupa-password/reset', [JemaahAuthController::class, 'resetPassword'])->middleware('throttle:10,15')->name('password.reset');
    });

    Route::middleware('jemaah')->prefix('jemaah')->name('tenant.jemaah.')->group(function () {
        Route::post('/logout', [JemaahAuthController::class, 'logout'])->name('logout');
        // Endpoint area-jemaah (mis. komentar) menyusul di Fase 2c.
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
        Route::post('/santri/{santri}/tabungan/batas-belanja', [PortalWaliTabunganController::class, 'setBatasBelanja'])->name('tabungan.batas-belanja');
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

    // Cetak (print-friendly HTML) dokumen operasional unit.
    Route::middleware('auth')->prefix('admin/cetak')->name('tenant.cetak.')->group(function () {
        Route::get('/jadwal-kelas', [CetakController::class, 'jadwalKelas'])->name('jadwal-kelas');
        Route::get('/nilai-legger', [CetakController::class, 'nilaiLegger'])->name('nilai-legger');
        Route::get('/penempatan-blok', [CetakController::class, 'penempatanBlok'])->name('penempatan-blok');
        Route::get('/stok-obat', [CetakController::class, 'stokObat'])->name('stok-obat');
        Route::get('/halaqah', [CetakController::class, 'halaqah'])->name('halaqah');
        Route::get('/hafalan', [CetakController::class, 'hafalan'])->name('hafalan');
        Route::get('/pegawai', [CetakController::class, 'pegawai'])->name('pegawai');
    });

    Route::middleware('auth')->prefix('admin/akademik/export')->name('tenant.akademik.export.')->group(function () {
        Route::get('/rapor/{rapor}/print', [AkademikExportController::class, 'raporPrint'])->name('rapor.print');
        // WP-10 — cetak rapor diniyah; blok tampil mengikuti Setting tenant.
        Route::get('/rapor-diniyah/{raporDiniyah}/print', [AkademikExportController::class, 'raporDiniyahPrint'])->name('rapor-diniyah.print');
        // WP-08 — cetak syahadah (sertifikat internal) per alumni.
        Route::get('/syahadah/{alumni}/print', [AkademikExportController::class, 'syahadahPrint'])->name('syahadah.print');
        // #328 — cetak ijazah Muadalah/PKPPS parameterik per jenjang ula/wustha/ulya.
        Route::get('/ijazah/{alumni}/print', [AkademikExportController::class, 'ijazahPrint'])->name('ijazah.print');
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

    // ── PWA Kepala Keuangan (/keuangan) — aset publik + layar ber-auth ──
    Route::prefix('keuangan')->name('tenant.kepala-keuangan.pwa.')->group(function () {
        Route::get('/manifest.json', [KepalaKeuanganPwaBrandingController::class, 'manifest'])->name('manifest');
        Route::get('/icon.svg', [KepalaKeuanganPwaBrandingController::class, 'icon'])->name('icon');
        Route::get('/sw.js', [KepalaKeuanganPwaBrandingController::class, 'serviceWorker'])->name('service-worker');
    });

    Route::middleware(['auth', 'can:manage_keuangan'])->prefix('keuangan')->name('tenant.kepala-keuangan.')->group(function () {
        // 01 Beranda
        Route::get('/', [KepalaKeuanganCtrl::class, 'beranda'])->name('beranda');
        // 02 Penerimaan
        Route::get('/penerimaan/tagihan', [KepalaKeuanganCtrl::class, 'tagihan'])->name('penerimaan.tagihan');
        Route::get('/penerimaan/verifikasi', [KepalaKeuanganCtrl::class, 'verifikasi'])->name('penerimaan.verifikasi');
        Route::get('/penerimaan/loket', [KepalaKeuanganCtrl::class, 'loket'])->name('penerimaan.loket');
        // 03 Kas Harian
        Route::get('/kas-harian/tabungan', [KepalaKeuanganCtrl::class, 'tabungan'])->name('kas-harian.tabungan');
        Route::get('/kas-harian/pos', [KepalaKeuanganCtrl::class, 'pos'])->name('kas-harian.pos');
        // 04 Pengeluaran
        Route::get('/pengeluaran/approval', [KepalaKeuanganCtrl::class, 'approval'])->name('pengeluaran.approval');
        Route::get('/pengeluaran/payroll', [KepalaKeuanganCtrl::class, 'payroll'])->name('pengeluaran.payroll');
        // 05 Ledger
        Route::get('/ledger/jurnal', [KepalaKeuanganCtrl::class, 'jurnal'])->name('ledger.jurnal');
        Route::get('/ledger/anggaran', [KepalaKeuanganCtrl::class, 'anggaran'])->name('ledger.anggaran');
        // 06 Laporan
        Route::get('/laporan', [KepalaKeuanganCtrl::class, 'laporan'])->name('laporan.index');
        Route::get('/laporan/neraca-lr', [KepalaKeuanganCtrl::class, 'neracaLr'])->name('laporan.neraca-lr');
        Route::get('/laporan/arus-kas', [KepalaKeuanganCtrl::class, 'arusKas'])->name('laporan.arus-kas');
        Route::get('/laporan/operasional', [KepalaKeuanganCtrl::class, 'laporanOperasional'])->name('laporan.operasional');
        Route::get('/laporan/dana', [KepalaKeuanganCtrl::class, 'laporanDana'])->name('laporan.dana');
        Route::get('/laporan/neraca-saldo', [KepalaKeuanganCtrl::class, 'laporanNeracaSaldo'])->name('laporan.neraca-saldo');
        Route::get('/laporan/psak109', [KepalaKeuanganCtrl::class, 'laporanPsak109'])->name('laporan.psak109');
        Route::get('/laporan/isak35', [KepalaKeuanganCtrl::class, 'laporanIsak35'])->name('laporan.isak35');
        Route::get('/pajak', [KepalaKeuanganCtrl::class, 'pajak'])->name('pajak.index');
        Route::get('/laporan/rekap-tahunan', [KepalaKeuanganCtrl::class, 'rekapTahunan'])->name('laporan.rekap-tahunan');
    });

    // API Kepala Keuangan (JSON, lazy-load dari Vue)
    Route::middleware(['auth', 'can:manage_keuangan'])->prefix('keuangan/api')->name('tenant.kepala-keuangan.api.')->group(function () {
        Route::get('/beranda', [KepalaKeuanganApiController::class, 'beranda'])->name('beranda');
        Route::get('/tagihan', [KepalaKeuanganApiController::class, 'tagihan'])->name('tagihan');
        Route::get('/pembayaran/pending', [KepalaKeuanganApiController::class, 'pembayaranPending'])->name('pembayaran.pending');
        Route::post('/pembayaran/{pembayaran}/verifikasi', [KepalaKeuanganApiController::class, 'verifikasiPembayaran'])->name('pembayaran.verifikasi');
        Route::post('/pembayaran/{pembayaran}/tolak', [KepalaKeuanganApiController::class, 'tolakPembayaran'])->name('pembayaran.tolak');
        Route::get('/tabungan/ringkasan', [KepalaKeuanganApiController::class, 'tabunganRingkasan'])->name('tabungan.ringkasan');
        Route::get('/jurnal', [KepalaKeuanganApiController::class, 'jurnal'])->name('jurnal');
        Route::get('/neraca-saldo', [KepalaKeuanganApiController::class, 'neracaSaldo'])->name('neraca-saldo');
        Route::get('/anggaran/realisasi', [KepalaKeuanganApiController::class, 'anggaranRealisasi'])->name('anggaran.realisasi');
        Route::get('/laporan/neraca', [KepalaKeuanganApiController::class, 'laporanNeraca'])->name('laporan.neraca');
        Route::get('/laporan/laba-rugi', [KepalaKeuanganApiController::class, 'laporanLabaRugi'])->name('laporan.laba-rugi');
        Route::get('/laporan/arus-kas', [KepalaKeuanganApiController::class, 'laporanArusKas'])->name('laporan.arus-kas');
        Route::get('/laporan/operasional', [KepalaKeuanganApiController::class, 'laporanOperasional'])->name('laporan.operasional');
        Route::get('/laporan/dana', [KepalaKeuanganApiController::class, 'laporanDana'])->name('laporan.dana');
        Route::get('/laporan/tutup-buku', [KepalaKeuanganApiController::class, 'tutupBukuPratinjau'])->name('laporan.tutup-buku.pratinjau');
        Route::post('/laporan/tutup-buku', [KepalaKeuanganApiController::class, 'tutupBuku'])->name('laporan.tutup-buku');
        Route::get('/laporan/psak109', [KepalaKeuanganApiController::class, 'laporanPsak109'])->name('laporan.psak109');
        Route::get('/laporan/isak35', [KepalaKeuanganApiController::class, 'laporanIsak35'])->name('laporan.isak35');
        Route::get('/laporan/rekap-tahunan', [KepalaKeuanganApiController::class, 'rekapTahunan'])->name('laporan.rekap-tahunan');

        // Pajak
        Route::get('/pajak/spt-masa', [KepalaKeuanganApiController::class, 'sptMasa'])->name('pajak.spt-masa');
        Route::get('/pajak/spt-masa-tahunan', [KepalaKeuanganApiController::class, 'sptMasaTahunan'])->name('pajak.spt-masa-tahunan');
        Route::get('/pajak/ebupot', [KepalaKeuanganApiController::class, 'ebupot'])->name('pajak.ebupot');
        Route::get('/pajak/ebupot/csv', [KepalaKeuanganApiController::class, 'ebupotCsv'])->name('pajak.ebupot.csv');
        Route::get('/pajak/ebupot-tahunan', [KepalaKeuanganApiController::class, 'ebupotTahunan'])->name('pajak.ebupot-tahunan');
        Route::get('/pajak/spt-badan', [KepalaKeuanganApiController::class, 'sptBadan'])->name('pajak.spt-badan');
        Route::get('/pajak/sisa-lebih', [KepalaKeuanganApiController::class, 'sisaLebih'])->name('pajak.sisa-lebih');

        // Loket Kas
        Route::get('/loket', [KepalaKeuanganApiController::class, 'loket'])->name('loket');
        Route::post('/loket/buka-sesi', [KepalaKeuanganApiController::class, 'bukaSesiLoket'])->name('loket.buka');
        Route::post('/loket/tutup-sesi', [KepalaKeuanganApiController::class, 'tutupSesiLoket'])->name('loket.tutup');
        Route::post('/loket/terima-tunai', [KepalaKeuanganApiController::class, 'terimaTunai'])->name('loket.terima');

        // POS Kantin
        Route::get('/pos/ringkasan', [KepalaKeuanganApiController::class, 'posRingkasan'])->name('pos.ringkasan');
        Route::get('/pos/produk', [KepalaKeuanganApiController::class, 'posProduk'])->name('pos.produk');
        Route::post('/pos/checkout', [KepalaKeuanganApiController::class, 'posCheckout'])->name('pos.checkout');

        // Approval
        Route::get('/approval', [KepalaKeuanganApiController::class, 'approval'])->name('approval');
        Route::post('/approval/{approval}/setujui', [KepalaKeuanganApiController::class, 'approvalSetujui'])->name('approval.setujui');
        Route::post('/approval/{approval}/tolak', [KepalaKeuanganApiController::class, 'approvalTolak'])->name('approval.tolak');

        // Payroll
        Route::get('/payroll/periode', [KepalaKeuanganApiController::class, 'payrollPeriode'])->name('payroll.periode');
        Route::get('/payroll/slip', [KepalaKeuanganApiController::class, 'payrollSlip'])->name('payroll.slip');
        Route::post('/payroll/proses', [KepalaKeuanganApiController::class, 'payrollProses'])->name('payroll.proses');
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
