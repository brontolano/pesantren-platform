<?php

namespace Modules\Keuangan\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Modules\Keuangan\Models\TabunganTransaksi;

/**
 * Rekonsiliasi Kantin harian (2b) — ringkasan READ-ONLY per kasir per hari:
 * jumlah transaksi, total belanja, total refund, netto. Tidak ada aksi tulis.
 */
class RekonKantin extends Page
{
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Rekon Kantin';

    protected static ?string $title = 'Rekonsiliasi Kantin Harian';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 21;

    protected static ?string $slug = 'keuangan/rekon-kantin';

    protected string $view = 'filament.pages.rekon-kantin';

    /** Tanggal rekap (Y-m-d). */
    public string $tanggal = '';

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && static::moduleVisible('keuangan')
            && ((Auth::user()?->can('manage_keuangan') ?? false)
                || (Auth::user()?->can('operate_pos') ?? false));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->tanggal = now()->toDateString();
    }

    /**
     * Rekap per kasir: [kasir, jumlah_belanja, total_belanja, jumlah_refund,
     * total_refund, netto].
     */
    public function rekap(): Collection
    {
        $rows = TabunganTransaksi::query()
            ->whereIn('tipe', ['belanja_kantin', 'refund_kantin'])
            ->whereDate('tanggal', $this->tanggal ?: now()->toDateString())
            ->with('creator')
            ->get();

        return $rows->groupBy('created_by')->map(function (Collection $grup) {
            $belanja = $grup->where('tipe', 'belanja_kantin');
            $refund = $grup->where('tipe', 'refund_kantin');

            return [
                'kasir' => $grup->first()?->creator?->name ?? '(tanpa kasir)',
                'jumlah_belanja' => $belanja->count(),
                'total_belanja' => (float) $belanja->sum('nominal'),
                'jumlah_refund' => $refund->count(),
                'total_refund' => (float) $refund->sum('nominal'),
                'netto' => (float) $belanja->sum('nominal') - (float) $refund->sum('nominal'),
            ];
        })->values();
    }
}
