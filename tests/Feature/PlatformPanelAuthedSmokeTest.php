<?php

namespace Tests\Feature;

use App\Filament\Pages\AktivasiModulTenant;
use App\Models\ModuleRegistry;
use App\Models\User;
use Database\Seeders\ModuleRegistrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use ReflectionMethod;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Anti-500 panel platform sesudah:
 *  - restrukturisasi navigationGroups (Pesantren / Langganan & Billing /
 *    Manajemen Proyek) — render AUTHED super_admin membangun sidebar penuh,
 *  - penulisan ulang halaman Aktivasi Modul (toggle dikelompokkan per
 *    klasifikasi: inti / opsional / pengembangan).
 */
class PlatformPanelAuthedSmokeTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // stub manifest aset → render layout tak gagal di @vite
        $this->ensurePpdbTestingSchemaReady([
            'users', 'tenants', 'domains', 'roles', 'permissions',
            'model_has_roles', 'role_has_permissions', 'module_registry', 'tenant_modules',
        ]);
        $this->truncatePpdbTables(['model_has_roles', 'domains', 'tenants', 'users', 'module_registry']);
    }

    private function superAdminPlatform(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ModuleRegistrySeeder::class);

        $super = User::withoutGlobalScopes()->create([
            'tenant_id' => null, // pemilik platform (central)
            'name' => 'Platform Super',
            'email' => 'platform-super@t.local',
            'password' => 'password',
        ]);
        $super->assignRole('super_admin');

        return $super;
    }

    public function test_panel_platform_authed_render_inti_tidak_500(): void
    {
        $super = $this->superAdminPlatform();

        // Tiap rute membangun seluruh sidebar (navigationGroups baru) → menangkap
        // regresi konfigurasi panel (mis. grup berikon bentrok item berikon di
        // Filament v5). Aktivasi Modul juga merender form yang ditulis ulang.
        $paths = [
            'platform',                        // dashboard
            'platform/aktivasi-modul-tenant',  // halaman modul (form + header actions)
            'platform/modules',                // Katalog Modul (Langganan & Billing)
        ];

        foreach ($paths as $path) {
            $status = $this->actingAs($super)->get("/{$path}")->getStatusCode();
            $this->assertLessThan(500, $status, "Render /{$path} mengembalikan {$status} (>=500). Panel platform rusak.");
        }
    }

    public function test_sidebar_platform_grup_terdaftar_lengkap_tanpa_orphan(): void
    {
        $resp = $this->actingAs($this->superAdminPlatform())->get('/platform/aktivasi-modul-tenant');

        $resp->assertSuccessful();
        // Keempat grup TERDAFTAR tampil (termasuk 'AI Khadim Digital' dari modul AI)...
        $resp->assertSee('Pesantren');
        $resp->assertSee('Langganan & Billing'); // assertSee meng-escape → cocok dgn &amp; di HTML
        $resp->assertSee('AI Khadim Digital');
        $resp->assertSee('Manajemen Proyek');
        // ...dan TIDAK ada grup yatim 'Billing SaaS' (regresi item yg lupa dimigrasi).
        $resp->assertDontSee('Billing SaaS');
    }

    public function test_klasifikasi_modul_partisi_lengkap_tanpa_tumpang_tindih(): void
    {
        $this->superAdminPlatform();

        $page = new AktivasiModulTenant;
        $ref = new ReflectionMethod($page, 'moduleToggles');
        $ref->setAccessible(true);

        $core = $ref->invoke($page, 'core');
        $optional = $ref->invoke($page, 'optional');
        $development = $ref->invoke($page, 'development');

        // Partisi LENGKAP & saling lepas → jumlah ketiganya == total katalog.
        $total = ModuleRegistry::query()->count();
        $this->assertSame($total, count($core) + count($optional) + count($development));

        // Sesuai katalog ModuleRegistrySeeder: 5 inti, 13 opsional, 2 pengembangan.
        $this->assertCount(5, $core);
        $this->assertCount(13, $optional);
        $this->assertCount(2, $development);
    }
}
