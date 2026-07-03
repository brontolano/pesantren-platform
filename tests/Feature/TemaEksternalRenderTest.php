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

    /** Buat tenant runtime + login admin — helper untuk test per-tema. */
    private function bootTenantAndLogin(string $sub): string
    {
        $tenant = $this->createRuntimeTenant($sub, 'Pesantren '.$sub);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Admin Tema',
            'email' => $sub.'@panel.test', 'password' => 'password123',
        ]);
        if (Schema::connection('tenant')->hasTable('roles')
            && DB::connection('tenant')->table('roles')->where('name', 'super_admin')->exists()) {
            $user->assignRole('super_admin');
        }
        Auth::login($user);

        return $tenant->id.'.localhost';
    }

    /**
     * Moonlight = tema GELAP murni (seluruh CSS vendor di-scope .dark).
     * Efek plugin harus tereplikasi: default theme mode panel = DARK
     * (halaman langsung gelap tanpa toggle manual) + link CSS moonlight.
     */
    public function test_moonlight_default_dark_dan_css_termuat(): void
    {
        $host = $this->bootTenantAndLogin('temamoon');
        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => 'moonlight']]);

        $html = $this->renderDashboard($host);

        $this->assertStringContainsString('theme-moonlight', $html, 'Build theme-moonlight harus termuat.');
        $this->assertStringContainsString('--default-theme-mode: dark', $html, 'Moonlight (CSS .dark-only) wajib default mode GELAP — tanpa ini tema tak terlihat sama sekali.');

        // Palet vendor: primer Moonlight (biru pastel #82aaff) menggantikan Emerald.
        $this->assertSame(
            \Filament\Support\Colors\Color::generatePalette('#82aaff'),
            \Filament\Support\Facades\FilamentColor::getColor('primary'),
            'Primer Moonlight harus terdaftar (bukan Emerald bawaan).',
        );

        tenancy()->end();
    }

    /**
     * Nord: identitas visual utamanya datang dari ->colors() plugin vendor
     * (gray = polar night, primary = frost, secondary dst) — WAJIB
     * tereplikasi penuh saat plugin tidak didaftarkan global.
     */
    public function test_nord_palet_vendor_terdaftar_penuh(): void
    {
        $host = $this->bootTenantAndLogin('temanord');
        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => 'nord']]);

        $html = $this->renderDashboard($host);

        $this->assertStringContainsString('theme-nord', $html);
        // Nord TIDAK gelap-default (light+dark mengikuti toggle).
        $this->assertStringContainsString('--default-theme-mode: system', $html);
        // Key non-default 'secondary' hanya ada bila palet vendor terdaftar.
        $this->assertStringContainsString('--secondary-500', $html, 'Palet vendor Nord (secondary) harus ter-render sebagai CSS var.');

        // Gray polar night #2e3440 (shade 900) — jantung look Nord.
        $this->assertSame(
            \Filament\Support\Colors\Color::convertToOklch('#2e3440'),
            \Filament\Support\Facades\FilamentColor::getColor('gray')[900] ?? null,
            'Gray polar-night Nord harus menggantikan Slate bawaan.',
        );

        tenancy()->end();
    }

    /** Brisk: font Kumbh Sans + palet monokrom zinc (primer near-black). */
    public function test_brisk_palet_monokrom_terdaftar(): void
    {
        $host = $this->bootTenantAndLogin('temabrisk');
        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => 'brisk']]);

        $html = $this->renderDashboard($host);

        $this->assertStringContainsString('theme-brisk', $html);
        $this->assertStringContainsString('--default-theme-mode: system', $html);
        $this->assertSame(
            \Filament\Support\Colors\Color::generatePalette('#18181b'),
            \Filament\Support\Facades\FilamentColor::getColor('primary'),
            'Primer Brisk (near-black) harus terdaftar.',
        );
        $this->assertSame(
            \Filament\Support\Colors\Color::Zinc,
            \Filament\Support\Facades\FilamentColor::getColor('gray'),
            'Gray Brisk harus Zinc (monokrom, sesuai demo Filafly).',
        );

        tenancy()->end();
    }

    /** Bawaan TIDAK berubah: mode system, primer Emerald, tanpa palet vendor. */
    public function test_bawaan_tetap_utuh(): void
    {
        $host = $this->bootTenantAndLogin('temabase');

        $html = $this->renderDashboard($host);

        $this->assertStringContainsString('--default-theme-mode: system', $html);
        $this->assertStringNotContainsString('--secondary-500', $html, 'Tema bawaan tidak boleh kecipratan palet vendor.');
        $this->assertSame(
            \Filament\Support\Colors\Color::Emerald,
            \Filament\Support\Facades\FilamentColor::getColor('primary'),
            'Bawaan harus tetap Emerald.',
        );

        tenancy()->end();
    }
}
