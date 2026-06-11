<?php

namespace Modules\Akademik\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Akademik\Models\Lembaga;

/**
 * WP-01 — Seed 1 lembaga default "Pondok" (jenis pondok) per tenant agar
 * tenant lama & pesantren kecil TIDAK berubah perilaku.
 *
 * Idempoten (firstOrCreate by nama) — aman dipanggil ulang oleh
 * tenant:reprovision. Dipanggil TenantProvisioner di dalam $tenant->run()
 * (pola KomponenNilaiSeeder); tenant_id terisi otomatis via BelongsToTenant.
 * Kunci sama dengan backfill di migrasi create_lembaga_table — tidak dobel.
 */
class LembagaSeeder extends Seeder
{
    public function run(): void
    {
        Lembaga::query()->firstOrCreate(
            ['nama' => 'Pondok'],
            ['jenis' => 'pondok', 'aktif' => true],
        );
    }
}
