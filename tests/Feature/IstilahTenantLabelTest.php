<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Support\IstilahRegistry;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-31 — Label istilah per tenant: helper istilah() + Setting label.istilah.
 */
class IstilahTenantLabelTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions',
            'settings',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
        IstilahRegistry::flushCache();
    }

    protected function tearDown(): void
    {
        IstilahRegistry::flushCache();
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }
        parent::tearDown();
    }

    private function initTenant(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        return $tenant;
    }

    private function simpanSetting(Tenant $tenant, mixed $value): void
    {
        Setting::updateOrCreate(
            ['tenant_id' => $tenant->id, 'key' => IstilahRegistry::SETTING_KEY],
            ['value' => $value],
        );
        IstilahRegistry::flushCache();
    }

    public function test_tanpa_setting_memakai_label_bawaan(): void
    {
        $this->initTenant('istdef');

        $this->assertSame('Musyrif', istilah('musyrif'));
        $this->assertSame('Wali Kelas Diniyah', istilah('mustahiq'));
        $this->assertSame("Ta'zir", istilah('takzir'));
        $this->assertSame('Halaqah', istilah('halaqah'));

        // Kunci tak dikenal: default eksplisit > kunci mentah.
        $this->assertSame('Apalah', istilah('tidak_ada', 'Apalah'));
        $this->assertSame('tidak_ada', istilah('tidak_ada'));
    }

    public function test_label_berbeda_per_tenant(): void
    {
        $tenantA = $this->initTenant('istla');
        $this->simpanSetting($tenantA, ['musyrif' => 'Murabbi']);
        $this->assertSame('Murabbi', istilah('musyrif'));
        $this->assertSame('Halaqah', istilah('halaqah'));
        tenancy()->end();

        $tenantB = $this->initTenant('istlb');
        $this->assertSame('Musyrif', istilah('musyrif'), 'Tenant B tanpa setting harus tetap bawaan.');
        tenancy()->end();

        // Kembali ke A: override masih berlaku (cache per tenant, bukan global).
        tenancy()->initialize($tenantA);
        $this->assertSame('Murabbi', istilah('musyrif'));
    }

    public function test_value_rusak_fallback_aman(): void
    {
        $tenant = $this->initTenant('istrk');

        // String non-JSON.
        $this->simpanSetting($tenant, 'bukan json sama sekali');
        $this->assertSame('Musyrif', istilah('musyrif'));

        // String JSON valid → tetap dibaca.
        $this->simpanSetting($tenant, json_encode(['musyrif' => 'Pembina']));
        $this->assertSame('Pembina', istilah('musyrif'));

        // Kunci asing + label non-string + label kosong → diabaikan, sisanya jalan.
        $this->simpanSetting($tenant, [
            'kunci_asing' => 'Apapun',
            'musyrif' => ['array' => 'bukan string'],
            'halaqah' => '   ',
            'ustadz' => '  Asatidz  ',
        ]);
        $this->assertSame('Musyrif', istilah('musyrif'));
        $this->assertSame('Halaqah', istilah('halaqah'));
        $this->assertSame('Asatidz', istilah('ustadz'));
    }

    public function test_cache_stale_dibuang_saat_tenancy_reinit_simulasi_queue_worker(): void
    {
        // Simulasi proses long-running (queue worker): cache statis sudah terisi,
        // lalu proses LAIN mengubah Setting (tanpa flushCache di proses ini).
        $tenant = $this->initTenant('istqw');
        $this->simpanSetting($tenant, ['musyrif' => 'Murabbi']);
        $this->assertSame('Murabbi', istilah('musyrif')); // cache terisi

        // Update via DB langsung = proses lain; TIDAK memanggil flushCache.
        DB::table('settings')
            ->where('tenant_id', $tenant->id)
            ->where('key', IstilahRegistry::SETTING_KEY)
            ->update(['value' => json_encode(['musyrif' => 'Pembimbing'])]);

        // Masih job yang sama → boleh stale (cache per-konteks).
        $this->assertSame('Murabbi', istilah('musyrif'));

        // Job berikutnya: tenancy end + initialize ulang → listener WP-31 flush cache.
        tenancy()->end();
        tenancy()->initialize($tenant);
        $this->assertSame('Pembimbing', istilah('musyrif'),
            'Cache istilah harus dibuang saat TenancyInitialized (queue worker long-running).');
    }

    public function test_pengaturan_istilah_403_untuk_user_tanpa_izin(): void
    {
        $tenant = $this->initTenant('ist403');
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Guru Biasa',
            'email' => 'guru-istilah@panel.test', 'password' => 'password123',
        ]);
        // Tanpa role / tanpa manage_tenant_settings.
        Auth::login($user);

        $host = $tenant->id.'.localhost';
        $req = Request::create("http://{$host}/admin/pengaturan/istilah", 'GET');
        $req->headers->set('Host', $host);
        $status = app(Kernel::class)->handle($req)->getStatusCode();

        $this->assertContains($status, [302, 403, 404], "Harus ditolak rapi (403/404/redirect), dapat {$status}.");
        $this->assertLessThan(500, $status);
    }

    public function test_label_html_di_setting_tidak_dirender_mentah_di_nav(): void
    {
        // XSS via label nav: simpan label berisi script, render halaman panel,
        // pastikan keluar ter-escape, bukan tag mentah.
        $tenant = $this->initTenant('istxss');
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);
        $this->simpanSetting($tenant, ['halaqah' => '<script>alert(1)</script>Halqoh']);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Admin XSS',
            'email' => 'xss-istilah@panel.test', 'password' => 'password123',
        ]);
        if (Schema::connection('tenant')->hasTable('roles')
            && DB::connection('tenant')->table('roles')->where('name', 'super_admin')->exists()) {
            $user->assignRole('super_admin');
        }
        Auth::login($user);

        $host = $tenant->id.'.localhost';
        $req = Request::create("http://{$host}/admin/tahfidz/halaqah/halaqahs", 'GET');
        $req->headers->set('Host', $host);
        $response = app(Kernel::class)->handle($req);
        $this->assertLessThan(500, $response->getStatusCode());

        if ($response->getStatusCode() === 200) {
            $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent(),
                'Label istilah berisi HTML TIDAK boleh dirender mentah (XSS).');
        }
    }

    public function test_smoke_halaman_pengaturan_istilah_dan_halaqah_render(): void
    {
        $tenant = $this->initTenant('istsm');

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Admin Istilah',
            'email' => 'istilah@panel.test', 'password' => 'password123',
        ]);
        if (Schema::connection('tenant')->hasTable('roles')
            && DB::connection('tenant')->table('roles')->where('name', 'super_admin')->exists()) {
            $user->assignRole('super_admin');
        }
        Auth::login($user);

        $host = $tenant->id.'.localhost';
        $kernel = app(Kernel::class);

        foreach (['admin/pengaturan/istilah', 'admin/tahfidz/halaqah/halaqahs'] as $path) {
            $req = Request::create("http://{$host}/{$path}", 'GET');
            $req->headers->set('Host', $host);
            $status = $kernel->handle($req)->getStatusCode();
            $this->assertLessThan(500, $status, "Render /{$path} mengembalikan {$status}.");
        }
    }
}
