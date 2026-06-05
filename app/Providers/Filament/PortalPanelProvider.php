<?php

namespace App\Providers\Filament;

use App\Filament\Portal\Auth\PhoneLogin;
use App\Filament\Portal\Pages\GantiPasswordPertama;
use App\Filament\Portal\Widgets\PesertaStatusWidget;
use App\Http\Middleware\EnsurePesertaPasswordReset;
use App\Http\Middleware\RedirectCentralToPlatform;
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

/**
 * Panel PORTAL PESERTA PPDB (`/portal`) — guard `web`, akun ber-role `peserta`.
 *
 * SATU sistem auth (Hukum #2): tidak ada guard baru; pembatasan akses lewat
 * User::canAccessPanel('portal'). Tenancy di-resolve dari domain (sama seperti
 * panel tenant staf) SEBELUM auth, agar user di-resolve dari DB tenant.
 */
class PortalPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('portal')
            ->path('portal')
            ->brandName('Portal PPDB')
            ->login(PhoneLogin::class)
            ->spa()
            ->colors([
                'primary' => Color::Teal,
            ])
            ->pages([
                Dashboard::class,
                GantiPasswordPertama::class,
            ])
            ->widgets([
                AccountWidget::class,
                PesertaStatusWidget::class,
            ])
            ->middleware([
                // Tenancy HARUS aktif sebelum session & auth agar user di-resolve
                // dari DB tenant. Domain central di-redirect / di-tolak.
                RedirectCentralToPlatform::class,
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
                // Paksa ganti password sementara saat login pertama.
                EnsurePesertaPasswordReset::class,
            ]);
    }
}
