<?php

namespace Modules\Keuangan\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Keuangan\Models\TabunganTransaksi;

/**
 * Dipancarkan setiap setor/tarik tabungan. Modul Ledger (bila aktif) auto-jurnal
 * (setor: D Kas / K Titipan Tabungan Santri; tarik: kebalikannya).
 */
class TabunganTransaksiDicatat
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly TabunganTransaksi $transaksi) {}
}
