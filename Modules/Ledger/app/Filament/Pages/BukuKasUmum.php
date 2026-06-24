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
use Modules\Ledger\Services\BukuBesarService;

/**
 * Buku Kas Umum (BKU) — akumulasi baris akun kas/bank (proyeksi, rekonsiliasi 100%).
 */
class BukuKasUmum extends Page implements HasForms
{
    use InteractsWithForms;
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Buku Kas Umum (BKU)';

    protected static ?string $title = 'Buku Kas Umum (BKU)';

    protected static string|\UnitEnum|null $navigationGroup = 'Buku Besar';

    protected static ?int $navigationSort = 11;

    protected static ?string $slug = 'ledger/bku';

    protected string $view = 'filament.pages.buku-kas-umum';

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
                Section::make('Rentang Tanggal')->schema([
                    DatePicker::make('from')->label('Dari')->live(),
                    DatePicker::make('to')->label('Sampai')->live(),
                ])->columns(2),
            ]);
    }

    #[Computed]
    public function hasil(): array
    {
        return app(BukuBesarService::class)->bku(
            $this->data['from'] ?? null,
            $this->data['to'] ?? null,
        );
    }
}
