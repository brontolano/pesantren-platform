<?php

declare(strict_types=1);

namespace App\Data\PortalGuru;

/**
 * KONTRAK: jurnal KBM hari ini (form mini pre-filled, nullable bila belum ada).
 * Cermin TS: guru.d.ts (PresensiJurnal). Wire-format snake_case (pengganti_id/jumlah_hadir) = kontrak lama.
 */
final class PresensiJurnalData
{
    public function __construct(
        public readonly ?string $materi,
        public readonly ?string $metode,
        public readonly ?string $status,
        public readonly ?int $penggantiId,
        public readonly ?int $jumlahHadir,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'materi' => $this->materi,
            'metode' => $this->metode,
            'status' => $this->status,
            'pengganti_id' => $this->penggantiId,
            'jumlah_hadir' => $this->jumlahHadir,
        ];
    }
}
