<?php

namespace Modules\Ledger\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Modules\Kesantrian\Models\Santri;
use Modules\Ledger\Services\PartyLedgerService;

/**
 * F4 — Buku Besar Pembantu per Pihak (piutang santri). Saldo & aging dari baris
 * jurnal ber-party. Terisi setelah rollout men-tag jurnal pembayaran/tagihan.
 */
class BukuPembantuPihak extends Page
{
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Buku Pembantu Piutang';

    protected static ?string $title = 'Buku Besar Pembantu — Piutang Santri';

    protected static string|\UnitEnum|null $navigationGroup = 'Buku Besar';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'ledger/buku-pembantu-pihak';

    protected string $view = 'filament.pages.buku-pembantu-pihak';

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && static::moduleVisible('ledger')
            && (Auth::user()?->canAny(['view_ledger', 'manage_ledger', 'view_laporan_pimpinan']) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::navAdvanced('ledger') && static::canAccess();
    }

    protected function getViewData(): array
    {
        $svc = app(PartyLedgerService::class);
        $daftar = $svc->daftarByTipe(Santri::class);

        $nama = Santri::query()->whereIn('id', array_keys($daftar))->pluck('nama', 'id');

        $baris = [];
        foreach ($daftar as $santriId => $saldo) {
            $baris[] = [
                'nama' => $nama[$santriId] ?? ('Santri #'.$santriId),
                'saldo' => $saldo,
                'aging' => $svc->aging(Santri::class, (int) $santriId),
            ];
        }
        usort($baris, fn ($a, $b) => $b['saldo'] <=> $a['saldo']);

        return [
            'baris' => $baris,
            'totalPiutang' => round(array_sum($daftar), 2),
        ];
    }
}
