<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Form ArticleResource dgn RichEditor->customBlocks([VideoKajianBlock]) WAJIB
 * render tanpa 500. Menangkap salah-konfigurasi blok (mis. signature toHtml,
 * schema modal) yang hanya muncul saat halaman create/edit ter-mount.
 */
class ArticleFormVideoBlockTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions',
            'model_has_roles', 'model_has_permissions', 'articles',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    public function test_form_buat_artikel_dengan_blok_video_tidak_500(): void
    {
        $tenant = $this->createRuntimeTenant('artikelvid', 'Pesantren Artikel Video');
        tenancy()->initialize($tenant);
        DB::connection('tenant')->table('tenants')->updateOrInsert(
            ['id' => $tenant->id],
            ['name' => $tenant->name, 'is_active' => true, 'data' => json_encode([]),
                'created_at' => now(), 'updated_at' => now()],
        );

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Admin Artikel',
            'email' => 'artikel@panel.test', 'password' => 'password123',
        ]);
        if (Schema::connection('tenant')->hasTable('roles')
            && DB::connection('tenant')->table('roles')->where('name', 'super_admin')->exists()) {
            $user->assignRole('super_admin');
        }
        Auth::login($user);

        // cms adalah modul core+released → aktif default. Pastikan tenant_modules ada.
        DB::connection('pgsql')->table('tenant_modules')->updateOrInsert(
            ['tenant_id' => $tenant->id, 'module_name' => 'cms'],
            ['is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        );

        $host = $tenant->id.'.localhost';
        $kernel = app(Kernel::class);

        foreach (['admin/article/articles', 'admin/article/articles/create'] as $path) {
            $req = Request::create("http://{$host}/{$path}", 'GET');
            $req->headers->set('Host', $host);
            $status = $kernel->handle($req)->getStatusCode();
            $this->assertLessThan(500, $status, "Render /{$path} = {$status} (>=500). Form artikel/blok video rusak.");
        }

        tenancy()->end();
    }
}
