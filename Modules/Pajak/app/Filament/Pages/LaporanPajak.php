<?php

namespace Modules\Pajak\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Modules\Pajak\Services\EBupotService;
use Modules\Pajak\Services\SisaLebihService;
use Modules\Pajak\Services\SptBadanService;
use Modules\Pajak\Services\SptMasaService;
use Symfony\Component\HttpFoundation\Response;

/**
 * gap-88 — Panel Laporan Pajak inti (READ-ONLY).
 *
 * Menampilkan keluaran service pajak yang SUDAH ADA (sebelumnya hanya lewat
 * PWA/API KepalaKeuangan): SPT Masa PPh 21, e-Bupot PPh 21, worksheet SPT
 * Badan 1771, dan dashboard Sisa Lebih Yayasan (PMK 68/2020).
 *
 * Halaman ini TIDAK menghitung pajak sendiri dan TIDAK menyimpan/posting apa
 * pun — hanya memanggil method service existing lalu merender + menyediakan
 * tombol ekspor CSV Coretax (delegasi penuh ke EBupotService::toCsv).
 *
 * ⚠️ GATE KEPATUHAN: format CSV Coretax bisa berubah; tinjau penasihat pajak
 *    sebelum upload ke coretax.pajak.go.id (lihat EBupotService).
 */
class LaporanPajak extends Page implements HasForms
{
    use InteractsWithForms;
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Laporan Pajak';

    protected static ?string $title = 'Laporan Pajak (SPT Masa, e-Bupot, SPT Badan, Sisa Lebih)';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 16;

    protected static ?string $slug = 'pajak/laporan';

    protected string $view = 'filament.pages.laporan-pajak';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && static::moduleVisible('pajak')
            && (Auth::user()?->canAny(['view_keuangan', 'manage_keuangan', 'manage_pajak']) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::navAdvanced('pajak') && static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill([
            'masa' => now()->format('Y-m'),
            'tahun_badan' => (string) (now()->year - 1),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Periode Laporan')->schema([
                    TextInput::make('masa')
                        ->label('Masa Pajak (PPh 21)')
                        ->placeholder('YYYY-MM')
                        ->helperText('Format: tahun-bulan, mis. 2026-06. Dipakai SPT Masa & e-Bupot.')
                        ->live(onBlur: true),
                    Select::make('tahun_badan')
                        ->label('Tahun Pajak (SPT Badan)')
                        ->native(false)
                        ->live()
                        ->options($this->opsiTahun()),
                ])->columns(2),
            ]);
    }

    /** @return array<string, string> */
    protected function opsiTahun(): array
    {
        $tahunIni = now()->year;
        $opsi = [];
        for ($t = $tahunIni; $t >= $tahunIni - 6; $t--) {
            $opsi[(string) $t] = (string) $t;
        }

        return $opsi;
    }

    protected function masa(): string
    {
        $masa = trim((string) ($this->data['masa'] ?? ''));

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $masa) === 1
            ? $masa
            : now()->format('Y-m');
    }

    protected function tahunBadan(): string
    {
        $tahun = trim((string) ($this->data['tahun_badan'] ?? ''));

        return preg_match('/^\d{4}$/', $tahun) === 1
            ? $tahun
            : (string) (now()->year - 1);
    }

    /** Rekap SPT Masa PPh 21 (formulir 1721) — delegasi ke service. */
    public function sptMasa(): array
    {
        return app(SptMasaService::class)->rekap($this->masa());
    }

    /** Preview e-Bupot PPh 21 (format CSV Coretax) — delegasi ke service. */
    public function eBupot(): array
    {
        return app(EBupotService::class)->generate($this->masa());
    }

    /** Worksheet SPT Tahunan Badan 1771 — delegasi ke service. */
    public function sptBadan(): array
    {
        return app(SptBadanService::class)->worksheet($this->tahunBadan());
    }

    /** Dashboard Sisa Lebih Yayasan (PMK 68/2020) — delegasi ke service. */
    public function sisaLebih(): array
    {
        return app(SisaLebihService::class)->ringkasan();
    }

    protected function getViewData(): array
    {
        return [
            'masa' => $this->masa(),
            'tahunBadan' => $this->tahunBadan(),
            'sptMasa' => $this->sptMasa(),
            'eBupot' => $this->eBupot(),
            'sptBadan' => $this->sptBadan(),
            'sisaLebih' => $this->sisaLebih(),
            'perluReview' => EBupotService::PERLU_REVIEW_PENASIHAT_PAJAK,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ebupotCsv')
                ->label('Ekspor e-Bupot CSV (Coretax)')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->action(fn (): Response => $this->unduhEbupotCsv()),
        ];
    }

    /**
     * Unduh CSV e-Bupot PPh 21 dalam format Coretax DJP.
     * Read+export murni: nilai CSV dihasilkan sepenuhnya oleh EBupotService::toCsv
     * (tidak ada perhitungan/penulisan ulang di halaman ini).
     */
    public function unduhEbupotCsv(): Response
    {
        $masa = $this->masa();
        $csv = app(EBupotService::class)->toCsv($masa);

        return response()->streamDownload(
            fn () => print ($csv),
            "ebupot-pph21-{$masa}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
