<?php

namespace Modules\PPDB\Services;

use InvalidArgumentException;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\HasilUjian;
use RuntimeException;

class PpdbExamResultService
{
    public function inputResult(CalonSantri|int $calonSantri, array $payload): HasilUjian
    {
        $this->ensureTenantContext();

        $candidate = $calonSantri instanceof CalonSantri
            ? $calonSantri
            : CalonSantri::query()->findOrFail($calonSantri);

        // KUNCI HASIL (F3): nilai tak boleh diubah setelah keputusan terbit —
        // hasil ujian adalah dasar keputusan; koreksi butuh pencabutan keputusan.
        if ($candidate->tanggal_keputusan !== null) {
            throw new InvalidArgumentException('Hasil ujian terkunci — keputusan sudah diterbitkan.');
        }

        $kategori = $this->normalizeKategori((string) ($payload['kategori_ujian'] ?? ''));
        $nilai = $this->normalizeNilai($payload['nilai'] ?? null);

        return HasilUjian::query()->updateOrCreate(
            [
                'calon_santri_id' => $candidate->id,
                'kategori_ujian' => $kategori,
            ],
            [
                'nilai' => $nilai,
                'catatan' => $payload['catatan'] ?? null,
            ]
        );
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk input hasil ujian PPDB.');
        }
    }

    private function normalizeKategori(string $kategori): string
    {
        $normalized = trim(strtolower(str_replace(['-', ' '], '_', $kategori)));

        if ($normalized === '') {
            throw new InvalidArgumentException('Kategori ujian wajib diisi.');
        }

        return $normalized;
    }

    private function normalizeNilai(mixed $nilai): float
    {
        if (! is_numeric($nilai)) {
            throw new InvalidArgumentException('Nilai ujian harus berupa angka.');
        }

        $numeric = round((float) $nilai, 2);

        if ($numeric < 0 || $numeric > 100) {
            throw new InvalidArgumentException('Nilai ujian harus berada pada rentang 0 sampai 100.');
        }

        return $numeric;
    }
}
