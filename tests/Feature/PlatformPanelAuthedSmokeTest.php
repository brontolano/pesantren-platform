<?php

namespace Tests\Feature;

use App\Filament\Pages\AktivasiModulTenant;
use App\Filament\Widgets\PlatformOverview;
use App\Models\ModuleRegistry;
use App\Models\User;
use Database\Seeders\ModuleRegistrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Livewire\Component;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Anti-500 panel PLATFORM (central, id 'admin', path /platform) TER-AUTENTIKASI.
 *
 * JEBAKAN: panel platform = id 'admin', path 'platform' (KEBALIKAN panel tenant
 * yg id 'tenant', path 'admin'). Central pakai koneksi DB central (pgsql), BUKAN
 * tenant DB; platform super_admin = tenant_id NULL + role super_admin
 * (isPlatformSuperAdmin()). Tidak menyentuh tenant apa pun (mis. albakids).
 *
 * Cakupan:
 *  - restrukturisasi navigationGroups (Pesantren / Langganan & Billing /
 *    AI Khadim Digital / Manajemen Proyek) — render AUTHED super_admin membangun
 *    sidebar penuh,
 *  - penulisan ulang halaman Aktivasi Modul (toggle per klasifikasi),
 *  - INSIDEN 2026-07-02: BillingOverviewWidget::canView() terdeklarasi GANDA
 *    (FatalError "cannot redeclare") → dashboard /platform 500. GET dashboard biasa
 *    TIDAK menginstansiasi widget ini karena $isLazy = true (dimuat via Livewire
 *    lazy) — maka test_widget_dashboard_platform_* MEMAKSA canView() + render
 *    Livewire tiap widget agar redeclare/fatal benar-benar tertangkap.
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
            'plans', 'tenant_subscriptions', 'platform_invoices', 'dev_projects', 'dev_tasks',
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

    /**
     * Regresi insiden 2026-07-02 (redeclare canView di BillingOverviewWidget).
     * Dashboard GET biasa TAK cukup: widget dashboard $isLazy=true tak diinstansiasi
     * pada request awal. Test ini MEMAKSA setiap widget dashboard panel 'admin':
     *  - panggil canView() (memicu FatalError bila method terdeklarasi ganda),
     *  - render Livewire penuh bila diizinkan tampil (memuat kelas + jalankan render).
     */
    public function test_widget_dashboard_platform_dipaksa_instansiasi_tanpa_fatal(): void
    {
        $super = $this->superAdminPlatform();
        $this->actingAs($super);

        $panel = Filament::getPanel('admin');
        $widgetDicek = 0;

        foreach ($panel->getWidgets() as $widgetClass) {
            $boleh = true;
            if (method_exists($widgetClass, 'canView')) {
                // Pemanggilan inilah yang memicu "Cannot redeclare ::canView()".
                $boleh = $widgetClass::canView();
            }

            if ($boleh && is_subclass_of($widgetClass, Component::class)) {
                Livewire::test($widgetClass)->assertOk();
            }

            $widgetDicek++;
        }

        $this->assertContains(PlatformOverview::class, $panel->getWidgets(), 'Widget operasi superadmin tidak terdaftar pada panel platform.');
        $this->assertGreaterThanOrEqual(1, $widgetDicek, 'Panel platform tidak memiliki widget yang dapat dirender.');
    }

    /**
     * Sweep DINAMIS: render halaman INDEX dari SEMUA resource + slug SEMUA Page
     * yang terdaftar di panel 'admin' (central). Resource/Page baru otomatis ikut
     * teruji. Assert < 500 (500 = panel platform rusak untuk super_admin login).
     */
    public function test_sweep_semua_resource_dan_page_platform_tidak_500(): void
    {
        $super = $this->superAdminPlatform();
        $this->actingAs($super);

        $host = 'pesantren.online';
        $kernel = app(Kernel::class);
        $panel = Filament::getPanel('admin');

        $sudah = [];
        $resourceDicek = 0;
        foreach ($panel->getResources() as $resourceClass) {
            try {
                $url = $resourceClass::getUrl('index', panel: 'admin');
            } catch (\Throwable) {
                continue; // resource tanpa halaman index
            }
            $path = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
            if ($path === '' || in_array($path, $sudah, true)) {
                continue;
            }
            $sudah[] = $path;

            $rReq = Request::create("http://{$host}/{$path}", 'GET');
            $rReq->headers->set('Host', $host);
            $rStatus = $kernel->handle($rReq)->getStatusCode();
            $this->assertLessThan(500, $rStatus, "Render /{$path} ({$resourceClass}) mengembalikan {$rStatus} (>=500). Panel platform rusak.");
            $resourceDicek++;
        }
        $this->assertGreaterThanOrEqual(5, $resourceDicek, 'Sweep resource panel platform terlalu sedikit — panel admin mungkin tak ter-boot.');

        foreach ($panel->getPages() as $pageClass) {
            try {
                $url = $pageClass::getUrl(panel: 'admin');
            } catch (\Throwable) {
                continue;
            }
            $path = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
            $pgReq = Request::create("http://{$host}/{$path}", 'GET');
            $pgReq->headers->set('Host', $host);
            $pgStatus = $kernel->handle($pgReq)->getStatusCode();
            $this->assertLessThan(500, $pgStatus, "Render page /{$path} ({$pageClass}) mengembalikan {$pgStatus} (>=500). Panel platform rusak.");
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

        // OPSI B — invarian partisi (tahan pertumbuhan katalog; katalog kini 23,
        // tak lagi hard-code 5/13/2 basi). Maksud asli tetap dijaga: partisi
        // LENGKAP tanpa tumpang-tindih.

        // (1) Tiap grup TIDAK kosong (klasifikasi benar-benar terisi).
        $this->assertNotEmpty($core, 'Grup modul inti tidak boleh kosong.');
        $this->assertNotEmpty($optional, 'Grup modul opsional tidak boleh kosong.');
        $this->assertNotEmpty($development, 'Grup modul pengembangan tidak boleh kosong.');

        // (2) LENGKAP → jumlah ketiganya == total katalog (tak ada modul tercecer).
        $total = ModuleRegistry::query()->count();
        $this->assertSame(
            $total,
            count($core) + count($optional) + count($development),
            'Partisi tidak lengkap: ada modul katalog yang tak masuk grup mana pun.'
        );

        // (3) SALING LEPAS → irisan antar-grup kosong (tak ada modul di >1 grup).
        // Nama toggle mengkodekan key modul: "modules.{key}".
        $keys = fn (array $toggles): array => array_map(
            fn ($t): string => $t->getName(),
            $toggles
        );
        $coreKeys = $keys($core);
        $optionalKeys = $keys($optional);
        $developmentKeys = $keys($development);

        $this->assertEmpty(array_intersect($coreKeys, $optionalKeys), 'Modul muncul di grup inti DAN opsional.');
        $this->assertEmpty(array_intersect($coreKeys, $developmentKeys), 'Modul muncul di grup inti DAN pengembangan.');
        $this->assertEmpty(array_intersect($optionalKeys, $developmentKeys), 'Modul muncul di grup opsional DAN pengembangan.');

        // Gabungan unik = total → konfirmasi ulang partisi bebas duplikat.
        $gabungan = array_merge($coreKeys, $optionalKeys, $developmentKeys);
        $this->assertCount($total, array_unique($gabungan), 'Ada key modul duplikat lintas grup.');
    }
}
