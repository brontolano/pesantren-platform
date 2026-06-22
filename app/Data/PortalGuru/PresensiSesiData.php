<?php

declare(strict_types=1);

namespace App\Data\PortalGuru;

/**
 * KONTRAK: header sesi pelajaran di layar Presensi. Cermin TS: guru.d.ts (PresensiSesi).
 * Prop internal camelCase; wire-format SENGAJA snake_case (jam_mulai/jam_selesai) agar
 * identik kontrak lama (drop-in, tanpa regresi). Migrasi ke camelCase = ubah di sini + .d.ts + .vue sekaligus.
 */
final class PresensiSesiData
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $pelajaran,
        public readonly ?string $kelas,
        public readonly ?string $jamMulai,
        public readonly ?string $jamSelesai,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'pelajaran' => $this->pelajaran,
            'kelas' => $this->kelas,
            'jam_mulai' => $this->jamMulai,
            'jam_selesai' => $this->jamSelesai,
        ];
    }
}
