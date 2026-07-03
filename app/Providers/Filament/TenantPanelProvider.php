<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\CadanganPemulihan;
use App\Filament\Pages\CadanganWebsite;
use App\Filament\Pages\KebijakanPesantren;
use App\Filament\Pages\KesiapanEmis;
use App\Filament\Pages\TenantDashboard;
use App\Filament\Resources\Approval\ApprovalRequestResource;
use App\Filament\Resources\Approval\ApprovalRuleResource;
use App\Filament\Resources\Audit\AuditLogResource;
use App\Filament\Resources\Document\DocumentResource;
use App\Filament\Resources\Media\MediaAuditResource;
use App\Filament\Resources\Media\MediaFileResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Santri\SantriResource;
use App\Filament\Resources\Surat\SuratResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\AgendaPengumuman;
use App\Filament\Widgets\GreetingBanner;
use App\Filament\Widgets\KategoriPengeluaranChart;
use App\Filament\Widgets\KeuanganAreaChart;
use App\Filament\Widgets\KontrakAkanHabisWidget;
use App\Filament\Widgets\OnboardingChecklist;
use App\Filament\Widgets\PondokOverviewWidget;
use App\Filament\Widgets\QuickAccessWidget;
use App\Filament\Widgets\StatsPesantren;
use App\Filament\Widgets\TransaksiTerbaru;
use App\Http\Middleware\ApplyPanelTheme;
use App\Http\Middleware\RedirectCentralToPlatform;
use App\Models\Setting;
use App\Support\TenantBranding;
use App\Support\TenantCustomizationRegistry;
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
use Alkoumi\FilamentN8nChat\FilamentN8nChatPlugin;
use App\Services\ModuleService;
use Leek\FilamentRightClick\FilamentRightClickPlugin;
use Modules\Akademik\Filament\Pages\GridNilai;
use Modules\Akademik\Filament\Pages\RekapJurnalKbm;
use Modules\Akademik\Filament\Pages\SidangKenaikan;
use Modules\Akademik\Filament\Resources\JadwalPelajaran\JadwalPelajaranResource;
use Modules\Akademik\Filament\Resources\KalenderAkademik\KalenderAkademikResource;
use Modules\Akademik\Filament\Resources\Kitab\KitabResource;
use Modules\Akademik\Filament\Resources\KomponenNilai\KomponenNilaiResource;
use Modules\Akademik\Filament\Resources\Kurikulum\KurikulumResource;
use Modules\Akademik\Filament\Resources\Lembaga\LembagaResource;
use Modules\Akademik\Filament\Resources\NgajiSesi\NgajiSesiResource;
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
use Modules\Akademik\Filament\Resources\SetoranSorogan\SetoranSoroganResource;
use Modules\Asrama\Filament\Resources\BiayaAsrama\BiayaAsramaResource;
use Modules\Asrama\Filament\Resources\BlokAsrama\BlokAsramaResource;
use Modules\Asrama\Filament\Resources\KamarAsrama\KamarAsramaResource;
use Modules\Asrama\Filament\Resources\PenempatanAsrama\PenempatanAsramaResource;
use Modules\CMS\Filament\Resources\Article\ArticleResource;
use Modules\CMS\Filament\Resources\Comment\CommentResource;
use Modules\CMS\Filament\Resources\Gallery\GalleryResource;
use Modules\CMS\Filament\Resources\Profile\ProfileResource;
use Modules\CMS\Filament\Resources\WebsiteSetting\WebsiteSettingResource;
use Modules\Kegiatan\Filament\Pages\LogGerbangPanel;
use Modules\Kegiatan\Filament\Pages\PengajuanJengukPanel;
use Modules\Kegiatan\Filament\Resources\IzinSantriResource;
use Modules\Kegiatan\Filament\Resources\KegiatanResource;
use Modules\Kegiatan\Filament\Resources\KunjunganWaliResource;
use Modules\Kegiatan\Filament\Resources\Recurring\KegiatanRecurringResource;
use Modules\Kegiatan\Filament\Resources\Template\KegiatanTemplateResource;
use Modules\Kesantrian\Filament\Pages\ModerasiPesanWali;
use Modules\Kesantrian\Filament\Resources\Alumni\AlumniResource;
use Modules\Kesantrian\Filament\Resources\JenisPelanggaran\JenisPelanggaranResource;
use Modules\Kesantrian\Filament\Resources\Kelas\KelasResource;
use Modules\Kesantrian\Filament\Resources\Kepengasuhan\PresensiAsrama\PresensiAsramaResource;
use Modules\Kesantrian\Filament\Resources\PelanggaranSantri\PelanggaranSantriResource;
use Modules\Kesantrian\Filament\Resources\PenempatanKelas\PenempatanKelasResource;
use Modules\Kesehatan\Filament\Resources\KunjunganUksResource;
use Modules\Kesehatan\Filament\Resources\ObatResource;
use Modules\Kesehatan\Filament\Resources\RiwayatKesehatanResource;
use Modules\Kesehatan\Filament\Resources\SesiKonselingResource;
use Modules\Keuangan\Filament\Pages\KasirKantin;
use Modules\Keuangan\Filament\Pages\RekonKantin;
use Modules\Keuangan\Filament\Resources\BaitulMaal\BeasiswaResource;
use Modules\Keuangan\Filament\Resources\BaitulMaal\DonasiResource;
use Modules\Keuangan\Filament\Resources\BaitulMaal\ProgramDonasiResource;
use Modules\Keuangan\Filament\Resources\Inventory\InventoryBatchResource;
use Modules\Keuangan\Filament\Resources\Inventory\InventoryItemResource;
use Modules\Keuangan\Filament\Resources\Inventory\InventoryLokasiResource;
use Modules\Keuangan\Filament\Resources\LaporanKeuangan\LaporanKeuanganResource;
use Modules\Keuangan\Filament\Resources\MetodePembayaran\MetodePembayaranResource;
use Modules\Keuangan\Filament\Resources\Pembayaran\PembayaranResource;
use Modules\Keuangan\Filament\Resources\Pos\PosKategoriResource;
use Modules\Keuangan\Filament\Resources\Pos\PosProfil\PosProfilResource;
use Modules\Keuangan\Filament\Resources\Rekonsiliasi\BankStatementResource;
use Modules\Keuangan\Filament\Resources\SetorKeliling\SetorKelilingResource;
use Modules\Keuangan\Filament\Resources\SkemaBiaya\SkemaBiayaResource;
use Modules\Keuangan\Filament\Resources\Tabungan\TabunganResource;
use Modules\Keuangan\Filament\Resources\TabunganTopup\TabunganTopupResource;
use Modules\Keuangan\Filament\Resources\Tagihan\TagihanResource;
use Modules\Keuangan\Filament\Resources\TagihanRutin\TagihanRutinResource;
use Modules\Ledger\Filament\Resources\AkunResource;
use Modules\Ledger\Filament\Resources\AlokasiBiaya\AlokasiBiayaResource;
use Modules\Ledger\Filament\Resources\AnggaranResource;
use Modules\Ledger\Filament\Resources\DivisiUnitResource;
use Modules\Ledger\Filament\Resources\JurnalResource;
use Modules\Ledger\Filament\Resources\KebijakanKehalalanResource;
use Modules\Ledger\Filament\Resources\KursValuta\KursValutaResource;
use Modules\Ledger\Filament\Resources\PeriodeAkuntansiResource;
use Modules\Ledger\Filament\Resources\SumberDanaResource;
use Modules\Mutu\Filament\Pages\MutuKelas;
use Modules\Mutu\Filament\Pages\MutuLembaga;
use Modules\Mutu\Filament\Pages\ProfilMutuSantri;
use Modules\Mutu\Filament\Resources\DokumenMutuResource;
use Modules\Mutu\Filament\Resources\MutuInspeksiResource;
use Modules\Mutu\Filament\Resources\MutuSasaranResource;
use Modules\Mutu\Filament\Resources\MutuSkmResource;
use Modules\Mutu\Filament\Resources\MutuTemuanResource;
use Modules\Notifikasi\Filament\Resources\BroadcastWa\BroadcastWaResource;
use Modules\Notifikasi\Filament\Resources\LogNotifikasi\LogNotifikasiResource;
use Modules\Notifikasi\Filament\Resources\TemplateNotifikasi\TemplateNotifikasiResource;
use Modules\Pajak\Filament\Resources\TarifPajakResource;
use Modules\Payroll\Filament\Pages\GenerateThr;
use Modules\Payroll\Filament\Pages\PratinjauKhidmah;
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
use Modules\Pembiayaan\Filament\Pages\PiketHariIni;
use Modules\Pembiayaan\Filament\Resources\AsetResource;
use Modules\Pembiayaan\Filament\Resources\MutasiAsetResource;
use Modules\Pembiayaan\Filament\Resources\PembiayaanResource;
use Modules\Pembiayaan\Filament\Resources\Sarpras\AreaPiketResource;
use Modules\Pembiayaan\Filament\Resources\Sarpras\LaporanKerusakanResource;
use Modules\Pengadaan\Filament\Resources\BelanjaResource;
use Modules\Pengadaan\Filament\Resources\PurchaseOrder\PurchaseOrderResource;
use Modules\Pengadaan\Filament\Resources\Supplier\SupplierResource;
use Modules\PPDB\Filament\Resources\CalonSantriDokumenResource;
use Modules\PPDB\Filament\Resources\CalonSantriResource;
use Modules\PPDB\Filament\Resources\Crm\CrmPenawaranResource;
use Modules\PPDB\Filament\Resources\GelombangPpdbResource;
use Modules\PPDB\Filament\Resources\HasilUjianResource;
use Modules\PPDB\Filament\Resources\PaketSoal\PaketSoalResource;
use Modules\PPDB\Filament\Resources\TagihanPpdbResource;
use Modules\PPDB\Filament\Resources\UjianAttempt\UjianAttemptResource;
use Modules\Tahfidz\Filament\Resources\HafalanTahfidzResource;
use Modules\Tahfidz\Filament\Resources\Halaqah\HalaqahResource;
use Modules\Tahfidz\Filament\Resources\Munaqasyah\MunaqasyahResource;
use Modules\Tahfidz\Filament\Resources\PenilaianTahfidzResource;
use Modules\Tahfidz\Filament\Resources\SimaAnResource;
use Modules\Tahfidz\Filament\Resources\TahfidzTarget\TahfidzTargetResource;
use Nomanur\FilamentSeoPro\SeoPlugin;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

class TenantPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // Tahap 3 #343: definisi panel per-tenant = "profil panel" di registry
        // (config/tenant-customization.php, kunci 'panel_profile') — provider
        // TIDAK lagi cek AlbaKidsTenant::isActive() inline. Resolusi registry
        // identik (tenant key → alias host), perilaku HARUS sama:
        // 'albakids_ringkas' = surface admin ringkas (PPDB + WA; /admin/*
        // resource lain 404 BY DESIGN), 'default' = panel penuh.
        $ringkas = TenantCustomizationRegistry::panelProfile() === 'albakids_ringkas';
        $resources = $ringkas ? $this->ringkasResources() : $this->defaultResources();
        // WP-13: PratinjauKhidmah didaftar EKSPLISIT (aturan WP uang) — aman
        // dobel dengan discoverPages karena getPages() = array_unique.
        // WP-07: SidangKenaikan didaftar eksplisit (pola layar kerja).
        $pages = $ringkas ? [Dashboard::class] : [TenantDashboard::class, PratinjauKhidmah::class, SidangKenaikan::class, GenerateThr::class, GridNilai::class, RekapJurnalKbm::class, KesiapanEmis::class, LogGerbangPanel::class, PengajuanJengukPanel::class, ModerasiPesanWali::class, KebijakanPesantren::class, ProfilMutuSantri::class, MutuKelas::class, MutuLembaga::class, KasirKantin::class, RekonKantin::class, PiketHariIni::class, CadanganPemulihan::class, CadanganWebsite::class];
        $widgets = $ringkas
            ? [
                GreetingBanner::class,
                AccountWidget::class,
            ]
            : [
                // === LAYER 1: Welcome & Onboarding ===
                GreetingBanner::class,           // sort=-10: welcome
                OnboardingChecklist::class,      // sort=-9: setup checklist (conditional)

                // === LAYER 2: Key Metrics ===
                StatsPesantren::class,           // sort=-8: 5 KPI cards (santri, pengurus, kas, tagihan)
                QuickAccessWidget::class,        // sort=-7: grid pintasan menu tersering (gerbang modul+izin)
                PondokOverviewWidget::class,     // sort=0: indikator per pondok (Banin/Banat) — bila multi-pondok

                // === LAYER 4: Financial Dashboard (for bendahara/tata usaha) ===
                KeuanganAreaChart::class,        // sort=-6: chart pendapatan/pengeluaran
                TransaksiTerbaru::class,         // sort=-4: tabel transaksi terbaru (4 cols)

                // === LAYER 5: Ringkasan Sekunder ===
                KategoriPengeluaranChart::class, // sort=-5: pie chart pengeluaran (conditional data)
                AgendaPengumuman::class,         // sort=-3: agenda & pengumuman

                // === LAYER 6: (dikonsolidasi) ===
                // KesantrianOverviewWidget & KeuanganOverviewWidget DICABUT dari
                // dasbor utama — angkanya dobel definisi dgn StatsPesantren
                // (super_admin dulu melihat 3 baris stats). Metrik unik yang
                // penting (santri cuti, sisa nominal tunggakan jatuh tempo)
                // sudah DISERAP ke StatsPesantren. Kelasnya dipertahankan utk
                // pemakaian di halaman domain bila dibutuhkan kelak.

                // === LAYER 7: Departemen Spesifik ===
                KontrakAkanHabisWidget::class,   // sort=12: pengingat kontrak (payroll only)

                // === LAYER 8: Admin/Dev (rarely needed) ===
                // BillingOverviewWidget (sort=10) + DevProgressWidget (sort=20) dihidden via canView() ===
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
            // (Kode mati dihapus: renderhook TOPBAR_LOGO_AFTER utk mega-menu
            // topNav — tak ada lagi tema ber-topNav=true di PanelTheme::THEMES.
            // Bila tema topNav dihidupkan kembali, pulihkan blade
            // filament/tema/topnav-megamenu dari riwayat git.)
            ->resources($resources)
            ->pages($pages)
            ->widgets($widgets)
            ->navigationGroups([
                // Atomisasi PENUH grup nav: tiap grup = 1 konsep tunggal (41 grup),
                // disusun BLOK BERDEKATAN (grup serumpun bersebelahan → minim scroll).
                // RBAC menyembunyikan grup yg itemnya tak boleh diakses peran → tiap
                // staf hanya melihat segelintir grup berlabel pendek-jelas.
                // JANGAN beri ->icon() pada grup: Filament v5.6.6 melempar 500 bila
                // grup berikon + sidebar collapsible + item berikon (lihat
                // TenantPanelAuthedSmokeTest + insiden prod 13 Jun).
                //
                // Fitur ②: tiap grup ->collapsed(closure) DINAMIS per peran — grup
                // sekunder utk peran login mulai TER-CIUT (tetap bisa dibuka), grup
                // primer terbuka. Peran luas (admin/super) & tak-terpetakan → semua
                // terbuka. Closure dievaluasi per-request (isCollapsed → evaluate()).
                // ── Blok Kesantrian ──
                NavigationGroup::make('Santri')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Santri')),
                NavigationGroup::make('Asrama & Pengasuhan')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Asrama & Pengasuhan')),
                NavigationGroup::make('Kedisiplinan')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Kedisiplinan')),
                NavigationGroup::make('Kegiatan')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Kegiatan')),
                NavigationGroup::make('Perizinan & Kunjungan')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Perizinan & Kunjungan')),
                NavigationGroup::make('Kesehatan')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Kesehatan')),
                NavigationGroup::make('Konseling')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Konseling')),
                NavigationGroup::make('Alumni')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Alumni')),
                // ── Blok Akademik ──
                NavigationGroup::make('Pembelajaran')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Pembelajaran')),
                NavigationGroup::make('Nilai & Rapor')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Nilai & Rapor')),
                NavigationGroup::make('Ujian Online')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Ujian Online')),
                NavigationGroup::make('Tahfidz')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Tahfidz')),
                NavigationGroup::make('Ngaji Kitab')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Ngaji Kitab')),
                NavigationGroup::make('Master Akademik')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Master Akademik')),
                // ── Blok PPDB ──
                NavigationGroup::make('Pendaftaran')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Pendaftaran')),
                NavigationGroup::make('Ujian Seleksi')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Ujian Seleksi')),
                NavigationGroup::make('CRM')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('CRM')),
                // ── Blok Keuangan (kas operasional) ──
                NavigationGroup::make('Tagihan Santri')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Tagihan Santri')),
                NavigationGroup::make('Tabungan Santri')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Tabungan Santri')),
                NavigationGroup::make('Kasir Kantin')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Kasir Kantin')),
                NavigationGroup::make('Donasi')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Donasi')),
                NavigationGroup::make('Beasiswa')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Beasiswa')),
                // ── Blok Akuntansi ──
                NavigationGroup::make('Buku Besar')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Buku Besar')),
                NavigationGroup::make('Laporan Keuangan')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Laporan Keuangan')),
                NavigationGroup::make('Pajak')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Pajak')),
                NavigationGroup::make('Pembiayaan')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Pembiayaan')),
                NavigationGroup::make('Anggaran & Perencanaan')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Anggaran & Perencanaan')),
                // ── Blok Koperasi & Logistik ──
                NavigationGroup::make('Stok Barang')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Stok Barang')),
                NavigationGroup::make('Pengaturan Koperasi')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Pengaturan Koperasi')),
                NavigationGroup::make('Aset & Sarpras')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Aset & Sarpras')),
                NavigationGroup::make('Pengadaan')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Pengadaan')),
                // ── Blok SDM ──
                NavigationGroup::make('Kepegawaian')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Kepegawaian')),
                NavigationGroup::make('Penggajian')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Penggajian')),
                // ── Blok Komunikasi ──
                NavigationGroup::make('Komunikasi')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Komunikasi')),
                NavigationGroup::make('AI Khadim')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('AI Khadim')),
                // ── Blok Mutu & Laporan ──
                NavigationGroup::make('Mutu')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Mutu')),
                NavigationGroup::make('Laporan')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Laporan')),
                // ── Blok Pengaturan & Tata Kelola ──
                NavigationGroup::make('Website & Konten')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Website & Konten')),
                NavigationGroup::make('Pengaturan Sistem')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Pengaturan Sistem')),
                NavigationGroup::make('Persetujuan')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Persetujuan')),
                NavigationGroup::make('Audit & Arsip')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Audit & Arsip')),
                NavigationGroup::make('Cadangan & Integrasi')->collapsible()->collapsed(fn (): bool => self::grupTerciutUntukPeran('Cadangan & Integrasi')),
            ]);

        if (! $ringkas) {
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
            // Favicon tab browser: pakai favicon branding tenant bila diunggah
            // (di-serve via /_tenant-storage — Storage::url 404 di Docker),
            // fallback aset default platform. Closure = dievaluasi saat render,
            // tenancy sudah aktif.
            ->favicon(fn (): string => TenantBranding::faviconUrl() ?? asset('favicon.ico'))
            // Pixio Design System: emerald #059669 sbg satu-satunya aksen (CTA,
            // active, link). gray = Slate (ramp ink). Tenant boleh override via
            // ApplyPanelTheme/preset warna.
            ->font('Plus Jakarta Sans')
            ->colors([
                'primary' => Color::Emerald,
                'gray' => Color::Slate,
            ])
            // SEO Pro (nomanur/filament-seo-pro): hanya komponen FORM (SeoSection
            // di ArticleResource) yang dipakai. Halaman "SEO Management" & widget
            // dashboard SENGAJA dimatikan agar:
            //  (1) tidak menambah grup nav 'SEO' tak-terdaftar (grup asing =
            //      risiko tampil ganjil / 500-trap ikon-grup Filament v5.6.6),
            //  (2) panel ringkas albakids tetap bersih (PPDB+WA saja).
            // Field mapping default diselaraskan dgn Article (title/slug/content).
            ->plugins([
                SeoPlugin::make()
                    ->enableManagementPage(false)
                    ->enableDashboardWidget(false)
                    ->defaultTitleField('title')
                    ->defaultSlugField('slug')
                    ->defaultContentField('content'),

                // A5 — Menu klik-kanan tabel (leek/filament-right-click).
                // Macro Table::contextMenuActions() dipanggil di resource inti
                // (Santri/Tagihan/PelanggaranSantri) yang MEMAKAI ULANG action
                // baris existing (nol logika/izin baru). Aset di-load-on-request
                // (hanya saat tabel ber-context-menu dirender). Registrasi
                // DILEWATI untuk profil 'albakids_ringkas' (surface PPDB+WA
                // ringkas — hindari risiko/beban tak perlu di panel ringkas).
                ...(! $ringkas ? [FilamentRightClickPlugin::make()] : []),

                // A4 — Widget chat "Khadim Digital" (alkoumi/filament-n8n-chat).
                // NONAKTIF secara default: 'enabled' closure = modul 'ai' aktif
                // DAN flag ai.n8n_chat.enabled (default OFF) → widget TIDAK tampil
                // sampai operator menyalakan flag pada tenant ber-modul AI.
                // webhookUrl diarahkan ke endpoint INTERNAL /ai/khadim-chat yang
                // BERMUARA ke AiGateway (audit+budget+policy) — bukan n8n/LLM
                // eksternal (Aturan Emas #4). Dilewati untuk profil ringkas.
                ...(! $ringkas ? [
                    FilamentN8nChatPlugin::make()
                        ->enabled(fn (): bool => self::widgetChatKhadimAktif())
                        ->showOnGuest(false)
                        ->webhookUrl(fn (): ?string => self::widgetChatKhadimAktif()
                            ? url('/ai/khadim-chat')
                            : null)
                        ->config(fn (): array => [
                            'defaultLanguage' => 'en',
                            'i18n' => [
                                'en' => [
                                    'title' => 'Khadim Digital',
                                    'subtitle' => 'Asisten AI pesantren',
                                    'getStarted' => 'Mulai Percakapan',
                                    'inputPlaceholder' => 'Tulis pertanyaan Anda...',
                                    'footer' => '',
                                ],
                            ],
                        ]),
                ] : []),
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
        // Label WAJIB persis sama dgn navigationGroups([...]) di atas.
        'guru' => ['Pembelajaran', 'Nilai & Rapor', 'Santri'],
        'asatidz' => ['Pembelajaran', 'Nilai & Rapor', 'Tahfidz', 'Ngaji Kitab'],
        'guru_tahfidz' => ['Tahfidz', 'Ngaji Kitab'],
        'bendahara' => ['Tagihan Santri', 'Tabungan Santri'],
        'kasir' => ['Kasir Kantin', 'Tagihan Santri', 'Stok Barang'],
        'kepala_keuangan' => ['Buku Besar', 'Laporan Keuangan', 'Anggaran & Perencanaan', 'Laporan'],
        'bendahara_pusat' => ['Buku Besar', 'Laporan Keuangan', 'Anggaran & Perencanaan'],
        'tu' => ['Santri', 'Pendaftaran', 'Pengaturan Sistem'],
        'kepala' => ['Mutu', 'Laporan'],
        'pengelola_asrama' => ['Asrama & Pengasuhan', 'Kedisiplinan'],
        'pengelola_uks' => ['Kesehatan'],
        'konselor' => ['Konseling', 'Santri'],
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
     * A4 — apakah widget chat Khadim (n8n-chat) boleh tampil untuk tenant ini?
     * Syarat KUMULATIF: (1) tenancy aktif, (2) modul 'ai' terlihat untuk tenant
     * (moduleVisible), (3) flag ai.n8n_chat.enabled ON. Default flag OFF →
     * widget tidak tampil di mana pun sampai operator menyalakannya. Defensif:
     * error apa pun → false (widget tersembunyi, fail-safe). Dievaluasi
     * per-request (closure plugin) — konsisten dgn gerbang endpoint controller.
     */
    public static function widgetChatKhadimAktif(): bool
    {
        try {
            if (! tenancy()->initialized) {
                return false;
            }

            if (! (bool) config('ai.n8n_chat.enabled', false)) {
                return false;
            }

            return app(ModuleService::class)->isVisibleForTenant('ai', tenant());
        } catch (\Throwable) {
            return false;
        }
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
            CommentResource::class,
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
            // Issue #330 — Sorogan & Ngaji Kitab
            SetoranSoroganResource::class,
            NgajiSesiResource::class,
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
            // Tagihan Rutin (langganan SPP/makan/asrama — auto-generate terjadwal)
            TagihanRutinResource::class,
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
     * Surface admin profil 'albakids_ringkas' (dulu khusus AlbaKids):
     * hanya PPDB + notifikasi WA; resource lain sengaja tidak terdaftar.
     *
     * @return array<int, class-string>
     */
    protected function ringkasResources(): array
    {
        return [
            CalonSantriResource::class,
            TagihanPpdbResource::class,
            TemplateNotifikasiResource::class,
            BroadcastWaResource::class,
        ];
    }
}
