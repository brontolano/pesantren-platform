<?php

namespace Modules\Mutu\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use App\Services\Mutu\MutuService;
use Filament\Pages\Page;

/**
 * 2a — Agregat mutu ringkas per lembaga (query GROUP BY, anti N+1).
 */
class MutuLembaga extends Page
{
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'Mutu per Lembaga';

    protected static ?string $title = 'Mutu per Lembaga';

    protected static string|\UnitEnum|null $navigationGroup = 'Mutu & Laporan';

    protected static ?int $navigationSort = 32;

    protected static ?string $slug = 'mutu-lembaga';

    protected string $view = 'filament.pages.mutu-lembaga';

    public static function canAccess(): bool
    {
        return static::moduleVisible('mutu') && (auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('mutu') && static::canAccess();
    }

    protected function getViewData(): array
    {
        return ['baris' => app(MutuService::class)->ringkasanLembaga()];
    }
}
