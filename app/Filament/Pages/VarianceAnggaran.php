<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Modules\Ledger\Services\AnggaranVarianceService;

/**
 * G1-08 — Dashboard Varians Anggaran (RKAM) vs Realisasi. READ-ONLY.
 */
class VarianceAnggaran extends Page
{
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Varians Anggaran';

    protected static ?string $title = 'Anggaran vs Realisasi (RKAM)';

    protected static string|\UnitEnum|null $navigationGroup = 'Akuntansi & Pajak';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'ledger/varians-anggaran';

    protected string $view = 'filament.pages.variance-anggaran';

    public ?string $tahun = null;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && static::moduleVisible('ledger')
            && (Auth::user()?->canAny(['view_ledger', 'manage_ledger', 'view_laporan_pimpinan']) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::canAccess();
    }

    public function mount(): void
    {
        $this->tahun = (string) now()->year;
    }

    /** Opsi tahun: tahun ini ± 2. */
    public function tahunOptions(): array
    {
        $kini = (int) now()->year;
        $opsi = [];
        foreach (range($kini + 1, $kini - 2) as $t) {
            $opsi[(string) $t] = (string) $t;
        }

        return $opsi;
    }

    protected function getViewData(): array
    {
        $bulan = ((string) now()->year === $this->tahun) ? (int) now()->month : 12;

        return [
            'laporan' => app(AnggaranVarianceService::class)->laporan((string) $this->tahun, $bulan),
            'tahunOptions' => $this->tahunOptions(),
        ];
    }
}
