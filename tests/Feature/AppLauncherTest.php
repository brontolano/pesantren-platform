<?php

namespace Tests\Feature;

use App\Http\Controllers\LauncherController;
use App\Models\User;
use App\Support\AppLauncher;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Inertia\Response;
use Modules\CMS\Http\Controllers\AppShellController;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Resolver "satu pintu" (App\Support\AppLauncher) + routing LauncherController.
 * Memastikan tiap peran diarahkan ke tujuan yang benar dan hub staf menampilkan
 * tile PWA yang tepat.
 */
class AppLauncherTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['users', 'roles', 'permissions', 'domains', 'tenants', 'settings']);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(?string $websiteDomain = null): void
    {
        $tenant = $this->createRuntimeTenant('launcher', 'Pesantren Launcher Test');
        if ($websiteDomain !== null) {
            $tenant->domains()->create(['domain' => $websiteDomain, 'type' => 'website']);
        }
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new RolesAndPermissionsSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @param array<int,string> $roles */
    private function user(array $roles): User
    {
        $u = User::withoutGlobalScopes()->create([
            'name' => 'U '.implode('-', $roles),
            'email' => str()->lower(str()->random(8)).'@'.tenant('id').'.localhost',
            'password' => 'password',
            'tenant_id' => tenant('id'),
        ]);
        $u->syncRoles($roles);

        return $u->fresh();
    }

    public function test_wali_redirect_ke_portal_wali(): void
    {
        $this->boot();
        $dest = AppLauncher::primaryDestination($this->user(['wali']));
        $this->assertSame('redirect', $dest['kind']);
        $this->assertStringContainsString('/wali', $dest['url']);
    }

    public function test_peserta_redirect_ke_spmb(): void
    {
        $this->boot();
        $dest = AppLauncher::primaryDestination($this->user(['peserta']));
        $this->assertSame('redirect', $dest['kind']);
        $this->assertStringContainsString('/spmb', $dest['url']);
    }

    public function test_santri_murni_redirect_ke_app_santri_nyata(): void
    {
        $this->boot();
        $dest = AppLauncher::primaryDestination($this->user(['santri']));
        $this->assertSame('redirect', $dest['kind']);
        // REGRESI (audit #3): santri murni HARUS ke App Santri nyata (/app,
        // AppSantriController), BUKAN aplikasi demo /santri-app (SantriDemoController).
        $this->assertStringContainsString('/app', $dest['url']);
        $this->assertStringNotContainsString('/santri-app', $dest['url']);
    }

    public function test_santri_yang_juga_guru_ke_hub(): void
    {
        $this->boot();
        // santri+guru BUKAN isTenantStaff (peran 'santri' dikecualikan) → adminUrl
        // null → hub fallback (bukan aturan device staf).
        $dest = AppLauncher::primaryDestination($this->user(['santri', 'guru']));
        $this->assertSame('hub', $dest['kind']);
    }

    public function test_guru_hub_dengan_pwa_guru(): void
    {
        $this->boot();
        $u = $this->user(['guru']);
        // Device-based: desktop → panel; HP → PWA guru.
        $this->assertStringContainsString('/admin', AppLauncher::primaryDestination($u, false)['url']);
        $this->assertStringContainsString('/guru', AppLauncher::primaryDestination($u, true)['url']);
        $this->assertStringContainsString('/guru', AppLauncher::pwaHome($u)['url']);
        $this->assertNotNull(AppLauncher::adminPanelUrl($u));
    }

    public function test_kepala_hub_dengan_pwa_pimpinan(): void
    {
        $this->boot();
        $u = $this->user(['kepala']);
        $this->assertStringContainsString('/pimpinan', AppLauncher::pwaHome($u)['url']);
    }

    public function test_kepala_keuangan_ke_aplikasi_keuangan(): void
    {
        $this->boot();
        $u = $this->user(['kepala_keuangan']);
        $this->assertStringContainsString('/keuangan', AppLauncher::pwaHome($u)['url']);
    }

    public function test_keuangan_lapangan_ke_kasir_pwa(): void
    {
        $this->boot();
        $u = $this->user(['keuangan']);
        // operate_pos tanpa close_period → kasir lapangan (/admin/keuangan/pwa).
        $this->assertStringContainsString('/admin/keuangan/pwa', AppLauncher::pwaHome($u)['url']);
    }

    public function test_pengelola_uks_hub_dengan_pwa_uks(): void
    {
        $this->boot();
        $u = $this->user(['pengelola_uks']);
        // view_kesehatan → tile "Kesehatan UKS" (/uks) di STAFF_APPS.
        $this->assertStringContainsString('/uks', AppLauncher::pwaHome($u)['url']);
        // Device-based: desktop → panel; HP → PWA UKS.
        $this->assertStringContainsString('/admin', AppLauncher::primaryDestination($u, false)['url']);
        $this->assertStringContainsString('/uks', AppLauncher::primaryDestination($u, true)['url']);
    }

    public function test_operator_divisi_hub_dengan_pwa_keuangan_lapangan(): void
    {
        $this->boot();
        $u = $this->user(['operator_divisi']);
        // input_belanja → tile "Keuangan Lapangan" (/admin/keuangan/pwa) di STAFF_APPS.
        $this->assertStringContainsString('/admin/keuangan/pwa', AppLauncher::pwaHome($u)['url']);
        // Device-based: desktop → panel; HP → PWA keuangan lapangan.
        $this->assertStringContainsString('/admin', AppLauncher::primaryDestination($u, false)['url']);
        $this->assertStringContainsString('/admin/keuangan/pwa', AppLauncher::primaryDestination($u, true)['url']);
    }

    public function test_dewan_pembina_hub_dengan_pwa_keuangan_lapangan(): void
    {
        $this->boot();
        $u = $this->user(['dewan_pembina']);
        // view_laporan_pimpinan → tile "Dasbor Keuangan" (/admin/keuangan/pwa)
        // (shell sama dgn operator_divisi, nav-nya sendiri yg adaptif per-izin).
        $this->assertStringContainsString('/admin/keuangan/pwa', AppLauncher::pwaHome($u)['url']);
        // Device-based: desktop → panel; HP → PWA keuangan lapangan.
        $this->assertStringContainsString('/admin', AppLauncher::primaryDestination($u, false)['url']);
        $this->assertStringContainsString('/admin/keuangan/pwa', AppLauncher::primaryDestination($u, true)['url']);
    }

    public function test_admin_pesantren_langsung_ke_admin_panel(): void
    {
        $this->boot();
        $u = $this->user(['admin_pesantren']);
        // ATURAN DEVICE: tujuan staf ditentukan perangkat, bukan peran.
        // Desktop → panel Filament /admin (fitur penuh). HP → PWA (admin_pesantren
        // punya view_kesehatan → pwaHome tile UKS). admin_pesantren tetap punya
        // akses panel penuh di desktop.
        $this->assertStringContainsString('/admin', AppLauncher::primaryDestination($u, false)['url']);
        $this->assertSame('redirect', AppLauncher::primaryDestination($u, true)['kind']);
        $this->assertNotNull(AppLauncher::adminPanelUrl($u));
        $this->assertNotNull(AppLauncher::pwaHome($u));
    }

    public function test_super_admin_ke_admin_panel_bukan_wali(): void
    {
        $this->boot();
        // super_admin dapat Gate::before "boleh segalanya" → can('view_own_children')
        // SELALU true. Pastikan TIDAK nyasar ke /wali, melainkan langsung ke /admin.
        $u = $this->user(['super_admin']);
        $this->assertTrue($u->can('view_own_children'), 'precondition: super_admin bypass aktif');
        $dest = AppLauncher::primaryDestination($u);
        $this->assertSame('redirect', $dest['kind']);
        $this->assertStringContainsString('/admin', $dest['url']);
        $this->assertStringNotContainsString('/wali', $dest['url']);
    }

    public function test_admin_panel_null_untuk_wali(): void
    {
        $this->boot();
        $this->assertNull(AppLauncher::adminPanelUrl($this->user(['wali'])));
    }

    public function test_website_url_dari_domain_type_website(): void
    {
        $this->boot('webtest.example.com');
        $this->assertSame('https://webtest.example.com', AppLauncher::websiteUrl());
    }

    public function test_website_url_null_tanpa_domain_website(): void
    {
        $this->boot();
        $this->assertNull(AppLauncher::websiteUrl());
    }

    public function test_controller_guest_render_beranda_publik_di_root(): void
    {
        $this->boot();
        Auth::logout();
        $resp = app(LauncherController::class)();
        // Satu pintu (Fase 2): TAMU (non-albakids) melihat Beranda publik (Inertia
        // landing) di '/', tidak lagi dipaksa form login. Login tetap terjangkau
        // di /masuk (route universal). AlbaKids (custom) tetap form login.
        $this->assertInstanceOf(Response::class, $resp);
    }

    public function test_controller_wali_auto_redirect(): void
    {
        $this->boot();
        $this->actingAs($this->user(['wali']));
        $resp = app(LauncherController::class)();
        $this->assertInstanceOf(RedirectResponse::class, $resp);
        $this->assertStringContainsString('/wali', $resp->getTargetUrl());
    }

    public function test_staf_shell_menu_diturunkan_dari_panel(): void
    {
        $this->boot();
        $u = $this->user(['admin_pesantren']);
        Auth::login($u);
        $req = Request::create(
            'http://x/staf', 'GET', server: ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 13; Mobile)'],
        );
        $resp = app(AppShellController::class)->stafHome($req);
        $this->assertInstanceOf(View::class, $resp);
        $labels = array_keys($resp->getData()['groups']);
        // Grup ini ADA di panel tapi TIDAK di katalog tangan lama → bukti menu
        // /staf kini DITURUNKAN dari navigasi panel (bukan daftar tangan).
        $this->assertContains('Donasi', $labels);
        $this->assertContains('Persetujuan', $labels);
        $this->assertContains('Santri', $labels);
    }

    public function test_controller_staf_desktop_ke_admin(): void
    {
        $this->boot();
        $this->actingAs($this->user(['guru']));
        // Request uji = userAgent non-mobile → ATURAN DEVICE: desktop → panel /admin.
        $resp = app(LauncherController::class)();
        $this->assertInstanceOf(RedirectResponse::class, $resp);
        $this->assertStringContainsString('/admin', $resp->getTargetUrl());
    }
}
