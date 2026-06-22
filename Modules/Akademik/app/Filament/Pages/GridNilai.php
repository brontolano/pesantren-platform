<?php

namespace Modules\Akademik\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Services\NilaiGridService;
use Modules\Kesantrian\Models\Kelas;

/**
 * F2-PWA — Halaman KEMBAR grid nilai di panel (guru ber-laptop).
 * DUA pintu SATU jalur persist: NilaiGridService::simpanBaris — sama persis
 * dengan PWA Guru (/guru/nilai). JANGAN tambah jalur tulis nilai lain.
 *
 * Didaftar EKSPLISIT di TenantPanelProvider (pola WP layar kerja).
 */
class GridNilai extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik & Tahfidz';

    protected static ?int $navigationSort = 35;

    protected string $view = 'filament.pages.grid-nilai';

    public ?int $kelas_id = null;

    public ?int $pelajaran_id = null;

    public ?int $periode_id = null;

    /** @var array<int, array<string, mixed>> */
    public array $baris = [];

    /** @var array<int, array<string, mixed>> */
    public array $komponen = [];

    public static function getNavigationLabel(): string
    {
        return 'Grid Nilai';
    }

    public function getTitle(): string
    {
        return 'Grid Nilai Massal';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_akademik') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getSubheading(): ?string
    {
        return 'Pilih kelas × pelajaran × periode → isi nilai per komponen. Validasi 0-100 & rumus nilai akhir di server (NilaiService).';
    }

    public function mount(): void
    {
        $this->periode_id = PeriodeAkademik::query()
            ->where('is_active', true)
            ->orderByDesc('tanggal_mulai')
            ->value('id');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('kelas_id')
                ->label('Kelas')
                ->options(fn () => Kelas::query()->where('is_active', true)
                    ->orderBy('tingkat')->orderBy('nama_kelas')
                    ->pluck('nama_kelas', 'id')->all())
                ->searchable()
                ->live()
                ->afterStateUpdated(fn () => $this->muatGrid()),
            Select::make('pelajaran_id')
                ->label('Pelajaran')
                ->options(function () {
                    $q = Pelajaran::query()->orderBy('nama');

                    if ($this->kelas_id) {
                        $ids = JadwalPelajaran::query()
                            ->where('kelas_id', $this->kelas_id)
                            ->pluck('pelajaran_id');
                        $q->whereIn('id', $ids);
                    }

                    return $q->pluck('nama', 'id')->all();
                })
                ->searchable()
                ->live()
                ->afterStateUpdated(fn () => $this->muatGrid()),
            Select::make('periode_id')
                ->label('Periode Akademik')
                ->options(fn () => PeriodeAkademik::query()
                    ->orderByDesc('tanggal_mulai')
                    ->pluck('nama', 'id')->all())
                ->live()
                ->afterStateUpdated(fn () => $this->muatGrid()),
        ])->columns(3);
    }

    public function muatGrid(): void
    {
        $this->baris = [];
        $this->komponen = [];

        if (! $this->kelas_id || ! $this->pelajaran_id || ! $this->periode_id) {
            return;
        }

        $grid = app(NilaiGridService::class)->dataGrid(
            (int) $this->kelas_id,
            (int) $this->pelajaran_id,
            (int) $this->periode_id,
        );

        $this->komponen = $grid['komponen'];
        $this->baris = $grid['baris'];
    }

    public function simpanSemua(): void
    {
        if (! $this->kelas_id || ! $this->pelajaran_id || ! $this->periode_id || $this->baris === []) {
            Notification::make()->title('Pilih kelas, pelajaran, dan periode terlebih dahulu.')->danger()->send();

            return;
        }

        $svc = app(NilaiGridService::class);
        $tersimpan = 0;
        $gagal = [];

        foreach ($this->baris as $b) {
            $komponenBersih = array_filter(
                $b['nilai_komponen'] ?? [],
                fn ($v): bool => $v !== null && $v !== '',
            );

            $adaIsi = $komponenBersih !== []
                || ($b['nilai_harian'] ?? null) !== null && ($b['nilai_harian'] ?? '') !== ''
                || ($b['nilai_uts'] ?? null) !== null && ($b['nilai_uts'] ?? '') !== ''
                || ($b['nilai_uas'] ?? null) !== null && ($b['nilai_uas'] ?? '') !== '';

            if (! $adaIsi) {
                continue; // baris kosong — jangan tulis nilai 0 palsu.
            }

            try {
                $nilai = $svc->simpanBaris([
                    'santri_id' => $b['santri_id'],
                    'pelajaran_id' => $this->pelajaran_id,
                    'periode_akademik_id' => $this->periode_id,
                    'kelas_id' => $this->kelas_id,
                    'nilai_komponen' => $komponenBersih === [] ? null : $komponenBersih,
                    'nilai_harian' => blank($b['nilai_harian'] ?? null) ? null : $b['nilai_harian'],
                    'nilai_uts' => blank($b['nilai_uts'] ?? null) ? null : $b['nilai_uts'],
                    'nilai_uas' => blank($b['nilai_uas'] ?? null) ? null : $b['nilai_uas'],
                ], auth()->user());

                $tersimpan++;
            } catch (ValidationException $e) {
                $gagal[] = $b['nama'].': '.collect($e->errors())->flatten()->first();
            }
        }

        $this->muatGrid();

        if ($gagal !== []) {
            Notification::make()
                ->title("Tersimpan {$tersimpan} baris, gagal ".count($gagal).'.')
                ->body(implode(' · ', array_slice($gagal, 0, 5)))
                ->warning()
                ->send();

            return;
        }

        Notification::make()->title("Tersimpan {$tersimpan} baris nilai.")->success()->send();
    }
}
