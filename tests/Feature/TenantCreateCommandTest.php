<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantCreateCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_command_creates_tenant_domain_and_bootstrap_users(): void
    {
        $this->artisan('tenant:create', [
            'name' => 'Asy-Syifaa',
            'subdomain' => 'asy-syifaa',
        ])
            ->expectsOutput("Tenant 'Asy-Syifaa' created with subdomain 'asy-syifaa'")
            ->assertSuccessful();

        $tenant = Tenant::query()->find('asy-syifaa');

        $this->assertNotNull($tenant);
        $this->assertSame('Asy-Syifaa', $tenant->name);
        $this->assertTrue($tenant->is_active);
        $this->assertDatabaseHas('domains', [
            'tenant_id' => 'asy-syifaa',
            'domain' => 'asy-syifaa',
        ]);
        $this->assertDatabaseHas('users', [
            'tenant_id' => 'asy-syifaa',
            'email' => 'admin@asy-syifaa.localhost',
        ]);
        $this->assertDatabaseHas('users', [
            'tenant_id' => 'asy-syifaa',
            'email' => 'tu@asy-syifaa.localhost',
        ]);

        $this->assertTrue(User::withoutGlobalScopes()->where('email', 'admin@asy-syifaa.localhost')->firstOrFail()->hasRole('super_admin'));
        $this->assertTrue(User::withoutGlobalScopes()->where('email', 'tu@asy-syifaa.localhost')->firstOrFail()->hasRole('tu'));
    }

    public function test_command_is_idempotent_and_repairs_missing_bootstrap_data(): void
    {
        $tenant = Tenant::create([
            'id' => 'asy-syifaa',
            'name' => 'Asy-Syifaa',
            'is_active' => true,
        ]);
        $tenant->domains()->create(['domain' => 'asy-syifaa']);

        $this->artisan('tenant:create', [
            'name' => 'Asy-Syifaa',
            'subdomain' => 'asy-syifaa',
        ])
            ->expectsOutput("Tenant dengan subdomain 'asy-syifaa' sudah ada. Data awal diverifikasi.")
            ->assertSuccessful();

        $this->assertSame(1, Tenant::query()->count());
        $this->assertDatabaseCount('domains', 1);
        $this->assertDatabaseHas('users', [
            'tenant_id' => 'asy-syifaa',
            'email' => 'admin@asy-syifaa.localhost',
        ]);
        $this->assertDatabaseHas('users', [
            'tenant_id' => 'asy-syifaa',
            'email' => 'tu@asy-syifaa.localhost',
        ]);
    }

    public function test_command_rejects_invalid_subdomain_format(): void
    {
        $this->artisan('tenant:create', [
            'name' => 'Asy-Syifaa',
            'subdomain' => 'Asy Syifaa',
        ])
            ->expectsOutput('Subdomain hanya boleh huruf kecil, angka, dan tanda hubung.')
            ->assertFailed();

        $this->assertDatabaseCount('tenants', 0);
    }
}
