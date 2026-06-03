<?php

namespace Tests\Feature;

use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenantResourceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_super_admin_can_access_tenant_resource(): void
    {
        $tenant = Tenant::create(['id' => 'asy-syifaa', 'name' => 'Asy-Syifaa', 'is_active' => true]);
        $tenant->domains()->create(['domain' => 'asy-syifaa']);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Super Admin',
            'email' => 'admin@asy-syifaa.localhost',
            'password' => 'password',
            'tenant_id' => 'asy-syifaa',
        ]);
        $user->assignRole('super_admin');

        $this->actingAs($user);

        $this->assertTrue(TenantResource::canAccess());
        $this->assertTrue(TenantResource::canViewAny());
        $this->assertTrue(Route::has('filament.admin.resources.tenants.index'));
    }

    public function test_non_super_admin_cannot_access_tenant_resource(): void
    {
        $tenant = Tenant::create(['id' => 'demo', 'name' => 'Demo', 'is_active' => true]);
        $tenant->domains()->create(['domain' => 'demo']);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'TU',
            'email' => 'tu@demo.localhost',
            'password' => 'password',
            'tenant_id' => 'demo',
        ]);
        $user->assignRole('tu');

        $this->actingAs($user);

        $this->assertFalse(TenantResource::canAccess());
        $this->assertFalse(TenantResource::canViewAny());
    }
}
