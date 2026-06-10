<?php

namespace Modules\Ledger\Services;

use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Akun;
use RuntimeException;

/**
 * G3-23 — COA template + wizard. Terapkan bagan akun standar (idempoten
 * updateOrCreate via LedgerSeeder) — tak menimpa saldo/jurnal, hanya memastikan
 * akun inti ada. Wizard sekali-klik untuk tenant baru/migrasi.
 */
class CoaTemplateService
{
    /**
     * Terapkan COA standar pesantren (idempoten). Mengembalikan jumlah akun aktif.
     */
    public function terapkanStandar(): int
    {
        $this->ensureTenantContext();

        (new LedgerSeeder)->run();

        return Akun::query()->count();
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
