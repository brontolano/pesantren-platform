<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * PWA scope ROOT "/" (1-aplikasi, fondasi TWA Fase 4): manifest + ikon agar
 * SELURUH aplikasi (Beranda) installable. Pasif (JSON/SVG), per-tenant branding.
 */
class RootPwaTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'tenant_storage', 'settings', 'users', 'roles', 'permissions', 'model_has_roles',
        ]);
        $this->truncatePpdbTables(['settings', 'domains', 'tenants', 'tenant_storage']);
    }

    /** @return array{0: Tenant, 1: string} */
    private function boot(string $prefix): array
    {
        $host = $prefix.'-'.Str::lower(Str::random(6)).'.localhost';
        $tenantId = explode('.', $host)[0];
        $tenant = $this->createProvisionedTenant($tenantId, 'Pesantren Root', $host);

        return [$tenant, $host];
    }

    /** @return array{HTTP_HOST: string, SERVER_NAME: string} */
    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    public function test_manifest_root_tersedia_scope_root(): void
    {
        [, $host] = $this->boot('mf');

        $resp = $this->get("http://{$host}/manifest.webmanifest", $this->headers($host));

        $resp->assertOk();
        $resp->assertHeader('Content-Type', 'application/manifest+json');
        $resp->assertJson([
            'id' => '/',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
        ]);
        $resp->assertJsonStructure([
            'name', 'short_name', 'theme_color', 'background_color',
            'icons' => [['src', 'sizes', 'type', 'purpose']],
        ]);
    }

    public function test_icon_root_render_svg(): void
    {
        [, $host] = $this->boot('ic');

        $resp = $this->get("http://{$host}/icon.svg?size=512", $this->headers($host));

        $resp->assertOk();
        $resp->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $resp->getContent());
    }

    public function test_service_worker_shell_only_allowlist_aman(): void
    {
        [, $host] = $this->boot('sw');

        $resp = $this->get("http://{$host}/sw.js", $this->headers($host));

        $resp->assertOk();
        $resp->assertHeader('Service-Worker-Allowed', '/');
        $js = $resp->getContent();

        // Shell allow-list HANYA manifest + ikon (3 URL); tak ada area dinamis/sensitif.
        $this->assertStringContainsString('const SHELL_URLS = ["\/manifest.webmanifest","\/icon.svg?size=192","\/icon.svg?size=512"]', $js);
        // Penjaga keamanan: non-GET dilewati, lintas-origin dilewati, non-shell dilewati
        // (allow-list → /admin /platform /livewire /api & halaman ber-auth TAK PERNAH di-cache).
        $this->assertStringContainsString("method !== 'GET'", $js);
        $this->assertStringContainsString('self.location.origin', $js);
        $this->assertStringContainsString('if (!isShellUrl)', $js);
    }

    public function test_manifest_pakai_branding_tenant(): void
    {
        [$tenant, $host] = $this->boot('br');

        tenancy()->initialize($tenant);
        Setting::updateOrCreate(['tenant_id' => $tenant->id, 'key' => 'branding_name'], ['value' => 'Pondok Uji Coba']);
        tenancy()->end();

        $this->get("http://{$host}/manifest.webmanifest", $this->headers($host))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Pondok Uji Coba']);
    }

    public function test_warna_hex_invalid_tidak_merusak(): void
    {
        [$tenant, $host] = $this->boot('hex');

        tenancy()->initialize($tenant);
        Setting::updateOrCreate(['tenant_id' => $tenant->id, 'key' => 'branding_primary_color'], ['value' => 'javascript:alert(1)']);
        tenancy()->end();

        // Warna tak valid → fallback aman, theme_color tetap hex; ikon tetap render.
        $this->get("http://{$host}/manifest.webmanifest", $this->headers($host))
            ->assertOk()
            ->assertJsonFragment(['theme_color' => '#0f5132']);

        $icon = $this->get("http://{$host}/icon.svg?size=192", $this->headers($host));
        $icon->assertOk();
        $this->assertStringNotContainsString('javascript:alert', $icon->getContent());
    }
}
