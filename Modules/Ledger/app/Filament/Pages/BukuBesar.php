<?php

namespace Modules\Ledger\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Services\BukuBesarService;

/**
 * Buku Besar per akun — proyeksi read-only dari jurnal_baris (BukuBesarService).
 */
class BukuBesar extends Page implements HasForms
{
    use InteractsWithForms;
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Buku Besar';

    protected static ?string $title = 'Buku Besar';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'ledger/buku-besar';

    protected string $view = 'filament.pages.buku-besar';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && static::moduleVisible('ledger')
            && (Auth::user()?->canAny(['view_ledger', 'manage_ledger']) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::navAdvanced('ledger') && static::canAccess();
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
                Section::make('Filter')->schema([
                    Select::make('akun_id')->label('Akun')
                        ->options(fn () => Akun::where('is_postable', true)->orderBy('kode')
                            ->get()->mapWithKeys(fn ($a) => [$a->id => "{$a->kode} — {$a->nama}"]))
                        ->searchable()->live()->required(),
                    DatePicker::make('from')->label('Dari')->live(),
                    DatePicker::make('to')->label('Sampai')->live(),
                ])->columns(3),
            ]);
    }

    #[Computed]
    public function hasil(): ?array
    {
        $akunId = $this->data['akun_id'] ?? null;
        if (! $akunId) {
            return null;
        }

        return app(BukuBesarService::class)->bukuBesar(
            (int) $akunId,
            $this->data['from'] ?? null,
            $this->data['to'] ?? null,
        );
    }
}
