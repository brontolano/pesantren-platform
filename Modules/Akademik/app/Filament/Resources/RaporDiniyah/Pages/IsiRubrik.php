<?php

namespace Modules\Akademik\Filament\Resources\RaporDiniyah\Pages;

use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Modules\Akademik\Filament\Resources\RaporDiniyah\RaporDiniyahResource;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\RubrikKriteria;
use Modules\Akademik\Models\RubrikSkor;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Modules\Tahfidz\Models\Halaqah;

/**
 * WP-10 — Input massal skor rubrik ubudiyah/akhlak: grid semua santri satu
 * kelas/halaqah × kriteria rubrik aktif (reuse RubrikKriteria G3-18).
 * Pola layar mengikuti IsiPresensi (Kepengasuhan).
 */
class IsiRubrik extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = RaporDiniyahResource::class;

    protected string $view = 'filament.pages.isi-rubrik-diniyah';

    protected static ?string $title = 'Isi Rubrik Ubudiyah & Akhlak';

    public ?int $periode_akademik_id = null;

    public ?string $mode = 'kelas';

    public ?int $kelas_id = null;

    public ?int $halaqah_id = null;

    /** @var array<int, array{santri_id:int,nama:string,skor:array<int|string, mixed>}> */
    public array $dataSantri = [];

    /** @var array<int, array{id:int,nama:string,bobot:float}> */
    public array $kriteria = [];

    public function mount(): void
    {
        $this->periode_akademik_id = PeriodeAkademik::query()->where('is_active', true)->value('id');
        $this->loadGrid();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('periode_akademik_id')
                ->label('Periode Akademik')
                ->options(fn () => PeriodeAkademik::query()->orderByDesc('tanggal_mulai')->pluck('nama', 'id')->all())
                ->required()
                ->live()
                ->afterStateUpdated(fn () => $this->loadGrid()),
            Select::make('mode')
                ->options(['kelas' => 'Per Kelas', 'halaqah' => 'Per Halaqah'])
                ->default('kelas')
                ->live()
                ->afterStateUpdated(fn () => $this->loadGrid()),
            Select::make('kelas_id')
                ->label('Kelas')
                ->options(fn () => Kelas::query()->where('is_active', true)->orderBy('nama_kelas')->pluck('nama_kelas', 'id')->all())
                ->searchable()
                ->visible(fn ($get) => $get('mode') === 'kelas')
                ->live()
                ->afterStateUpdated(fn () => $this->loadGrid()),
            Select::make('halaqah_id')
                ->label('Halaqah')
                ->options(fn () => Halaqah::query()->where('aktif', true)->pluck('nama', 'id')->all())
                ->searchable()
                ->visible(fn ($get) => $get('mode') === 'halaqah')
                ->live()
                ->afterStateUpdated(fn () => $this->loadGrid()),
        ]);
    }

    public function loadGrid(): void
    {
        $this->kriteria = [];
        $this->dataSantri = [];

        if (! $this->periode_akademik_id) {
            return;
        }

        // Kriteria aktif: spesifik periode ini ATAU tanpa ikatan periode.
        $kriteriaList = RubrikKriteria::query()
            ->aktif()
            ->where(fn ($q) => $q
                ->where('periode_akademik_id', $this->periode_akademik_id)
                ->orWhereNull('periode_akademik_id'))
            ->orderBy('nama')
            ->get();

        $this->kriteria = $kriteriaList
            ->map(fn (RubrikKriteria $k): array => ['id' => $k->id, 'nama' => $k->nama, 'bobot' => (float) $k->bobot])
            ->values()
            ->all();

        $santriList = collect();
        if ($this->mode === 'kelas' && $this->kelas_id) {
            $santriList = Santri::query()
                ->where('kelas_id', $this->kelas_id)
                ->where('status', 'aktif')
                ->orderBy('nama')
                ->get();
        } elseif ($this->mode === 'halaqah' && $this->halaqah_id) {
            $santriList = Halaqah::query()->find($this->halaqah_id)?->santriAktif ?? collect();
        }

        if ($santriList->isEmpty() || $kriteriaList->isEmpty()) {
            $this->dataSantri = [];

            return;
        }

        $existing = RubrikSkor::query()
            ->whereIn('santri_id', $santriList->pluck('id'))
            ->where('periode_akademik_id', $this->periode_akademik_id)
            ->whereIn('kriteria_id', $kriteriaList->pluck('id'))
            ->get()
            ->groupBy('santri_id');

        $this->dataSantri = $santriList->map(function (Santri $s) use ($kriteriaList, $existing): array {
            $skorSantri = $existing->get($s->id, collect())->keyBy('kriteria_id');

            $skor = [];
            foreach ($kriteriaList as $k) {
                $val = $skorSantri->get($k->id)?->skor;
                $skor[$k->id] = $val === null ? '' : (string) (float) $val;
            }

            return ['santri_id' => $s->id, 'nama' => $s->nama, 'skor' => $skor];
        })->values()->all();
    }

    public function simpan(): void
    {
        if (! $this->periode_akademik_id || empty($this->dataSantri)) {
            Notification::make()->title('Pilih periode dan kelas/halaqah terlebih dahulu.')->danger()->send();

            return;
        }

        // Validasi SEMUA dulu — "abc" jangan diam-diam jadi 0 di dokumen resmi.
        foreach ($this->dataSantri as $row) {
            foreach ($row['skor'] as $skor) {
                if ($skor === '' || $skor === null) {
                    continue; // kosong = tidak dinilai, jangan timpa.
                }
                if (! is_numeric($skor) || (float) $skor < 0 || (float) $skor > 100) {
                    Notification::make()->title('Skor harus angka 0–100.')->danger()->send();

                    return;
                }
            }
        }

        // Satu transaksi: gagal di tengah tidak meninggalkan grid setengah-tersimpan.
        $tersimpan = DB::transaction(function (): int {
            $jumlah = 0;
            foreach ($this->dataSantri as $row) {
                foreach ($row['skor'] as $kriteriaId => $skor) {
                    if ($skor === '' || $skor === null) {
                        continue;
                    }

                    RubrikSkor::query()->updateOrCreate([
                        'santri_id' => $row['santri_id'],
                        'kriteria_id' => (int) $kriteriaId,
                        'periode_akademik_id' => $this->periode_akademik_id,
                    ], ['skor' => (float) $skor]);
                    $jumlah++;
                }
            }

            return $jumlah;
        });

        Notification::make()->title("{$tersimpan} skor rubrik tersimpan.")->success()->send();
    }
}
