<?php

namespace Modules\Keuangan\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Models\TagihanRutin;

/**
 * Mesin penerbitan Tagihan Rutin (langganan SPP/makan/asrama/dll).
 *
 * IDEMPOTEN dua lapis (kritis — ini jalur uang):
 *  1. Level ATURAN: command melewati aturan yang `terakhir_terbit_periode`-nya
 *     sudah == periode (kecuali --force) → tak generate ganda saat re-run.
 *  2. Level SANTRI: tiap santri yang SUDAH punya Tagihan (jenis + bulan_masehi
 *     periode) DILEWATI → aman dipanggil ulang / untuk santri yang baru masuk.
 *
 * Penerbitan tagihan tetap lewat TagihanService (jalur posting yang sudah teruji)
 * — service ini hanya menentukan SIAPA, BERAPA, KAPAN, lalu menjaga idempotensi.
 */
class TagihanRutinService
{
    public function __construct(
        private readonly TagihanService $tagihanService = new TagihanService,
    ) {}

    /**
     * Terbitkan tagihan untuk satu aturan rutin pada satu periode (Y-m).
     *
     * @return array{aturan:int, periode:string, target:int, dibuat:int, dilewati:int}
     */
    public function terbitkan(TagihanRutin $aturan, ?string $periode = null, bool $force = false): array
    {
        $periode ??= now()->format('Y-m');

        $summary = [
            'aturan' => $aturan->id,
            'periode' => $periode,
            'target' => 0,
            'dibuat' => 0,
            'dilewati' => 0,
        ];

        // Lapis 1: aturan sudah diterbitkan utk periode ini → tak ulang (kec. force).
        if (! $force && $aturan->terakhir_terbit_periode === $periode) {
            return $summary;
        }

        $santriList = $this->resolveSantri($aturan)->get();
        $summary['target'] = $santriList->count();

        $jatuhTempo = $this->hitungJatuhTempo($aturan, $periode);
        $bulanHijriah = $this->tagihanService->hitungBulanHijriah($jatuhTempo);

        /** @var list<Tagihan> $tagihanBaru */
        $tagihanBaru = [];

        DB::transaction(function () use ($aturan, $santriList, $periode, $jatuhTempo, $bulanHijriah, &$summary, &$tagihanBaru): void {
            foreach ($santriList as $santri) {
                // Lapis 2: idempotensi per-santri (jenis + periode masehi).
                $sudahAda = Tagihan::query()
                    ->where('santri_id', $santri->id)
                    ->where('jenis_tagihan', $aturan->jenis_tagihan)
                    ->where('bulan_masehi', $periode)
                    ->exists();

                if ($sudahAda) {
                    $summary['dilewati']++;

                    continue;
                }

                $tagihanBaru[] = $this->tagihanService->buatTagihanUntukSantri($santri, [
                    'jenis_tagihan' => $aturan->jenis_tagihan,
                    'keterangan' => $aturan->keterangan ?? $aturan->nama,
                    'nominal' => (float) $aturan->nominal,
                    // #332: salin rincian komponen ke tagihan (transparansi wali).
                    'komponen' => \Modules\Keuangan\Support\SyahriahKomponen::normalize($aturan->komponen) ?: null,
                    'jatuh_tempo' => $jatuhTempo,
                    'status' => 'belum_bayar',
                    'sumber_dana_id' => $aturan->sumber_dana_id,
                    'divisi_unit_id' => $aturan->divisi_unit_id,
                    'bulan_masehi' => $periode,
                    'bulan_hijriah' => $bulanHijriah,
                ]);

                $summary['dibuat']++;
            }

            $aturan->forceFill(['terakhir_terbit_periode' => $periode])->save();
        });

        // #339 — notif WA ke wali SETELAH commit (gagal WA ≠ gagal terbit).
        // Hanya tagihan yang BARU dibuat run ini (idempoten ganda: run ulang
        // melewati tagihan existing → tak ada kirim ulang; plus penanda
        // notif_wa_sent_at di KeuanganNotifikasiService::tagihanTerbit).
        if ($aturan->notif_wa && $tagihanBaru !== []) {
            $notifikasi = app(KeuanganNotifikasiService::class);
            foreach ($tagihanBaru as $tagihan) {
                $notifikasi->tagihanTerbit($tagihan);
            }
        }

        return $summary;
    }

    /**
     * Query santri AKTIF sesuai target aturan (semua/lembaga/kelas/tingkat).
     *
     * @return Builder<Santri>
     */
    private function resolveSantri(TagihanRutin $aturan)
    {
        $query = Santri::query()->where('status', 'aktif');

        return match ($aturan->target_tipe) {
            'lembaga' => $query->whereHas('kelas', fn ($q) => $q->where('lembaga_id', $aturan->target_lembaga_id)),
            'kelas' => $query->where('kelas_id', $aturan->target_kelas_id),
            'tingkat' => $query->whereHas('kelas', fn ($q) => $q->where('tingkat', $aturan->target_tingkat)),
            default => $query, // 'semua'
        };
    }

    /**
     * Tanggal jatuh tempo = (hari ke-`tanggal_terbit` pada bulan periode) + `tempo_hari`.
     * `tanggal_terbit` di-clamp ke jumlah hari bulan tsb (cegah 31 Feb).
     */
    private function hitungJatuhTempo(TagihanRutin $aturan, string $periode): Carbon
    {
        $awal = Carbon::createFromFormat('Y-m', $periode)->startOfMonth();
        $hari = min(max((int) $aturan->tanggal_terbit, 1), $awal->daysInMonth);

        return $awal->day($hari)->addDays((int) $aturan->tempo_hari);
    }
}
