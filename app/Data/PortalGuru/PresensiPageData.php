<?php

declare(strict_types=1);

namespace App\Data\PortalGuru;

use Illuminate\Support\Collection;

/**
 * KONTRAK AGREGAT: props fitur layar PortalGuru/Presensi (DI LUAR sharedProps).
 *
 * Controller: Inertia::render('PortalGuru/Presensi', [...$this->sharedProps($request), ...PresensiPageData::...->toArray()]).
 * Output identik dengan kontrak lama (lihat PortalGuruPresensiController::show). Cermin TS: guru.d.ts (PresensiPage).
 *
 * @param Collection<int,PresensiSantriItemData> $santri
 * @param array<int,string>                      $statusOptions  ['hadir','izin','sakit','alpa']
 */
final class PresensiPageData
{
    public function __construct(
        public readonly PresensiSesiData $sesi,
        public readonly string $tanggal,
        public readonly Collection $santri,
        public readonly array $statusOptions,
        public readonly ?PresensiJurnalData $jurnal,
        public readonly string $jurnalUrl,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'sesi' => $this->sesi->toArray(),
            'tanggal' => $this->tanggal,
            'santri' => $this->santri->map(fn (PresensiSantriItemData $d) => $d->toArray())->values()->all(),
            'statusOptions' => array_values($this->statusOptions),
            'jurnal' => $this->jurnal?->toArray(),
            'jurnal_url' => $this->jurnalUrl,
        ];
    }
}
