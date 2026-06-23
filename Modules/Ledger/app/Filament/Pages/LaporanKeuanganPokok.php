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
use Modules\Ledger\Services\LaporanService;

/**
 * L6 — Laporan Keuangan Pokok: Neraca, Laba-Rugi/Surplus-Defisit, Arus Kas.
 */
class LaporanKeuanganPokok extends Page implements HasForms
{
    use InteractsWithForms;
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'L6 — Laporan Keuangan Pokok';

    protected static ?string $title = 'L6 — Laporan Keuangan Pokok';

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan Pimpinan';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'laporan/keuangan-pokok';

    protected string $view = 'filament.pages.laporan-keuangan-pokok';

    public ?array $data = [];

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

    public function mount(): void
    {
        $this->form->fill([
            'from' => now()->startOfYear()->toDateString(),
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
    public function neraca(): array
    {
        return app(LaporanService::class)->neraca($this->data['to'] ?? null);
    }

    #[Computed]
    public function labaRugi(): array
    {
        return app(LaporanService::class)->labaRugi($this->data['from'] ?? null, $this->data['to'] ?? null);
    }

    #[Computed]
    public function arusKas(): array
    {
        return app(LaporanService::class)->arusKas($this->data['from'] ?? null, $this->data['to'] ?? null);
    }
}
