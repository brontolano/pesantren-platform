<?php

namespace Tests\Feature;

use App\Http\Middleware\RedirectDomainKanonis;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Domain kanonis per-tenant — OPT-IN MURNI via pengaturan platform.
 * Kasus nyata: asy-syifaa.pesantren.online (sistem, subdomain platform) → 301
 * ke pesantren.asy-syifaa.com (sistem, custom) HANYA setelah owner menandai
 * pesantren.asy-syifaa.com sebagai is_canonical=true (aksi UI "Domain Utama").
 * Tanpa penandaan eksplisit itu, TIDAK ADA redirect — berlaku default untuk
 * semua tenant (demo, albakids, tenant baru).
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

    public function test_tanpa_is_canonical_subdomain_tidak_redirect(): void
    {
        // Default untuk SEMUA tenant: tanpa owner mengeset "Domain Utama"
        // secara eksplisit, subdomain platform TIDAK di-redirect meski tenant
        // punya custom domain ber-type sama. Ini yang membedakan dari versi
        // otomatis (insiden 2026-07-04): keberadaan custom domain saja tidak
        // pernah cukup untuk memicu redirect.
        $this->buatTenantDuaDomain(kanonis: false);

        $res = $this->jalankan('https://kanonis-test.pesantren.online/admin?x=1');

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('lolos', $res->getContent());
    }

    public function test_dengan_is_canonical_eksplisit_subdomain_redirect_301_ke_kanonis(): void
    {
        $this->buatTenantDuaDomain(kanonis: true);

        $res = $this->jalankan('https://kanonis-test.pesantren.online/admin/santri?page=2&q=abc');

        $this->assertSame(301, $res->getStatusCode());
        $this->assertSame(
            'https://pesantren.kanonis-test.com/admin/santri?page=2&q=abc',
            $res->headers->get('Location'),
        );
    }

    public function test_custom_domain_tidak_pernah_redirect_otomatis(): void
    {
        $this->buatTenantDuaDomain(kanonis: false);

        $res = $this->jalankan('https://pesantren.kanonis-test.com/admin');

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('lolos', $res->getContent());
    }

    public function test_record_label_telanjang_ditandai_kanonis_tetap_ditolak_guard_host_valid(): void
    {
        // Jaring pengaman independen (insiden 2026-07-04): walau seharusnya
        // owner tak akan menandai record label-telanjang (mis. 'albakids',
        // artefak identifikasi subdomain stancl tanpa titik) sebagai domain
        // utama via UI, guard host-valid tetap berlaku bila data domains
        // tercemar record semacam itu — kelas kegagalan yang sama (redirect
        // ke http://albakids/) harus mustahil terulang.
        $tenant = $this->createRuntimeTenant('kanonis', 'Pesantren Label');
        $tenant->domains()->delete();
        $tenant->domains()->create(['domain' => 'label-telanjang', 'type' => 'subdomain', 'is_canonical' => true]);
        $tenant->domains()->create(['domain' => 'label-telanjang.pesantren.online', 'type' => 'subdomain', 'is_canonical' => false]);

        $res = $this->jalankan('https://label-telanjang.pesantren.online/manifest.webmanifest');

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('lolos', $res->getContent());
    }

    public function test_tenant_hanya_subdomain_platform_tidak_redirect_kasus_albakids(): void
    {
        // Tenant seperti albakids/demo: HANYA subdomain platform, tanpa
        // is_canonical diset → nol perubahan perilaku (default aman).
        $tenant = $this->createRuntimeTenant('kanonis', 'Alba Kids Like');
        $tenant->domains()->delete();
        $tenant->domains()->create(['domain' => 'albakids-like.pesantren.online', 'type' => 'sistem', 'is_canonical' => false]);

        $res = $this->jalankan('https://albakids-like.pesantren.online/admin');

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('lolos', $res->getContent());
    }

    public function test_is_canonical_ber_type_beda_tidak_dipakai_sebagai_target(): void
    {
        // is_canonical=true pada domain ber-type 'website' tidak menjadi
        // target redirect untuk request ber-type 'sistem' (konten beda).
        $tenant = $this->createRuntimeTenant('kanonis', 'Pesantren Kanonis');
        $tenant->domains()->delete();
        $tenant->domains()->create(['domain' => 'kanonis-web.pesantren.online', 'type' => 'sistem', 'is_canonical' => false]);
        $tenant->domains()->create(['domain' => 'kanonis-web.com', 'type' => 'website', 'is_canonical' => true]);

        $res = $this->jalankan('https://kanonis-web.pesantren.online/admin');

        $this->assertSame(200, $res->getStatusCode());
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
