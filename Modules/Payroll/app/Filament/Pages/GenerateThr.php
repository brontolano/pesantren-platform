<?php

namespace Modules\Payroll\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Modules\Payroll\Services\ThrService;

/**
 * WP-15 — Layar Generate THR untuk bendahara: pilih tahun → pratinjau pegawai
 * aktif + nominal per formula tenant (input per pegawai bila formula manual)
 * → tombol Generate (konfirmasi, idempoten — yang sudah punya dilewati).
 *
 * Slip THR dibuat DRAFT tanpa PPh21/BPJS otomatis; finalisasi & potongan
 * tetap lewat jalur SlipGaji existing.
 */
class GenerateThr extends Page
{
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'Buat THR';

    protected static ?string $title = 'Generate THR';

    protected static string|\UnitEnum|null $navigationGroup = 'Kepegawaian';

    protected static ?int $navigationSort = 28;

    protected static ?string $slug = 'payroll/generate-thr';

    protected string $view = 'filament.pages.generate-thr';

    public int $tahun = 2026;

    /** @var array<int, string> employee_id => nominal (input manual) */
    public array $manual = [];

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
        $this->tahun = (int) now()->format('Y');
    }

    /** @return array<int, float> hanya entri numerik (parsing toleran input) */
    private function overrides(): array
    {
        $bersih = [];
        foreach ($this->manual as $employeeId => $nominal) {
            if (is_numeric($nominal)) {
                $bersih[(int) $employeeId] = (float) $nominal;
            }
        }

        return $bersih;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Generate THR')
                ->icon('heroicon-o-gift')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(fn (): string => sprintf(
                    'Membuat slip THR DRAFT %d untuk semua pegawai aktif sesuai pratinjau. Pegawai yang sudah punya THR tahun ini DILEWATI (idempoten). Slip dibuat tanpa potongan PPh21/BPJS otomatis.',
                    $this->tahun,
                ))
                ->action(function (): void {
                    try {
                        $hasil = app(ThrService::class)->generateMassal($this->tahun, $this->overrides(), Auth::id());
                        Notification::make()->success()
                            ->title('Generate THR selesai')
                            ->body(sprintf('%d slip dibuat, %d dilewati (sudah ada), %d tanpa akun login, %d nominal 0 (tidak dibuat).',
                                $hasil['dibuat'], $hasil['dilewati'], $hasil['tanpa_akun'], $hasil['nominal_nol']))
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()->danger()->title('Generate THR gagal')->body($e->getMessage())->send();
                    }
                }),
        ];
    }

    protected function getViewData(): array
    {
        $svc = app(ThrService::class);

        return [
            'formula' => $svc->formula(),
            'baris' => $svc->pratinjau($this->tahun, $this->overrides()),
        ];
    }
}
