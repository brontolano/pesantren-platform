<?php

namespace Modules\Kesantrian\Services;

use Modules\Akademik\Models\Lembaga;
use Modules\Kesantrian\Models\Kelas;

/**
 * Template kesetaraan siap-pakai (#338): memetakan kelas internal berurutan
 * ke jenjang formal PKPPS/SPM — Ula 1-6, Wustha 1-3, Ulya 1-3.
 */
class KesetaraanTemplateService
{
    /**
     * Terapkan template pada kelas aktif sebuah lembaga (null = kelas tanpa lembaga /
     * lembaga default tenant). Bila Lembaga.jenjang sudah baku (ula/wustha/ulya),
     * kelas diisi jenjang itu tingkat 1..N; bila tidak, kelas diurutkan menyusuri
     * seluruh tangga Ula 1-6 → Wustha 1-3 → Ulya 1-3.
     *
     * @param  bool  $timpa  true = timpa pemetaan lama; false = hanya isi yang kosong
     * @return int jumlah kelas yang terpetakan
     */
    public function terapkan(?int $lembagaId = null, bool $timpa = false): int
    {
        $kelas = Kelas::query()
            ->where('is_active', true)
            ->when(
                $lembagaId === null,
                fn ($q) => $q->whereNull('lembaga_id'),
                fn ($q) => $q->where('lembaga_id', $lembagaId),
            )
            ->orderBy('nama_kelas')
            ->get()
            // Urut numerik dari teks tingkat ("7", "1 Ibtidaiyah") — di PHP agar portabel lintas DB.
            ->sortBy([
                fn (Kelas $a, Kelas $b) => $this->angkaTingkat($a->tingkat) <=> $this->angkaTingkat($b->tingkat),
                fn (Kelas $a, Kelas $b) => strnatcasecmp((string) $a->nama_kelas, (string) $b->nama_kelas),
            ])
            ->values();

        if ($kelas->isEmpty()) {
            return 0;
        }

        $slots = $this->slotUntuk($lembagaId);
        $terpetakan = 0;

        foreach ($kelas as $i => $k) {
            if (! isset($slots[$i])) {
                break; // kelas melebihi tangga template — sisanya dibiarkan
            }
            if (! $timpa && $k->jenjang_kesetaraan !== null) {
                continue;
            }
            [$jenjang, $tingkat] = $slots[$i];
            $k->update([
                'jenjang_kesetaraan' => $jenjang,
                'tingkat_kesetaraan' => $tingkat,
            ]);
            $terpetakan++;
        }

        return $terpetakan;
    }

    /** Ambil angka pertama dari teks tingkat; tanpa angka = paling belakang. */
    private function angkaTingkat(?string $tingkat): int
    {
        return preg_match('/\d+/', (string) $tingkat, $m) ? (int) $m[0] : PHP_INT_MAX;
    }

    /** @return array<int, array{0: string, 1: int}> */
    private function slotUntuk(?int $lembagaId): array
    {
        $jenjangLembaga = $lembagaId === null
            ? null
            : Kelas::normalisasiJenjang(Lembaga::query()->whereKey($lembagaId)->value('jenjang'));

        $slots = [];
        $daftar = $jenjangLembaga !== null
            ? [$jenjangLembaga => Kelas::JENJANG_KESETARAAN[$jenjangLembaga]]
            : Kelas::JENJANG_KESETARAAN;

        foreach ($daftar as $jenjang => $jumlah) {
            for ($t = 1; $t <= $jumlah; $t++) {
                $slots[] = [$jenjang, $t];
            }
        }

        return $slots;
    }
}
