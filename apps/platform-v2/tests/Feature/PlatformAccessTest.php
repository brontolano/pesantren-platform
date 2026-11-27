<?php

namespace Tests\Feature;

use App\Models\PlatformOperator;
use App\Models\TenantAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_routes_require_a_platform_operator(): void
    {
        $this->get('/platform')->assertRedirect('/platform/login');

        $operator = PlatformOperator::query()->create([
            'name' => 'Operator Demo',
            'email' => 'operator@example.test',
            'password' => 'test-password',
            'is_active' => true,
        ]);

        $this->actingAs($operator, 'platform')
            ->get('/platform')
            ->assertOk()
            ->assertSee('Ringkasan platform');
    }

    public function test_a_tenant_admin_identity_does_not_authenticate_to_the_platform_guard(): void
    {
        config(['database.connections.tenant' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        \Illuminate\Support\Facades\DB::purge('tenant');
        \Illuminate\Support\Facades\Schema::connection('tenant')->create('tenant_admins', function ($table): void {
            $table->id(); $table->string('name'); $table->string('email')->unique(); $table->string('password'); $table->boolean('is_active')->default(true); $table->rememberToken(); $table->timestamps();
        });
        $tenantAdmin = TenantAdmin::query()->create(['name' => 'Admin Demo', 'email' => 'admin@example.test', 'password' => 'test-password', 'is_active' => true]);

        $this->actingAs($tenantAdmin, 'tenant')
            ->get('/platform')
            ->assertRedirect('/platform/login');
    }
}
