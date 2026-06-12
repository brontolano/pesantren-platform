<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Modules\Akademik\Models\KeputusanKenaikan;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Services\KenaikanKelasService;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;

/**
 * WP-07 — Layar kerja Sidang Kenaikan: pilih kelas + periode → evaluasi
 * massal (rekomendasi mesin) → rapat meng-override keputusan + catatan →
 * simpan draft → finalisasi (satu transaksi, idempoten).
 *
 * Didaftar EKSPLISIT di TenantPanelProvider (pola WP layar kerja).
 */
class SidangKenaikan extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik & Tahfidz';

    protected static ?int $navigationSort = 40;

    protected string $view = 'filament.pages.sidang-kenaikan';

    public ?int $kelas_id = null;

    public ?int $periode_id = null;

    /** @var array<int, array<string, mixed>> */
    public array $baris = [];

    /** @var array<int|string, string> */
    public array $opsiTujuan = [];

    public string $modePenempatan = 'berjenjang';

    public bool $sudahFinal = false;

    public static function getNavigationLabel(): string
    {
        return 'Sidang Kenaikan';
    }

    public function getTitle(): string
    {
        return 'Sidang Kenaikan Kelas';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canAny(['manage_santri', 'view_santri']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getSubheading(): ?string
    {
        return 'Mesin hanya MEREKOMENDASIKAN — keputusan akhir di tangan rapat. Mode penempatan: '
            .app(KenaikanKelasService::class)->modePenempatan().'.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('kelas_id')
                ->label('Kelas')
                ->options(fn () => Kelas::query()->where('is_active', true)
                    ->orderBy('tingkat')->orderBy('nama_kelas')
                    ->get()
                    ->mapWithKeys(fn (Kelas $k) => [$k->id => $k->nama_kelas.' (tingkat '.$k->tingkat.')'])
                    ->all())
                ->searchable()
                ->live()
                ->afterStateUpdated(fn () => $this->muatEvaluasi()),
            Select::make('periode_id')
                ->label('Periode Akademik')
                ->options(fn () => PeriodeAkademik::query()
                    ->orderByDesc('tanggal_mulai')
                    ->pluck('nama', 'id')->all())
                ->searchable()
                ->live()
                ->afterStateUpdated(fn () => $this->muatEvaluasi()),
        ])->columns(2);
    }

    public function muatEvaluasi(): void
    {
        $this->baris = [];
        $this->opsiTujuan = [];
        $this->sudahFinal = false;

        $kelas = $this->kelas_id ? Kelas::find($this->kelas_id) : null;
        $periode = $this->periode_id ? PeriodeAkademik::find($this->periode_id) : null;

        if (! $kelas || ! $periode) {
            return;
        }

        $svc = app(KenaikanKelasService::class);
        $this->modePenempatan = $svc->modePenempatan();
        $this->opsiTujuan = $svc->kelasTujuanOptions($kelas)
            ->mapWithKeys(fn (Kelas $k) => [$k->id => $k->nama_kelas.' (tingkat '.$k->tingkat.')'])
            ->all();
        $tujuanDefault = array_key_first($this->opsiTujuan);

        $tersimpan = KeputusanKenaikan::query()
            ->where('periode_akademik_id', $periode->id)
            ->where('kelas_asal_id', $kelas->id)
            ->get()
            ->keyBy('santri_id');

        $santriList = Santri::query()
            ->where('kelas_id', $kelas->id)
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->get();

        $this->baris = $santriList->map(function (Santri $s) use ($svc, $periode, $tersimpan, $tujuanDefault) {
            $lama = $tersimpan->get($s->id);

            if ($lama && $lama->finalized_at !== null) {
                $this->sudahFinal = true;
            }

            $hasil = $lama
                ? ['rekomendasi' => $lama->rekomendasi, 'alasan' => $lama->alasan ?? []]
                : $svc->evaluasi($s, $periode);

            return [
                'santri_id' => $s->id,
                'nama' => $s->nama,
                'nis' => $s->nis,
                'rekomendasi' => $hasil['rekomendasi'],
                'alasan' => implode(' ', $hasil['alasan']),
                'keputusan' => $lama->keputusan ?? $hasil['rekomendasi'],
                'kelas_tujuan_id' => $lama->kelas_tujuan_id ?? ($hasil['rekomendasi'] === 'naik' ? $tujuanDefault : null),
                'catatan' => $lama->catatan ?? '',
                'final' => (bool) ($lama?->finalized_at),
            ];
        })->values()->all();
    }

    /**
     * Aksi mutasi (draft/finalisasi) butuh manage_santri — canAccess() yang
     * mengizinkan view_santri hanya untuk MELIHAT layar (BLOCKER adversarial).
     */
    private function authorizeMutation(): void
    {
        abort_unless(auth()->user()?->can('manage_santri') ?? false, 403);
    }

    public function simpanDraft(): bool
    {
        $this->authorizeMutation();

        if (! $this->kelas_id || ! $this->periode_id || $this->baris === []) {
            Notification::make()->title('Pilih kelas & periode terlebih dahulu.')->danger()->send();

            return false;
        }

        $kelas = Kelas::find($this->kelas_id);
        $periode = PeriodeAkademik::find($this->periode_id);

        if (! $kelas || ! $periode) {
            Notification::make()->title('Kelas/periode tidak ditemukan.')->danger()->send();

            return false;
        }

        $svc = app(KenaikanKelasService::class);

        // Validasi server-side: tujuan harus anggota opsi sah kelas asal,
        // rekomendasi diambil dari server (baris tersimpan / evaluasi ulang) —
        // payload Livewire bisa dipalsukan klien (MAJOR adversarial).
        $tujuanSah = $svc->kelasTujuanOptions($kelas)->pluck('id')->all();
        $tersimpanLama = KeputusanKenaikan::query()
            ->where('periode_akademik_id', $periode->id)
            ->where('kelas_asal_id', $kelas->id)
            ->get()
            ->keyBy('santri_id');

        $tersimpan = 0;

        try {
            foreach ($this->baris as $b) {
                if ($b['final']) {
                    continue; // baris final tak boleh diubah
                }

                $tujuanId = $b['kelas_tujuan_id'] !== null && $b['kelas_tujuan_id'] !== ''
                    ? (int) $b['kelas_tujuan_id'] : null;

                if ($tujuanId !== null && ! in_array($tujuanId, $tujuanSah, true)) {
                    throw new \RuntimeException('Kelas tujuan tidak sah untuk kelas asal ini.');
                }

                $santri = Santri::query()->where('kelas_id', $kelas->id)->find($b['santri_id']);
                if (! $santri) {
                    continue; // santri bukan anggota kelas ini — abaikan baris palsu
                }

                $lama = $tersimpanLama->get($santri->id);
                $rekomendasi = $lama->rekomendasi
                    ?? $svc->evaluasi($santri, $periode)['rekomendasi'];

                $svc->simpanDraft([
                    'santri_id' => $santri->id,
                    'periode_akademik_id' => $this->periode_id,
                    'kelas_asal_id' => $this->kelas_id,
                    'kelas_tujuan_id' => $tujuanId,
                    'rekomendasi' => $rekomendasi,
                    'keputusan' => $b['keputusan'],
                    'alasan' => $b['alasan'] !== '' ? [$b['alasan']] : [],
                    'catatan' => $b['catatan'] !== '' ? $b['catatan'] : null,
                ], auth()->user());

                $tersimpan++;
            }
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return false;
        }

        Notification::make()->title("Draft keputusan tersimpan ({$tersimpan} santri).")->success()->send();
        $this->muatEvaluasi();

        return true;
    }

    public function finalisasi(): void
    {
        $this->authorizeMutation();

        $kelas = $this->kelas_id ? Kelas::find($this->kelas_id) : null;
        $periode = $this->periode_id ? PeriodeAkademik::find($this->periode_id) : null;

        if (! $kelas || ! $periode) {
            Notification::make()->title('Pilih kelas & periode terlebih dahulu.')->danger()->send();

            return;
        }

        // Pastikan draft terbaru tersimpan dulu; gagal simpan = STOP —
        // jangan finalisasi nilai basi (MAJOR adversarial).
        if (! $this->simpanDraft()) {
            return;
        }

        try {
            $jumlah = app(KenaikanKelasService::class)->finalisasi($periode, $kelas, auth()->user());
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()
            ->title($jumlah > 0
                ? "Finalisasi selesai — {$jumlah} keputusan dieksekusi."
                : 'Tidak ada keputusan baru untuk difinalisasi (sudah final).')
            ->success()
            ->send();

        $this->muatEvaluasi();
    }
}
