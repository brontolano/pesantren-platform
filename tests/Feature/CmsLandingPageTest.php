<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\CMS\Models\Article;
use Modules\CMS\Models\Gallery;
use Modules\CMS\Models\GalleryImage;
use Modules\CMS\Models\WebsiteSection;
use Modules\CMS\Models\WebsiteSetting;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class CmsLandingPageTest extends TestCase
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
            'domains',
            'tenants',
        ]);
    }

    public function test_tenant_root_uses_cms_landing_page_component(): void
    {
        $this->createTestTenant('cms-home', 'Pesantren CMS', 'cms-home');

        $response = $this
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->call('GET', 'http://cms-home.localhost/', [], [], [], [
                'HTTP_HOST' => 'cms-home.localhost',
                'SERVER_NAME' => 'cms-home.localhost',
            ]);

        $response->assertOk();
        $response->assertSee('CMS\\/LandingPage', false);
        $response->assertSee('Pesantren CMS');
    }

    public function test_landing_page_only_exposes_active_sections_in_defined_order(): void
    {
        $tenant = $this->createTestTenant('cms-section', 'Pesantren Section', 'cms-section');

        tenancy()->initialize($tenant);

        WebsiteSection::create([
            'tenant_id' => $tenant->id,
            'section_type' => 'profile',
            'sort_order' => 2,
            'is_active' => true,
            'config' => [
                'title' => 'Profil Pesantren',
                'body' => 'Konten profil aktif.',
            ],
        ]);

        WebsiteSection::create([
            'tenant_id' => $tenant->id,
            'section_type' => 'hero',
            'sort_order' => 1,
            'is_active' => true,
            'config' => [
                'title' => 'Hero Aktif',
                'description' => 'Hero section tenant aktif.',
            ],
        ]);

        WebsiteSection::create([
            'tenant_id' => $tenant->id,
            'section_type' => 'faq',
            'sort_order' => 3,
            'is_active' => false,
            'config' => [
                'title' => 'FAQ Nonaktif',
            ],
        ]);

        tenancy()->end();

        $response = $this->call('GET', 'http://cms-section.localhost/', [], [], [], [
            'HTTP_HOST' => 'cms-section.localhost',
            'SERVER_NAME' => 'cms-section.localhost',
        ]);

        $response->assertOk();

        $page = $response->viewData('page');
        $sections = $page['props']['website']['sections'];

        $this->assertCount(2, $sections);
        $this->assertSame('hero', $sections[0]['type']);
        $this->assertSame('profile', $sections[1]['type']);
    }

    public function test_profile_section_exposes_about_vision_mission_and_contact_from_tenant_config(): void
    {
        $tenant = $this->createTestTenant('cms-profile', 'Pesantren Profil', 'cms-profile');

        tenancy()->initialize($tenant);

        WebsiteSetting::create([
            'tenant_id' => $tenant->id,
            'key' => 'contact',
            'value' => [
                'address' => 'Jl. Raya Pesantren 99',
                'phone' => '082233445566',
                'email' => 'info@profil.test',
            ],
        ]);

        WebsiteSection::create([
            'tenant_id' => $tenant->id,
            'section_type' => 'profile',
            'sort_order' => 1,
            'is_active' => true,
            'config' => [
                'title' => 'Profil Pesantren',
                'about' => 'Pesantren menyiapkan santri dengan adab, ilmu, dan kepemimpinan.',
                'vision' => 'Menjadi pesantren rujukan yang melahirkan kader umat berakhlak.',
                'mission' => [
                    'Menguatkan tahfidz dan diniyah.',
                    'Membina karakter santri melalui disiplin harian.',
                ],
                'highlights' => [
                    'Program bilingual Arab-Inggris',
                ],
            ],
        ]);

        tenancy()->end();

        $response = $this->call('GET', 'http://cms-profile.localhost/', [], [], [], [
            'HTTP_HOST' => 'cms-profile.localhost',
            'SERVER_NAME' => 'cms-profile.localhost',
        ]);

        $response->assertOk();
        $response->assertSee('Pesantren menyiapkan santri dengan adab, ilmu, dan kepemimpinan.');
        $response->assertSee('Menjadi pesantren rujukan yang melahirkan kader umat berakhlak.');
        $response->assertSee('Menguatkan tahfidz dan diniyah.');
        $response->assertSee('Jl. Raya Pesantren 99');

        $profile = $response->viewData('page')['props']['website']['sections'][0];

        $this->assertSame('profile', $profile['type']);
        $this->assertSame('Pesantren menyiapkan santri dengan adab, ilmu, dan kepemimpinan.', $profile['config']['about']);
        $this->assertSame('Menjadi pesantren rujukan yang melahirkan kader umat berakhlak.', $profile['config']['vision']);
        $this->assertSame('082233445566', $profile['config']['contact']['phone']);
        $this->assertCount(2, $profile['config']['mission']);
    }

    public function test_hero_section_config_is_normalized_and_rendered_from_tenant_config(): void
    {
        $tenant = $this->createTestTenant('cms-hero', 'Pesantren Hero', 'cms-hero');

        tenancy()->initialize($tenant);

        WebsiteSection::create([
            'tenant_id' => $tenant->id,
            'section_type' => 'hero',
            'sort_order' => 1,
            'is_active' => true,
            'config' => [
                'eyebrow' => 'Penerimaan Santri Baru 2026',
                'title' => 'Belajar adab, ilmu, dan kepemimpinan dalam satu lingkungan pesantren.',
                'imageUrl' => 'https://example.test/hero-cms.jpg',
                'primaryCtaLabel' => 'Gabung Sekarang',
                'primaryCtaHref' => '/ppdb',
                'stats' => [
                    ['label' => 'Program', 'value' => 'Muadalah'],
                    ['label' => 'Asrama', 'value' => 'Putra & Putri'],
                    ['label' => 'Bahasa', 'value' => 'Arab & Inggris'],
                ],
            ],
        ]);

        tenancy()->end();

        $response = $this->call('GET', 'http://cms-hero.localhost/', [], [], [], [
            'HTTP_HOST' => 'cms-hero.localhost',
            'SERVER_NAME' => 'cms-hero.localhost',
        ]);

        $response->assertOk();
        $response->assertSee('Penerimaan Santri Baru 2026');
        $response->assertSee('Belajar adab, ilmu, dan kepemimpinan dalam satu lingkungan pesantren.');
        $response->assertSee('Gabung Sekarang');

        $hero = $response->viewData('page')['props']['website']['sections'][0];

        $this->assertSame('hero', $hero['type']);
        $this->assertSame('https://example.test/hero-cms.jpg', $hero['config']['imageUrl']);
        $this->assertSame('Belajar adab, ilmu, dan kepemimpinan dalam satu lingkungan pesantren.', $hero['config']['imageAlt']);
        $this->assertCount(3, $hero['config']['stats']);
    }

    public function test_landing_page_branding_and_contact_are_scoped_to_current_tenant(): void
    {
        $tenantA = $this->createTestTenant('brand-a', 'Pesantren A', 'brand-a');
        $tenantB = $this->createTestTenant('brand-b', 'Pesantren B', 'brand-b');

        tenancy()->initialize($tenantA);
        Setting::create([
            'tenant_id' => $tenantA->id,
            'key' => 'branding_name',
            'value' => ['text' => 'Asy-Syifaa Digital'],
        ]);
        WebsiteSetting::create([
            'tenant_id' => $tenantA->id,
            'key' => 'contact',
            'value' => [
                'address' => 'Jl. Pesantren No. 1',
                'phone' => '08123456789',
            ],
        ]);
        tenancy()->end();

        tenancy()->initialize($tenantB);
        Setting::create([
            'tenant_id' => $tenantB->id,
            'key' => 'branding_name',
            'value' => ['text' => 'Tenant B'],
        ]);
        WebsiteSetting::create([
            'tenant_id' => $tenantB->id,
            'key' => 'contact',
            'value' => [
                'address' => 'Jl. Tenant B',
            ],
        ]);
        tenancy()->end();

        $response = $this->call('GET', 'http://brand-a.localhost/', [], [], [], [
            'HTTP_HOST' => 'brand-a.localhost',
            'SERVER_NAME' => 'brand-a.localhost',
        ]);

        $response->assertOk();

        $website = $response->viewData('page')['props']['website'];

        $this->assertSame('Asy-Syifaa Digital', $website['branding']['name']);
        $this->assertSame('Jl. Pesantren No. 1', $website['contact']['address']);
        $this->assertSame('08123456789', $website['contact']['phone']);
        $this->assertSame('Asy-Syifaa Digital', $website['seo']['title']);
        $this->assertSame('http://brand-a.localhost/sitemap.xml', $website['seo']['sitemapUrl']);
    }

    public function test_blog_section_exposes_only_current_tenant_published_articles(): void
    {
        $tenantA = $this->createTestTenant('cms-blog-a', 'Pesantren Blog A', 'cms-blog-a');
        $tenantB = $this->createTestTenant('cms-blog-b', 'Pesantren Blog B', 'cms-blog-b');

        tenancy()->initialize($tenantA);
        WebsiteSection::create([
            'tenant_id' => $tenantA->id,
            'section_type' => 'blog',
            'sort_order' => 3,
            'is_active' => true,
            'config' => [
                'title' => 'Kabar Terkini',
                'description' => 'Update kegiatan santri dan pesantren.',
                'limit' => 4,
            ],
        ]);
        Article::create([
            'tenant_id' => $tenantA->id,
            'title' => 'Mabit Akbar Santri',
            'slug' => 'mabit-akbar-santri',
            'excerpt' => 'Santri mengikuti mabit bersama pengasuh.',
            'content' => 'Konten mabit akbar santri.',
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);
        Article::create([
            'tenant_id' => $tenantA->id,
            'title' => 'Draft Internal',
            'slug' => 'draft-internal',
            'excerpt' => 'Belum tayang.',
            'content' => 'Artikel ini belum dipublikasi.',
            'is_published' => false,
        ]);
        tenancy()->end();

        tenancy()->initialize($tenantB);
        Article::create([
            'tenant_id' => $tenantB->id,
            'title' => 'Artikel Tenant B',
            'slug' => 'artikel-tenant-b',
            'excerpt' => 'Tidak boleh bocor ke tenant lain.',
            'content' => 'Konten tenant B.',
            'is_published' => true,
            'published_at' => now(),
        ]);
        tenancy()->end();

        $response = $this->call('GET', 'http://cms-blog-a.localhost/', [], [], [], [
            'HTTP_HOST' => 'cms-blog-a.localhost',
            'SERVER_NAME' => 'cms-blog-a.localhost',
        ]);

        $response->assertOk();
        $response->assertSee('Mabit Akbar Santri');
        $response->assertDontSee('Artikel Tenant B');

        $sections = $response->viewData('page')['props']['website']['sections'];
        $blogSection = collect($sections)->firstWhere('type', 'blog');

        $this->assertNotNull($blogSection);
        $this->assertSame('Kabar Terkini', $blogSection['config']['title']);
        $this->assertCount(1, $blogSection['config']['articles']);
        $this->assertSame('/artikel/mabit-akbar-santri', $blogSection['config']['articles'][0]['href']);
    }

    public function test_article_detail_route_renders_published_article_for_current_tenant(): void
    {
        $tenant = $this->createTestTenant('cms-article', 'Pesantren Artikel', 'cms-article');

        tenancy()->initialize($tenant);
        Article::create([
            'tenant_id' => $tenant->id,
            'title' => 'Wisuda Tahfidz 2026',
            'slug' => 'wisuda-tahfidz-2026',
            'excerpt' => 'Wisuda tahfidz angkatan 2026 berlangsung khidmat.',
            'content' => 'Alhamdulillah, wisuda tahfidz berjalan dengan khidmat dan dihadiri wali santri.',
            'featured_image_url' => 'https://example.test/wisuda.jpg',
            'is_published' => true,
            'published_at' => now()->subHours(3),
        ]);
        tenancy()->end();

        $response = $this
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->call('GET', 'http://cms-article.localhost/artikel/wisuda-tahfidz-2026', [], [], [], [
                'HTTP_HOST' => 'cms-article.localhost',
                'SERVER_NAME' => 'cms-article.localhost',
            ]);

        $response->assertOk();
        $response->assertSee('CMS\\/ArticleDetail', false);
        $response->assertSee('Wisuda Tahfidz 2026');
        $response->assertSee('wisuda tahfidz berjalan dengan khidmat', false);
    }

    public function test_gallery_section_exposes_only_current_tenant_published_galleries_with_images(): void
    {
        $tenantA = $this->createTestTenant('cms-gallery-a', 'Pesantren Galeri A', 'cms-gallery-a');
        $tenantB = $this->createTestTenant('cms-gallery-b', 'Pesantren Galeri B', 'cms-gallery-b');

        tenancy()->initialize($tenantA);
        WebsiteSection::create([
            'tenant_id' => $tenantA->id,
            'section_type' => 'gallery',
            'sort_order' => 4,
            'is_active' => true,
            'config' => [
                'title' => 'Galeri Santri',
                'description' => 'Dokumentasi kegiatan santri setiap pekan.',
                'limit' => 2,
            ],
        ]);
        $galleryA = Gallery::create([
            'tenant_id' => $tenantA->id,
            'title' => 'Rihlah Tarbawiyah',
            'slug' => 'rihlah-tarbawiyah',
            'description' => 'Kegiatan rihlah dan tadabbur alam santri.',
            'cover_image_url' => 'https://example.test/gallery-a-cover.jpg',
            'is_published' => true,
            'published_at' => now()->subHours(5),
        ]);
        GalleryImage::create([
            'tenant_id' => $tenantA->id,
            'gallery_id' => $galleryA->id,
            'image_url' => 'https://example.test/gallery-a-1.jpg',
            'caption' => 'Santri berkegiatan di alam terbuka.',
            'sort_order' => 1,
            'is_featured' => true,
        ]);
        GalleryImage::create([
            'tenant_id' => $tenantA->id,
            'gallery_id' => $galleryA->id,
            'image_url' => 'https://example.test/gallery-a-2.jpg',
            'caption' => 'Tadabbur bersama musyrif.',
            'sort_order' => 2,
        ]);
        Gallery::create([
            'tenant_id' => $tenantA->id,
            'title' => 'Draft Galeri',
            'slug' => 'draft-galeri',
            'description' => 'Belum tayang.',
            'is_published' => false,
        ]);
        tenancy()->end();

        tenancy()->initialize($tenantB);
        $galleryB = Gallery::create([
            'tenant_id' => $tenantB->id,
            'title' => 'Galeri Tenant B',
            'slug' => 'galeri-tenant-b',
            'description' => 'Tidak boleh bocor ke tenant A.',
            'is_published' => true,
            'published_at' => now(),
        ]);
        GalleryImage::create([
            'tenant_id' => $tenantB->id,
            'gallery_id' => $galleryB->id,
            'image_url' => 'https://example.test/gallery-b-1.jpg',
            'caption' => 'Konten tenant lain.',
            'sort_order' => 1,
        ]);
        tenancy()->end();

        $response = $this->call('GET', 'http://cms-gallery-a.localhost/', [], [], [], [
            'HTTP_HOST' => 'cms-gallery-a.localhost',
            'SERVER_NAME' => 'cms-gallery-a.localhost',
        ]);

        $response->assertOk();
        $response->assertSee('Rihlah Tarbawiyah');
        $response->assertDontSee('Galeri Tenant B');

        $sections = $response->viewData('page')['props']['website']['sections'];
        $gallerySection = collect($sections)->firstWhere('type', 'gallery');

        $this->assertNotNull($gallerySection);
        $this->assertSame('Galeri Santri', $gallerySection['config']['title']);
        $this->assertCount(1, $gallerySection['config']['galleries']);
        $this->assertSame('https://example.test/gallery-a-cover.jpg', $gallerySection['config']['galleries'][0]['coverImageUrl']);
        $this->assertCount(2, $gallerySection['config']['galleries'][0]['images']);
        $this->assertSame('Santri berkegiatan di alam terbuka.', $gallerySection['config']['galleries'][0]['images'][0]['caption']);
    }

    public function test_faq_section_exposes_only_valid_items_from_current_tenant_config(): void
    {
        $tenant = $this->createTestTenant('cms-faq', 'Pesantren FAQ', 'cms-faq');

        tenancy()->initialize($tenant);
        WebsiteSection::create([
            'tenant_id' => $tenant->id,
            'section_type' => 'faq',
            'sort_order' => 5,
            'is_active' => true,
            'config' => [
                'title' => 'FAQ PPDB',
                'description' => 'Jawaban cepat sebelum wali mendaftar.',
                'items' => [
                    [
                        'question' => 'Kapan jadwal pendaftaran dibuka?',
                        'answer' => 'Pendaftaran dibuka setiap hari melalui formulir online selama kuota masih tersedia.',
                    ],
                    [
                        'question' => 'Apakah ada tes masuk?',
                        'answer' => 'Ada, jadwal dan jenis ujian akan diinformasikan setelah berkas diverifikasi.',
                    ],
                    [
                        'question' => '',
                        'answer' => 'Item kosong tidak boleh lolos.',
                    ],
                ],
            ],
        ]);
        tenancy()->end();

        $response = $this->call('GET', 'http://cms-faq.localhost/', [], [], [], [
            'HTTP_HOST' => 'cms-faq.localhost',
            'SERVER_NAME' => 'cms-faq.localhost',
        ]);

        $response->assertOk();
        $response->assertSee('FAQ PPDB');
        $response->assertSee('Kapan jadwal pendaftaran dibuka?');
        $response->assertSee('Apakah ada tes masuk?');
        $response->assertDontSee('Item kosong tidak boleh lolos.');

        $sections = $response->viewData('page')['props']['website']['sections'];
        $faqSection = collect($sections)->firstWhere('type', 'faq');

        $this->assertNotNull($faqSection);
        $this->assertSame('FAQ PPDB', $faqSection['config']['title']);
        $this->assertCount(2, $faqSection['config']['items']);
        $this->assertSame('Kapan jadwal pendaftaran dibuka?', $faqSection['config']['items'][0]['question']);
        $this->assertSame('Ada, jadwal dan jenis ujian akan diinformasikan setelah berkas diverifikasi.', $faqSection['config']['items'][1]['answer']);
    }

    public function test_ppdb_cta_section_exposes_tenant_specific_copy_and_links_to_public_ppdb_form(): void
    {
        $tenant = $this->createTestTenant('cms-ppdb', 'Pesantren PPDB', 'cms-ppdb');

        tenancy()->initialize($tenant);
        WebsiteSection::create([
            'tenant_id' => $tenant->id,
            'section_type' => 'ppdb_cta',
            'sort_order' => 6,
            'is_active' => true,
            'config' => [
                'title' => 'Daftar Santri Baru 2026',
                'body' => 'Isi formulir PPDB online untuk memulai proses seleksi dan verifikasi berkas.',
                'buttonLabel' => 'Mulai Pendaftaran',
                'buttonHref' => '/ppdb',
                'helperText' => 'Setelah submit, wali akan menerima link tracking status pendaftaran.',
            ],
        ]);
        tenancy()->end();

        $response = $this->call('GET', 'http://cms-ppdb.localhost/', [], [], [], [
            'HTTP_HOST' => 'cms-ppdb.localhost',
            'SERVER_NAME' => 'cms-ppdb.localhost',
        ]);

        $response->assertOk();
        $response->assertSee('Daftar Santri Baru 2026');
        $response->assertSee('Mulai Pendaftaran');
        $response->assertSee('Setelah submit, wali akan menerima link tracking status pendaftaran.');

        $sections = $response->viewData('page')['props']['website']['sections'];
        $ppdbCtaSection = collect($sections)->firstWhere('type', 'ppdb_cta');

        $this->assertNotNull($ppdbCtaSection);
        $this->assertSame('Daftar Santri Baru 2026', $ppdbCtaSection['config']['title']);
        $this->assertSame('/ppdb', $ppdbCtaSection['config']['buttonHref']);
        $this->assertSame('Mulai Pendaftaran', $ppdbCtaSection['config']['buttonLabel']);
        $this->assertSame('Setelah submit, wali akan menerima link tracking status pendaftaran.', $ppdbCtaSection['config']['helperText']);

        $ppdbResponse = $this
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->call('GET', 'http://cms-ppdb.localhost/ppdb', [], [], [], [
                'HTTP_HOST' => 'cms-ppdb.localhost',
                'SERVER_NAME' => 'cms-ppdb.localhost',
            ]);

        $ppdbResponse->assertOk();
        $ppdbResponse->assertSee('PPDB\\/PublicForm', false);
    }

    public function test_testimonial_section_exposes_tenant_specific_sambutan_pimpinan(): void
    {
        $tenant = $this->createTestTenant('cms-testimonial', 'Pesantren Testimoni', 'cms-testimonial');

        tenancy()->initialize($tenant);
        WebsiteSection::create([
            'tenant_id' => $tenant->id,
            'section_type' => 'testimonial',
            'sort_order' => 6,
            'is_active' => true,
            'config' => [
                'title' => 'Sambutan Abi',
                'description' => 'Pesan ringkas dari pimpinan pesantren untuk wali santri baru.',
                'quote' => 'Kami ingin setiap santri tumbuh dengan adab yang kuat, ilmu yang kokoh, dan jiwa khidmah.',
                'leaderName' => 'KH. Abdullah Mahmud',
                'leaderRole' => 'Pimpinan Pesantren',
                'leaderPhotoUrl' => 'https://example.test/pimpinan.jpg',
                'ctaLabel' => 'Kenal Lebih Dekat',
                'ctaHref' => '#kontak',
                'highlights' => [
                    'Pendampingan harian oleh musyrif dan ustadz.',
                    'Sinergi pembinaan asrama dan kelas.',
                    'Kolaborasi aktif dengan wali santri.',
                ],
            ],
        ]);
        tenancy()->end();

        $response = $this->call('GET', 'http://cms-testimonial.localhost/', [], [], [], [
            'HTTP_HOST' => 'cms-testimonial.localhost',
            'SERVER_NAME' => 'cms-testimonial.localhost',
        ]);

        $response->assertOk();
        $response->assertSee('Sambutan Abi');
        $response->assertSee('KH. Abdullah Mahmud');
        $response->assertSee('Kami ingin setiap santri tumbuh dengan adab yang kuat, ilmu yang kokoh, dan jiwa khidmah.');
        $response->assertSee('Kenal Lebih Dekat');

        $sections = $response->viewData('page')['props']['website']['sections'];
        $testimonialSection = collect($sections)->firstWhere('type', 'testimonial');

        $this->assertNotNull($testimonialSection);
        $this->assertSame('Sambutan Abi', $testimonialSection['config']['title']);
        $this->assertSame('KH. Abdullah Mahmud', $testimonialSection['config']['leaderName']);
        $this->assertSame('https://example.test/pimpinan.jpg', $testimonialSection['config']['leaderPhotoUrl']);
        $this->assertCount(3, $testimonialSection['config']['highlights']);
        $this->assertSame('Kenal Lebih Dekat', $testimonialSection['config']['ctaLabel']);
    }

    public function test_landing_page_seo_uses_tenant_specific_meta_configuration(): void
    {
        $tenant = $this->createTestTenant('cms-seo', 'Pesantren SEO', 'cms-seo');

        tenancy()->initialize($tenant);
        WebsiteSetting::create([
            'tenant_id' => $tenant->id,
            'key' => 'seo',
            'value' => [
                'meta_title' => 'PPDB Pesantren SEO 2026',
                'meta_description' => 'Informasi resmi PPDB dan profil Pesantren SEO untuk wali santri baru.',
                'image_url' => 'https://example.test/seo-cover.jpg',
                'robots' => 'index,follow,max-image-preview:large',
            ],
        ]);
        WebsiteSetting::create([
            'tenant_id' => $tenant->id,
            'key' => 'contact',
            'value' => [
                'address' => 'Jl. SEO Pesantren 10',
                'phone' => '081122334455',
                'email' => 'seo@pesantren.test',
            ],
        ]);
        tenancy()->end();

        $response = $this->call('GET', 'http://cms-seo.localhost/', [], [], [], [
            'HTTP_HOST' => 'cms-seo.localhost',
            'SERVER_NAME' => 'cms-seo.localhost',
        ]);

        $response->assertOk();

        $seo = $response->viewData('page')['props']['website']['seo'];

        $this->assertSame('PPDB Pesantren SEO 2026', $seo['title']);
        $this->assertSame('Informasi resmi PPDB dan profil Pesantren SEO untuk wali santri baru.', $seo['description']);
        $this->assertSame('https://example.test/seo-cover.jpg', $seo['imageUrl']);
        $this->assertSame('index,follow,max-image-preview:large', $seo['robots']);
        $this->assertSame('http://cms-seo.localhost', $seo['canonicalUrl']);
        $this->assertSame('http://cms-seo.localhost/sitemap.xml', $seo['sitemapUrl']);
        $this->assertSame('EducationalOrganization', $seo['schema']['@type']);
    }

    public function test_tenant_sitemap_lists_home_ppdb_and_published_articles_only(): void
    {
        $tenantA = $this->createTestTenant('cms-sitemap-a', 'Pesantren Sitemap A', 'cms-sitemap-a');
        $tenantB = $this->createTestTenant('cms-sitemap-b', 'Pesantren Sitemap B', 'cms-sitemap-b');

        tenancy()->initialize($tenantA);
        Article::create([
            'tenant_id' => $tenantA->id,
            'title' => 'Agenda Dauroh',
            'slug' => 'agenda-dauroh',
            'excerpt' => 'Agenda dauroh santri.',
            'content' => 'Konten artikel tenant A.',
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);
        Article::create([
            'tenant_id' => $tenantA->id,
            'title' => 'Draft Internal Tenant A',
            'slug' => 'draft-internal-tenant-a',
            'excerpt' => 'Tidak boleh tampil.',
            'content' => 'Masih draft.',
            'is_published' => false,
        ]);
        tenancy()->end();

        tenancy()->initialize($tenantB);
        Article::create([
            'tenant_id' => $tenantB->id,
            'title' => 'Artikel Tenant B',
            'slug' => 'artikel-tenant-b',
            'excerpt' => 'Tidak boleh tampil di sitemap tenant A.',
            'content' => 'Konten tenant B.',
            'is_published' => true,
            'published_at' => now(),
        ]);
        tenancy()->end();

        $response = $this->call('GET', 'http://cms-sitemap-a.localhost/sitemap.xml', [], [], [], [
            'HTTP_HOST' => 'cms-sitemap-a.localhost',
            'SERVER_NAME' => 'cms-sitemap-a.localhost',
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('http://cms-sitemap-a.localhost/');
        $response->assertSee('http://cms-sitemap-a.localhost/ppdb');
        $response->assertSee('http://cms-sitemap-a.localhost/artikel/agenda-dauroh');
        $response->assertDontSee('draft-internal-tenant-a');
        $response->assertDontSee('http://cms-sitemap-b.localhost/artikel/artikel-tenant-b');
    }
}
