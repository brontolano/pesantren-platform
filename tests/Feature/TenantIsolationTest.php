<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['id' => 'tenant-a', 'name' => 'Tenant A', 'is_active' => true]);
        $this->tenantA->domains()->create(['domain' => 'tenant-a.localhost']);

        $this->tenantB = Tenant::create(['id' => 'tenant-b', 'name' => 'Tenant B', 'is_active' => true]);
        $this->tenantB->domains()->create(['domain' => 'tenant-b.localhost']);

        $this->userA = User::withoutGlobalScopes()->create([
            'name' => 'User A',
            'email' => 'user@tenant-a.localhost',
            'password' => bcrypt('password'),
            'tenant_id' => 'tenant-a',
        ]);

        $this->userB = User::withoutGlobalScopes()->create([
            'name' => 'User B',
            'email' => 'user@tenant-b.localhost',
            'password' => bcrypt('password'),
            'tenant_id' => 'tenant-b',
        ]);

        TenantModule::create(['tenant_id' => 'tenant-a', 'module_name' => 'Hello', 'is_active' => true]);
        TenantModule::create(['tenant_id' => 'tenant-b', 'module_name' => 'Hello', 'is_active' => false]);
    }

    public function test_user_query_only_returns_own_tenant_data(): void
    {
        tenancy()->initialize($this->tenantA);

        $users = User::all();

        $this->assertCount(1, $users);
        $this->assertEquals('tenant-a', $users->first()->tenant_id);
        $this->assertEquals('User A', $users->first()->name);

        tenancy()->end();
    }

    public function test_tenant_b_cannot_see_tenant_a_users(): void
    {
        tenancy()->initialize($this->tenantB);

        $users = User::all();

        $this->assertCount(1, $users);
        $this->assertEquals('tenant-b', $users->first()->tenant_id);

        $userIds = $users->pluck('id')->toArray();
        $this->assertNotContains($this->userA->id, $userIds);

        tenancy()->end();
    }

    public function test_creating_user_in_tenant_context_auto_sets_tenant_id(): void
    {
        tenancy()->initialize($this->tenantA);

        $newUser = User::create([
            'name' => 'New User A',
            'email' => 'new@tenant-a.localhost',
            'password' => bcrypt('password'),
        ]);

        $this->assertEquals('tenant-a', $newUser->tenant_id);

        tenancy()->end();
    }

    public function test_module_active_check_works_per_tenant(): void
    {
        $this->assertTrue(TenantModule::isModuleActive('tenant-a', 'Hello'));
        $this->assertFalse(TenantModule::isModuleActive('tenant-b', 'Hello'));
    }

    public function test_no_cross_tenant_data_leakage(): void
    {
        tenancy()->initialize($this->tenantA);
        $countA = User::count();
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        $countB = User::count();
        tenancy()->end();

        $totalWithoutScope = User::withoutGlobalScopes()->count();

        $this->assertEquals(1, $countA);
        $this->assertEquals(1, $countB);
        $this->assertEquals(2, $totalWithoutScope);
    }
}
