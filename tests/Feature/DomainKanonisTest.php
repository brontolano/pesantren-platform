<?php

namespace Tests\Feature;

use App\Http\Middleware\RedirectDomainKanonis;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Domain kanonis per-tenant (opt-in) — hapus dualitas panel admin.
 * Kasus nyata: pesantren.asy-syifaa.com (sistem, kanonis) vs
 * asy-syifaa.pesantren.online (sistem, kembar) → 301.
 */
class DomainKanonisTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains']);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    protected function buatTenantDuaDomain(bool $kanonis = true): Tenant
    {
        // ID acak: pembuatan Tenant memprovisikan DB fisik per tenant — ID tetap
        // akan bentrok "database already exists" antar-test.
        $tenant = $this->createRuntimeTenant('kanonis', 'Pesantren Kanonis');
        $tenant->domains()->delete();
        $tenant->domains()->create([
            'domain' => 'pesantren.kanonis-test.com', 'type' => 'sistem', 'is_canonical' => $kanonis,
        ]);
        $tenant->domains()->create([
            'domain' => 'kanonis-test.pesantren.online', 'type' => 'sistem', 'is_canonical' => false,
        ]);
        $tenant->domains()->create([
            'domain' => 'kanonis-test.com', 'type' => 'website', 'is_canonical' => false,
        ]);

        return $tenant;
    }

    protected function jalankan(string $url, string $method = 'GET'): Response
    {
        $request = Request::create($url, $method);

        return (new RedirectDomainKanonis)->handle($request, fn () => response('lolos'));
    }

    public function test_subdomain_kembar_redirect_301_ke_kanonis_dengan_path_dan_query(): void
    {
        $this->buatTenantDuaDomain();

        $res = $this->jalankan('https://kanonis-test.pesantren.online/admin/santri?page=2&q=abc');

        $this->assertSame(301, $res->getStatusCode());
        $this->assertSame(
            'https://pesantren.kanonis-test.com/admin/santri?page=2&q=abc',
            $res->headers->get('Location'),
        );
    }

    public function test_tanpa_kanonis_tidak_redirect(): void
    {
        $this->buatTenantDuaDomain(kanonis: false);

        $res = $this->jalankan('https://kanonis-test.pesantren.online/admin');

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('lolos', $res->getContent());
    }

    public function test_host_kanonis_sendiri_tidak_loop(): void
    {
        $this->buatTenantDuaDomain();

        $res = $this->jalankan('https://pesantren.kanonis-test.com/admin');

        $this->assertSame(200, $res->getStatusCode());
    }

    public function test_domain_type_beda_tidak_redirect(): void
    {
        // Website publik (type=website) TIDAK ikut dialihkan ke domain sistem.
        $this->buatTenantDuaDomain();

        $res = $this->jalankan('https://kanonis-test.com/');

        $this->assertSame(200, $res->getStatusCode());
    }

    public function test_rute_infrastruktur_dan_non_get_tidak_redirect(): void
    {
        $this->buatTenantDuaDomain();

        foreach ([
            'https://kanonis-test.pesantren.online/livewire/update',
            'https://kanonis-test.pesantren.online/api/v1/santri',
            'https://kanonis-test.pesantren.online/_tenant-storage/logo.png',
            'https://kanonis-test.pesantren.online/ppdb/dok/1/bukti',
            'https://kanonis-test.pesantren.online/up',
            'https://kanonis-test.pesantren.online/embed/widget.js',
        ] as $url) {
            $this->assertSame(200, $this->jalankan($url)->getStatusCode(), "Harus lolos: {$url}");
        }

        $this->assertSame(200, $this->jalankan('https://kanonis-test.pesantren.online/admin', 'POST')->getStatusCode());
    }

    public function test_host_central_dan_host_tak_terdaftar_tidak_redirect(): void
    {
        $this->buatTenantDuaDomain();

        $central = config('tenancy.central_domains')[0] ?? 'pesantren.online';
        $this->assertSame(200, $this->jalankan("https://{$central}/")->getStatusCode());
        $this->assertSame(200, $this->jalankan('https://tidak-terdaftar.example.com/')->getStatusCode());
    }

    public function test_feature_http_redirect_terjadi_sebelum_tenancy_init(): void
    {
        // Lewat HTTP stack penuh: redirect terjadi SEBELUM inisialisasi tenancy
        // (tenant DB runtime tidak dibutuhkan) — bukti resolusi tenancy tak diubah.
        $this->buatTenantDuaDomain();

        $res = $this->get('https://kanonis-test.pesantren.online/admin?x=1');

        $res->assertStatus(301);
        $res->assertRedirect('https://pesantren.kanonis-test.com/admin?x=1');
    }
}
