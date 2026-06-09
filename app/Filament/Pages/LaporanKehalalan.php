<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Modules\Ledger\Services\KehalalanService;

/**
 * F3 — Laporan Kehalalan (diferensiator syariah). Ringkasan pendapatan per
 * klasifikasi halal/syubhat/non_halal + %halal + saldo Dana Kebajikan.
 */
class LaporanKehalalan extends Page
{
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationLabel = 'Laporan Kehalalan';

    protected static ?string $title = 'Laporan Kehalalan Dana';

    protected static string|\UnitEnum|null $navigationGroup = 'Buku Besar';

    protected static ?int $navigationSort = 9;

    protected static ?string $slug = 'ledger/laporan-kehalalan';

    protected string $view = 'filament.pages.laporan-kehalalan';

    public ?string $dari = null;

    public ?string $sampai = null;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && static::moduleVisible('ledger')
            && (Auth::user()?->canAny(['view_ledger', 'manage_ledger', 'view_laporan_pimpinan']) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->dari = now()->startOfYear()->toDateString();
        $this->sampai = now()->toDateString();
    }

    protected function getViewData(): array
    {
        return [
            'laporan' => app(KehalalanService::class)->laporan($this->dari, $this->sampai),
        ];
    }
}
