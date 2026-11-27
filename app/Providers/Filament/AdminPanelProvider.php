<?php

namespace App\Providers\Filament;

use App\Filament\Pages\AktivasiModulTenant;
use App\Filament\Pages\InstalasiTenant;
use App\Filament\Pages\KalkulatorLangganan;
use App\Filament\Pages\KelolaKuotaPenyimpanan;
use App\Filament\Pages\SupportDesk;
use App\Filament\Platform\Pages\PlatformDashboard;
use App\Filament\Resources\Billing\PlanResource;
use App\Filament\Resources\Billing\PlatformInvoiceResource;
use App\Filament\Resources\Billing\TenantSubscriptionResource;
use App\Filament\Resources\DevProjects\DevProjectResource;
use App\Filament\Resources\DevTasks\DevTaskResource;
use App\Filament\Resources\Modules\ModuleResource;
use App\Filament\Resources\PlatformAnnouncements\PlatformAnnouncementResource;
use App\Filament\Resources\Tenants\TenantResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\PlatformOverview;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('platform')
            ->login()
            ->passwordReset()
            ->profile()
            ->multiFactorAuthentication(
                [EmailAuthentication::make()],
                isRequired: fn (): bool => auth()->user()?->hasAnyRole(['super_admin', 'kepala']) ?? false,
            )
            ->spa()
            ->defaultThemeMode(ThemeMode::Light)
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('17rem')
            ->viteTheme('resources/css/filament/pesantren/theme.css')
            ->font('Plus Jakarta Sans')
            ->brandName('Pesantren.online')
            ->brandLogo(fn () => view('filament.brand', ['name' => 'Pesantren.online']))
            ->brandLogoHeight('2.25rem')
            ->colors([
                'primary' => Color::Green,
                'gray' => Color::Slate,
            ])
            ->resources([
                // Central domain only — kelola platform/SaaS (BUKAN data operasional tenant).
                // Role/permission pesantren dikelola di panel tenant masing-masing.
                TenantResource::class,
                UserResource::class,
                // Langganan & Billing (paket, langganan, invoice, katalog modul)
                ModuleResource::class,
                PlanResource::class,
                TenantSubscriptionResource::class,
                PlatformInvoiceResource::class,
                PlatformAnnouncementResource::class,
                // Manajemen Proyek (internal — pantau progres pengembangan)
                DevProjectResource::class,
                DevTaskResource::class,
            ])
            // Struktur menu platform — 4 grup berurutan. JANGAN beri ->icon() pada
            // grup: Filament v5 melempar 500 bila grup berikon memuat item berikon
            // (pola sama dengan panel tenant). Dashboard & Users sengaja dibiarkan
            // tanpa grup → tampil di puncak (selaras gaya panel tenant).
            // 'AI Khadim Digital' = halaman Suara Pasar yg didaftarkan modul AI ke
            // panel admin (Modules/AI AIServiceProvider) → daftarkan agar tak yatim.
            ->navigationGroups([
                NavigationGroup::make('Pesantren'),
                NavigationGroup::make('Langganan & Billing')->collapsible(),
                NavigationGroup::make('Komunikasi & Dukungan')->collapsible(),
                NavigationGroup::make('AI Khadim Digital')->collapsible(),
                NavigationGroup::make('Manajemen Proyek')->collapsible(),
            ])
            ->pages([
                PlatformDashboard::class,
                SupportDesk::class,
                InstalasiTenant::class,
                KalkulatorLangganan::class,
                AktivasiModulTenant::class,
                KelolaKuotaPenyimpanan::class,
            ])
            ->widgets([
                PlatformOverview::class,
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
