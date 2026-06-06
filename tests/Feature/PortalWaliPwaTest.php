<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PortalWaliPwaTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants',
            'domains',
            'tenant_storage',
            'settings',
        ]);

        $this->truncatePpdbTables([
            'settings',
            'domains',
            'tenants',
            'tenant_storage',
        ]);
    }

    public function test_manifest_json_is_tenant_aware_and_installable(): void
    {
        $suffix = Str::lower(Str::random(6));
        $tenantId = 'wali-manifest-'.$suffix;
        $host = $tenantId.'.localhost';
        $tenant = $this->createProvisionedTenant($tenantId, 'Pesantren Wali', $host);

        tenancy()->initialize($tenant);
        Setting::create([
            'tenant_id' => $tenant->id,
            'key' => 'branding_name',
            'value' => ['text' => 'Pondok Wali'],
        ]);
        Setting::create([
            'tenant_id' => $tenant->id,
            'key' => 'branding_tagline',
            'value' => ['text' => 'Portal Wali Terkelola'],
        ]);
        Setting::create([
            'tenant_id' => $tenant->id,
            'key' => 'branding_primary_color',
            'value' => ['color' => '#102a43'],
        ]);
        Setting::create([
            'tenant_id' => $tenant->id,
            'key' => 'branding_secondary_color',
            'value' => ['color' => '#f59e0b'],
        ]);
        tenancy()->end();

        $response = $this->call('GET', "http://{$host}/wali/manifest.json", [], [], [], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('application/manifest+json', (string) $response->headers->get('content-type'));
        $response->assertJsonPath('name', 'Pondok Wali · Portal Wali');
        $response->assertJsonPath('short_name', 'Pondok Wali');
        $response->assertJsonPath('description', 'Pantau tagihan, tabungan, dan status pembayaran wali Pondok Wali langsung dari HP.');
        $response->assertJsonPath('start_url', '/wali');
        $response->assertJsonPath('scope', '/wali/');
        $response->assertJsonPath('display', 'standalone');
        $response->assertJsonPath('theme_color', '#102a43');
        $response->assertJsonPath('background_color', '#fffdf9');
        $response->assertJsonCount(2, 'icons');
        $response->assertJsonCount(2, 'screenshots');
        $response->assertJsonPath('icons.0.src', "http://{$host}/wali/icon.svg?size=192");
        $response->assertJsonPath('icons.1.src', "http://{$host}/wali/icon.svg?size=512");
        $response->assertJsonPath('screenshots.0.src', "http://{$host}/wali/screenshot.svg?viewport=wide");
        $response->assertJsonPath('screenshots.1.src', "http://{$host}/wali/screenshot.svg?viewport=narrow");
    }

    public function test_icon_and_screenshot_endpoints_reflect_tenant_branding(): void
    {
        $suffix = Str::lower(Str::random(6));
        $tenantId = 'wali-assets-'.$suffix;
        $host = $tenantId.'.localhost';
        $tenant = $this->createProvisionedTenant($tenantId, 'Asy Syifaa Wali', $host);

        tenancy()->initialize($tenant);
        Setting::create([
            'tenant_id' => $tenant->id,
            'key' => 'branding_primary_color',
            'value' => ['color' => '#225588'],
        ]);
        Setting::create([
            'tenant_id' => $tenant->id,
            'key' => 'branding_secondary_color',
            'value' => ['color' => '#d97706'],
        ]);
        tenancy()->end();

        $iconResponse = $this->call('GET', "http://{$host}/wali/icon.svg?size=512", [], [], [], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        $iconResponse->assertOk();
        $this->assertStringContainsString('image/svg+xml', (string) $iconResponse->headers->get('content-type'));
        $iconResponse->assertSee('<svg', false);
        $iconResponse->assertSee('#225588', false);
        $iconResponse->assertSee('#d97706', false);
        $iconResponse->assertSee('AS', false);

        $screenshotResponse = $this->call('GET', "http://{$host}/wali/screenshot.svg?viewport=narrow", [], [], [], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        $screenshotResponse->assertOk();
        $this->assertStringContainsString('image/svg+xml', (string) $screenshotResponse->headers->get('content-type'));
        $screenshotResponse->assertSee('Portal Wali', false);
        $screenshotResponse->assertSee('Bayar Sekarang', false);
        $screenshotResponse->assertSee('#225588', false);
    }

    public function test_service_worker_response_contains_portal_wali_shell_cache_urls(): void
    {
        $suffix = Str::lower(Str::random(6));
        $tenantId = 'wali-sw-'.$suffix;
        $host = $tenantId.'.localhost';
        $this->createProvisionedTenant($tenantId, 'Pesantren SW Wali', $host);

        $response = $this->call('GET', "http://{$host}/wali/sw.js", [], [], [], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('application/javascript', (string) $response->headers->get('content-type'));
        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringContainsString("wali-shell-{$tenantId}", $body);
        $this->assertStringContainsString('"\\/wali"', $body);
        $this->assertStringContainsString('"\\/wali\\/manifest.json"', $body);
        $this->assertStringContainsString('"\\/wali\\/icon.svg?size=192"', $body);
        $this->assertStringContainsString('"\\/wali\\/screenshot.svg?viewport=wide"', $body);
    }
}
