<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Etalase demo: pemilih peran (/coba) + auto-login peran baru (admin/jemaah).
 * Digerbang DEMO_AUTOLOGIN + tenant demo; selain itu 404.
 */
class DemoPickerTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'tenant_storage', 'users', 'roles', 'permissions', 'model_has_roles', 'settings',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    /** @return array{0: Tenant, 1: string} */
    private function bootDemo(bool $autologin): array
    {
        $host = 'demo-'.Str::lower(Str::random(6)).'.localhost';
        $tenantId = explode('.', $host)[0];
        $tenant = $this->createProvisionedTenant($tenantId, 'Pesantren Demo', $host);

        // Samakan tenant demo dgn tenant uji + nyalakan/matikan flag.
        config([
            'demo.autologin_enabled' => $autologin,
            'demo.autologin_tenant' => $tenantId,
        ]);

        return [$tenant, $host];
    }

    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    public function test_picker_tampil_dengan_kartu_peran(): void
    {
        [, $host] = $this->bootDemo(autologin: true);

        $resp = $this->get("http://{$host}/coba", $this->headers($host));

        $resp->assertOk();
        $resp->assertSee('Mode Demo');
        $resp->assertSee('Admin Pesantren');
        $resp->assertSee('Jemaah / Umum');
        $resp->assertSee('/demo-masuk/admin', escape: false);
        $resp->assertSee('/demo-masuk/jemaah', escape: false);
    }

    public function test_picker_404_tanpa_flag(): void
    {
        [, $host] = $this->bootDemo(autologin: false);

        $this->get("http://{$host}/coba", $this->headers($host))->assertNotFound();
    }

    public function test_demo_masuk_admin_login_dan_redirect(): void
    {
        [, $host] = $this->bootDemo(autologin: true);

        $resp = $this->get("http://{$host}/demo-masuk/admin", $this->headers($host));

        $resp->assertRedirect("http://{$host}/admin");
        $this->assertAuthenticated();
    }

    /**
     * Regresi: role demo utk tu/ppdb-panitia/keamanan/pengasuhan sebelumnya
     * memetakan ke role yang TIDAK PUNYA permission gate route-nya (mis.
     * 'tata_usaha' yang tak ada di RolesAndPermissionsSeeder, atau 'guru'
     * yang tak punya manage_asrama/view_kegiatan) — hasilnya 403 walau
     * sudah login. Pastikan tiap persona benar2 bisa GET halaman beranda-nya
     * (bukan 403) setelah demo-masuk.
     */
    public function test_demo_masuk_persona_pwa_tidak_403(): void
    {
        [, $host] = $this->bootDemo(autologin: true);

        foreach (['tu' => '/tu', 'ppdb-panitia' => '/ppdb-panitia', 'keamanan' => '/keamanan', 'pengasuhan' => '/pengasuhan'] as $peran => $path) {
            $this->get("http://{$host}/demo-masuk/{$peran}", $this->headers($host));

            $resp = $this->get("http://{$host}{$path}", $this->headers($host));

            $resp->assertOk("Persona demo '{$peran}' seharusnya bisa akses {$path} tanpa 403.");

            auth()->logout();
        }
    }
}
