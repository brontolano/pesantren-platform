<?php

namespace App\Providers\Filament;

use App\Filament\Resources\Billing\PlanResource;
use App\Filament\Resources\Billing\PlatformInvoiceResource;
use App\Filament\Resources\Billing\TenantSubscriptionResource;
use App\Filament\Resources\DevProjects\DevProjectResource;
use App\Filament\Resources\DevTasks\DevTaskResource;
use App\Filament\Resources\Modules\ModuleResource;
use App\Filament\Resources\Tenants\TenantResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\BillingOverviewWidget;
use App\Filament\Widgets\DevProgressWidget;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
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
            ->viteTheme('resources/css/filament/pesantren/theme.css')
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
                // Manajemen Proyek (internal — pantau progres pengembangan)
                DevProjectResource::class,
                DevTaskResource::class,
            ])
            // Struktur menu platform — 3 grup berurutan. JANGAN beri ->icon() pada
            // grup: Filament v5 melempar 500 bila grup berikon memuat item berikon
            // (pola sama dengan panel tenant). Dashboard & Users sengaja dibiarkan
            // tanpa grup → tampil di puncak (selaras gaya panel tenant).
            ->navigationGroups([
                NavigationGroup::make('Pesantren'),
                NavigationGroup::make('Langganan & Billing')->collapsible(),
                NavigationGroup::make('Manajemen Proyek')->collapsible(),
            ])
            ->pages([
                Dashboard::class,
                \App\Filament\Pages\InstalasiTenant::class,
                \App\Filament\Pages\KalkulatorLangganan::class,
                \App\Filament\Pages\AktivasiModulTenant::class,
                \App\Filament\Pages\KelolaKuotaPenyimpanan::class,
            ])
            ->widgets([
                \App\Filament\Widgets\GreetingBanner::class,
                AccountWidget::class,
                BillingOverviewWidget::class,
                DevProgressWidget::class,
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
