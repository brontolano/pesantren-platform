<?php

namespace Tests\Feature;

use App\Models\TenantModule;
use Database\Seeders\ModuleRegistrySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * QC F1 — migrasi 2026_06_19 konvergensi registry + backfill aman crm/koperasi.
 *
 * Membuktikan kontrak NON-DISRUPTIF: tenant yang TERBUKTI memakai POS/inventory
 * tetap melihat modul (koperasi diaktifkan), tenant tanpa data tidak (#223 default
 * mati). Penting karena F2 menyembunyikan POS bila 'koperasi' tak aktif — backfill
 * inilah jaring pengamannya.
 */
class KonvergensiRegistriBackfillTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles',
            'module_registry', 'tenant_modules', 'settings',
        ]);

        $this->seed(ModuleRegistrySeeder::class);
    }

    /** Jalankan up() migrasi F1 secara langsung (idempoten, aman dipanggil ulang). */
    private function jalankanBackfill(): void
    {
        $migration = require base_path(
            'database/migrations/2026_06_19_000000_konvergensi_module_registry_backfill_opsional.php'
        );
        $migration->up();
    }

    public function test_backfill_mengaktifkan_koperasi_untuk_tenant_berdata_pos(): void
    {
        $id = 'bfkop-'.str()->lower(str()->random(5));
        $tenant = $this->createProvisionedTenant($id, 'Pesantren POS', $id.'.localhost');

        // Mulai dari kondisi koperasi MATI (default minimal tenant baru).
        TenantModule::query()
            ->where('tenant_id', $tenant->id)
            ->where('module_name', 'koperasi')
            ->delete();

        // Simulasikan pemakaian POS: satu loket di DB tenant.
        $tenant->run(function (): void {
            if (Schema::hasTable('pos_profil')) {
                DB::table('pos_profil')->insert([
                    'tenant_id' => tenant('id'),
                    'nama' => 'Loket Koperasi',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $this->jalankanBackfill();

        $this->assertTrue(
            TenantModule::query()
                ->where('tenant_id', $tenant->id)
                ->where('module_name', 'koperasi')
                ->where('is_active', true)
                ->exists(),
            'koperasi wajib diaktifkan untuk tenant yang punya data POS (jaring pengaman F2).'
        );
    }

    public function test_backfill_tidak_mengaktifkan_untuk_tenant_tanpa_data(): void
    {
        $id = 'bfnon-'.str()->lower(str()->random(5));
        $tenant = $this->createProvisionedTenant($id, 'Pesantren Tanpa POS', $id.'.localhost');

        TenantModule::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('module_name', ['koperasi', 'crm'])
            ->delete();

        $this->jalankanBackfill();

        $this->assertFalse(
            TenantModule::query()->where('tenant_id', $tenant->id)
                ->where('module_name', 'koperasi')->where('is_active', true)->exists(),
            'koperasi tidak boleh aktif tanpa data POS (#223 default mati).'
        );
        $this->assertFalse(
            TenantModule::query()->where('tenant_id', $tenant->id)
                ->where('module_name', 'crm')->where('is_active', true)->exists(),
            'crm tidak boleh aktif tanpa data CRM (#223 default mati).'
        );
    }
}
