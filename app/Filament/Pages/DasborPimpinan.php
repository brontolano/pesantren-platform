<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Modules\Keuangan\Models\Tagihan;
use Modules\Ledger\Services\BukuBesarService;
use Modules\Ledger\Services\LaporanService;

/** L1 — Dasbor Pimpinan: kartu KPI ringkas (mode sederhana). */
class DasborPimpinan extends Page
{
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'L1 — Dasbor Pimpinan';

    protected static ?string $title = 'Dasbor Pimpinan';

    protected static string|\UnitEnum|null $navigationGroup = 'Pelaporan';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'laporan/dasbor-pimpinan';

    protected string $view = 'filament.pages.dasbor-pimpinan';

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && static::moduleVisible('ledger')
            && (Auth::user()?->canAny(['view_laporan_pimpinan', 'view_ledger', 'manage_ledger']) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    #[Computed]
    public function kpi(): array
    {
        $lr = app(LaporanService::class)->labaRugi();
        $saldoKas = app(BukuBesarService::class)->bku()['saldo_akhir'];
        $tunggakan = Tagihan::belumLunas()->get()->sum(fn (Tagihan $t) => $t->sisa_tagihan);

        return [
            'kas' => $saldoKas,
            'pendapatan' => $lr['total_pendapatan'],
            'beban' => $lr['total_beban'],
            'surplus' => $lr['surplus_defisit'],
            'tunggakan' => round((float) $tunggakan, 2),
        ];
    }

    /** Posisi per sumber dana (terikat) untuk transparansi. */
    #[Computed]
    public function danaTerikat(): array
    {
        return app(LaporanService::class)->laporanDanaTerikat();
    }
}
