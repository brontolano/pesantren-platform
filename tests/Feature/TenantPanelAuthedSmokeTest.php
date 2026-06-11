<?php

namespace Tests\Feature;

use App\Models\Tenant;
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
 * Smoke panel admin tenant TER-AUTENTIKASI: render dashboard + resource inti
 * harus < 500. Menangkap regresi konfigurasi panel (mis. navigationGroups
 * berikon bentrok dgn item berikon di Filament v5) yang TIDAK terlihat dari
 * halaman login (login tetap 200 walau dashboard 500).
 */
class TenantPanelAuthedSmokeTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions',
            'santri', 'tagihan', 'pembayaran', 'calon_santri',
            'jenis_pelanggaran', 'pelanggaran_santri', 'sanksi_santri', 'eskalasi_disiplin',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    public function test_dashboard_dan_resource_inti_tidak_500_untuk_user_login(): void
    {
        $tenant = $this->createRuntimeTenant('smoke', 'Pesantren Smoke');
        tenancy()->initialize($tenant);
        DB::connection('tenant')->table('tenants')->updateOrInsert(
            ['id' => $tenant->id],
            ['name' => $tenant->name, 'is_active' => true, 'data' => json_encode([]),
                'created_at' => now(), 'updated_at' => now()],
        );

        // Provisioning seeder lengkap (role + permission) agar user bisa lihat semua menu.
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Admin Smoke',
            'email' => 'smoke@panel.test', 'password' => 'password123',
        ]);
        if (Schema::connection('tenant')->hasTable('roles')
            && DB::connection('tenant')->table('roles')->where('name', 'super_admin')->exists()) {
            $user->assignRole('super_admin');
        }
        Auth::login($user);

        $host = $tenant->id.'.localhost';
        $kernel = app(Kernel::class);

        $paths = ['admin', 'admin/santri/santris', 'admin/tagihan/tagihans', 'admin/tahfidz/halaqah/halaqahs', 'admin/tahfidz/munaqasyah/munaqasyahs', 'admin/akademik/kitab/kitabs', 'admin/akademik/lembaga/lembagas', 'admin/akademik/kurikulum/kurikulums', 'admin/akademik/kalender-akademik/kalender-akademiks', 'admin/payroll/jabatan/jabatans', 'admin/payroll/penugasan/penugasan-pegawais', 'admin/payroll/employee/employees', 'admin/kesantrian/jenis-pelanggaran/jenis-pelanggarans', 'admin/kesantrian/pelanggaran-santri/pelanggaran-santris', 'admin/rekap-disiplin', 'admin/kesiapan-emis', 'admin/sidang-kenaikan', 'admin/grid-nilai', 'admin/rekap-jurnal-kbm', 'admin/alumni/alumnis', 'admin/payroll/generate-thr', 'admin/akademik/rapor-diniyah', 'admin/akademik/rapor-diniyah/isi-rubrik', 'admin/payroll/kontrak/kontrak-kerjas', 'admin/log-gerbang', 'admin/pengajuan-jenguk', 'admin/moderasi-pesan-wali', 'admin/pengaturan/kebijakan'];
        foreach ($paths as $path) {
            $req = Request::create("http://{$host}/{$path}", 'GET');
            $req->headers->set('Host', $host);
            $status = $kernel->handle($req)->getStatusCode();

            $this->assertLessThan(500, $status, "Render /{$path} mengembalikan {$status} (>=500). Panel rusak utk user login.");
        }

        tenancy()->end();
    }
}
