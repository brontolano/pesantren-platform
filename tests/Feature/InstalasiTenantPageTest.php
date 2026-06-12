<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Render authed halaman wizard Instalasi Tenant di panel CENTRAL (/platform).
 * Pola sama dengan TenantPanelAuthedSmokeTest: halaman login selalu 200,
 * regresi konfigurasi page/schema baru terlihat saat render ter-autentikasi.
 */
class InstalasiTenantPageTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    public function test_wizard_render_tidak_500_untuk_super_admin_central(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        $user = User::withoutGlobalScopes()->firstOrCreate(
            ['email' => 'central-wizard@test.local'],
            ['name' => 'Central Admin', 'password' => 'password123'],
        );
        $user->assignRole('super_admin');
        Auth::login($user);

        $host = 'pesantren.online'; // central domain (config/tenancy.php)
        $req = Request::create("https://{$host}/platform/instalasi-tenant", 'GET');
        $req->headers->set('Host', $host);
        $status = app(Kernel::class)->handle($req)->getStatusCode();

        $this->assertSame(200, $status, "Wizard /platform/instalasi-tenant mengembalikan {$status}, harus render 200.");
    }
}
