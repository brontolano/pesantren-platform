<?php

namespace Tests\Feature;

use App\Filament\Pages\SetupUnit;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Pusat Setup (SetupUnit): ter-gate ke panel tenant + user berizin unit.
 * Render & data dicakup smoke test + UnitSetupRegistryTest.
 */
class SetupUnitTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions',
            'model_has_roles', 'model_has_permissions',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('setupunit', 'Pesantren SetupUnit');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('tenant'));
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    public function test_admin_boleh_akses(): void
    {
        $admin = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Admin', 'email' => 'a@s.test', 'password' => 'x12345678',
        ]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $this->assertTrue(SetupUnit::canAccess());
    }

    public function test_user_tanpa_izin_unit_ditolak(): void
    {
        $polos = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Polos', 'email' => 'p@s.test', 'password' => 'x12345678',
        ]);
        $this->actingAs($polos);

        $this->assertFalse(SetupUnit::canAccess());
    }
}
