<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Modules\CMS\Models\Article;
use Modules\CMS\Services\TenantWebsiteService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Integrasi SEO Pro (nomanur/filament-seo-pro) untuk artikel + landing tenant.
 *
 * Prinsip terkunci: plugin = AUGMENT/FALLBACK, BUKAN override. Meta yang sudah
 * diisi editor (relasi seo ATAU kolom lama meta_title/meta_description) HARUS
 * menang; artikel tanpa meta → fallback otomatis dari excerpt/judul.
 *
 * Memakai createRuntimeTenant → memprovisi DB per-tenant sungguhan (event
 * TenantCreated menjalankan migrasi tenant, termasuk seo_meta), sama seperti
 * TenantPanelAuthedSmokeTest. Bukan single-DB (createTestTenant), karena relasi
 * polimorfik seo hidup di DB tenant.
 */
class ArticleSeoProTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains']);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        parent::tearDown();
    }

    /** seo_meta harus ada di DB tenant (migrasi tenant aditif dari modul CMS). */
    public function test_tabel_seo_meta_dibuat_di_db_tenant(): void
    {
        $tenant = $this->createRuntimeTenant('seo-tbl', 'Pesantren SEO Tabel');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        $this->assertTrue(
            Schema::connection('tenant')->hasTable('seo_meta'),
            'Tabel seo_meta tidak dibuat di DB tenant — migrasi SEO Pro belum jalan.'
        );
    }

    /** Meta manual (kolom lama) TIDAK boleh tertimpa fallback plugin. */
    public function test_meta_manual_tidak_tertimpa(): void
    {
        $tenant = $this->createRuntimeTenant('seo-manual', 'Pesantren SEO Manual');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        $article = Article::create([
            'tenant_id' => $tenant->id,
            'title' => 'Judul Artikel',
            'slug' => 'judul-artikel',
            'excerpt' => 'Ringkasan otomatis yang tidak boleh dipakai.',
            'content' => 'Isi.',
            'is_published' => true,
            'published_at' => now(),
            'meta_title' => 'Meta Title Editor',
            'meta_description' => 'Deskripsi meta yang ditulis editor secara manual.',
        ]);

        $seo = app(TenantWebsiteService::class)->buildArticleSeoPayload($article);

        $this->assertSame('Meta Title Editor', $seo['title']);
        $this->assertStringContainsString('ditulis editor secara manual', $seo['description']);
    }

    /** Relasi seo (plugin) PALING spesifik — menang atas kolom lama & fallback. */
    public function test_relasi_seo_plugin_diprioritaskan(): void
    {
        $tenant = $this->createRuntimeTenant('seo-plugin', 'Pesantren SEO Plugin');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        $article = Article::create([
            'tenant_id' => $tenant->id,
            'title' => 'Judul',
            'slug' => 'judul-plugin',
            'excerpt' => 'Ringkasan.',
            'content' => 'Isi.',
            'is_published' => true,
            'published_at' => now(),
            'meta_title' => 'Meta Lama',
            'featured_image_url' => 'cms/articles/foto.jpg',
        ]);
        $article->seo()->create([
            'title' => 'Judul SEO Pro',
            'description' => 'Deskripsi SEO Pro yang paling spesifik.',
            'robots' => 'noindex, follow',
            'og_image' => 'https://cdn.example.test/og.jpg',
            'schema_type' => 'Article',
        ]);
        $article->refresh();

        $seo = app(TenantWebsiteService::class)->buildArticleSeoPayload($article);

        $this->assertSame('Judul SEO Pro', $seo['title']);
        $this->assertStringContainsString('paling spesifik', $seo['description']);
        $this->assertSame('noindex, follow', $seo['robots']);
        $this->assertSame('https://cdn.example.test/og.jpg', $seo['imageUrl']);
        $this->assertSame('Article', $seo['schema']['@type']);
    }

    /** Artikel tanpa meta apa pun → fallback dari judul + excerpt + featured image. */
    public function test_fallback_dari_excerpt_dan_judul(): void
    {
        $tenant = $this->createRuntimeTenant('seo-fallback', 'Pesantren SEO Fallback');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        $article = Article::create([
            'tenant_id' => $tenant->id,
            'title' => 'Judul Tanpa Meta',
            'slug' => 'tanpa-meta',
            'excerpt' => 'Ringkasan artikel dipakai sebagai deskripsi meta.',
            'content' => 'Isi artikel.',
            'is_published' => true,
            'published_at' => now(),
            'featured_image_url' => 'cms/articles/foto.jpg',
        ]);

        $seo = app(TenantWebsiteService::class)->buildArticleSeoPayload($article);

        $this->assertSame('Judul Tanpa Meta', $seo['title']);
        $this->assertStringContainsString('Ringkasan artikel dipakai', $seo['description']);
        // OG image jatuh ke featured image (di-serve via /_tenant-storage).
        $this->assertStringContainsString('cms/articles/foto.jpg', (string) $seo['imageUrl']);
        $this->assertSame('BlogPosting', $seo['schema']['@type']);
    }

    /** Landing membaca branding tenant aktif: schema Organization + OG image dari logo. */
    public function test_landing_schema_dan_og_per_tenant(): void
    {
        $tenant = $this->createRuntimeTenant('seo-landing', 'Pesantren SEO Landing');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        Setting::create(['tenant_id' => $tenant->id, 'key' => 'branding_name', 'value' => ['text' => 'Pondok Tenant Aktif']]);
        Setting::create(['tenant_id' => $tenant->id, 'key' => 'branding_logo', 'value' => ['text' => 'branding/logo.png']]);

        $payload = app(TenantWebsiteService::class)->buildLandingPagePayload();

        $this->assertSame('Pondok Tenant Aktif', $payload['seo']['siteName']);
        $this->assertSame('EducationalOrganization', $payload['seo']['schema']['@type']);
        $this->assertSame('Pondok Tenant Aktif', $payload['seo']['schema']['name']);
        // OG image + schema logo jatuh ke logo brand tenant.
        $this->assertStringContainsString('branding/logo.png', (string) $payload['seo']['imageUrl']);
        $this->assertStringContainsString('branding/logo.png', (string) $payload['seo']['schema']['logo']);
    }
}
