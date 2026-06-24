<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\CadanganPemulihan;
use App\Filament\Pages\CadanganWebsite;
use Modules\Payroll\Filament\Pages\GenerateThr;
use Modules\Akademik\Filament\Pages\GridNilai;
use Modules\Keuangan\Filament\Pages\KasirKantin;
use App\Filament\Pages\KebijakanPesantren;
use App\Filament\Pages\KesiapanEmis;
use Modules\Kegiatan\Filament\Pages\LogGerbangPanel;
use Modules\Kesantrian\Filament\Pages\ModerasiPesanWali;
use Modules\Mutu\Filament\Pages\MutuKelas;
use Modules\Mutu\Filament\Pages\MutuLembaga;
use Modules\Kegiatan\Filament\Pages\PengajuanJengukPanel;
use Modules\Pembiayaan\Filament\Pages\PiketHariIni;
use Modules\Payroll\Filament\Pages\PratinjauKhidmah;
use Modules\Mutu\Filament\Pages\ProfilMutuSantri;
use Modules\Akademik\Filament\Pages\RekapJurnalKbm;
use Modules\Keuangan\Filament\Pages\RekonKantin;
use Modules\Akademik\Filament\Pages\SidangKenaikan;
use App\Filament\Pages\TenantDashboard;
use Modules\Akademik\Filament\Resources\JadwalPelajaran\JadwalPelajaranResource;
use Modules\Akademik\Filament\Resources\KomponenNilai\KomponenNilaiResource;
use Modules\Akademik\Filament\Resources\KalenderAkademik\KalenderAkademikResource;
use Modules\Akademik\Filament\Resources\Kitab\KitabResource;
use Modules\Akademik\Filament\Resources\Kurikulum\KurikulumResource;
use Modules\Akademik\Filament\Resources\Lembaga\LembagaResource;
use Modules\Akademik\Filament\Resources\Nilai\NilaiResource;
use Modules\Akademik\Filament\Resources\PaketSoalAkademik\PaketSoalAkademikResource;
use Modules\Akademik\Filament\Resources\Pelajaran\PelajaranResource;
use Modules\Akademik\Filament\Resources\PeriodeAkademik\PeriodeAkademikResource;
use Modules\Akademik\Filament\Resources\PresensiSantri\PresensiSantriResource;
use Modules\Akademik\Filament\Resources\Program\ProgramStudiResource;
use Modules\Akademik\Filament\Resources\Rapor\RaporResource;
use Modules\Akademik\Filament\Resources\RaporDiniyah\RaporDiniyahResource;
use Modules\Akademik\Filament\Resources\Rubrik\RubrikKriteriaResource;
use Modules\Akademik\Filament\Resources\SesiUjianAkademik\SesiUjianAkademikResource;
use Modules\Kesantrian\Filament\Resources\Alumni\AlumniResource;
use App\Filament\Resources\Approval\ApprovalRequestResource;
use App\Filament\Resources\Approval\ApprovalRuleResource;
use Modules\Asrama\Filament\Resources\BiayaAsrama\BiayaAsramaResource;
use Modules\Asrama\Filament\Resources\BlokAsrama\BlokAsramaResource;
use Modules\Asrama\Filament\Resources\KamarAsrama\KamarAsramaResource;
use Modules\Asrama\Filament\Resources\PenempatanAsrama\PenempatanAsramaResource;
use App\Filament\Resources\Audit\AuditLogResource;
use Modules\Keuangan\Filament\Resources\BaitulMaal\BeasiswaResource;
use Modules\Keuangan\Filament\Resources\BaitulMaal\DonasiResource;
use Modules\Keuangan\Filament\Resources\BaitulMaal\ProgramDonasiResource;
use Modules\Notifikasi\Filament\Resources\BroadcastWa\BroadcastWaResource;
use Modules\Notifikasi\Filament\Resources\LogNotifikasi\LogNotifikasiResource;
use Modules\CMS\Filament\Resources\Article\ArticleResource;
use Modules\CMS\Filament\Resources\Gallery\GalleryResource;
use Modules\CMS\Filament\Resources\Profile\ProfileResource;
use Modules\CMS\Filament\Resources\WebsiteSetting\WebsiteSettingResource;
use Modules\PPDB\Filament\Resources\Crm\CrmPenawaranResource;
use App\Filament\Resources\Document\DocumentResource;
use Modules\Keuangan\Filament\Resources\Inventory\InventoryBatchResource;
use Modules\Keuangan\Filament\Resources\Inventory\InventoryItemResource;
use Modules\Keuangan\Filament\Resources\Inventory\InventoryLokasiResource;
use Modules\Kegiatan\Filament\Resources\IzinSantriResource;
use Modules\Kegiatan\Filament\Resources\KegiatanResource;
use Modules\Kegiatan\Filament\Resources\KunjunganWaliResource;
use Modules\Kegiatan\Filament\Resources\Recurring\KegiatanRecurringResource;
use Modules\Kegiatan\Filament\Resources\Template\KegiatanTemplateResource;
use Modules\Kesantrian\Filament\Resources\Kelas\KelasResource;
use Modules\Kesantrian\Filament\Resources\Kepengasuhan\PresensiAsrama\PresensiAsramaResource;
use Modules\Kesantrian\Filament\Resources\JenisPelanggaran\JenisPelanggaranResource;
use Modules\Kesantrian\Filament\Resources\PelanggaranSantri\PelanggaranSantriResource;
use Modules\Kesehatan\Filament\Resources\KunjunganUksResource;
use Modules\Kesehatan\Filament\Resources\ObatResource;
use Modules\Kesehatan\Filament\Resources\RiwayatKesehatanResource;
use Modules\Kesehatan\Filament\Resources\SesiKonselingResource;
use Modules\Keuangan\Filament\Resources\LaporanKeuangan\LaporanKeuanganResource;
use Modules\Ledger\Filament\Resources\AkunResource;
use Modules\Ledger\Filament\Resources\AlokasiBiaya\AlokasiBiayaResource;
use Modules\Ledger\Filament\Resources\AnggaranResource;
use Modules\Ledger\Filament\Resources\DivisiUnitResource;
use Modules\Ledger\Filament\Resources\JurnalResource;
use Modules\Ledger\Filament\Resources\KebijakanKehalalanResource;
use Modules\Ledger\Filament\Resources\KursValuta\KursValutaResource;
use Modules\Ledger\Filament\Resources\PeriodeAkuntansiResource;
use Modules\Ledger\Filament\Resources\SumberDanaResource;
use App\Filament\Resources\Media\MediaAuditResource;
use App\Filament\Resources\Media\MediaFileResource;
use Modules\Keuangan\Filament\Resources\MetodePembayaran\MetodePembayaranResource;
use Modules\Mutu\Filament\Resources\DokumenMutuResource;
use Modules\Mutu\Filament\Resources\MutuInspeksiResource;
use Modules\Mutu\Filament\Resources\MutuSasaranResource;
use Modules\Mutu\Filament\Resources\MutuSkmResource;
use Modules\Mutu\Filament\Resources\MutuTemuanResource;
use Modules\Pajak\Filament\Resources\TarifPajakResource;
use Modules\Payroll\Filament\Resources\Cuti\CutiResource;
use Modules\Payroll\Filament\Resources\Employee\EmployeeResource;
use Modules\Payroll\Filament\Resources\Jabatan\JabatanResource;
use Modules\Payroll\Filament\Resources\Khidmah\KhidmahResource;
use Modules\Payroll\Filament\Resources\Kontrak\KontrakKerjaResource;
use Modules\Payroll\Filament\Resources\Pelamar\PelamarResource;
use Modules\Payroll\Filament\Resources\Penugasan\PenugasanPegawaiResource;
use Modules\Payroll\Filament\Resources\Pinjaman\PinjamanPegawaiResource;
use Modules\Payroll\Filament\Resources\PresensiStaf\PresensiStafResource;
use Modules\Payroll\Filament\Resources\SlipGajiResource;
use Modules\Keuangan\Filament\Resources\Pembayaran\PembayaranResource;
use Modules\Pembiayaan\Filament\Resources\AsetResource;
use Modules\Pembiayaan\Filament\Resources\MutasiAsetResource;
use Modules\Pembiayaan\Filament\Resources\PembiayaanResource;
use Modules\Kesantrian\Filament\Resources\PenempatanKelas\PenempatanKelasResource;
use Modules\Pengadaan\Filament\Resources\BelanjaResource;
use Modules\Pengadaan\Filament\Resources\PurchaseOrder\PurchaseOrderResource;
use Modules\Pengadaan\Filament\Resources\Supplier\SupplierResource;
use Modules\Keuangan\Filament\Resources\Pos\PosKategoriResource;
use Modules\Keuangan\Filament\Resources\Pos\PosProfil\PosProfilResource;
use Modules\PPDB\Filament\Resources\CalonSantriDokumenResource;
use Modules\PPDB\Filament\Resources\CalonSantriResource;
use Modules\PPDB\Filament\Resources\GelombangPpdbResource;
use Modules\PPDB\Filament\Resources\HasilUjianResource;
use Modules\PPDB\Filament\Resources\PaketSoal\PaketSoalResource;
use Modules\PPDB\Filament\Resources\TagihanPpdbResource;
use Modules\PPDB\Filament\Resources\UjianAttempt\UjianAttemptResource;
use Modules\Keuangan\Filament\Resources\Rekonsiliasi\BankStatementResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Santri\SantriResource;
use Modules\Pembiayaan\Filament\Resources\Sarpras\AreaPiketResource;
use Modules\Pembiayaan\Filament\Resources\Sarpras\LaporanKerusakanResource;
use Modules\Keuangan\Filament\Resources\SetorKeliling\SetorKelilingResource;
use Modules\Keuangan\Filament\Resources\SkemaBiaya\SkemaBiayaResource;
use App\Filament\Resources\Surat\SuratResource;
use Modules\Keuangan\Filament\Resources\Tabungan\TabunganResource;
use Modules\Keuangan\Filament\Resources\TabunganTopup\TabunganTopupResource;
use Modules\Keuangan\Filament\Resources\Tagihan\TagihanResource;
use Modules\Tahfidz\Filament\Resources\HafalanTahfidzResource;
use Modules\Tahfidz\Filament\Resources\SimaAnResource;
use Modules\Tahfidz\Filament\Resources\Halaqah\HalaqahResource;
use Modules\Tahfidz\Filament\Resources\Munaqasyah\MunaqasyahResource;
use Modules\Tahfidz\Filament\Resources\PenilaianTahfidzResource;
use Modules\Tahfidz\Filament\Resources\TahfidzTarget\TahfidzTargetResource;
use Modules\Notifikasi\Filament\Resources\TemplateNotifikasi\TemplateNotifikasiResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\AgendaPengumuman;
use App\Filament\Widgets\GreetingBanner;
use App\Filament\Widgets\KasOverviewWidget;
use App\Filament\Widgets\KategoriPengeluaranChart;
use App\Filament\Widgets\KeuanganAreaChart;
use App\Filament\Widgets\KontrakAkanHabisWidget;
use App\Filament\Widgets\OnboardingChecklist;
use App\Filament\Widgets\PengajuanTerbaru;
use App\Filament\Widgets\QuickAccessWidget;
use App\Filament\Widgets\StatsPesantren;
use App\Filament\Widgets\TransaksiTerbaru;
use App\Http\Middleware\ApplyPanelTheme;
use App\Http\Middleware\RedirectCentralToPlatform;
use App\Models\Setting;
use App\Support\AlbaKidsTenant;
use App\Support\TenantBranding;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

class TenantPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $isAlbaKids = AlbaKidsTenant::isActive();
        $resources = $isAlbaKids ? $this->albaKidsResources() : $this->defaultResources();
        // WP-13: PratinjauKhidmah didaftar EKSPLISIT (aturan WP uang) — aman
        // dobel dengan discoverPages karena getPages() = array_unique.
        // WP-07: SidangKenaikan didaftar eksplisit (pola layar kerja).
        $pages = $isAlbaKids ? [Dashboard::class] : [TenantDashboard::class, PratinjauKhidmah::class, SidangKenaikan::class, GenerateThr::class, GridNilai::class, RekapJurnalKbm::class, KesiapanEmis::class, LogGerbangPanel::class, PengajuanJengukPanel::class, ModerasiPesanWali::class, KebijakanPesantren::class, ProfilMutuSantri::class, MutuKelas::class, MutuLembaga::class, KasirKantin::class, RekonKantin::class, PiketHariIni::class, CadanganPemulihan::class, CadanganWebsite::class];
        $widgets = $isAlbaKids
            ? [
                GreetingBanner::class,
                AccountWidget::class,
            ]
            : [
                GreetingBanner::class,
                OnboardingChecklist::class,
                StatsPesantren::class,
                KeuanganAreaChart::class,
                KategoriPengeluaranChart::class,
                TransaksiTerbaru::class,
                // Penekanan keuangan tema Baitul Maal (canView = tema aktif saja)
                KasOverviewWidget::class,
                PengajuanTerbaru::class,
                AgendaPengumuman::class,
                QuickAccessWidget::class,
                // WP-16 — pengingat kontrak/SK akan habis (canView: payroll + ada data)
                KontrakAkanHabisWidget::class,
            ];

        // PENTING: ->id() & ->path() HARUS dipanggil SEBELUM ->resources()/
        // ->discoverPages(), karena Filament memakai panel id utk cache path saat
        // registrasi resource/page. Jika tidak, boot melempar
        // "A panel has been registered without an id()" → 500 di SEMUA request.
        $panel = $panel
            ->id('tenant')
            ->path('admin')
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): View => view('filament.ui-mode-toggle'),
            )
            // Tema panel per tenant: suntik variabel CSS preset warna + kelas
            // body tema layout (lihat App\Support\PanelTheme).
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): View => view('filament.theme-vars'),
            )
            // Elemen LAYOUT tema Baitul Maal — tiap view self-guard (kosong
            // bila tema lain aktif): font, badge periode, kartu tenant, footer.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.tema.baitul-maal-head'),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): View => view('filament.tema.baitul-maal-topbar'),
            )
            // Tema topNav: bar prioritas + waffle "Semua Menu" (mega-panel)
            // menggantikan deretan grup bawaan yang tak muat (self-guard).
            ->renderHook(
                PanelsRenderHook::TOPBAR_LOGO_AFTER,
                fn (): View => view('filament.tema.topnav-megamenu'),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): View => view('filament.tema.baitul-maal-sidebar-footer'),
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): View => view('filament.tema.baitul-maal-footer'),
            )
            ->resources($resources)
            ->pages($pages)
            ->widgets($widgets)
            ->navigationGroups([
                // Atomisasi grup nav: tiap grup = 1 konsep tunggal (Santri,
                // Pengasuhan, Kesehatan, dst.) — dirilis bertahap per blok.
                // FASE 1 (blok kesantrian): "Santri & Pengasuhan" lama dipecah jadi
                // 7 grup atomik di bawah. Blok lain (Keuangan, Akademik, dll) masih
                // gabungan, dipecah di fase berikutnya. RBAC menyembunyikan grup yg
                // itemnya tak boleh diakses peran → tiap staf lihat segelintir grup.
                // JANGAN beri ->icon() pada grup: Filament v5.6.6 melempar 500 bila
                // grup berikon + sidebar collapsible + item berikon (lihat
                // TenantPanelAuthedSmokeTest + insiden prod 13 Jun).
                //
                // Fitur ②: tiap grup ->collapsed(closure) DINAMIS per peran —
                // grup sekunder utk peran login mulai TER-CIUT (tetap bisa dibuka),
                // grup primer terbuka. Peran luas (admin/super) & tak-terpetakan →
                // semua terbuka. Closure dievaluasi per-request (isCollapsed →
                // evaluate()). Urutan grup statis; reorder-by-frekuensi di fase akhir.
                NavigationGroup::make('PPDB')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('PPDB')),
                NavigationGroup::make('Santri')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Santri')),
                NavigationGroup::make('Asrama & Pengasuhan')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Asrama & Pengasuhan')),
                NavigationGroup::make('Kedisiplinan')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Kedisiplinan')),
                NavigationGroup::make('Kegiatan & Perizinan')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Kegiatan & Perizinan')),
                NavigationGroup::make('Kesehatan')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Kesehatan')),
                NavigationGroup::make('Konseling')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Konseling')),
                NavigationGroup::make('Alumni')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Alumni')),
                NavigationGroup::make('Akademik & Tahfidz')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Akademik & Tahfidz')),
                NavigationGroup::make('Keuangan')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Keuangan')),
                NavigationGroup::make('Kepegawaian')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Kepegawaian')),
                NavigationGroup::make('Sarana & Logistik')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Sarana & Logistik')),
                NavigationGroup::make('Mutu & Laporan')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Mutu & Laporan')),
                NavigationGroup::make('Komunikasi & AI')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Komunikasi & AI')),
                NavigationGroup::make('Kelembagaan & Pengaturan')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Kelembagaan & Pengaturan')),
            ]);

        if (! $isAlbaKids) {
            $panel->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages');
            // Halaman Filament yang sudah pindah ke modulnya (vertical slice) —
            // daftarkan otomatis dari tiap Modules/{X}/app/Filament/Pages.
            foreach (glob(base_path('Modules/*/app/Filament/Pages'), GLOB_ONLYDIR) as $modulePagesDir) {
                $module = basename(dirname($modulePagesDir, 3));
                $panel->discoverPages(in: $modulePagesDir, for: "Modules\\{$module}\\Filament\\Pages");
            }
        }

        return $panel
            // Login panel DISATUKAN ke halaman bermerek di '/': page login bawaan
            // /admin/login hanya redirect ke '/' (lihat App\Filament\Pages\Auth\Login).
            // Aman — mengganti page class, BUKAN shadow route web (yang dulu 500).
            ->login(Login::class)
            ->passwordReset()
            ->profile()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            // Sidebar dapat diciutkan jadi ikon-saja (minimize/expand) di desktop;
            // pilihan pengguna diingat browser. Hanya berdampak pada tema sidebar
            // (tema navigasi-atas tak punya sidebar). Grup TETAP tanpa ->icon()
            // (hindari 500-trap); saat menciut yang tampil ikon item.
            ->sidebarCollapsibleOnDesktop()
            ->spa()
            ->viteTheme('resources/css/filament/pesantren/theme.css')
            ->brandLogo(fn () => view('filament.brand', [
                'name' => static::resolveTenantBrandName(),
                'logoUrl' => TenantBranding::logoUrl(),
            ]))
            ->brandLogoHeight('2.25rem')
            ->colors([
                'primary' => Color::Green,
                'gray' => Color::Slate,
            ])
            ->middleware([
                // Tenancy HARUS diinisialisasi sebelum session & auth agar sesi/user
                // di-resolve dari database tenant (bukan central). Mencegah redirect loop.
                RedirectCentralToPlatform::class,
                PreventAccessFromCentralDomains::class,
                InitializeTenancyByDomain::class,
                // Tema panel per tenant (warna + layout) — butuh tenancy aktif.
                ApplyPanelTheme::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * Nama brand panel tenant: pakai branding_name tenant (jika di-set),
     * fallback ke nama tenant, lalu 'Pesantren'. Di-resolve saat render
     * (tenancy sudah aktif via InitializeTenancyByDomain).
     */
    protected static function resolveTenantBrandName(): string
    {
        if (tenant() === null) {
            return 'Pesantren';
        }

        try {
            $name = Setting::getTenantStringValue('branding_name');
            if (is_string($name) && trim($name) !== '') {
                return trim($name);
            }
        } catch (\Throwable $e) {
            // Setting belum termigrasi / tenancy belum siap → fallback nama tenant.
        }

        return (string) (tenant()->name ?? 'Pesantren') ?: 'Pesantren';
    }

    /**
     * Fitur ② — peta peran → grup nav PRIMER (mulai TERBUKA). Grup di luar
     * daftar peran tersebut mulai TER-CIUT demi tampilan ramping per peran.
     * Peran luas (admin_pesantren/super_admin) & peran tak-terpetakan → semua
     * terbuka.
     *
     * @var array<string, list<string>>
     */
    private const NAV_PRIMER_PER_PERAN = [
        'guru' => ['Akademik & Tahfidz', 'Santri'],
        'asatidz' => ['Akademik & Tahfidz', 'Santri'],
        'bendahara' => ['Keuangan'],
        'tu' => ['Santri', 'PPDB', 'Kelembagaan & Pengaturan'],
        'kepala' => ['Mutu & Laporan', 'Keuangan'],
        // Blok kesantrian atomik (Fase 1) — peran pengasuhan/kesehatan/konseling.
        'pengelola_asrama' => ['Asrama & Pengasuhan', 'Kedisiplinan'],
        'pengelola_uks' => ['Kesehatan'],
        'konselor' => ['Konseling'],
    ];

    /**
     * Fitur ② — apakah grup nav mulai TER-CIUT untuk peran pengguna login?
     * Dievaluasi per-request oleh NavigationGroup::isCollapsed() (evaluate()).
     * Defensif: tanpa user / peran luas / peran tak-terpetakan / error → false
     * (terbuka = perilaku lama). Grup TETAP collapsible (pengguna bisa buka);
     * RBAC & visibilitas item TIDAK disentuh.
     */
    public static function grupTerciutUntukPeran(string $grup): bool
    {
        $user = Auth::user();

        if ($user === null || ! method_exists($user, 'hasRole')) {
            return false;
        }

        try {
            if ($user->hasRole('super_admin') || $user->hasRole('admin_pesantren')) {
                return false;
            }

            foreach (self::NAV_PRIMER_PER_PERAN as $peran => $primer) {
                if ($user->hasRole($peran)) {
                    return ! in_array($grup, $primer, true);
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    /**
     * Resource penuh untuk tenant umum.
     *
     * @return array<int, class-string>
     */
    protected function defaultResources(): array
    {
        return [
            UserResource::class,
            RoleResource::class,
            SantriResource::class,
            KelasResource::class,
            PenempatanKelasResource::class,
            AlumniResource::class,
            // WP-09 — Disiplin/ta'zir berbasis poin
            JenisPelanggaranResource::class,
            PelanggaranSantriResource::class,
            TagihanResource::class,
            PembayaranResource::class,
            TabunganResource::class,
            MetodePembayaranResource::class,
            TabunganTopupResource::class,
            LaporanKeuanganResource::class,
            // 2c — Baitul Maal Digital (donasi publik + beasiswa potong SPP)
            ProgramDonasiResource::class,
            DonasiResource::class,
            BeasiswaResource::class,
            CrmPenawaranResource::class,
            // POS / Koperasi
            PosKategoriResource::class,
            PosProfilResource::class,
            InventoryItemResource::class,
            InventoryLokasiResource::class,
            InventoryBatchResource::class,
            // Ledger / Baitul Maal (Buku Besar)
            AkunResource::class,
            SuratResource::class,
            PaketSoalResource::class,
            UjianAttemptResource::class,
            SetorKelilingResource::class,
            AlokasiBiayaResource::class,
            KursValutaResource::class,
            AnggaranResource::class,
            SumberDanaResource::class,
            DivisiUnitResource::class,
            PeriodeAkuntansiResource::class,
            JurnalResource::class,
            KebijakanKehalalanResource::class,
            BelanjaResource::class,
            TarifPajakResource::class,
            SlipGajiResource::class,
            AsetResource::class,
            MutasiAsetResource::class,
            PembiayaanResource::class,
            DokumenMutuResource::class,
            MutuSasaranResource::class,
            MutuTemuanResource::class,
            MutuInspeksiResource::class,
            MutuSkmResource::class,
            TemplateNotifikasiResource::class,
            BroadcastWaResource::class,
            LogNotifikasiResource::class,
            CalonSantriResource::class,
            CalonSantriDokumenResource::class,
            GelombangPpdbResource::class,
            HasilUjianResource::class,
            ArticleResource::class,
            GalleryResource::class,
            ProfileResource::class,
            WebsiteSettingResource::class,
            LembagaResource::class, // WP-01
            KalenderAkademikResource::class, // WP-02
            KurikulumResource::class, // WP-06
            PeriodeAkademikResource::class,
            PelajaranResource::class,
            ProgramStudiResource::class,
            JadwalPelajaranResource::class,
            KomponenNilaiResource::class, // gap-85 — bobot nilai per pelajaran (configurable)
            NilaiResource::class,
            PaketSoalAkademikResource::class, // F4 — Bank Soal Akademik
            SesiUjianAkademikResource::class, // F4 — Sesi Ujian Online per rombel
            PresensiSantriResource::class,
            RubrikKriteriaResource::class,
            RaporResource::class,
            RaporDiniyahResource::class, // WP-10

            BlokAsramaResource::class,
            KamarAsramaResource::class,
            PenempatanAsramaResource::class,
            BiayaAsramaResource::class,
            HafalanTahfidzResource::class,
            SimaAnResource::class, // WP-11 — Sima'an (lihat/koreksi dari panel)
            // F0-PWA Guru: halaqah + kitab
            HalaqahResource::class,
            KitabResource::class,
            TahfidzTargetResource::class,
            PenilaianTahfidzResource::class,
            // WP-11 — munaqasyah + daftar wisuda
            MunaqasyahResource::class,
            RiwayatKesehatanResource::class,
            SesiKonselingResource::class,
            KunjunganUksResource::class, // WP-27 — Kunjungan UKS (registrasi tertinggal)
            ObatResource::class, // WP-28 — Stok Obat UKS
            LaporanKerusakanResource::class, // WP-33 — Sarpras
            AreaPiketResource::class, // WP-33 — Sarpras
            KegiatanResource::class,
            KegiatanTemplateResource::class,
            KegiatanRecurringResource::class,
            IzinSantriResource::class,
            KunjunganWaliResource::class,
            // WP-25 — Presensi Kamar & Sholat (Kepengasuhan)
            PresensiAsramaResource::class,
            MediaFileResource::class,
            MediaAuditResource::class,
            // Pengaturan / Persetujuan (F1 Approval Workflow)
            ApprovalRequestResource::class,
            ApprovalRuleResource::class,
            // Jejak Audit (F2)
            AuditLogResource::class,
            // Repositori Dokumen (F5)
            DocumentResource::class,
            // Rekonsiliasi Bank (F6)
            BankStatementResource::class,
            // Skema Biaya / Pricing (G2-16)
            SkemaBiayaResource::class,
            // Procurement (G2-09)
            SupplierResource::class,
            PurchaseOrderResource::class,
            // Pegawai + Struktur Gaji (G2-10)
            EmployeeResource::class,
            // WP-03 — Master Jabatan + Penugasan multi-peran (jantung SDM)
            JabatanResource::class,
            PenugasanPegawaiResource::class,
            // Presensi + Khidmah + Cuti (G2-11)
            PresensiStafResource::class,
            KhidmahResource::class,
            CutiResource::class,
            // WP-23 — Kasbon / pinjaman pegawai (potongan cicilan otomatis)
            PinjamanPegawaiResource::class,
            // WP-16 — Kontrak, SK & Pengabdian
            KontrakKerjaResource::class,
            // WP-19 — Rekrutmen Ringan
            PelamarResource::class,
        ];
    }

    /**
     * Surface admin khusus tenant Alba Kids.
     *
     * @return array<int, class-string>
     */
    protected function albaKidsResources(): array
    {
        return [
            CalonSantriResource::class,
            TagihanPpdbResource::class,
            TemplateNotifikasiResource::class,
            BroadcastWaResource::class,
        ];
    }
}
