<?php

namespace Modules\Tahfidz\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Kesantrian\Models\Santri;
use Modules\Tahfidz\Models\HafalanTahfidz;
use Modules\Tahfidz\Models\PenilaianTahfidz;

class TahfidzService
{
    // ── Hafalan ─────────────────────────────────────────────────────────────

    public function createHafalan(Santri $santri, array $data): HafalanTahfidz
    {
        return HafalanTahfidz::create([
            'tenant_id' => $santri->tenant_id,
            'santri_id' => $santri->getKey(),
            'surah' => $data['surah'] ?? null,
            'ayat_awal' => $data['ayat_awal'] ?? null,
            'ayat_akhir' => $data['ayat_akhir'] ?? null,
            'tanggal_target' => $data['tanggal_target'] ?? null,
            'status' => $data['status'] ?? 'direncanakan',
            'catatan' => $data['catatan'] ?? null,
        ]);
    }

    public function updateHafalan(HafalanTahfidz $hafalan, array $data): HafalanTahfidz
    {
        $hafalan->update(array_filter([
            'surah' => $data['surah'] ?? $hafalan->surah,
            'ayat_awal' => $data['ayat_awal'] ?? $hafalan->ayat_awal,
            'ayat_akhir' => $data['ayat_akhir'] ?? $hafalan->ayat_akhir,
            'tanggal_target' => $data['tanggal_target'] ?? $hafalan->tanggal_target,
            'tanggal_selesai' => $data['tanggal_selesai'] ?? $hafalan->tanggal_selesai,
            'status' => $data['status'] ?? $hafalan->status,
            'catatan' => $data['catatan'] ?? $hafalan->catatan,
        ], fn ($v) => $v !== null));

        return $hafalan->fresh();
    }

    public function selesaikanHafalan(HafalanTahfidz $hafalan): HafalanTahfidz
    {
        $hafalan->update([
            'status' => 'selesai',
            'tanggal_selesai' => now()->toDateString(),
        ]);

        return $hafalan->fresh();
    }

    public function getSantriHafalan(Santri $santri): Collection
    {
        return HafalanTahfidz::query()
            ->where('santri_id', $santri->getKey())
            ->with('penilaian')
            ->orderByDesc('tanggal_target')
            ->get();
    }

    public function getRingkasanHafalan(Santri $santri): array
    {
        $hafalan = HafalanTahfidz::query()
            ->where('santri_id', $santri->getKey())
            ->get();

        return [
            'total' => $hafalan->count(),
            'selesai' => $hafalan->where('status', 'selesai')->count(),
            'direncanakan' => $hafalan->where('status', 'direncanakan')->count(),
            'berjalan' => $hafalan->where('status', 'berjalan')->count(),
        ];
    }

    // ── Setoran PWA Guru (F1-PWA) ────────────────────────────────────────────

    /**
     * Setoran hafalan dari PWA Guru: tulis HafalanTahfidz (status selesai,
     * halaqah_id) + PenilaianTahfidz (jenis ziyadah|murajaah, nilai, input_by)
     * dalam SATU transaksi.
     *
     * Idempoten via client_ref (uuid dari klien, pola WP-32): retry dengan
     * client_ref sama mengembalikan hafalan yang sudah tercatat — unique index
     * (tenant_id, client_ref) di DB adalah gerbang dedup race-safe.
     */
    public function setor(Santri $santri, array $data, ?int $inputBy = null): HafalanTahfidz
    {
        $clientRef = isset($data['client_ref']) && $data['client_ref'] !== ''
            ? (string) $data['client_ref']
            : null;

        if ($clientRef !== null) {
            $existing = HafalanTahfidz::query()->where('client_ref', $clientRef)->first();
            if ($existing !== null) {
                return $existing->load('penilaian');
            }
        }

        try {
            return DB::transaction(function () use ($santri, $data, $inputBy, $clientRef): HafalanTahfidz {
                $hafalan = HafalanTahfidz::create([
                    'tenant_id' => $santri->tenant_id,
                    'santri_id' => $santri->getKey(),
                    'halaqah_id' => $data['halaqah_id'] ?? null,
                    'surah' => $data['surah'],
                    'ayat_awal' => $data['ayat_awal'] ?? null,
                    'ayat_akhir' => $data['ayat_akhir'] ?? null,
                    'status' => 'selesai',
                    'tanggal_selesai' => $data['tanggal'] ?? now()->toDateString(),
                    'catatan' => $data['catatan'] ?? null,
                    'client_ref' => $clientRef,
                ]);

                $this->addPenilaian($hafalan, [
                    'jenis' => $data['jenis'] ?? 'murajaah',
                    'nilai' => $data['nilai'] ?? null,
                    'catatan' => $data['catatan'] ?? null,
                    'tanggal' => $data['tanggal'] ?? now()->toDateString(),
                    'input_by' => $inputBy,
                ]);

                return $hafalan->load('penilaian');
            });
        } catch (QueryException $e) {
            // Race retry: dua request paralel dengan client_ref sama — yang
            // kalah unique index mengambil baris pemenang, bukan error 500.
            if ($clientRef !== null) {
                $existing = HafalanTahfidz::query()->where('client_ref', $clientRef)->first();
                if ($existing !== null) {
                    return $existing->load('penilaian');
                }
            }

            throw $e;
        }
    }

    // ── Penilaian ────────────────────────────────────────────────────────────

    public function addPenilaian(HafalanTahfidz $hafalan, array $data): PenilaianTahfidz
    {
        return PenilaianTahfidz::create([
            'tenant_id' => $hafalan->tenant_id,
            'santri_id' => $hafalan->santri_id,
            'hafalan_id' => $hafalan->getKey(),
            'jenis' => $data['jenis'] ?? 'murajaah',
            'nilai' => $data['nilai'] ?? null,
            'catatan' => $data['catatan'] ?? null,
            'tanggal' => $data['tanggal'] ?? now()->toDateString(),
            'input_by' => $data['input_by'] ?? null,
        ]);
    }

    public function getRataRataNilai(Santri $santri): ?float
    {
        $avg = PenilaianTahfidz::query()
            ->where('santri_id', $santri->getKey())
            ->whereNotNull('nilai')
            ->avg('nilai');

        return $avg !== null ? round((float) $avg, 2) : null;
    }
}
