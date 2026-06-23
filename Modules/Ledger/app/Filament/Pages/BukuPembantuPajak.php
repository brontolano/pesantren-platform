<?php

namespace Modules\Ledger\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\JurnalBaris;

/**
 * Buku Pembantu Pajak — rekap utang PPN & PPh (dipungut vs disetor) per jenis,
 * proyeksi dari jurnal_baris pada akun Utang Pajak (2-1110..2-1150).
 */
class BukuPembantuPajak extends Page implements HasForms
{
    use InteractsWithForms;
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'Buku Pembantu Pajak';

    protected static ?string $title = 'Buku Pembantu Pajak';

    protected static string|\UnitEnum|null $navigationGroup = 'Pajak';

    protected static ?int $navigationSort = 13;

    protected static ?string $slug = 'pajak/buku-pembantu';

    protected string $view = 'filament.pages.buku-pembantu-pajak';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && static::moduleVisible('pajak')
            && (Auth::user()?->canAny(['view_ledger', 'manage_ledger', 'manage_pajak']) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill([
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Periode')->schema([
                    DatePicker::make('from')->label('Dari')->live(),
                    DatePicker::make('to')->label('Sampai')->live(),
                ])->columns(2),
            ]);
    }

    #[Computed]
    public function rows(): array
    {
        $from = $this->data['from'] ?? null;
        $to = $this->data['to'] ?? null;

        // Akun utang pajak: 2-1110 (PPN) s/d 2-1150 (PPh Final).
        $akun = Akun::where('kode', 'like', '2-11%')->where('is_postable', true)->orderBy('kode')->get();

        return $akun->map(function (Akun $a) use ($from, $to) {
            $q = JurnalBaris::where('akun_id', $a->id)
                ->whereHas('jurnal', function ($j) use ($from, $to) {
                    $j->where('status', 'posted');
                    if ($from) {
                        $j->whereDate('tanggal', '>=', $from);
                    }
                    if ($to) {
                        $j->whereDate('tanggal', '<=', $to);
                    }
                });

            $dipungut = (float) (clone $q)->sum('kredit');
            $disetor = (float) (clone $q)->sum('debit');

            return [
                'kode' => $a->kode,
                'nama' => $a->nama,
                'dipungut' => round($dipungut, 2),
                'disetor' => round($disetor, 2),
                'saldo' => round($dipungut - $disetor, 2),
            ];
        })->filter(fn ($r) => $r['dipungut'] != 0 || $r['disetor'] != 0)->values()->all();
    }
}
