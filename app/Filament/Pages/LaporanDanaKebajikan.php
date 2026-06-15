<?php

namespace App\Filament\Pages;

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
use Modules\Ledger\Support\FiturKeuangan;

/**
 * Laporan Sumber & Penggunaan Dana Kebajikan (uang syubhat) — terpisah dari surplus.
 * Hanya relevan bila pemisahan kehalalan aktif.
 */
class LaporanDanaKebajikan extends Page implements HasForms
{
    use InteractsWithForms;
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Dana Kebajikan (Pembersihan)';

    protected static ?string $title = 'Laporan Sumber & Penggunaan Dana Kebajikan';

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan Pimpinan';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'laporan/dana-kebajikan';

    protected string $view = 'filament.pages.laporan-dana-kebajikan';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && static::moduleVisible('ledger')
            && FiturKeuangan::pemisahanKehalalanAktif()
            && (Auth::user()?->canAny(['view_ledger', 'manage_ledger', 'manage_dana_kebajikan']) ?? false);
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
        return $schema->statePath('data')->components([
            Section::make('Periode')->schema([
                DatePicker::make('from')->label('Dari')->live(),
                DatePicker::make('to')->label('Sampai')->live(),
            ])->columns(2),
        ]);
    }

    #[Computed]
    public function laporan(): array
    {
        return app(LaporanService::class)->danaKebajikan($this->data['from'] ?? null, $this->data['to'] ?? null);
    }
}
