<?php

namespace Modules\Pengadaan\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Pengadaan\Models\Belanja;

/**
 * Dipancarkan saat belanja disetujui & terbukukan. Modul lain (Notifikasi/Pelaporan)
 * dapat mendengarkan untuk pemberitahuan / refresh laporan.
 */
class BelanjaDisetujui
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Belanja $belanja) {}
}
