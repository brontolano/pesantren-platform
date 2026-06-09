<?php

namespace Tests\Feature;

use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Smoke test anti-500 (TODO #16) untuk panel platform CENTRAL.
 * Membuktikan rute inti panel admin render TANPA 500 (200 untuk login,
 * 302 redirect ke login untuk dashboard saat guest).
 *
 * Catatan: panel TENANT (per pesantren) butuh DB tenant fisik → smoke-nya
 * dijalankan di environment live/Docker, bukan sandbox lokal.
 */
class PanelSmokeTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'users', 'tenants', 'domains', 'roles', 'permissions',
        ]);
    }

    public function test_halaman_login_platform_render_200(): void
    {
        $response = $this->get('/platform/login');

        $this->assertNotSame(500, $response->getStatusCode(), 'Login panel platform tidak boleh 500');
        $response->assertStatus(200);
    }

    public function test_dashboard_platform_redirect_login_saat_guest(): void
    {
        $response = $this->get('/platform');

        $this->assertNotSame(500, $response->getStatusCode(), 'Dashboard panel platform tidak boleh 500');
        $response->assertRedirect();
    }

    public function test_health_endpoint_200(): void
    {
        $this->get('/up')->assertStatus(200);
    }
}
