<?php

namespace App\Providers\Filament;

use App\Filament\Resources\Billing\PlanResource;
use App\Filament\Resources\Billing\PlatformInvoiceResource;
use App\Filament\Resources\Billing\TenantSubscriptionResource;
use App\Filament\Resources\Modules\ModuleResource;
use App\Filament\Resources\Tenants\TenantResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\BillingOverviewWidget;
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
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->resources([
                // Central domain only — kelola platform/SaaS (BUKAN data operasional tenant).
                // Role/permission pesantren dikelola di panel tenant masing-masing.
                TenantResource::class,
                UserResource::class,
                // Billing SaaS
                ModuleResource::class,
                PlanResource::class,
                TenantSubscriptionResource::class,
                PlatformInvoiceResource::class,
            ])
            ->pages([
                Dashboard::class,
                \App\Filament\Pages\KalkulatorLangganan::class,
            ])
            ->widgets([
                AccountWidget::class,
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
