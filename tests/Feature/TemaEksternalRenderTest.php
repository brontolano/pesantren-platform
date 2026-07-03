<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\PanelTheme;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * BUKTI runtime arsitektur "multi-build CSS + pilih runtime": saat Setting
 * tenant memilih tema eksternal (brisk), respons HTML dashboard memuat
 * <link> ke build theme-brisk — dan TIDAK memuat build theme.css bawaan
 * (tidak dobel/konflik). Tanpa setting → build bawaan.
 *
 * Butuh manifest Vite (npm run build) karena assert href asset nyata.
 */
class TemaEksternalRenderTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! file_exists(public_path('build/manifest.json'))) {
            $this->markTestSkipped('Manifest Vite belum ada — jalankan `npm run build` dulu.');
        }

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'settings',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function renderDashboard(string $host): string
    {
        $req = Request::create("http://{$host}/admin", 'GET');
        $req->headers->set('Host', $host);
        $res = app(Kernel::class)->handle($req);

        $this->assertSame(200, $res->getStatusCode(), 'Dashboard harus 200 untuk user login.');

        return (string) $res->getContent();
    }

    public function test_link_css_tema_aktif_ikut_render_sesuai_setting_tenant(): void
    {
        $tenant = $this->createRuntimeTenant('temacss', 'Pesantren Tema CSS');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Admin Tema',
            'email' => 'tema@panel.test', 'password' => 'password123',
        ]);
        if (Schema::connection('tenant')->hasTable('roles')
            && DB::connection('tenant')->table('roles')->where('name', 'super_admin')->exists()) {
            $user->assignRole('super_admin');
        }
        Auth::login($user);

        $host = $tenant->id.'.localhost';

        // 1) Tanpa setting → build bawaan theme.css, TANPA build eksternal.
        $html = $this->renderDashboard($host);
        $this->assertStringNotContainsString('theme-brisk', $html, 'Bawaan tidak boleh memuat build Brisk.');

        // 2) Setting tenant = brisk → link build theme-brisk termuat,
        //    build bawaan TIDAK ikut (tidak dobel).
        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => 'brisk']]);

        $html = $this->renderDashboard($host);
        $this->assertStringContainsString('theme-brisk', $html, 'Setting brisk harus memuat build theme-brisk.');
        // Font Brisk (Kumbh Sans) ikut ter-render lewat Panel::font().
        $this->assertStringContainsString('Kumbh Sans', $html, 'Font Kumbh Sans (Brisk) harus termuat.');

        // Build bawaan tidak boleh ikut termuat (hindari CSS dobel/konflik):
        // cari href asset hasil manifest utk entry theme.css bawaan.
        $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);
        $bawaanFile = $manifest[PanelTheme::DEFAULT_CSS_ENTRY]['file'] ?? null;
        $this->assertNotNull($bawaanFile, 'Entry theme.css bawaan harus ada di manifest.');
        $this->assertStringNotContainsString($bawaanFile, $html, 'Build bawaan tidak boleh dimuat dobel saat tema eksternal aktif.');

        // 3) Ganti ke nord → link build theme-nord, brisk hilang.
        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => 'nord']]);

        $html = $this->renderDashboard($host);
        $this->assertStringContainsString('theme-nord', $html);
        $this->assertStringNotContainsString('theme-brisk', $html, 'Ganti tema harus mengganti build (tidak bocor antar request).');

        tenancy()->end();
    }
}
