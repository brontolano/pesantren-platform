<?php

namespace Modules\Mutu\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use App\Services\Mutu\MutuService;
use Filament\Pages\Page;
use Modules\Kesantrian\Models\Kelas;

/**
 * 2a — Heatmap mutu per kelas: tabel santri × dimensi dengan warna sel via
 * kelas blade (tanpa library chart). Agregat batched (anti N+1).
 */
class MutuKelas extends Page
{
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Mutu per Kelas';

    protected static ?string $title = 'Heatmap Mutu per Kelas';

    protected static string|\UnitEnum|null $navigationGroup = 'Mutu';

    protected static ?int $navigationSort = 31;

    protected static ?string $slug = 'mutu-kelas';

    protected string $view = 'filament.pages.mutu-kelas';

    public ?int $kelasId = null;

    public static function canAccess(): bool
    {
        return static::moduleVisible('mutu') && (auth()->user()?->canAny(['manage_mutu', 'approve_mutu']) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('mutu') && static::canAccess();
    }

    protected function getViewData(): array
    {
        return [
            'kelasList' => Kelas::query()->where('is_active', true)->orderBy('nama_kelas')->get(['id', 'nama_kelas']),
            'baris' => $this->kelasId ? app(MutuService::class)->ringkasanKelas($this->kelasId) : [],
        ];
    }
}
