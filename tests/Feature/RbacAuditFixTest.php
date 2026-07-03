<?php

namespace Tests\Feature;

use App\Filament\Resources\Approval\ApprovalRequestResource;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Models\ApprovalRule;
use Modules\Kesehatan\Filament\Resources\RiwayatKesehatanResource;
use Modules\Tahfidz\Filament\Resources\HafalanTahfidzResource;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Audit RBAC 2026-07 — regresi fix P0-1/P1-1/P1-2/P1-3:
 *  (1) view_* = baca-saja; mutasi butuh manage_* (kepala read-only, pelaksana tetap bisa);
 *  (2) role santri bukan staf panel /admin, tapi App Santri /app tetap jalan;
 *  (3) manage_payroll (hantu) diganti run_payroll — Cetak Daftar Pegawai hidup lagi;
 *  (4) menu Persetujuan hanya utk approver potensial + aksi bergerbang approver ybs.
 */
class RbacAuditFixTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles',
            'model_has_permissions', 'riwayat_kesehatan', 'employee', 'approval_rule', 'approval_request',
        ]);
        $this->truncatePpdbTables(['approval_request', 'approval_rule', 'domains', 'tenants']);
    }

    /** @return array{0: Tenant} */
    private function boot(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        return $tenant;
    }

    private function userDenganRole(Tenant $tenant, string $role, string $email): User
    {
        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Uji '.$role,
            'email' => $email, 'password' => 'password123',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function aktifkanModul(Tenant $tenant, array $moduls): void
    {
        foreach ($moduls as $modul) {
            DB::connection('pgsql')->table('module_registry')->where('key', $modul)->update(['status' => 'released']);
            DB::connection('pgsql')->table('tenant_modules')->updateOrInsert(
                ['tenant_id' => $tenant->id, 'module_name' => $modul],
                ['is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    private function host(Tenant $t): array
    {
        $h = $t->id.'.localhost';

        return [$h, ['HTTP_HOST' => $h, 'SERVER_NAME' => $h]];
    }

    // ------------------------------------------------------------------
    // P0-1 — gerbang mutasi manage_*
    // ------------------------------------------------------------------

    public function test_kepala_hanya_baca_riwayat_kesehatan_perawat_bisa_mutasi(): void
    {
        $tenant = $this->boot('rb1');
        $this->aktifkanModul($tenant, ['kesehatan', 'tahfidz']);

        $kepala = $this->userDenganRole($tenant, 'kepala', 'kepala-rb1@uji.test');
        Auth::login($kepala);
        $this->assertTrue(RiwayatKesehatanResource::canAccess(), 'kepala tetap boleh LIHAT');
        $this->assertFalse(RiwayatKesehatanResource::canCreate(), 'kepala tak boleh create rekam medis');
        $this->assertFalse(RiwayatKesehatanResource::canDeleteAny(), 'kepala tak boleh hapus massal');
        $this->assertFalse(HafalanTahfidzResource::canCreate(), 'kepala tak boleh input hafalan');

        $perawat = $this->userDenganRole($tenant, 'pengelola_uks', 'uks-rb1@uji.test');
        Auth::login($perawat);
        $this->assertTrue(RiwayatKesehatanResource::canAccess());
        $this->assertTrue(RiwayatKesehatanResource::canCreate(), 'perawat (manage_kesehatan) tetap bisa input');
        $this->assertTrue(RiwayatKesehatanResource::canDeleteAny());

        $guruTahfidz = $this->userDenganRole($tenant, 'guru_tahfidz', 'gt-rb1@uji.test');
        Auth::login($guruTahfidz);
        $this->assertTrue(HafalanTahfidzResource::canCreate(), 'guru tahfidz tetap bisa input setoran');

        tenancy()->end();
    }

    // ------------------------------------------------------------------
    // P1-1 — santri bukan staf panel
    // ------------------------------------------------------------------

    public function test_santri_bukan_staf_panel_tapi_app_santri_tetap_jalan(): void
    {
        $tenant = $this->boot('rb2');
        $santri = $this->userDenganRole($tenant, 'santri', 'santri-rb2@uji.test');

        $this->assertFalse($santri->isTenantStaff(), 'santri bukan staf');
        $this->assertFalse($santri->canAccessPanel(Filament::getPanel('tenant')), 'santri ditolak /admin');

        $staf = $this->userDenganRole($tenant, 'tu', 'tu-rb2@uji.test');
        $this->assertTrue($staf->isTenantStaff(), 'staf biasa tetap staf (regresi)');

        tenancy()->end();
        [$h, $server] = $this->host($tenant);

        // /admin harus menolak santri (redirect login/403 — bukan 200 dashboard staf).
        $res = $this->actingAs($santri)->call('GET', "http://{$h}/admin", [], [], [], $server);
        $this->assertContains($res->getStatusCode(), [302, 403], '/admin tertutup untuk santri');

        // App Santri /app tetap hidup untuk akun santri.
        $home = $this->actingAs($santri)->call('GET', "http://{$h}/app", [], [], [], $server);
        $this->assertLessThan(500, $home->getStatusCode());
        $this->assertNotSame(403, $home->getStatusCode(), '/app tidak boleh 403 untuk santri');
    }

    // ------------------------------------------------------------------
    // P1-2 — run_payroll membuka Cetak Daftar Pegawai
    // ------------------------------------------------------------------

    public function test_run_payroll_membuka_cetak_daftar_pegawai(): void
    {
        $tenant = $this->boot('rb3');
        tenancy()->end();
        [$h, $server] = $this->host($tenant);

        tenancy()->initialize($tenant);
        $petugas = $this->userDenganRole($tenant, 'petugas_payroll', 'pp-rb3@uji.test');
        $guru = $this->userDenganRole($tenant, 'guru', 'guru-rb3@uji.test');
        tenancy()->end();

        $ok = $this->actingAs($petugas)->call('GET', "http://{$h}/admin/cetak/pegawai", [], [], [], $server);
        $ok->assertOk();

        // Tanpa run_payroll: 403 (atau redirect 403-anggun ke dashboard /admin
        // sesuai handler di bootstrap/app.php) — yang penting BUKAN 200 berisi data.
        Auth::logout();
        $this->app->get('auth')->forgetGuards();
        $tolak = $this->actingAs($guru)->call('GET', "http://{$h}/admin/cetak/pegawai", [], [], [], $server);
        $this->assertContains($tolak->getStatusCode(), [302, 403], 'tanpa run_payroll ditolak');
        if ($tolak->getStatusCode() === 302) {
            $this->assertStringEndsWith('/admin', (string) $tolak->headers->get('Location'), 'redirect 403-anggun ke dashboard');
        }
    }

    // ------------------------------------------------------------------
    // P1-3 — menu Persetujuan hanya approver potensial
    // ------------------------------------------------------------------

    public function test_menu_persetujuan_tersembunyi_untuk_non_approver(): void
    {
        $tenant = $this->boot('rb4');
        Filament::setCurrentPanel(Filament::getPanel('tenant'));

        $guru = $this->userDenganRole($tenant, 'guru', 'guru-rb4@uji.test');
        Auth::login($guru);
        $this->assertFalse(ApprovalRequestResource::canAccess(), 'guru bukan approver → menu tersembunyi');

        // Bendahara jadi approver potensial via aturan aktif.
        ApprovalRule::create([
            'approvable_type' => 'Modules\\Pengadaan\\Models\\Belanja',
            'nama' => 'Aturan uji', 'field' => 'total', 'operator' => '>=', 'nilai' => 1,
            'approver_role' => 'bendahara', 'mode' => 'wajib', 'urutan' => 1, 'aktif' => true,
        ]);
        $bendahara = $this->userDenganRole($tenant, 'bendahara', 'bend-rb4@uji.test');
        Auth::login($bendahara);
        $this->assertTrue(ApprovalRequestResource::canAccess(), 'approver potensial melihat menu');

        // Admin pesantren (kelola_approval) selalu melihat menu.
        $admin = $this->userDenganRole($tenant, 'admin_pesantren', 'admin-rb4@uji.test');
        Auth::login($admin);
        $this->assertTrue(ApprovalRequestResource::canAccess());

        // Gerbang aksi: hanya pemegang role approver permintaan ybs.
        $req = ApprovalRequest::create([
            'tenant_id' => $tenant->id,
            'approvable_type' => 'Modules\\Pengadaan\\Models\\Belanja', 'approvable_id' => 1,
            'approver_role' => 'bendahara', 'status' => 'menunggu', 'diminta_oleh' => $guru->id,
        ]);
        Auth::login($bendahara);
        $this->assertTrue(ApprovalRequestResource::bolehMemutus($req), 'approver ybs boleh memutus');
        Auth::login($guru);
        $this->assertFalse(ApprovalRequestResource::bolehMemutus($req), 'role lain tak boleh memutus');

        tenancy()->end();
    }
}
