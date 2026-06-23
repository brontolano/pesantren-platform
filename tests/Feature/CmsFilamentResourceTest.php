<?php

namespace Tests\Feature;

use Modules\CMS\Filament\Resources\Article\ArticleResource;
use Modules\CMS\Filament\Resources\Gallery\GalleryResource;
use Modules\CMS\Filament\Resources\Profile\ProfileResource;
use Modules\CMS\Filament\Resources\WebsiteSetting\WebsiteSettingResource;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\CMS\Models\Article;
use Modules\CMS\Models\Gallery;
use Modules\CMS\Models\WebsiteSection;
use Modules\CMS\Models\WebsiteSetting;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * T2.4.17 — CMS Filament resources & branding tests
 *
 * Covers:
 * - T2.4.13: Filament routes for Article, Gallery, WebsiteSetting accessible
 * - T2.4.15: branding.logoUrl & branding.favicon sent from TenantWebsiteService
 * - T2.4.16: website tenant pages do not leak platform brand strings
 * - T2.4.17: website builder section toggle reflected in landing page
 */
class CmsFilamentResourceTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->configurePpdbTestingDatabase();

        if (! Schema::connection('pgsql')->hasTable('website_sections')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        $this->truncatePpdbTables([
            'articles',
            'gallery_images',
            'galleries',
            'website_sections',
            'website_settings',
            'settings',
            'role_has_permissions',
            'model_has_roles',
            'model_has_permissions',
            'roles',
            'permissions',
            'users',
            'domains',
            'tenants',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_cms_manager_can_access_cms_resource_routes(): void
    {
        $tenant = $this->createTestTenant('cms-admin', 'Pesantren CMS Admin', 'cms-admin');

        tenancy()->initialize($tenant);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Super Admin CMS',
            'email' => 'cms-admin@localhost.test',
            'password' => 'password',
        ]);
        $user->assignRole('super_admin');

        $this->actingAs($user);

        $this->assertTrue(ArticleResource::canAccess());
        $this->assertTrue(GalleryResource::canAccess());
        $this->assertTrue(ProfileResource::canAccess());
        $this->assertTrue(WebsiteSettingResource::canAccess());

        $this->assertTrue(Route::has('filament.admin.resources.c-m-s.article.articles.index'));
        $this->assertTrue(Route::has('filament.admin.resources.c-m-s.gallery.galleries.index'));
        $this->assertTrue(Route::has('filament.admin.resources.c-m-s.profile.profiles.index'));
        $this->assertTrue(Route::has('filament.admin.resources.c-m-s.website-setting.website-settings.index'));

        tenancy()->end();
    }

    public function test_cms_resources_only_query_current_tenant_records(): void
    {
        $tenantA = $this->createTestTenant('cms-query-a', 'Pesantren CMS A', 'cms-query-a');
        $tenantB = $this->createTestTenant('cms-query-b', 'Pesantren CMS B', 'cms-query-b');

        tenancy()->initialize($tenantA);
        Article::create([
            'tenant_id' => $tenantA->id,
            'title' => 'Artikel Tenant A',
            'slug' => 'artikel-tenant-a',
        ]);
        Gallery::create([
            'tenant_id' => $tenantA->id,
            'title' => 'Galeri Tenant A',
            'slug' => 'galeri-tenant-a',
        ]);
        WebsiteSection::create([
            'tenant_id' => $tenantA->id,
            'section_type' => 'profile',
            'sort_order' => 2,
            'is_active' => true,
            'config' => ['title' => 'Profil Tenant A'],
        ]);
        WebsiteSetting::create([
            'tenant_id' => $tenantA->id,
            'key' => 'contact',
            'value' => ['phone' => '0811111111'],
        ]);
        tenancy()->end();

        tenancy()->initialize($tenantB);
        Article::create([
            'tenant_id' => $tenantB->id,
            'title' => 'Artikel Tenant B',
            'slug' => 'artikel-tenant-b',
        ]);
        Gallery::create([
            'tenant_id' => $tenantB->id,
            'title' => 'Galeri Tenant B',
            'slug' => 'galeri-tenant-b',
        ]);
        WebsiteSection::create([
            'tenant_id' => $tenantB->id,
            'section_type' => 'profile',
            'sort_order' => 2,
            'is_active' => true,
            'config' => ['title' => 'Profil Tenant B'],
        ]);
        WebsiteSetting::create([
            'tenant_id' => $tenantB->id,
            'key' => 'contact',
            'value' => ['phone' => '0822222222'],
        ]);

        $this->assertCount(1, ArticleResource::getEloquentQuery()->get());
        $this->assertSame('Artikel Tenant B', ArticleResource::getEloquentQuery()->first()->title);
        $this->assertCount(1, GalleryResource::getEloquentQuery()->get());
        $this->assertSame('Galeri Tenant B', GalleryResource::getEloquentQuery()->first()->title);
        $this->assertCount(1, ProfileResource::getEloquentQuery()->get());
        $this->assertSame('Profil Tenant B', ProfileResource::getEloquentQuery()->first()->config['title']);
        $this->assertCount(1, WebsiteSettingResource::getEloquentQuery()->get());
        $this->assertSame('0822222222', WebsiteSettingResource::getEloquentQuery()->first()->value['phone']);

        tenancy()->end();
    }

    // -----------------------------------------------------------------------
    // T2.4.15 — branding.logoUrl and branding.favicon in landing page payload
    // -----------------------------------------------------------------------

    public function test_branding_logo_url_is_sent_in_website_payload(): void
    {
        $tenant = $this->createTestTenant('cms-logo', 'Pesantren Logo', 'cms-logo');

        tenancy()->initialize($tenant);
        Setting::create([
            'tenant_id' => $tenant->id,
            'key' => 'branding_logo',
            'value' => ['text' => 'https://example.test/logo-pesantren.png'],
        ]);
        Setting::create([
            'tenant_id' => $tenant->id,
            'key' => 'branding_primary_color',
            'value' => ['text' => '#0f766e'],
        ]);
        Setting::create([
            'tenant_id' => $tenant->id,
            'key' => 'branding_secondary_color',
            'value' => ['text' => '#9a3412'],
        ]);
        Setting::create([
            'tenant_id' => $tenant->id,
            'key' => 'branding_font',
            'value' => ['text' => 'serif'],
        ]);
        tenancy()->end();

        $response = $this->call('GET', 'http://cms-logo.localhost/', [], [], [], [
            'HTTP_HOST' => 'cms-logo.localhost',
            'SERVER_NAME' => 'cms-logo.localhost',
        ]);

        $response->assertOk();

        $branding = $response->viewData('page')['props']['website']['branding'];

        $this->assertSame('https://example.test/logo-pesantren.png', $branding['logoUrl']);
        $this->assertSame('#0f766e', $branding['primaryColor']);
        $this->assertSame('#9a3412', $branding['secondaryColor']);
        $this->assertSame('serif', $branding['font']);
        $this->assertStringContainsString('Georgia', $branding['fontFamily']);
        $this->assertArrayHasKey('favicon', $branding);
        $this->assertArrayHasKey('font', $branding);
    }

    public function test_branding_favicon_is_sent_in_website_payload(): void
    {
        $tenant = $this->createTestTenant('cms-favicon', 'Pesantren Favicon', 'cms-favicon');

        tenancy()->initialize($tenant);
        Setting::create([
            'tenant_id' => $tenant->id,
            'key' => 'branding_favicon',
            'value' => ['text' => 'https://example.test/favicon-pesantren.ico'],
        ]);
        tenancy()->end();

        $response = $this->call('GET', 'http://cms-favicon.localhost/', [], [], [], [
            'HTTP_HOST' => 'cms-favicon.localhost',
            'SERVER_NAME' => 'cms-favicon.localhost',
        ]);

        $response->assertOk();

        $branding = $response->viewData('page')['props']['website']['branding'];

        $this->assertSame('https://example.test/favicon-pesantren.ico', $branding['favicon']);
    }

    public function test_branding_defaults_to_null_logo_when_not_set(): void
    {
        $this->createTestTenant('cms-no-logo', 'Pesantren NoLogo', 'cms-no-logo');

        $response = $this->call('GET', 'http://cms-no-logo.localhost/', [], [], [], [
            'HTTP_HOST' => 'cms-no-logo.localhost',
            'SERVER_NAME' => 'cms-no-logo.localhost',
        ]);

        $response->assertOk();

        $branding = $response->viewData('page')['props']['website']['branding'];

        $this->assertNull($branding['logoUrl']);
        $this->assertNull($branding['favicon']);
    }

    // -----------------------------------------------------------------------
    // T2.4.16 — White-label: platform brand strings must not appear on tenant website
    // -----------------------------------------------------------------------

    public function test_tenant_website_does_not_expose_platform_brand_in_response(): void
    {
        $this->createTestTenant('cms-whitelabel', 'Asy-Syifaa', 'cms-whitelabel');

        $response = $this->call('GET', 'http://cms-whitelabel.localhost/', [], [], [], [
            'HTTP_HOST' => 'cms-whitelabel.localhost',
            'SERVER_NAME' => 'cms-whitelabel.localhost',
        ]);

        $response->assertOk();

        // Platform brand must NOT appear in the tenant page output
        $response->assertDontSee('Pesantren Platform');
        $response->assertDontSee('pesantren-platform');
        $response->assertDontSee('Multi-Tenant SaaS');
    }

    public function test_article_detail_does_not_expose_platform_brand(): void
    {
        $tenant = $this->createTestTenant('cms-wl-article', 'Pesantren WL', 'cms-wl-article');

        tenancy()->initialize($tenant);
        Article::create([
            'tenant_id' => $tenant->id,
            'title' => 'Kegiatan Pesantren WL',
            'slug' => 'kegiatan-pesantren-wl',
            'content' => 'Konten kegiatan santri.',
            'is_published' => true,
            'published_at' => now(),
        ]);
        tenancy()->end();

        $response = $this->call('GET', 'http://cms-wl-article.localhost/artikel/kegiatan-pesantren-wl', [], [], [], [
            'HTTP_HOST' => 'cms-wl-article.localhost',
            'SERVER_NAME' => 'cms-wl-article.localhost',
        ]);

        $response->assertOk();
        $response->assertDontSee('Pesantren Platform');
        $response->assertDontSee('pesantren-platform');
    }

    // -----------------------------------------------------------------------
    // T2.4.17 — Section toggle reflected in landing page
    // -----------------------------------------------------------------------

    public function test_website_builder_section_toggle_persists_to_landing_page(): void
    {
        $tenant = $this->createTestTenant('cms-toggle', 'Pesantren Toggle', 'cms-toggle');

        tenancy()->initialize($tenant);

        // Hero aktif, gallery nonaktif
        WebsiteSection::create([
            'tenant_id' => $tenant->id,
            'section_type' => 'hero',
            'sort_order' => 1,
            'is_active' => true,
            'config' => ['title' => 'Hero Aktif Toggle', 'description' => 'Desc hero'],
        ]);
        WebsiteSection::create([
            'tenant_id' => $tenant->id,
            'section_type' => 'gallery',
            'sort_order' => 2,
            'is_active' => false,
            'config' => ['title' => 'Galeri Nonaktif'],
        ]);

        tenancy()->end();

        $response = $this->call('GET', 'http://cms-toggle.localhost/', [], [], [], [
            'HTTP_HOST' => 'cms-toggle.localhost',
            'SERVER_NAME' => 'cms-toggle.localhost',
        ]);

        $response->assertOk();

        $sections = $response->viewData('page')['props']['website']['sections'];
        $types = collect($sections)->pluck('type');

        $this->assertContains('hero', $types->all());
        $this->assertNotContains('gallery', $types->all());
    }

    public function test_section_sort_order_reflected_in_landing_page(): void
    {
        $tenant = $this->createTestTenant('cms-order', 'Pesantren Order', 'cms-order');

        tenancy()->initialize($tenant);

        WebsiteSection::create([
            'tenant_id' => $tenant->id,
            'section_type' => 'faq',
            'sort_order' => 1,
            'is_active' => true,
            'config' => ['title' => 'FAQ Dulu'],
        ]);
        WebsiteSection::create([
            'tenant_id' => $tenant->id,
            'section_type' => 'hero',
            'sort_order' => 2,
            'is_active' => true,
            'config' => ['title' => 'Hero Belakang'],
        ]);

        tenancy()->end();

        $response = $this->call('GET', 'http://cms-order.localhost/', [], [], [], [
            'HTTP_HOST' => 'cms-order.localhost',
            'SERVER_NAME' => 'cms-order.localhost',
        ]);

        $response->assertOk();

        $sections = $response->viewData('page')['props']['website']['sections'];

        $this->assertSame('faq', $sections[0]['type']);
        $this->assertSame('hero', $sections[1]['type']);
    }
}
