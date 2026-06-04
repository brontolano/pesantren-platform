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
use App\Filament\Resources\Billing\PlanResource;
use App\Filament\Resources\Billing\PlatformInvoiceResource;
use App\Filament\Resources\Billing\TenantSubscriptionResource;
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
use App\Filament\Resources\PPDB\CalonSantriDokumenResource;
use App\Filament\Resources\PPDB\CalonSantriResource;
use App\Filament\Resources\PPDB\HasilUjianResource;
use App\Filament\Resources\Pembayaran\PembayaranResource;
use App\Filament\Resources\PenempatanKelas\PenempatanKelasResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Santri\SantriResource;
use App\Filament\Resources\Settings\SettingResource;
use App\Filament\Resources\Tabungan\TabunganResource;
use App\Filament\Resources\Tagihan\TagihanResource;
use App\Filament\Resources\Tahfidz\HafalanTahfidzResource;
use App\Filament\Resources\Tahfidz\PenilaianTahfidzResource;
use App\Filament\Resources\TemplateNotifikasi\TemplateNotifikasiResource;
use App\Filament\Resources\Tenants\TenantResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\BillingOverviewWidget;
use App\Filament\Widgets\KesantrianOverviewWidget;
use App\Filament\Widgets\KeuanganOverviewWidget;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
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
// Tenancy middleware tidak dipakai di admin panel (central domain only)

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->profile()
            ->multiFactorAuthentication(
                [EmailAuthentication::make()],
                isRequired: fn (): bool => auth()->user()?->hasAnyRole(['super_admin', 'kepala']) ?? false,
            )
            ->spa()
            ->colors([
                'primary' => Color::Emerald,
            ])
            // Daftarkan resources secara eksplisit — jauh lebih cepat daripada discoverResources()
            // discoverResources() scan filesystem setiap request → timeout 30s dengan 43 files
            ->resources([
                // Core
                TenantResource::class,
                UserResource::class,
                RoleResource::class,
                SettingResource::class,
                // Kesantrian
                SantriResource::class,
                KelasResource::class,
                PenempatanKelasResource::class,
                AlumniResource::class,
                // Keuangan
                TagihanResource::class,
                PembayaranResource::class,
                TabunganResource::class,
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
                // Billing
                PlanResource::class,
                TenantSubscriptionResource::class,
                PlatformInvoiceResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                AccountWidget::class,
                KesantrianOverviewWidget::class,
                KeuanganOverviewWidget::class,
                BillingOverviewWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                // Tenancy middleware dihapus dari admin panel
                // Admin panel diakses via central domain (pesantren.online)
                // Tenant panel akan punya middleware sendiri
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
