<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Modules\CMS\Models\Article;
use Modules\CMS\Models\Gallery;
use Modules\CMS\Models\GalleryImage;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Portal PWA Humas (menggantikan HumasDemoController).
 * - 403 tanpa permission manage_santri
 * - 200 dengan permission (role tu — tak ada role "humas" khusus di
 *   RolesAndPermissionsSeeder; lihat docblock HumasController)
 * - Berita & Galeri menampilkan data nyata dari Modules\CMS\Article/Gallery
 */
class HumasModulTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions',
            'articles', 'galleries', 'gallery_images',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('f4humas', 'Pesantren F4 Humas');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);
        tenancy()->end();
    }

    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    public function test_akses_ditolak_403_tanpa_permission_manage_santri(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Guru Biasa', 'email' => 'guru-nonhumas@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('guru');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/humas", $this->headers($host));

        $response->assertStatus(403);
    }

    public function test_akses_diterima_200_dengan_role_tu(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Staf Humas', 'email' => 'humas@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('tu');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/humas", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Humas/Beranda'));
    }

    public function test_halaman_berita_menampilkan_artikel_nyata(): void
    {
        tenancy()->initialize($this->tenant);

        Article::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Wisuda Tahfidz Angkatan ke-12',
            'slug' => 'wisuda-tahfidz-ke-12',
            'excerpt' => 'Ringkasan uji.',
            'content' => 'Isi artikel uji.',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Staf Humas Berita', 'email' => 'humas-berita@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('tu');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/humas/berita", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Humas/Berita')
            ->where('artikel.0.judul', 'Wisuda Tahfidz Angkatan ke-12')
        );
    }

    public function test_halaman_galeri_menampilkan_album_dan_foto_nyata(): void
    {
        tenancy()->initialize($this->tenant);

        $gallery = Gallery::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Wisuda Tahfidz ke-12',
            'slug' => 'wisuda-tahfidz-ke-12-galeri',
            'is_published' => true,
            'published_at' => now(),
        ]);
        GalleryImage::create([
            'tenant_id' => $this->tenant->id,
            'gallery_id' => $gallery->id,
            'image_url' => 'cms/galleries/foto-uji.jpg',
            'caption' => 'Foto uji pembukaan',
            'sort_order' => 0,
        ]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Staf Humas Galeri', 'email' => 'humas-galeri@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('tu');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/humas/galeri", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Humas/Galeri')
            ->where('album.0.judul', 'Wisuda Tahfidz ke-12')
            ->where('album.0.jumlah', 1)
            ->where('album.0.foto.0.caption', 'Foto uji pembukaan')
        );
    }
}
