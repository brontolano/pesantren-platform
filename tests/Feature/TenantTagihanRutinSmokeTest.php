<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class TenantTagihanRutinSmokeTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions',
            'module_registry', 'tenant_modules',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    public function test_authenticated_tenant_can_render_dashboard_and_tagihan_rutin_pages_in_advanced_mode(): void
    {
        $tenant = $this->createRuntimeTenant('billing-smoke', 'Pesantren Billing Smoke');
        tenancy()->initialize($tenant);
        DB::connection('tenant')->table('tenants')->updateOrInsert(
            ['id' => $tenant->id],
            ['name' => $tenant->name, 'is_active' => true, 'data' => json_encode([]),
                'created_at' => now(), 'updated_at' => now()],
        );

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Billing Smoke',
            'email' => 'billing-smoke@panel.test',
            'password' => 'password123',
        ]);
        $user->forceFill(['ui_mode' => 'advanced'])->save();
        $user->assignRole('super_admin');
        Auth::login($user);

        DB::connection('pgsql')->table('module_registry')->where('key', 'keuangan')->update(['status' => 'released']);
        DB::connection('pgsql')->table('tenant_modules')->insert([
            'tenant_id' => $tenant->id,
            'module_name' => 'keuangan',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $host = $tenant->id.'.localhost';
        $kernel = app(Kernel::class);
        foreach (['admin', 'admin/tagihan-rutin/tagihan-rutins', 'admin/tagihan-rutin/tagihan-rutins/create'] as $path) {
            $request = Request::create("http://{$host}/{$path}", 'GET');
            $request->headers->set('Host', $host);
            $response = $kernel->handle($request);

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Render /{$path} returned {$response->getStatusCode()} (redirect: ".($response->headers->get('Location') ?? 'none').').',
            );
        }

        tenancy()->end();
    }
}
