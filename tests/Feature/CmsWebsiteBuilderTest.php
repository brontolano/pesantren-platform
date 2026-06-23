<?php

namespace Tests\Feature;

use Modules\CMS\Filament\Pages\WebsiteBuilder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\CMS\Models\WebsiteSection;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class CmsWebsiteBuilderTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->configurePpdbTestingDatabase();

        $this->truncatePpdbTables([
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
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    public function test_website_builder_requires_manage_tenant_settings_permission(): void
    {
        $tenant = $this->createTestTenant('cms-builder-access', 'Pesantren Builder Access', 'cms-builder-access');

        tenancy()->initialize($tenant);

        $wali = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Wali Builder',
            'email' => 'wali-builder@test.local',
            'password' => 'password',
        ]);
        $wali->assignRole('wali');

        $this->actingAs($wali);

        $this->assertFalse(WebsiteBuilder::canAccess());
        tenancy()->end();
    }

    public function test_website_builder_can_persist_section_content_toggle_and_order(): void
    {
        $tenant = $this->createTestTenant('cms-builder', 'Pesantren Builder', 'cms-builder');

        tenancy()->initialize($tenant);

        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Builder',
            'email' => 'admin-builder@test.local',
            'password' => 'password',
        ]);
        $admin->assignRole('super_admin');

        WebsiteSection::create([
            'tenant_id' => $tenant->id,
            'section_type' => 'hero',
            'sort_order' => 1,
            'is_active' => true,
            'config' => [
                'title' => 'Hero Lama',
                'description' => 'Deskripsi lama',
            ],
        ]);

        WebsiteSection::create([
            'tenant_id' => $tenant->id,
            'section_type' => 'faq',
            'sort_order' => 5,
            'is_active' => true,
            'config' => [
                'title' => 'FAQ Lama',
                'items' => [
                    ['question' => 'Q1', 'answer' => 'A1'],
                ],
            ],
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(WebsiteBuilder::class);
        $sections = $component->get('sections');

        $sections[0]['config']['title'] = 'Hero Builder Baru';
        $sections[0]['config']['description'] = 'Deskripsi builder baru';
        $sections[0]['config']['stats_text'] = "Program|Muadalah\nBahasa|Arab";
        $sections[1]['config']['mission_text'] = "Misi 1\nMisi 2";
        $sections[1]['config']['highlights_text'] = "Highlight 1\nHighlight 2";
        $sections[3]['is_active'] = false;
        $sections[4]['config']['title'] = 'FAQ Builder';
        $sections[4]['config']['items_text'] = "Kapan daftar?|Sekarang\nTes masuk?|Ada";

        $reordered = [
            $sections[4],
            $sections[0],
            $sections[1],
            $sections[2],
            $sections[3],
            $sections[5],
            $sections[6],
        ];

        $component
            ->set('sections', $reordered)
            ->call('saveSections')
            ->assertHasNoErrors();

        $faq = WebsiteSection::query()->where('section_type', 'faq')->firstOrFail();
        $hero = WebsiteSection::query()->where('section_type', 'hero')->firstOrFail();
        $profile = WebsiteSection::query()->where('section_type', 'profile')->firstOrFail();
        $gallery = WebsiteSection::query()->where('section_type', 'gallery')->firstOrFail();

        $this->assertSame(1, $faq->sort_order);
        $this->assertSame('FAQ Builder', $faq->config['title']);
        $this->assertCount(2, $faq->config['items']);
        $this->assertSame(2, $hero->sort_order);
        $this->assertSame('Hero Builder Baru', $hero->config['title']);
        $this->assertCount(2, $hero->config['stats']);
        $this->assertSame(['Misi 1', 'Misi 2'], $profile->config['mission']);
        $this->assertFalse($gallery->is_active);

        tenancy()->end();

        $response = $this->call('GET', 'http://cms-builder.localhost/', [], [], [], [
            'HTTP_HOST' => 'cms-builder.localhost',
            'SERVER_NAME' => 'cms-builder.localhost',
        ]);

        $response->assertOk();
        $response->assertSee('FAQ Builder');
        $response->assertSee('Hero Builder Baru');
        $response->assertDontSee('Galeri Kegiatan Pesantren');

        $pageSections = $response->viewData('page')['props']['website']['sections'];
        $this->assertSame('faq', $pageSections[0]['type']);
        $this->assertSame('hero', $pageSections[1]['type']);
    }
}
