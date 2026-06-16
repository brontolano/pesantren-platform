<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AppLauncher;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Login staf UNIVERSAL (perbaikan: tenant subdomain yang '/'-nya dipakai website
 * publik dulu tak punya pintu /admin). Kini:
 *  - GET /masuk merender form login di SEMUA domain tenant (bukan redirect ke '/').
 *  - GET /login dan GET /admin/login dialihkan ke /masuk.
 *  - Pasca-login, staf super_admin diarahkan ke /admin (via AppLauncher), bukan
 *    nyangkut di website publik '/'.
 */
class StaffLoginRoutingTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    public function test_masuk_universal_dan_redirect_login_ke_masuk_dan_tujuan_staf(): void
    {
        $tenant = $this->createRuntimeTenant('login', 'Pesantren Login');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        $this->seed(RolesAndPermissionsSeeder::class);
        $staf = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Staf', 'email' => 'staf@login.test', 'password' => 'password123',
        ]);
        $staf->assignRole('super_admin');

        $host = $tenant->domains()->first()->domain;
        $kernel = app(Kernel::class);

        // GET /masuk (tamu) → FORM login (200) di domain tenant apa pun.
        $masuk = $kernel->handle(Request::create("http://{$host}/masuk", 'GET'));
        $this->assertSame(200, $masuk->getStatusCode(), 'GET /masuk harus menampilkan form login (bukan redirect)');

        // GET /login → 302 ke /masuk.
        $login = $kernel->handle(Request::create("http://{$host}/login", 'GET'));
        $this->assertSame(302, $login->getStatusCode());
        $this->assertStringContainsString('/masuk', (string) $login->headers->get('Location'));

        // GET /admin/login (tamu) → 302 ke /masuk (Login::mount).
        $adminLogin = $kernel->handle(Request::create("http://{$host}/admin/login", 'GET'));
        $this->assertSame(302, $adminLogin->getStatusCode());
        $this->assertStringContainsString('/masuk', (string) $adminLogin->headers->get('Location'));

        // Tujuan pasca-login staf super_admin = /admin (dipakai StaffLoginController::store).
        $dest = AppLauncher::primaryDestination($staf);
        $this->assertSame('redirect', $dest['kind']);
        $this->assertStringContainsString('/admin', $dest['url']);

        tenancy()->end();
    }
}
