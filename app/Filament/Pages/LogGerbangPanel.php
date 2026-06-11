<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Modules\Kegiatan\Models\LogGerbang;

/**
 * WP-29 — Log Gerbang: tabel keluar/masuk santri ber-filter tanggal/arah/
 * tanpa-izin. Grup Kesantrian (domain Keamanan).
 *
 * Didaftar EKSPLISIT di TenantPanelProvider (pola WP layar kerja).
 */
class LogGerbangPanel extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Kesantrian';

    protected static ?int $navigationSort = 60;

    protected static ?string $slug = 'log-gerbang';

    protected string $view = 'filament.pages.log-gerbang';

    public string $tanggal = '';

    public string $arah = ''; // '' = semua | keluar | masuk

    public bool $hanyaTanpaIzin = false;

    /** @var array<int, array<string, mixed>> */
    public array $logs = [];

    public static function getNavigationLabel(): string
    {
        return 'Log Gerbang';
    }

    public function getTitle(): string
    {
        return 'Log Gerbang';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canAny(['manage_santri', 'view_santri']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->tanggal = now()->format('Y-m-d');
        $this->muatLog();
    }

    public function updatedTanggal(): void
    {
        $this->muatLog();
    }

    public function updatedArah(): void
    {
        $this->muatLog();
    }

    public function updatedHanyaTanpaIzin(): void
    {
        $this->muatLog();
    }

    public function muatLog(): void
    {
        $this->logs = [];

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->tanggal) !== 1) {
            return;
        }

        $query = LogGerbang::query()
            ->with(['santri:id,nis,nama', 'petugas:id,name'])
            ->whereDate('waktu', $this->tanggal)
            ->orderByDesc('waktu');

        if (in_array($this->arah, ['keluar', 'masuk'], true)) {
            $query->where('arah', $this->arah);
        }

        if ($this->hanyaTanpaIzin) {
            $query->where('is_tanpa_izin', true);
        }

        $this->logs = $query->limit(200)->get()->map(fn (LogGerbang $log): array => [
            'waktu' => $log->waktu->format('H:i'),
            'nama' => $log->santri?->nama ?? '-',
            'nis' => (string) ($log->santri?->nis ?? ''),
            'arah' => $log->arah,
            'is_tanpa_izin' => $log->is_tanpa_izin,
            'petugas' => $log->petugas?->name ?? '-',
            'catatan' => $log->catatan,
        ])->all();
    }
}
