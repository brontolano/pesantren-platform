<?php

namespace Tests\Feature;

use App\Http\Controllers\LauncherController;
use App\Models\User;
use App\Support\AppLauncher;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Inertia\Response;
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
            'name' => 'U ' . implode('-', $roles),
            'email' => str()->lower(str()->random(8)) . '@' . tenant('id') . '.localhost',
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

    public function test_santri_murni_redirect_ke_santri_app(): void
    {
        $this->boot();
        $dest = AppLauncher::primaryDestination($this->user(['santri']));
        $this->assertSame('redirect', $dest['kind']);
        $this->assertStringContainsString('/santri-app', $dest['url']);
    }

    public function test_santri_yang_juga_guru_ke_hub(): void
    {
        $this->boot();
        $dest = AppLauncher::primaryDestination($this->user(['santri', 'guru']));
        $this->assertSame('hub', $dest['kind']);
    }

    public function test_guru_hub_dengan_pwa_guru(): void
    {
        $this->boot();
        $u = $this->user(['guru']);
        $this->assertSame('hub', AppLauncher::primaryDestination($u)['kind']);
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

    public function test_admin_pesantren_langsung_ke_admin_panel(): void
    {
        $this->boot();
        $u = $this->user(['admin_pesantren']);
        // Staf admin tanpa aplikasi PWA khusus → auto-redirect ke /admin (bukan hub).
        $dest = AppLauncher::primaryDestination($u);
        $this->assertSame('redirect', $dest['kind']);
        $this->assertStringContainsString('/admin', $dest['url']);
        $this->assertNull(AppLauncher::pwaHome($u));
        $this->assertNotNull(AppLauncher::adminPanelUrl($u));
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

    public function test_controller_staf_render_hub(): void
    {
        $this->boot();
        $this->actingAs($this->user(['guru']));
        $resp = app(LauncherController::class)();
        $this->assertInstanceOf(View::class, $resp);
        $data = $resp->getData();
        $this->assertNotNull($data['adminPanelUrl']);
        $this->assertStringContainsString('/guru', $data['pwaHome']['url']);
    }
}
