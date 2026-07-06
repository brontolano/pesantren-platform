<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Beranda Staf (PWA) `/staf` — app-shell mobile menu-terfilter-izin untuk
 * staf yang belum punya PWA khusus (keamanan, UKS, TU, konselor, dll).
 * Desktop → redirect /admin; mobile → tampil menu sesuai permission.
 */
class StafShellTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('stafshell', 'Pesantren Staf Shell');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);
        tenancy()->end();
    }

    private function headers(string $host, bool $mobile = true): array
    {
        $ua = $mobile
            ? 'Mozilla/5.0 (Linux; Android 13; SM-G991B)'
            : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';

        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host, 'HTTP_USER_AGENT' => $ua];
    }

    public function test_desktop_diarahkan_ke_admin(): void
    {
        tenancy()->initialize($this->tenant);
        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Petugas UKS', 'email' => 'uks@stafshell.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('pengelola_uks');
        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/staf", $this->headers($host, mobile: false));

        $response->assertRedirect('/admin');
    }

    public function test_mobile_menampilkan_menu_sesuai_izin(): void
    {
        tenancy()->initialize($this->tenant);
        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Petugas UKS', 'email' => 'uks2@stafshell.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('pengelola_uks');
        // Menu /staf kini diturunkan dari navigasi panel yang HORMATI gerbang
        // modul (persis panel). Aktifkan modul kesehatan agar grup UKS tampil
        // (tenant baru default minimal). Ini juga menegaskan perilaku benar:
        // modul non-aktif → tersembunyi di mobile SAMA seperti panel.
        \Illuminate\Support\Facades\DB::connection('pgsql')->table('tenant_modules')->updateOrInsert(
            ['tenant_id' => $this->tenant->id, 'module_name' => 'kesehatan'],
            ['is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        );
        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/staf", $this->headers($host, mobile: true));

        $response->assertOk();
        $response->assertSee('Kunjungan UKS');
        $response->assertDontSee('Jurnal');
    }

    public function test_tanpa_login_ditolak_akses(): void
    {
        $host = $this->tenant->id.'.localhost';

        $response = $this->get("http://{$host}/staf", $this->headers($host));

        $response->assertRedirect();
        $this->assertNotSame(200, $response->getStatusCode());
    }
}
