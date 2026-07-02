<?php

namespace Tests\Feature;

use App\Models\TenantModule;
use Database\Seeders\ModuleRegistrySeeder;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * QC command tenant:reprovision (TODO #5) — backfill idempotent.
 */
class TenantReprovisionCommandTest extends TestCase
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

    public function test_reprovision_membackfill_modul_yang_hilang(): void
    {
        // Default tenant baru kini RAMPING (hanya modul inti). Untuk menguji
        // mekanisme BACKFILL modul released, aktifkan flag rollback agar provision
        // & reprovision mengaktifkan modul released (lihat TenantProvisioner::seedModules).
        config(['tenant_modules.default_active_released' => true]);

        $id = 'repro-'.str()->lower(str()->random(5));
        $tenant = $this->createProvisionedTenant($id, 'Pesantren Reprovision', $id.'.localhost');

        // Simulasikan tenant "lama": hapus aktivasi modulnya.
        TenantModule::query()->where('tenant_id', $tenant->id)->delete();
        $this->assertSame(0, TenantModule::query()->where('tenant_id', $tenant->id)->count());

        $this->artisan('tenant:reprovision', ['tenant' => $tenant->id])
            ->assertExitCode(0);

        $sesudah = TenantModule::query()->where('tenant_id', $tenant->id)->where('is_active', true)->count();
        $this->assertGreaterThan(0, $sesudah, 'Modul harus dibackfill oleh reprovision');
    }

    public function test_reprovision_idempotent_dijalankan_dua_kali(): void
    {
        $id = 'repro2-'.str()->lower(str()->random(5));
        $tenant = $this->createProvisionedTenant($id, 'Pesantren Reprovision 2', $id.'.localhost');

        $this->artisan('tenant:reprovision', ['tenant' => $tenant->id])->assertExitCode(0);
        $count1 = TenantModule::query()->where('tenant_id', $tenant->id)->count();

        $this->artisan('tenant:reprovision', ['tenant' => $tenant->id])->assertExitCode(0);
        $count2 = TenantModule::query()->where('tenant_id', $tenant->id)->count();

        $this->assertSame($count1, $count2, 'Reprovision tak boleh menggandakan baris');
    }

    public function test_reprovision_tenant_tak_dikenal_gagal_anggun(): void
    {
        $this->artisan('tenant:reprovision', ['tenant' => 'tidak-ada-xyz'])
            ->assertExitCode(1);
    }
}
