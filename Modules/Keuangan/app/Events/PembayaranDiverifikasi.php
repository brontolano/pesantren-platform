<?php

namespace Modules\Keuangan\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Keuangan\Models\Pembayaran;

/**
 * Dipancarkan saat pembayaran terverifikasi.
 *
 * Modul Ledger (bila aktif) mendengarkan event ini untuk auto-jurnal
 * (debit Kas, kredit Pendapatan SPP). Loose coupling — bila Ledger non-aktif,
 * tidak ada listener & modul Keuangan tetap berjalan normal (konstitusi §4.2).
 */
class PembayaranDiverifikasi
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Pembayaran $pembayaran,
        public readonly int $verifiedBy,
    ) {}
}
