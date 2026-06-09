<?php

namespace Tests\Feature;

use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Penanganan host non-tenant & non-central (kurangi noise scanner/bot yang
 * sebelumnya menghasilkan 500 "TenantCouldNotBeIdentifiedOnDomain"):
 *  - www.<apex>  → redirect permanen (301) ke apex kanonik.
 *  - host tak dikenal / IP mentah → 404 ringan (BUKAN 500, tidak ter-log ERROR).
 *  - domain central tetap berfungsi normal.
 */
class HostResolutionTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'users', 'tenants', 'domains', 'roles', 'permissions',
        ]);
    }

    public function test_www_apex_redirect_permanen_ke_apex(): void
    {
        $response = $this->get('http://www.pesantren.online/');

        $response->assertStatus(301);
        $response->assertRedirect('https://pesantren.online/');
    }

    public function test_www_apex_mempertahankan_path_dan_query(): void
    {
        $response = $this->get('http://www.pesantren.online/artikel/contoh?ref=ig');

        $response->assertStatus(301);
        $response->assertRedirect('https://pesantren.online/artikel/contoh?ref=ig');
    }

    public function test_host_tak_dikenal_balas_404_bukan_500(): void
    {
        $response = $this->get('http://203.0.113.99/');

        $this->assertNotSame(500, $response->getStatusCode(), 'Host tak dikenal tidak boleh 500');
        $response->assertStatus(404);
    }

    public function test_domain_central_tetap_berfungsi(): void
    {
        $response = $this->get('http://pesantren.online/platform/login');

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertStatus(200);
    }
}
