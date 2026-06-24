<?php

namespace Modules\PPDB\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use Filament\Pages\Page;
use Modules\PPDB\Models\CalonSantri;

class PpdbReport extends Page
{
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Laporan PPDB';

    protected static string|\UnitEnum|null $navigationGroup = 'Pendaftaran';

    protected static ?string $slug = 'ppdb/report';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.ppdb-report';

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('ppdb') && static::canAccess();
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('ppdb') && (auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false);
    }

    public function getViewData(): array
    {
        return [
            'counts' => [
                'total' => CalonSantri::count(),
                'lulus' => CalonSantri::where('status_pendaftaran', 'lulus')->count(),
                'cadangan' => CalonSantri::where('status_pendaftaran', 'cadangan')->count(),
                'ditolak' => CalonSantri::where('status_pendaftaran', 'ditolak')->count(),
                'belum_verifikasi' => CalonSantri::where('status_pendaftaran', 'pending')->count(),
            ],
        ];
    }
}
