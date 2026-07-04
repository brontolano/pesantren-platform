<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Modules\Payroll\Models\Cuti;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\SlipGaji;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F4 — Portal PWA Kepegawaian (menggantikan KepegawaianDemoController).
 * - 403 tanpa permission run_payroll
 * - 200 dengan permission (role petugas_payroll)
 * - Halaman pegawai/slip/cuti benar-benar membaca data dari Modules\Payroll
 *   (Employee/SlipGaji/Cuti) — model yang SUDAH dipakai panel admin
 *   (EmployeeResource/SlipGajiResource/CutiResource), tanpa duplikasi logika.
 */
class KepegawaianModulTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions',
            'employee', 'cuti', 'slip_gaji', 'presensi_staf',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('f4pegawai', 'Pesantren F4 Kepegawaian');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);
        tenancy()->end();
    }

    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    public function test_akses_ditolak_403_tanpa_permission_run_payroll(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Guru Biasa', 'email' => 'guru-nonpayroll@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('guru');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/kepegawaian", $this->headers($host));

        $response->assertStatus(403);
    }

    public function test_akses_diterima_200_dengan_role_petugas_payroll(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Petugas Payroll', 'email' => 'payroll@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('petugas_payroll');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/kepegawaian", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Kepegawaian/Beranda'));
    }

    public function test_halaman_pegawai_menampilkan_data_nyata(): void
    {
        tenancy()->initialize($this->tenant);

        Employee::create([
            'tenant_id' => $this->tenant->id, 'nama' => 'Ustadz Uji Pegawai', 'nip' => '199001012020011001',
            'jabatan' => 'Guru Fiqih', 'status_kepegawaian' => 'gty', 'aktif' => true,
            'tanggal_masuk' => '2020-01-01',
        ]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Petugas Payroll Pegawai', 'email' => 'payroll-pegawai@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('petugas_payroll');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/kepegawaian/pegawai", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Kepegawaian/Pegawai')
            ->where('pegawai.0.nama', 'Ustadz Uji Pegawai')
        );
    }

    public function test_halaman_slip_menampilkan_data_nyata(): void
    {
        tenancy()->initialize($this->tenant);

        $pegawai = Employee::create([
            'tenant_id' => $this->tenant->id, 'nama' => 'Ustadz Uji Slip', 'nip' => '199002022021011002',
            'jabatan' => 'Guru Tahfizh', 'status_kepegawaian' => 'gty', 'aktif' => true,
            'tanggal_masuk' => '2021-01-01',
        ]);
        $penerimaUser = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Ustadz Uji Slip', 'email' => 'penerima-slip@f4.test', 'password' => bcrypt('password'),
        ]);

        SlipGaji::create([
            'tenant_id' => $this->tenant->id, 'users_id' => $penerimaUser->id, 'periode' => '2026-06',
            'tipe' => 'bulanan', 'honor_pokok' => 4000000, 'tunjangan' => 500000,
            'bruto' => 4500000, 'pph21_dipotong' => 0, 'bpjs_dipotong' => 0,
            'potongan_cuti' => 0, 'potongan_kasbon' => 0, 'neto' => 4500000, 'status' => 'final',
        ]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Petugas Payroll Slip', 'email' => 'payroll-slip@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('petugas_payroll');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/kepegawaian/slip", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Kepegawaian/Slip')
            ->where('slip.0.nama', 'Ustadz Uji Slip')
            ->where('slip.0.neto', 4_500_000)
        );

        $this->assertNotNull($pegawai);
    }

    public function test_halaman_cuti_menampilkan_data_nyata(): void
    {
        tenancy()->initialize($this->tenant);

        $pegawai = Employee::create([
            'tenant_id' => $this->tenant->id, 'nama' => 'Ustadz Uji Cuti', 'nip' => '199003032022011003',
            'jabatan' => 'Guru Bahasa Arab', 'status_kepegawaian' => 'gty', 'aktif' => true,
            'tanggal_masuk' => '2022-01-01',
        ]);

        Cuti::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $pegawai->id, 'jenis' => 'tahunan',
            'is_berbayar' => true, 'tanggal_mulai' => now()->addDays(3)->toDateString(),
            'tanggal_selesai' => now()->addDays(5)->toDateString(), 'jumlah_hari' => 3,
            'alasan' => 'Acara keluarga.', 'status' => 'diajukan',
        ]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Petugas Payroll Cuti', 'email' => 'payroll-cuti@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('petugas_payroll');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/kepegawaian/cuti", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Kepegawaian/Cuti')
            ->where('pengajuan.0.nama', 'Ustadz Uji Cuti')
            ->where('pengajuan.0.status', 'diajukan')
        );
    }
}
