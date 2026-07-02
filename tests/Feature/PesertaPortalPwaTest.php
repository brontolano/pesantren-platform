<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PesertaPortalPwaTest extends TestCase
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
        $tenantId = 'manifest-'.$suffix;
        $host = $tenantId.'.localhost';
        $tenant = $this->createProvisionedTenant($tenantId, 'Pesantren Manifest', $host);

        tenancy()->initialize($tenant);
        Setting::create([
            'tenant_id' => $tenant->id,
            'key' => 'branding_name',
            'value' => ['text' => 'Pondok Manifest'],
        ]);
        Setting::create([
            'tenant_id' => $tenant->id,
            'key' => 'branding_tagline',
            'value' => ['text' => 'Portal SPMB siap pasang'],
        ]);
        Setting::create([
            'tenant_id' => $tenant->id,
            'key' => 'branding_primary_color',
            'value' => ['color' => '#123456'],
        ]);
        Setting::create([
            'tenant_id' => $tenant->id,
            'key' => 'branding_secondary_color',
            'value' => ['color' => '#abcdef'],
        ]);
        tenancy()->end();

        $response = $this->call('GET', "http://{$host}/spmb/manifest.json", [], [], [], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('application/manifest+json', (string) $response->headers->get('content-type'));
        $response->assertJsonPath('name', 'Pondok Manifest · SPMB');
        $response->assertJsonPath('short_name', 'Pondok Manif');
        $response->assertJsonPath('description', 'Portal SPMB siap pasang');
        $response->assertJsonPath('start_url', '/spmb');
        $response->assertJsonPath('scope', '/spmb/');
        $response->assertJsonPath('display', 'standalone');
        $response->assertJsonPath('theme_color', '#123456');
        $response->assertJsonPath('background_color', '#f5f5f4');
        $response->assertJsonCount(2, 'icons');
        $response->assertJsonPath('icons.0.src', "http://{$host}/spmb/icon.svg?size=192");
        $response->assertJsonPath('icons.1.src', "http://{$host}/spmb/icon.svg?size=512");
    }

    public function test_service_worker_response_contains_tenant_shell_cache_urls(): void
    {
        $suffix = Str::lower(Str::random(6));
        $tenantId = 'sw-'.$suffix;
        $host = $tenantId.'.localhost';
        $this->createProvisionedTenant($tenantId, 'Pesantren SW', $host);

        $response = $this->call('GET', "http://{$host}/spmb/sw.js", [], [], [], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('application/javascript', (string) $response->headers->get('content-type'));
        $response->assertSee("spmb-shell-{$tenantId}", false);
        $response->assertSee('/spmb/login', false);
        $response->assertSee('/spmb/aktivasi', false);
        $response->assertSee('/spmb/lupa-password', false);
        $response->assertSee('/spmb/manifest.json', false);
    }

    public function test_icon_endpoint_returns_svg_icon_with_tenant_branding(): void
    {
        $suffix = Str::lower(Str::random(6));
        $tenantId = 'icon-'.$suffix;
        $host = $tenantId.'.localhost';
        $tenant = $this->createProvisionedTenant($tenantId, 'Asy Syifaa', $host);

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

        $response = $this->call('GET', "http://{$host}/spmb/icon.svg?size=512", [], [], [], [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('image/svg+xml', (string) $response->headers->get('content-type'));
        $response->assertSee('<svg', false);
        $response->assertSee('#225588', false);
        $response->assertSee('#d97706', false);
        $response->assertSee('AS', false);
    }
}
