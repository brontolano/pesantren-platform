<?php

namespace Modules\Ledger\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Exceptions\ReportPdfUnavailableException;
use Modules\Core\Services\ReportExportService;
use Modules\Ledger\Services\KehalalanService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * F3 — Laporan Kehalalan (diferensiator syariah). Ringkasan pendapatan per
 * klasifikasi halal/syubhat/non_halal + %halal + saldo Dana Kebajikan.
 */
class LaporanKehalalan extends Page
{
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationLabel = 'Laporan Kehalalan';

    protected static ?string $title = 'Laporan Kehalalan Dana';

    protected static string|\UnitEnum|null $navigationGroup = 'Akuntansi';

    protected static ?int $navigationSort = 9;

    protected static ?string $slug = 'ledger/laporan-kehalalan';

    protected string $view = 'filament.pages.laporan-kehalalan';

    public ?string $dari = null;

    public ?string $sampai = null;

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
        $this->dari = now()->startOfYear()->toDateString();
        $this->sampai = now()->toDateString();
    }

    protected function getViewData(): array
    {
        return [
            'laporan' => app(KehalalanService::class)->laporan($this->dari, $this->sampai),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sertifikat')->label('Unduh Sertifikat')
                ->icon('heroicon-o-document-check')->color('success')
                ->action(fn (): Response => $this->unduhSertifikat()),
            Action::make('csv')->label('Ekspor CSV')
                ->icon('heroicon-o-table-cells')->color('gray')
                ->action(fn (): StreamedResponse => $this->eksporCsv()),
        ];
    }

    /** Sertifikat Kehalalan via F8 — PDF bila dompdf terpasang, jika tidak HTML print-ready. */
    public function unduhSertifikat(): Response
    {
        $l = app(KehalalanService::class)->laporan($this->dari, $this->sampai);
        $svc = app(ReportExportService::class);
        $data = [
            'judul' => 'Sertifikat Kehalalan',
            'periode' => ($this->dari ?? '-').' s/d '.($this->sampai ?? '-'),
            'persen_halal' => $l['persen_halal'],
            'nominal_syubhat' => $l['pendapatan']['syubhat'] ?? 0,
            'saldo_kebajikan' => $l['dana_kebajikan'] ?? 0,
            'status' => ($l['status'] ?? '') === 'bersih'
                ? 'Sesuai Prinsip Syariah'
                : 'Terdapat pendapatan syubhat (disalurkan ke Dana Kebajikan)',
            'tempat_tanggal' => 'Dicetak '.now()->translatedFormat('d F Y'),
            'penandatangan' => Auth::user()?->name ?? 'Pengawas Syariah',
            'jabatan' => 'Pengawas Syariah',
        ];

        try {
            return $svc->pdf('sertifikat-kehalalan', $data, 'sertifikat-kehalalan.pdf');
        } catch (ReportPdfUnavailableException) {
            $html = $svc->html('sertifikat-kehalalan', $data);

            return response()->streamDownload(
                fn () => print ($html),
                'sertifikat-kehalalan.html',
                ['Content-Type' => 'text/html; charset=UTF-8'],
            );
        }
    }

    /** Ekspor ringkasan kehalalan ke CSV (F8). */
    public function eksporCsv(): StreamedResponse
    {
        $l = app(KehalalanService::class)->laporan($this->dari, $this->sampai);
        $p = $l['pendapatan'];
        $rows = [
            ['Pendapatan Halal', $p['halal'] ?? 0],
            ['Pendapatan Syubhat', $p['syubhat'] ?? 0],
            ['Pendapatan Non-halal', $p['non_halal'] ?? 0],
            ['Total Pendapatan', $p['total'] ?? 0],
            ['Persentase Halal (%)', $l['persen_halal']],
            ['Saldo Dana Kebajikan', $l['dana_kebajikan'] ?? 0],
        ];

        return app(ReportExportService::class)->csv(['Klasifikasi', 'Nilai'], $rows, 'laporan-kehalalan.csv');
    }
}
