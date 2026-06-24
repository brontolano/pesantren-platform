<?php

namespace Modules\Mutu\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Modules\Mutu\Services\MutuKpiService;

/**
 * G3-17 — Dashboard KPI Mutu: ringkasan capaian sasaran, status temuan, indeks SKM.
 */
class DashboardMutu extends Page
{
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Dashboard Mutu';

    protected static ?string $title = 'Dashboard KPI Mutu (SPMI)';

    protected static string|\UnitEnum|null $navigationGroup = 'Mutu';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'mutu/dashboard';

    protected string $view = 'filament.pages.dashboard-mutu';

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && static::moduleVisible('mutu')
            && (Auth::user()?->can('manage_mutu') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::canAccess();
    }

    protected function getViewData(): array
    {
        return ['ringkasan' => app(MutuKpiService::class)->ringkasan()];
    }
}
