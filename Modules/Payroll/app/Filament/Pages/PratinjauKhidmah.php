<?php

namespace Modules\Payroll\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Modules\Payroll\Models\TarifKhidmah;
use Modules\Payroll\Services\KhidmahAggregatorService;

/**
 * WP-13 — Pratinjau Bisyaroh/Khidmah untuk bendahara: pilih periode →
 * rekap poin per pegawai dengan rincian sumber, tombol Agregasi Ulang +
 * Generate ke Slip.
 *
 * SENGAJA tidak ada input edit poin di sini — selisih dikoreksi di SUMBER
 * (jurnal mengajar / presensi / khidmah manual), lalu agregasi ulang.
 */
class PratinjauKhidmah extends Page
{
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Pratinjau Bisyaroh';

    protected static ?string $title = 'Pratinjau Bisyaroh (Khidmah)';

    protected static string|\UnitEnum|null $navigationGroup = 'Kepegawaian';

    protected static ?int $navigationSort = 27;

    protected static ?string $slug = 'payroll/pratinjau-khidmah';

    protected string $view = 'filament.pages.pratinjau-khidmah';

    public string $periode = '';

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && static::moduleVisible('payroll')
            && (Auth::user()?->can('run_payroll') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::canAccess();
    }

    public function mount(): void
    {
        $this->periode = now()->format('Y-m');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('agregasiUlang')
                ->label('Agregasi Ulang')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalDescription('Baris hasil agregasi sebelumnya pada periode ini dihapus & ditulis ulang dari jurnal mengajar + presensi. Baris manual TIDAK disentuh.')
                ->action(function (): void {
                    try {
                        $hasil = app(KhidmahAggregatorService::class)->agregasi($this->periode);
                        Notification::make()->success()
                            ->title('Agregasi selesai')
                            ->body(sprintf('%d baris khidmah (%s poin) untuk %d pegawai — periode %s.',
                                $hasil['baris'], $hasil['total_poin'], $hasil['pegawai'], $this->periode))
                            ->send();

                        if (($hasil['dilewati_tanpa_employee'] ?? 0) > 0) {
                            Notification::make()->warning()
                                ->title('Sesi dilewati — pengganti tanpa data pegawai')
                                ->body(sprintf('%d sesi dilewati karena guru/pengganti belum tertaut data Employee aktif. Poinnya TIDAK dihitung — tautkan akun ke Employee lalu agregasi ulang.',
                                    $hasil['dilewati_tanpa_employee']))
                                ->persistent()
                                ->send();
                        }
                    } catch (\Throwable $e) {
                        Notification::make()->danger()->title('Agregasi ditolak')->body($e->getMessage())->send();
                    }
                }),
            Action::make('generateSlip')
                ->label('Generate ke Slip')
                ->icon('heroicon-o-document-currency-dollar')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Membuat slip gaji DRAFT per pegawai dengan poin = Σ khidmah periode ini. Slip yang sudah ada tidak diubah (idempoten).')
                ->action(function (): void {
                    try {
                        $hasil = app(KhidmahAggregatorService::class)->generateSlip($this->periode, Auth::id());
                        Notification::make()->success()
                            ->title('Slip draft dibuat')
                            ->body(sprintf('%d slip diproses, %d dilewati (pegawai tanpa akun login).', $hasil['dibuat'], $hasil['dilewati']))
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()->danger()->title('Generate slip gagal')->body($e->getMessage())->send();
                    }
                }),
        ];
    }

    protected function getViewData(): array
    {
        return [
            'rekap' => app(KhidmahAggregatorService::class)->rekap($this->periode),
            'labelJenis' => TarifKhidmah::JENIS_SESI + ['manual' => 'Input Manual'],
        ];
    }
}
