<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PermissionAccessTest extends TestCase
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

    public function test_user_without_manage_users_permission_is_denied_user_resource_endpoint(): void
    {
        $user = User::withoutGlobalScopes()->create([
            'name' => 'Wali Asy-Syifaa',
            'email' => 'wali@asy-syifaa.localhost',
            'password' => 'password',
            'tenant_id' => 'asy-syifaa',
        ]);
        $user->assignRole('wali');

        $response = $this
            ->withServerVariables(['HTTP_HOST' => 'asy-syifaa.localhost'])
            ->actingAs($user)
            ->get('/admin/users');

        $response->assertForbidden();
    }
}
