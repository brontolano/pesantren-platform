<?php

namespace Tests\Feature;

use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * QC command tenant:migrate-all (TODO #10) — idempotensi + laporan per tenant.
 */
class TenantMigrateAllCommandTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users',
        ]);
    }

    public function test_migrate_all_idempotent_pada_tenant_terprovisi(): void
    {
        $id = 'migr-'.str()->lower(str()->random(5));
        $tenant = $this->createProvisionedTenant($id, 'Pesantren Migrasi', $id.'.localhost');

        // Tenant sudah termigrasi saat dibuat → run pertama OK.
        $this->artisan('tenant:migrate-all', ['tenant' => $tenant->id])
            ->assertExitCode(0);

        // Idempotent: run kedua tetap OK (tak ada migrasi pending baru).
        $this->artisan('tenant:migrate-all', ['tenant' => $tenant->id])
            ->assertExitCode(0);
    }

    public function test_tenant_tak_dikenal_gagal_anggun(): void
    {
        $this->artisan('tenant:migrate-all', ['tenant' => 'tidak-ada-xyz'])
            ->assertExitCode(1);
    }
}
