<?php

namespace App\Providers\Filament;

use App\Filament\Resources\Akademik\JadwalPelajaran\JadwalPelajaranResource;
use App\Filament\Resources\Akademik\Nilai\NilaiResource;
use App\Filament\Resources\Akademik\PresensiSantri\PresensiSantriResource;
use App\Filament\Resources\Akademik\Rubrik\RubrikKriteriaResource;
use App\Filament\Resources\Akademik\Pelajaran\PelajaranResource;
use App\Filament\Resources\Akademik\Program\ProgramStudiResource;
use App\Filament\Resources\Akademik\PeriodeAkademik\PeriodeAkademikResource;
use App\Filament\Resources\Akademik\Rapor\RaporResource;
use App\Filament\Resources\Alumni\AlumniResource;
use App\Filament\Resources\Asrama\BiayaAsrama\BiayaAsramaResource;
use App\Filament\Resources\Asrama\BlokAsrama\BlokAsramaResource;
use App\Filament\Resources\Asrama\KamarAsrama\KamarAsramaResource;
use App\Filament\Resources\Asrama\PenempatanAsrama\PenempatanAsramaResource;
use App\Filament\Resources\BroadcastWa\BroadcastWaResource;
use App\Filament\Resources\CMS\Article\ArticleResource;
use App\Filament\Resources\CMS\Gallery\GalleryResource;
use App\Filament\Resources\CMS\Profile\ProfileResource;
use App\Filament\Resources\CMS\WebsiteSetting\WebsiteSettingResource;
use App\Filament\Resources\Kegiatan\IzinSantriResource;
use App\Filament\Resources\Kegiatan\KegiatanResource;
use App\Filament\Resources\Kegiatan\Recurring\KegiatanRecurringResource;
use App\Filament\Resources\Kegiatan\Template\KegiatanTemplateResource;
use App\Filament\Resources\Kegiatan\KunjunganWaliResource;
use App\Filament\Resources\Kelas\KelasResource;
use App\Filament\Resources\Kesehatan\RiwayatKesehatanResource;
use App\Filament\Resources\Kesehatan\SesiKonselingResource;
use App\Filament\Resources\Inventory\InventoryBatchResource;
use App\Filament\Resources\Inventory\InventoryItemResource;
use App\Filament\Resources\Inventory\InventoryLokasiResource;
use App\Filament\Resources\Ledger\AkunResource;
use App\Filament\Resources\Surat\SuratResource;
use App\Filament\Resources\PPDB\PaketSoal\PaketSoalResource;
use App\Filament\Resources\PPDB\UjianAttempt\UjianAttemptResource;
use App\Filament\Resources\Ledger\AlokasiBiaya\AlokasiBiayaResource;
use App\Filament\Resources\Ledger\KursValuta\KursValutaResource;
use App\Filament\Resources\Ledger\AnggaranResource;
use App\Filament\Resources\Ledger\DivisiUnitResource;
use App\Filament\Resources\Pos\PosKategoriResource;
use App\Filament\Resources\Pos\PosProfil\PosProfilResource;
use App\Filament\Resources\Ledger\JurnalResource;
use App\Filament\Resources\Ledger\KebijakanKehalalanResource;
use App\Filament\Resources\Pajak\TarifPajakResource;
use App\Filament\Resources\Ledger\PeriodeAkuntansiResource;
use App\Filament\Resources\Ledger\SumberDanaResource;
use App\Filament\Resources\Pengadaan\BelanjaResource;
use App\Filament\Resources\Payroll\SlipGajiResource;
use App\Filament\Resources\Pembiayaan\AsetResource;
use App\Filament\Resources\Pembiayaan\MutasiAsetResource;
use App\Filament\Resources\Pembiayaan\PembiayaanResource;
use App\Filament\Resources\Mutu\DokumenMutuResource;
use App\Filament\Resources\Mutu\MutuInspeksiResource;
use App\Filament\Resources\Mutu\MutuSasaranResource;
use App\Filament\Resources\Mutu\MutuSkmResource;
use App\Filament\Resources\Mutu\MutuTemuanResource;
use App\Filament\Resources\Crm\CrmPenawaranResource;
use App\Filament\Resources\LaporanKeuangan\LaporanKeuanganResource;
use App\Filament\Resources\Media\MediaFileResource;
use App\Filament\Resources\MetodePembayaran\MetodePembayaranResource;
use App\Filament\Resources\PPDB\CalonSantriDokumenResource;
use App\Filament\Resources\PPDB\CalonSantriResource;
use App\Filament\Resources\PPDB\HasilUjianResource;
use App\Filament\Resources\Pembayaran\PembayaranResource;
use App\Filament\Resources\PenempatanKelas\PenempatanKelasResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Santri\SantriResource;
use App\Filament\Resources\Tabungan\TabunganResource;
use App\Filament\Resources\TabunganTopup\TabunganTopupResource;
use App\Filament\Resources\Tagihan\TagihanResource;
use App\Filament\Resources\Tahfidz\HafalanTahfidzResource;
use App\Filament\Resources\Tahfidz\TahfidzTarget\TahfidzTargetResource;
use App\Filament\Resources\Tahfidz\PenilaianTahfidzResource;
use App\Filament\Resources\TemplateNotifikasi\TemplateNotifikasiResource;
use App\Filament\Resources\Users\UserResource;
use App\Support\AlbaKidsTenant;
use App\Filament\Widgets\KesantrianOverviewWidget;
use App\Filament\Widgets\KeuanganOverviewWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

class TenantPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $isAlbaKids = AlbaKidsTenant::isActive();
        $resources = $isAlbaKids ? $this->albaKidsResources() : $this->defaultResources();
        $pages = $isAlbaKids ? [Dashboard::class] : [\App\Filament\Pages\TenantDashboard::class];
        $widgets = $isAlbaKids
            ? [
                \App\Filament\Widgets\GreetingBanner::class,
                AccountWidget::class,
            ]
            : [
                \App\Filament\Widgets\GreetingBanner::class,
                \App\Filament\Widgets\StatsPesantren::class,
                \App\Filament\Widgets\KeuanganAreaChart::class,
                \App\Filament\Widgets\KategoriPengeluaranChart::class,
                \App\Filament\Widgets\TransaksiTerbaru::class,
                \App\Filament\Widgets\AgendaPengumuman::class,
                \App\Filament\Widgets\QuickAccessWidget::class,
            ];

        // PENTING: ->id() & ->path() HARUS dipanggil SEBELUM ->resources()/
        // ->discoverPages(), karena Filament memakai panel id utk cache path saat
        // registrasi resource/page. Jika tidak, boot melempar
        // "A panel has been registered without an id()" → 500 di SEMUA request.
        $panel = $panel
            ->id('tenant')
            ->path('admin')
            ->renderHook(
                \Filament\View\PanelsRenderHook::USER_MENU_BEFORE,
                fn (): \Illuminate\Contracts\View\View => view('filament.ui-mode-toggle'),
            )
            ->resources($resources)
            ->pages($pages)
            ->widgets($widgets)
            // Urutan & ikon grup navigasi — mengikuti jalur adopsi nyata
            // (Kesantrian/PPDB/Website dulu; keuangan lanjutan di bawah &
            // collapsed). Grup yang tak terdaftar di sini tetap muncul di bawah.
            ->navigationGroups([
                NavigationGroup::make('Kesantrian')->icon('heroicon-o-user-group'),
                NavigationGroup::make('PPDB')->icon('heroicon-o-academic-cap'),
                NavigationGroup::make('Website & CMS')->icon('heroicon-o-globe-alt'),
                NavigationGroup::make('Asrama')->icon('heroicon-o-home-modern'),
                NavigationGroup::make('Akademik')->icon('heroicon-o-book-open'),
                NavigationGroup::make('Tahfidz')->icon('heroicon-o-sparkles'),
                NavigationGroup::make('Kegiatan & Perizinan')->icon('heroicon-o-calendar-days'),
                NavigationGroup::make('Kesehatan & Konseling')->icon('heroicon-o-heart'),
                NavigationGroup::make('Administrasi')->icon('heroicon-o-document-text'),
                NavigationGroup::make('Keuangan')->icon('heroicon-o-banknotes'),
                NavigationGroup::make('Buku Besar')->icon('heroicon-o-calculator')->collapsed(),
                NavigationGroup::make('Pelaporan')->icon('heroicon-o-chart-bar')->collapsed(),
                NavigationGroup::make('Pembiayaan')->icon('heroicon-o-building-library')->collapsed(),
                NavigationGroup::make('Mutu / SYAMIL')->icon('heroicon-o-shield-check')->collapsed(),
                NavigationGroup::make('File Storage')->icon('heroicon-o-folder'),
                NavigationGroup::make('Notifikasi')->icon('heroicon-o-bell'),
                NavigationGroup::make('Pengaturan')->icon('heroicon-o-cog-6-tooth')->collapsed(),
            ]);

        if (! $isAlbaKids) {
            $panel->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages');
        }

        return $panel
            ->login()
            ->passwordReset()
            ->profile()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->spa()
            ->viteTheme('resources/css/filament/pesantren/theme.css')
            ->brandLogo(fn () => view('filament.brand', [
                'name' => static::resolveTenantBrandName(),
                'logoUrl' => \App\Support\TenantBranding::logoUrl(),
            ]))
            ->brandLogoHeight('2.25rem')
            ->colors([
                'primary' => Color::Green,
                'gray' => Color::Slate,
            ])
            ->middleware([
                // Tenancy HARUS diinisialisasi sebelum session & auth agar sesi/user
                // di-resolve dari database tenant (bukan central). Mencegah redirect loop.
                \App\Http\Middleware\RedirectCentralToPlatform::class,
                PreventAccessFromCentralDomains::class,
                InitializeTenancyByDomain::class,
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
            $name = \App\Models\Setting::getTenantStringValue('branding_name');
            if (is_string($name) && trim($name) !== '') {
                return trim($name);
            }
        } catch (\Throwable $e) {
            // Setting belum termigrasi / tenancy belum siap → fallback nama tenant.
        }

        return (string) (tenant()->name ?? 'Pesantren') ?: 'Pesantren';
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
            TagihanResource::class,
            PembayaranResource::class,
            TabunganResource::class,
            MetodePembayaranResource::class,
            TabunganTopupResource::class,
            LaporanKeuanganResource::class,
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
            \App\Filament\Resources\SetorKeliling\SetorKelilingResource::class,
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
            CalonSantriResource::class,
            CalonSantriDokumenResource::class,
            HasilUjianResource::class,
            ArticleResource::class,
            GalleryResource::class,
            ProfileResource::class,
            WebsiteSettingResource::class,
            PeriodeAkademikResource::class,
            PelajaranResource::class,
            ProgramStudiResource::class,
            JadwalPelajaranResource::class,
            NilaiResource::class,
            PresensiSantriResource::class,
            RubrikKriteriaResource::class,
            RaporResource::class,
            BlokAsramaResource::class,
            KamarAsramaResource::class,
            PenempatanAsramaResource::class,
            BiayaAsramaResource::class,
            HafalanTahfidzResource::class,
            TahfidzTargetResource::class,
            PenilaianTahfidzResource::class,
            RiwayatKesehatanResource::class,
            SesiKonselingResource::class,
            KegiatanResource::class,
            KegiatanTemplateResource::class,
            KegiatanRecurringResource::class,
            IzinSantriResource::class,
            KunjunganWaliResource::class,
            MediaFileResource::class,
            \App\Filament\Resources\Media\MediaAuditResource::class,
            // Pengaturan / Persetujuan (F1 Approval Workflow)
            \App\Filament\Resources\Approval\ApprovalRequestResource::class,
            \App\Filament\Resources\Approval\ApprovalRuleResource::class,
            // Jejak Audit (F2)
            \App\Filament\Resources\Audit\AuditLogResource::class,
            // Repositori Dokumen (F5)
            \App\Filament\Resources\Document\DocumentResource::class,
            // Rekonsiliasi Bank (F6)
            \App\Filament\Resources\Rekonsiliasi\BankStatementResource::class,
            // Skema Biaya / Pricing (G2-16)
            \App\Filament\Resources\SkemaBiaya\SkemaBiayaResource::class,
            // Procurement (G2-09)
            \App\Filament\Resources\Pengadaan\Supplier\SupplierResource::class,
            \App\Filament\Resources\Pengadaan\PurchaseOrder\PurchaseOrderResource::class,
            // Pegawai + Struktur Gaji (G2-10)
            \App\Filament\Resources\Payroll\Employee\EmployeeResource::class,
            // Presensi + Khidmah + Cuti (G2-11)
            \App\Filament\Resources\Payroll\PresensiStaf\PresensiStafResource::class,
            \App\Filament\Resources\Payroll\Khidmah\KhidmahResource::class,
            \App\Filament\Resources\Payroll\Cuti\CutiResource::class,
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
            \App\Filament\Resources\PPDB\TagihanPpdbResource::class,
            TemplateNotifikasiResource::class,
            BroadcastWaResource::class,
        ];
    }
}
