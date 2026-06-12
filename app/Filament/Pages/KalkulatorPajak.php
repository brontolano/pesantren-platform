<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Modules\Pajak\Services\MesinPajakService;

/**
 * G1-05 — Kalkulator Pajak (preview). Input dpp/jenis/dana BOS/NPWP/kehalalan →
 * PPN/PPh otomatis (memakai MesinPajakService + kebijakan kehalalan G1-04).
 * READ-ONLY: tidak menyimpan apa pun.
 */
class KalkulatorPajak extends Page implements HasForms
{
    use InteractsWithForms;
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Kalkulator Pajak';

    protected static ?string $title = 'Kalkulator Pajak (PPN/PPh)';

    protected static string|\UnitEnum|null $navigationGroup = 'Akuntansi & Pajak';

    protected static ?int $navigationSort = 15;

    protected static ?string $slug = 'pajak/kalkulator';

    protected string $view = 'filament.pages.kalkulator-pajak';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && static::moduleVisible('pajak')
            && (Auth::user()?->canAny(['view_keuangan', 'manage_keuangan', 'manage_pajak']) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill([
            'dpp' => 1000000, 'jenis' => 'barang', 'dana_bos' => false,
            'vendor_npwp' => true, 'kehalalan' => 'halal',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Parameter Transaksi')->schema([
                    TextInput::make('dpp')->label('DPP (Rp)')->numeric()->live()->prefix('Rp')->default(1000000),
                    Select::make('jenis')->label('Jenis')->native(false)->live()
                        ->options(['barang' => 'Barang', 'jasa' => 'Jasa', 'sewa' => 'Sewa']),
                    Toggle::make('dana_bos')->label('Dana BOS/BOP (negara)')->live()->inline(false),
                    Toggle::make('vendor_npwp')->label('Vendor ber-NPWP')->live()->inline(false),
                    Select::make('kehalalan')->label('Kehalalan Sumber Dana')->native(false)->live()
                        ->options(['halal' => 'Halal', 'syubhat' => 'Syubhat', 'non_halal' => 'Non-halal'])
                        ->helperText('Dana syubhat/non-halal: pajak negara = 0 (G1-04).'),
                ])->columns(2),
            ]);
    }

    /** Hasil perhitungan dari state form (read langsung, tanpa validasi/persist). */
    public function hasil(): array
    {
        $d = $this->data ?? [];
        $dpp = round((float) ($d['dpp'] ?? 0), 2);
        if ($dpp <= 0) {
            return ['dpp' => 0.0, 'ppn' => 0.0, 'pph' => 0.0, 'pph_jenis' => null, 'total' => 0.0];
        }

        $p = app(MesinPajakService::class)->hitung(
            $dpp,
            $d['jenis'] ?? 'barang',
            (bool) ($d['dana_bos'] ?? false),
            (bool) ($d['vendor_npwp'] ?? false),
            $d['kehalalan'] ?? 'halal',
        );

        return [
            'dpp' => $dpp,
            'ppn' => (float) $p['ppn'],
            'pph' => (float) $p['pph'],
            'pph_jenis' => $p['pph_jenis'],
            'total' => round($dpp + (float) $p['ppn'], 2),
        ];
    }

    protected function getViewData(): array
    {
        return ['hasil' => $this->hasil()];
    }
}
