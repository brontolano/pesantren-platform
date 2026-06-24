<?php

namespace Modules\Akademik\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use Filament\Pages\Page;
use Modules\Akademik\Services\JurnalKbmService;

/**
 * F2-PWA — Rekap bulanan jurnal KBM per guru: filter bulan → tabel guru ×
 * jumlah sesi terlaksana/kosong/diganti. Sumber: jurnal_mengajar (F0).
 *
 * Didaftar EKSPLISIT di TenantPanelProvider (pola WP layar kerja).
 */
class RekapJurnalKbm extends Page
{
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 36;

    protected string $view = 'filament.pages.rekap-jurnal-kbm';

    public string $bulan = '';

    /** @var array<int, array<string, mixed>> */
    public array $rekap = [];

    public static function getNavigationLabel(): string
    {
        return 'Rekap Jurnal KBM';
    }

    public function getTitle(): string
    {
        return 'Rekap Jurnal KBM Bulanan';
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('akademik') && (auth()->user()?->canAny(['manage_akademik', 'view_akademik']) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('akademik') && static::canAccess();
    }

    public function mount(): void
    {
        $this->bulan = now()->format('Y-m');
        $this->muatRekap();
    }

    public function updatedBulan(): void
    {
        $this->muatRekap();
    }

    public function muatRekap(): void
    {
        $this->rekap = [];

        if (preg_match('/^\d{4}-\d{2}$/', $this->bulan) !== 1) {
            return;
        }

        $this->rekap = app(JurnalKbmService::class)->rekapBulanan($this->bulan);
    }
}
