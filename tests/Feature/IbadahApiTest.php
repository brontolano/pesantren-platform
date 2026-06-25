<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * API publik ibadah (1-aplikasi / native Android): GET /api/v1/jadwal-sholat.
 * Per-tenant (resolve domain), tanpa auth. Menghormati toggle CMS per-fitur —
 * sejajar TenantLandingPageController::ibadahPayload.
 */
class IbadahApiTest extends TestCase
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
    private function bootTenant(): array
    {
        $host = 'ibadah-'.Str::lower(Str::random(6)).'.localhost';
        $tenantId = explode('.', $host)[0];
        $tenant = $this->createProvisionedTenant($tenantId, 'Pesantren Uji', $host);

        return [$tenant, $host];
    }

    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    public function test_jadwal_sholat_default_lengkap(): void
    {
        [, $host] = $this->bootTenant();

        $resp = $this->getJson("http://{$host}/api/v1/jadwal-sholat", $this->headers($host));

        $resp->assertOk();
        $resp->assertJsonPath('ok', true);
        // Lokasi default (Bandung) → 5 waktu sholat selalu terhitung.
        $resp->assertJsonCount(5, 'data.jadwalSholat');
        $resp->assertJsonPath('data.jadwalSholat.0.key', 'subuh');
        $resp->assertJsonPath('data.jadwalSholat.4.key', 'isya');
        $resp->assertJsonPath('data.donasiUrl', '/donasi');
        $this->assertIsInt($resp->json('data.arahKiblat'));
    }

    public function test_toggle_off_menyembunyikan_jadwal_dan_donasi(): void
    {
        [$tenant, $host] = $this->bootTenant();

        $tenant->run(function () use ($tenant) {
            Setting::updateOrCreate(
                ['tenant_id' => $tenant->getTenantKey(), 'key' => 'beranda.tampil_jadwal_sholat'],
                ['value' => false],
            );
            Setting::updateOrCreate(
                ['tenant_id' => $tenant->getTenantKey(), 'key' => 'beranda.tampil_donasi'],
                ['value' => false],
            );
        });

        $resp = $this->getJson("http://{$host}/api/v1/jadwal-sholat", $this->headers($host));

        $resp->assertOk();
        $resp->assertJsonPath('ok', true);
        $resp->assertJsonCount(0, 'data.jadwalSholat');
        $resp->assertJsonPath('data.donasiUrl', null);
    }
}
