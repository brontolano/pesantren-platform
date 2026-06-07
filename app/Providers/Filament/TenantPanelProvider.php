<?php

namespace App\Providers\Filament;

use App\Filament\Resources\Akademik\JadwalPelajaran\JadwalPelajaranResource;
use App\Filament\Resources\Akademik\Nilai\NilaiResource;
use App\Filament\Resources\Akademik\Pelajaran\PelajaranResource;
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
use App\Filament\Resources\Kegiatan\KunjunganWaliResource;
use App\Filament\Resources\Kelas\KelasResource;
use App\Filament\Resources\Kesehatan\RiwayatKesehatanResource;
use App\Filament\Resources\Kesehatan\SesiKonselingResource;
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
use App\Filament\Resources\Tahfidz\PenilaianTahfidzResource;
use App\Filament\Resources\TemplateNotifikasi\TemplateNotifikasiResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\KesantrianOverviewWidget;
use App\Filament\Widgets\KeuanganOverviewWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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
        return $panel
            ->id('tenant')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->profile()
            ->spa()
            ->viteTheme('resources/css/filament/pesantren/theme.css')
            ->brandLogo(fn () => view('filament.brand', ['name' => static::resolveTenantBrandName()]))
            ->brandLogoHeight('2.25rem')
            ->colors([
                'primary' => Color::Emerald,
                'gray' => Color::Slate,
            ])
            ->resources([
                // Core tenant
                UserResource::class,
                RoleResource::class,
                // Kesantrian
                SantriResource::class,
                KelasResource::class,
                PenempatanKelasResource::class,
                AlumniResource::class,
                // Keuangan
                TagihanResource::class,
                PembayaranResource::class,
                TabunganResource::class,
                MetodePembayaranResource::class,
                TabunganTopupResource::class,
                LaporanKeuanganResource::class,
                // Notifikasi
                TemplateNotifikasiResource::class,
                BroadcastWaResource::class,
                // PPDB
                CalonSantriResource::class,
                CalonSantriDokumenResource::class,
                HasilUjianResource::class,
                // CMS
                ArticleResource::class,
                GalleryResource::class,
                ProfileResource::class,
                WebsiteSettingResource::class,
                // Akademik
                PeriodeAkademikResource::class,
                PelajaranResource::class,
                JadwalPelajaranResource::class,
                NilaiResource::class,
                RaporResource::class,
                // Asrama
                BlokAsramaResource::class,
                KamarAsramaResource::class,
                PenempatanAsramaResource::class,
                BiayaAsramaResource::class,
                // Tahfidz
                HafalanTahfidzResource::class,
                PenilaianTahfidzResource::class,
                // Kesehatan
                RiwayatKesehatanResource::class,
                SesiKonselingResource::class,
                // Kegiatan
                KegiatanResource::class,
                IzinSantriResource::class,
                KunjunganWaliResource::class,
                // File Storage
                MediaFileResource::class,
                \App\Filament\Resources\Media\MediaAuditResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                // Dashboard mockup TU Pesantren — banner → statistik → (grafik/tabel/panel kanan menyusul).
                \App\Filament\Widgets\GreetingBanner::class,
                \App\Filament\Widgets\StatsPesantren::class,
                \App\Filament\Widgets\KeuanganAreaChart::class,
                \App\Filament\Widgets\KategoriPengeluaranChart::class,
                \App\Filament\Widgets\TransaksiTerbaru::class,
                \App\Filament\Widgets\AgendaPengumuman::class,
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
}
