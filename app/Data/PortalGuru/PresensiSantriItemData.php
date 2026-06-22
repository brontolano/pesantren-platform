<?php

declare(strict_types=1);

namespace App\Data\PortalGuru;

use Modules\Kesantrian\Models\Santri;

/**
 * KONTRAK: satu baris santri di layar Presensi Portal Guru.
 * Cermin TS: resources/js/types/guru.d.ts (PresensiSantriRow).
 * Catatan: status default 'hadir' diisi controller (tersimpan[id] ?? 'hadir').
 */
final class PresensiSantriItemData
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $nis,
        public readonly string $nama,
        public readonly string $status,   // 'hadir' | 'izin' | 'sakit' | 'alpa'
    ) {}

    public static function fromModel(Santri $s, string $status): self
    {
        return new self((int) $s->id, $s->nis, (string) $s->nama, $status);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id, 'nis' => $this->nis, 'nama' => $this->nama, 'status' => $this->status];
    }
}
