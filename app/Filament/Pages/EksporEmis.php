<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/** Ekspor terformat EMIS (CSV) — bukan API real-time. */
class EksporEmis extends Page
{
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-on-square';

    protected static ?string $navigationLabel = 'Ekspor EMIS';

    protected static ?string $title = 'Ekspor Terformat EMIS';

    protected static string|\UnitEnum|null $navigationGroup = 'Santri';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'laporan/ekspor-emis';

    protected string $view = 'filament.pages.ekspor-emis';

    public static function canAccess(): bool
    {
        return static::moduleVisible('akademik')
            && Filament::getCurrentPanel()?->getId() === 'tenant'
            && (Auth::user()?->canAny(['view_keuangan', 'manage_keuangan', 'view_santri']) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('akademik') && static::canAccess();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('unduh')
                ->label('Unduh CSV EMIS')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn () => route('tenant.emis.export.csv', [], false))
                ->openUrlInNewTab(),
        ];
    }
}
