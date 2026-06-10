<?php

namespace Modules\Asrama\Services;

use Modules\Asrama\Models\KamarAsrama;
use Modules\Asrama\Models\PenempatanAsrama;
use Modules\Kesantrian\Models\Santri;
use RuntimeException;

/**
 * G3-22 — Penempatan kamar asrama OTOMATIS (di atas PenempatanAsramaService teruji
 * yang TIDAK diubah). Aturan keras:
 *   - GENDER: santri putra → blok putra; putri → blok putri (blok 'campur'/null
 *     = tanpa batasan). TIDAK pernah menempatkan lintas-gender.
 *   - KAPASITAS: hanya kamar dengan sisa > 0 (anti-overflow; guard kedua di
 *     PenempatanAsramaService::tempatkan).
 *   - IDEMPOTEN: santri yang SUDAH punya penempatan aktif DILEWATI (re-run aman).
 *   - GROUPING: utamakan kamar berisi santri seangkatan.
 */
class PenempatanOtomatisService
{
    public function __construct(private readonly PenempatanAsramaService $penempatan)
    {
    }

    /**
     * Tempatkan santri yang belum punya kamar aktif.
     *
     * @param  array{tahun_angkatan?:int|string, gender?:string}  $opts
     * @return array{ditempatkan:int, gagal:int, detail:array<int, array<string,mixed>>}
     */
    public function tempatkanOtomatis(array $opts = []): array
    {
        $this->ensureTenantContext();

        $sudahDitempatkan = PenempatanAsrama::query()->where('is_current', true)->pluck('santri_id')->all();

        $kandidat = Santri::query()
            ->where('status', 'aktif')
            ->whereIn('jenis_kelamin', ['L', 'P']) // gender tak valid → tak ditempatkan (anti fail-silent)
            ->whereNotIn('id', $sudahDitempatkan)
            ->when(isset($opts['tahun_angkatan']), fn ($q) => $q->where('tahun_angkatan', $opts['tahun_angkatan']))
            ->when(isset($opts['gender']), fn ($q) => $q->where('jenis_kelamin', $opts['gender'] === 'putri' ? 'P' : 'L'))
            ->orderBy('tahun_angkatan')
            ->orderBy('id')
            ->get();

        $hasil = ['ditempatkan' => 0, 'gagal' => 0, 'detail' => []];

        foreach ($kandidat as $santri) {
            $kamar = $this->cariKamar($santri);
            if ($kamar === null) {
                $hasil['gagal']++;
                $hasil['detail'][] = ['santri_id' => $santri->id, 'status' => 'tak_ada_kamar'];

                continue;
            }
            try {
                $this->penempatan->tempatkan($santri, $kamar); // reuse guard kapasitas + is_current
                $hasil['ditempatkan']++;
                $hasil['detail'][] = ['santri_id' => $santri->id, 'kamar_id' => $kamar->id, 'status' => 'ditempatkan'];
            } catch (\Throwable $e) {
                $hasil['gagal']++;
                $hasil['detail'][] = ['santri_id' => $santri->id, 'status' => 'gagal', 'pesan' => $e->getMessage()];
            }
        }

        return $hasil;
    }

    /** Cari kamar cocok: gender match + sisa kapasitas; utamakan seangkatan. */
    private function cariKamar(Santri $santri): ?KamarAsrama
    {
        $gender = ($santri->jenis_kelamin === 'P') ? 'putri' : 'putra';

        $kamarList = KamarAsrama::query()
            ->where('is_active', true)
            ->whereHas('blok', fn ($q) => $q->where('is_active', true)
                ->where(fn ($q2) => $q2->whereIn('gender', [$gender, 'campur'])->orWhereNull('gender')))
            ->withCount(['penempatanAktif as terisi'])
            ->get()
            ->filter(fn (KamarAsrama $k): bool => (int) $k->terisi < (int) $k->kapasitas)
            ->values();

        if ($kamarList->isEmpty()) {
            return null;
        }

        // Utamakan kamar yang sudah berisi santri seangkatan (grouping).
        $seangkatan = $kamarList->first(function (KamarAsrama $k) use ($santri): bool {
            return PenempatanAsrama::query()
                ->where('kamar_id', $k->id)
                ->where('is_current', true)
                ->whereHas('santri', fn ($q) => $q->where('tahun_angkatan', $santri->tahun_angkatan))
                ->exists();
        });
        if ($seangkatan !== null) {
            return $seangkatan;
        }

        // Selain itu: kamar dengan sisa kapasitas terbanyak (pengisian merata).
        return $kamarList->sortByDesc(fn (KamarAsrama $k): int => (int) $k->kapasitas - (int) $k->terisi)->first();
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
