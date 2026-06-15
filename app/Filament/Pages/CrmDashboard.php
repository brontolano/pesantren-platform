<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Modules\PPDB\Services\CrmService;

/**
 * G3-20 — Dashboard CRM ringan: funnel pendaftaran + indikator churn (tunggakan).
 */
class CrmDashboard extends Page
{
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'CRM (Funnel & Churn)';

    protected static ?string $title = 'CRM — Funnel Pendaftaran & Churn';

    protected static string|\UnitEnum|null $navigationGroup = 'Kelembagaan';

    protected static ?int $navigationSort = 29;

    protected static ?string $slug = 'crm/dashboard';

    protected string $view = 'filament.pages.crm-dashboard';

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && static::moduleVisible('keuangan')
            && (Auth::user()?->canAny(['view_laporan_pimpinan', 'manage_keuangan']) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::canAccess();
    }

    protected function getViewData(): array
    {
        $crm = app(CrmService::class);

        return [
            'funnel' => $crm->funnel(),
            'churn' => $crm->churnBerisiko(['ambang_tunggakan' => 0])->take(20),
        ];
    }
}
