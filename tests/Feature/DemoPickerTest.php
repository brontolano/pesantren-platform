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
}
