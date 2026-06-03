<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenantA = Tenant::create(['id' => 'asy-syifaa', 'name' => 'Asy-Syifaa', 'is_active' => true]);
        $this->tenantA->domains()->create(['domain' => 'asy-syifaa']);

        $this->tenantB = Tenant::create(['id' => 'demo', 'name' => 'Demo', 'is_active' => true]);
        $this->tenantB->domains()->create(['domain' => 'demo']);
    }

    public function test_user_manager_can_access_user_resource_routes(): void
    {
        tenancy()->initialize($this->tenantA);

        $user = User::create([
            'name' => 'TU Asy-Syifaa',
            'email' => 'tu@asy-syifaa.localhost',
            'password' => 'password',
        ]);
        $user->assignRole('tu');

        $this->actingAs($user);

        $this->assertTrue(UserResource::canAccess());
        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(Route::has('filament.admin.resources.users.index'));
        $this->assertTrue(Route::has('filament.admin.resources.users.create'));
        $this->assertTrue(Route::has('filament.admin.resources.users.edit'));

        tenancy()->end();
    }

    public function test_user_resource_only_queries_current_tenant_users(): void
    {
        tenancy()->initialize($this->tenantA);

        User::create([
            'name' => 'Admin A',
            'email' => 'admin@asy-syifaa.localhost',
            'password' => 'password',
        ]);

        tenancy()->end();

        tenancy()->initialize($this->tenantB);

        User::create([
            'name' => 'Admin B',
            'email' => 'admin@demo.localhost',
            'password' => 'password',
        ]);

        $tenantBUsers = UserResource::getEloquentQuery()->get();

        $this->assertCount(1, $tenantBUsers);
        $this->assertSame('demo', $tenantBUsers->first()->tenant_id);
        $this->assertSame('Admin B', $tenantBUsers->first()->name);

        tenancy()->end();
    }

    public function test_user_without_manage_users_permission_cannot_access_user_resource(): void
    {
        tenancy()->initialize($this->tenantA);

        $user = User::create([
            'name' => 'Wali Asy-Syifaa',
            'email' => 'wali@asy-syifaa.localhost',
            'password' => 'password',
        ]);
        $user->assignRole('wali');

        $this->actingAs($user);

        $this->assertFalse(UserResource::canAccess());
        $this->assertFalse(UserResource::canViewAny());

        tenancy()->end();
    }
}
