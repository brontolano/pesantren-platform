<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\SlipGaji;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * KepalaKeuanganApiController::payrollProses — sebelumnya stub (validasi
 * saja, tak memanggil PayrollService). Ini menguji implementasi nyata:
 * buat slip massal (idempoten) + terapkan potongan + finalisasi (posting
 * jurnal), reuse method PayrollService yang sudah teruji di EmployeeSalaryTest.
 */
class PayrollProsesMassalTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions',
            'akun', 'sumber_dana', 'periode_akuntansi', 'slip_gaji', 'employee', 'komponen_gaji',
            'jurnal', 'jurnal_baris', 'cuti', 'pinjaman_pegawai',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('payrollmassal', 'Pesantren Payroll Massal');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);
        (new LedgerSeeder)->run();
        tenancy()->end();
    }

    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    private function employeeDenganStruktur(?int $usersId, string $nama = 'Ustadz Fulan'): Employee
    {
        $e = Employee::create([
            'users_id' => $usersId, 'nama' => $nama, 'jabatan' => 'Guru',
            'status_kepegawaian' => 'tetap', 'ptkp_status' => 'TK0', 'aktif' => true,
        ]);
        $e->komponenGaji()->createMany([
            ['nama' => 'Honor Pokok', 'tipe' => 'tetap', 'nominal' => 2000000, 'aktif' => true],
            ['nama' => 'Tunjangan Jabatan', 'tipe' => 'tunjangan', 'nominal' => 500000, 'aktif' => true],
        ]);

        return $e->fresh('komponenGaji');
    }

    public function test_petugas_payroll_bisa_proses_payroll_massal_dan_menerbitkan_slip_final(): void
    {
        tenancy()->initialize($this->tenant);

        $u1 = User::withoutGlobalScopes()->create(['name' => 'Ustadz Satu', 'email' => 'ustadz1@payrollmassal.test', 'password' => bcrypt('password'), 'tenant_id' => $this->tenant->id]);
        $u2 = User::withoutGlobalScopes()->create(['name' => 'Ustadz Dua', 'email' => 'ustadz2@payrollmassal.test', 'password' => bcrypt('password'), 'tenant_id' => $this->tenant->id]);
        $this->employeeDenganStruktur($u1->id, 'Ustadz Satu');
        $this->employeeDenganStruktur($u2->id, 'Ustadz Dua');

        $petugas = User::withoutGlobalScopes()->create(['name' => 'Petugas Payroll', 'email' => 'petugas@payrollmassal.test', 'password' => bcrypt('password'), 'tenant_id' => $this->tenant->id]);
        $petugas->assignRole('petugas_payroll');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($petugas)->postJson("http://{$host}/keuangan/api/payroll/proses", [
            'periode_id' => '2026-06',
        ], $this->headers($host));

        $response->assertStatus(200);
        $response->assertJsonPath('ringkasan.total_pegawai', 2);
        $response->assertJsonPath('ringkasan.slip_dibuat_baru', 2);
        $response->assertJsonPath('ringkasan.slip_final', 2);
        $response->assertJsonPath('ringkasan.gagal', []);

        tenancy()->initialize($this->tenant);
        $this->assertSame(2, SlipGaji::where('periode', '2026-06')->where('status', 'final')->count());
        tenancy()->end();
    }

    public function test_proses_payroll_idempoten_tidak_duplikat_slip_saat_dijalankan_dua_kali(): void
    {
        tenancy()->initialize($this->tenant);

        $u1 = User::withoutGlobalScopes()->create(['name' => 'Ustadz Idem', 'email' => 'idem@payrollmassal.test', 'password' => bcrypt('password'), 'tenant_id' => $this->tenant->id]);
        $this->employeeDenganStruktur($u1->id, 'Ustadz Idem');

        $petugas = User::withoutGlobalScopes()->create(['name' => 'Petugas Payroll Idem', 'email' => 'petugas-idem@payrollmassal.test', 'password' => bcrypt('password'), 'tenant_id' => $this->tenant->id]);
        $petugas->assignRole('petugas_payroll');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $this->actingAs($petugas)->postJson("http://{$host}/keuangan/api/payroll/proses", ['periode_id' => '2026-06'], $this->headers($host))
            ->assertStatus(200)
            ->assertJsonPath('ringkasan.slip_dibuat_baru', 1);

        $response = $this->actingAs($petugas)->postJson("http://{$host}/keuangan/api/payroll/proses", ['periode_id' => '2026-06'], $this->headers($host));
        $response->assertStatus(200);
        $response->assertJsonPath('ringkasan.slip_dibuat_baru', 0);
        $response->assertJsonPath('ringkasan.slip_sudah_ada', 1);

        tenancy()->initialize($this->tenant);
        $this->assertSame(1, SlipGaji::where('periode', '2026-06')->count());
        tenancy()->end();
    }

    public function test_pegawai_tanpa_akun_tercatat_di_gagal_bukan_menghentikan_batch(): void
    {
        tenancy()->initialize($this->tenant);

        $u1 = User::withoutGlobalScopes()->create(['name' => 'Ustadz Valid', 'email' => 'valid@payrollmassal.test', 'password' => bcrypt('password'), 'tenant_id' => $this->tenant->id]);
        $this->employeeDenganStruktur($u1->id, 'Ustadz Valid');
        $this->employeeDenganStruktur(null, 'Ustadz Tanpa Akun');

        $petugas = User::withoutGlobalScopes()->create(['name' => 'Petugas Payroll Gagal', 'email' => 'petugas-gagal@payrollmassal.test', 'password' => bcrypt('password'), 'tenant_id' => $this->tenant->id]);
        $petugas->assignRole('petugas_payroll');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($petugas)->postJson("http://{$host}/keuangan/api/payroll/proses", [
            'periode_id' => '2026-06',
        ], $this->headers($host));

        $response->assertStatus(200);
        $response->assertJsonPath('ringkasan.total_pegawai', 2);
        $response->assertJsonPath('ringkasan.slip_final', 1);
        $response->assertJsonCount(1, 'ringkasan.gagal');
        $response->assertJsonPath('ringkasan.gagal.0.pegawai', 'Ustadz Tanpa Akun');
    }
}
