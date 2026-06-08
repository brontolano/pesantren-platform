<?php

namespace Modules\Ledger\Services;

use Modules\Ledger\Models\PeriodeAkuntansi;
use RuntimeException;

/**
 * Tutup buku & buka kembali periode akuntansi (Pagar #4).
 */
class PeriodeService
{
    public function tutupBuku(PeriodeAkuntansi $periode, ?int $closedBy = null): PeriodeAkuntansi
    {
        $this->ensureTenantContext();

        if ($periode->isClosed()) {
            throw new RuntimeException("Periode \"{$periode->nama}\" sudah closed.");
        }

        $periode->update([
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by' => $closedBy,
        ]);

        return $periode->refresh();
    }

    public function bukaKembali(PeriodeAkuntansi $periode): PeriodeAkuntansi
    {
        $this->ensureTenantContext();

        $periode->update([
            'status' => 'open',
            'closed_at' => null,
            'closed_by' => null,
        ]);

        return $periode->refresh();
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
