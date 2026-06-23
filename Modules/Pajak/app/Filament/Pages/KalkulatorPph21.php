<?php

namespace Modules\Pajak\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Modules\Pajak\Services\PajakPenghasilanService;
use Modules\Payroll\Services\BpjsService;

/**
 * G2-12 — Kalkulator PPh21 (PTKP-tiered) + BPJS. Preview, tak menyimpan.
 */
class KalkulatorPph21 extends Page implements HasForms
{
    use InteractsWithForms;
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Kalkulator PPh21 & BPJS';

    protected static ?string $title = 'Kalkulator PPh21 (PTKP-tiered) & BPJS';

    protected static string|\UnitEnum|null $navigationGroup = 'Kepegawaian';

    protected static ?int $navigationSort = 28;

    protected static ?string $slug = 'pajak/kalkulator-pph21';

    protected string $view = 'filament.pages.kalkulator-pph21';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && static::moduleVisible('payroll')
            && (Auth::user()?->canAny(['run_payroll', 'manage_pajak', 'manage_keuangan']) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill(['bruto' => 10000000, 'ptkp_status' => 'TK0']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Parameter')->schema([
                TextInput::make('bruto')->label('Penghasilan Bruto / Bulan (Rp)')->numeric()->live()->prefix('Rp'),
                Select::make('ptkp_status')->label('Status PTKP')->native(false)->live()
                    ->options(['TK0' => 'TK/0', 'TK1' => 'TK/1', 'TK2' => 'TK/2', 'TK3' => 'TK/3',
                        'K0' => 'K/0', 'K1' => 'K/1', 'K2' => 'K/2', 'K3' => 'K/3']),
            ])->columns(2),
        ]);
    }

    public function hasil(): array
    {
        $bruto = round((float) ($this->data['bruto'] ?? 0), 2);
        $ptkp = $this->data['ptkp_status'] ?? 'TK0';

        return [
            'pph21' => app(PajakPenghasilanService::class)->hitungBulanan($bruto, $ptkp),
            'bpjs' => app(BpjsService::class)->rincian($bruto),
        ];
    }

    protected function getViewData(): array
    {
        return ['hasil' => $this->hasil()];
    }
}
