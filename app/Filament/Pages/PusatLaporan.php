<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Modules\Ledger\Support\FiturKeuangan;

/** Pusat Laporan — indeks tautan ke seluruh laporan keuangan (L1–L7). */
class PusatLaporan extends Page
{
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder-open';

    protected static ?string $navigationLabel = 'Pusat Laporan';

    protected static ?string $title = 'Pusat Laporan';

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan Pimpinan';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'laporan';

    protected string $view = 'filament.pages.pusat-laporan';

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && static::moduleVisible('ledger')
            && (Auth::user()?->canAny(['view_ledger', 'manage_ledger', 'view_laporan_pimpinan']) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::canAccess();
    }

    /** @return array<int, array{label:string, desc:string, page:class-string}> */
    public function laporanTersedia(): array
    {
        $items = [
            ['label' => 'L6 — Laporan Keuangan Pokok', 'desc' => 'Neraca, Laba-Rugi/Surplus-Defisit, Arus Kas', 'page' => LaporanKeuanganPokok::class],
            ['label' => 'L2 — Dana BOS / BOP', 'desc' => 'LPJ penerimaan vs penggunaan dana negara', 'page' => LaporanDanaBosBop::class],
            ['label' => 'L5 — RKAM & Realisasi', 'desc' => 'Realisasi beban per divisi', 'page' => LaporanRkam::class],
        ];

        if (FiturKeuangan::pemisahanKehalalanAktif()) {
            $items[] = ['label' => 'Dana Kebajikan', 'desc' => 'Sumber & penggunaan dana syubhat (terpisah)', 'page' => LaporanDanaKebajikan::class];
        }

        return $items;
    }
}
