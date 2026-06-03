<?php

namespace Tests\Feature;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleResourceTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenant = Tenant::create(['id' => 'asy-syifaa', 'name' => 'Asy-Syifaa', 'is_active' => true]);
        $this->tenant->domains()->create(['domain' => 'asy-syifaa']);
    }

    public function test_super_admin_can_access_role_resource_routes(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@asy-syifaa.localhost',
            'password' => 'password',
        ]);
        $user->assignRole('super_admin');

        $this->actingAs($user);

        $this->assertTrue(RoleResource::canAccess());
        $this->assertTrue(RoleResource::canViewAny());
        $this->assertTrue(Route::has('filament.admin.resources.roles.index'));
        $this->assertTrue(Route::has('filament.admin.resources.roles.create'));
        $this->assertTrue(Route::has('filament.admin.resources.roles.edit'));

        tenancy()->end();
    }

    public function test_user_without_manage_roles_permission_cannot_access_role_resource(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::create([
            'name' => 'TU',
            'email' => 'tu@asy-syifaa.localhost',
            'password' => 'password',
        ]);
        $user->assignRole('tu');

        $this->actingAs($user);

        $this->assertFalse(RoleResource::canAccess());
        $this->assertFalse(RoleResource::canViewAny());

        tenancy()->end();
    }
}
