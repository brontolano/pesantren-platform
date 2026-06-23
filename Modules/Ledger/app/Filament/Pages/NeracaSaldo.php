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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Modules\Ledger\Services\BukuBesarService;

/**
 * Neraca Saldo (trial balance) — Σdebit = Σkredit, dasar Neraca & Laba-Rugi.
 */
class NeracaSaldo extends Page implements HasForms
{
    use InteractsWithForms;
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Neraca Saldo';

    protected static ?string $title = 'Neraca Saldo (Trial Balance)';

    protected static string|\UnitEnum|null $navigationGroup = 'Akuntansi';

    protected static ?int $navigationSort = 12;

    protected static ?string $slug = 'ledger/neraca-saldo';

    protected string $view = 'filament.pages.neraca-saldo';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && static::moduleVisible('ledger')
            && (Auth::user()?->canAny(['view_ledger', 'manage_ledger']) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill(['to' => now()->endOfMonth()->toDateString()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Per Tanggal')->schema([
                    DatePicker::make('to')->label('Sampai Tanggal')->live(),
                ]),
            ]);
    }

    #[Computed]
    public function rows(): Collection
    {
        return app(BukuBesarService::class)->neracaSaldo($this->data['to'] ?? null);
    }
}
