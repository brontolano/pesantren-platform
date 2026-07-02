<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/** Halaman PPDB iframe-friendly: CSP frame-ancestors per domain tenant + origin embed. */
class PpdbFrameAncestorsTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users', 'calon_santri', 'settings']);
        $this->truncatePpdbTables(['calon_santri', 'domains', 'tenants']);
    }

    public function test_csp_frame_ancestors_berisi_domain_tenant_dan_origin_embed(): void
    {
        $tenant = $this->createRuntimeTenant('ifr1', 'Pesantren Iframe');
        $tenant->domains()->firstOrCreate(['domain' => $tenant->id]); // BySubdomain butuh subdomain polos
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        Setting::updateOrCreate(
            ['key' => 'embed_allowed_origins'],
            ['value' => ['text' => 'https://pondokku.sch.id, http://jahat.example, https://kedua.sch.id']]
        );
        tenancy()->end();

        $res = $this->get("http://{$tenant->id}.localhost/ppdb");
        $res->assertOk();
        $csp = (string) $res->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("https://{$tenant->id}.localhost", $csp, 'Domain terdaftar tenant ikut diizinkan.');
        $this->assertStringContainsString('https://pondokku.sch.id', $csp, 'Origin embed dari setting diizinkan.');
        $this->assertStringContainsString('https://kedua.sch.id', $csp);
        $this->assertStringNotContainsString('jahat.example', $csp, 'Origin non-https DIBUANG.');
        $this->assertFalse($res->headers->has('X-Frame-Options'));
    }

    public function test_tanpa_setting_default_self_plus_domain_tenant(): void
    {
        $tenant = $this->createRuntimeTenant('ifr2', 'Pesantren Polos');
        $tenant->domains()->firstOrCreate(['domain' => $tenant->id]); // BySubdomain butuh subdomain polos

        $res = $this->get("http://{$tenant->id}.localhost/ppdb");
        $res->assertOk();
        $csp = (string) $res->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringNotContainsString('pondokku', $csp);
    }

    private function seedTenantRow(Tenant $tenant): void
    {
        DB::connection('tenant')->table('tenants')->updateOrInsert(
            ['id' => $tenant->id],
            ['name' => $tenant->name, 'is_active' => $tenant->is_active,
                'data' => json_encode([]), 'created_at' => now(), 'updated_at' => now()],
        );
    }
}
