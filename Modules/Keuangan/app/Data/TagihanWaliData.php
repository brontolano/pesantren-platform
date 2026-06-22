<?php

declare(strict_types=1);

namespace Modules\Keuangan\Data;

use Modules\Keuangan\Models\Tagihan;

/**
 * KONTRAK: bentuk satu tagihan saat dikirim ke Portal Wali (frontend).
 *
 * Ini "endpoint sebagai kontrak" dalam bentuk objek: satu-satunya sumber kebenaran
 * bentuk data tagihan untuk frontend. Cermin TypeScript-nya ada di
 * resources/js/types/wali.d.ts (TagihanWali). Kalau menambah/ubah field di sini,
 * ubah juga di .d.ts itu — keduanya satu pasang.
 *
 * Sengaja plain-PHP (tanpa paket eksternal) supaya bisa dipakai HARI INI. Saat
 * Fase 0 (spatie/laravel-data) terpasang, cukup `extends Data` dan hapus toArray().
 */
final class TagihanWaliData
{
    public function __construct(
        public readonly int $id,
        public readonly string $jenisTagihan,
        public readonly ?string $keterangan,
        public readonly float $nominalAkhir,
        public readonly float $totalDibayar,
        public readonly float $sisaTagihan,
        public readonly string $status,          // 'lunas' | 'sebagian' | 'belum_bayar'
        public readonly ?string $jatuhTempo,     // 'Y-m-d'
        public readonly ?string $periodeAkademik,
    ) {}

    public static function fromModel(Tagihan $t): self
    {
        return new self(
            id: (int) $t->id,
            jenisTagihan: (string) $t->jenis_tagihan,
            keterangan: $t->keterangan,
            nominalAkhir: (float) $t->nominal_akhir,
            totalDibayar: (float) $t->total_dibayar,
            sisaTagihan: (float) $t->sisa_tagihan,
            status: (string) $t->status,
            jatuhTempo: $t->jatuh_tempo?->format('Y-m-d'),
            periodeAkademik: $t->periode_akademik,
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'jenisTagihan' => $this->jenisTagihan,
            'keterangan' => $this->keterangan,
            'nominalAkhir' => $this->nominalAkhir,
            'totalDibayar' => $this->totalDibayar,
            'sisaTagihan' => $this->sisaTagihan,
            'status' => $this->status,
            'jatuhTempo' => $this->jatuhTempo,
            'periodeAkademik' => $this->periodeAkademik,
        ];
    }
}
