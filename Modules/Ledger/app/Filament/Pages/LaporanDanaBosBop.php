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

/** L2 — Laporan Dana BOS / BOP (penerimaan vs penggunaan per sumber dana negara). */
class LaporanDanaBosBop extends Page implements HasForms
{
    use InteractsWithForms;
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'L2 — Dana BOS / BOP';

    protected static ?string $title = 'L2 — Laporan Dana BOS / BOP';

    protected static string|\UnitEnum|null $navigationGroup = 'Mutu & Laporan';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'laporan/dana-bos-bop';

    protected string $view = 'filament.pages.laporan-dana-bos-bop';

    public ?array $data = [];

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

    public function mount(): void
    {
        $this->form->fill([
            'from' => now()->startOfYear()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Periode')->schema([
                DatePicker::make('from')->label('Dari')->live(),
                DatePicker::make('to')->label('Sampai')->live(),
            ])->columns(2),
        ]);
    }

    #[Computed]
    public function rows(): array
    {
        return app(LaporanService::class)->danaBosBop($this->data['from'] ?? null, $this->data['to'] ?? null);
    }
}
