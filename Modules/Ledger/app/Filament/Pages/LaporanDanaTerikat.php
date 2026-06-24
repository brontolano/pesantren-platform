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

/** Segregation of Funds — Dana Wakaf (PSAK 112) & ZIS (PSAK 109) disajikan terpisah. */
class LaporanDanaTerikat extends Page implements HasForms
{
    use InteractsWithForms;
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'Dana Terikat (Wakaf/ZIS)';

    protected static ?string $title = 'Laporan Dana Terikat (PSAK 112/109)';

    protected static string|\UnitEnum|null $navigationGroup = 'Mutu & Laporan';

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'laporan/dana-terikat';

    protected string $view = 'filament.pages.laporan-dana-terikat';

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
    public function dana(): array
    {
        return app(LaporanService::class)->laporanDanaTerikat($this->data['from'] ?? null, $this->data['to'] ?? null);
    }
}
