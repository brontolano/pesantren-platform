<?php

namespace Tests\Feature;

use App\Filament\Pages\KesiapanEmis;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Akademik\Models\Lembaga;
use Modules\Kesantrian\Models\Santri;
use Modules\Payroll\Models\Employee;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-12 — Kesiapan EMIS. Menguji:
 * (a) angka rekap santri cocok query manual (5 santri, 3 ber-NIK → 60%),
 *     santri non-aktif & NIK string kosong TIDAK dihitung;
 * (b) angka pegawai (NIK/NUPTK/sertifikasi) & lembaga tanpa legalitas benar;
 * (c) kolom is_mukim tersimpan di santri;
 * (d) halaman render < 500 utk user login (smoke).
 */
class Wp12KesiapanEmisTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles',
            'santri', 'employee', 'sertifikasi_pegawai', 'lembaga',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    private function santri(string $nis, array $extra = []): Santri
    {
        return Santri::create(array_merge([
            'tenant_id' => tenant('id'),
            'nis' => $nis,
            'nama' => 'Santri '.$nis,
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ], $extra));
    }

    // (a) Rekap santri: 5 aktif, 3 ber-NIK → 60%; 2 ber-NISN → 40%.
    public function test_rekap_santri_cocok_query_manual(): void
    {
        $this->boot('emisa');

        $this->santri('S1', ['nik' => '3201010101010001', 'nisn' => '0012345678']);
        $this->santri('S2', ['nik' => '3201010101010002', 'nisn' => '0012345679']);
        $this->santri('S3', ['nik' => '3201010101010003']);
        $this->santri('S4', ['nik' => '']); // string kosong = belum diisi
        $this->santri('S5');
        // Non-aktif ber-NIK: TIDAK boleh masuk hitungan.
        $this->santri('S6', ['nik' => '3201010101010006', 'status' => 'lulus']);

        $stat = KesiapanEmis::hitungStatistik();

        $this->assertSame(5, $stat['santri']['total']);
        $this->assertSame(3, $stat['santri']['ber_nik']);
        $this->assertSame(2, $stat['santri']['ber_nisn']);
        $this->assertSame(60.0, $stat['santri']['persen_nik']);
        $this->assertSame(40.0, $stat['santri']['persen_nisn']);
        tenancy()->end();
    }

    // (b) Pegawai: NIK/NUPTK/sertifikasi; lembaga tanpa NSM/IJOP.
    public function test_rekap_pegawai_dan_lembaga_benar(): void
    {
        $this->boot('emisb');

        $a = Employee::create(['nama' => 'A', 'status_kepegawaian' => 'gty', 'aktif' => true,
            'nik' => '3201010101019001', 'nuptk' => '1234567890123456']);
        $b = Employee::create(['nama' => 'B', 'status_kepegawaian' => 'gtt_honorer', 'aktif' => true,
            'nik' => '3201010101019002']);
        Employee::create(['nama' => 'C', 'status_kepegawaian' => 'gty', 'aktif' => true]);
        // Non-aktif: tidak dihitung.
        Employee::create(['nama' => 'D', 'status_kepegawaian' => 'gty', 'aktif' => false,
            'nik' => '3201010101019004', 'nuptk' => '6543210987654321']);

        $a->sertifikasi()->create([
            'tenant_id' => tenant('id'), 'jenis' => 'tpg', 'nomor_sk' => 'TPG-001',
        ]);

        Lembaga::create(['tenant_id' => tenant('id'), 'nama' => 'MTs', 'jenis' => 'formal',
            'nomor_statistik' => '121232110001', 'izin_operasional' => 'IJOP-1']);
        Lembaga::create(['tenant_id' => tenant('id'), 'nama' => 'Pondok', 'jenis' => 'pondok']);
        Lembaga::create(['tenant_id' => tenant('id'), 'nama' => 'Diniyah', 'jenis' => 'diniyah',
            'nomor_statistik' => '', 'izin_operasional' => 'IJOP-2']);

        $stat = KesiapanEmis::hitungStatistik();

        $this->assertSame(3, $stat['pegawai']['total']);
        $this->assertSame(2, $stat['pegawai']['ber_nik']);
        $this->assertSame(1, $stat['pegawai']['ber_nuptk']);
        $this->assertSame(1, $stat['pegawai']['ber_sertifikasi']);
        $this->assertEqualsWithDelta(66.7, $stat['pegawai']['persen_nik'], 0.01);

        $this->assertSame(3, $stat['lembaga']['total']);
        $this->assertSame(2, $stat['lembaga']['tanpa_legalitas']);
        tenancy()->end();
    }

    // (c) Kolom is_mukim tersimpan & ter-cast boolean; NULL = belum didata.
    public function test_kolom_is_mukim_tersimpan(): void
    {
        $this->boot('emisc');

        $mukim = $this->santri('M1', ['is_mukim' => true]);
        $laju = $this->santri('M2', ['is_mukim' => false]);
        $belum = $this->santri('M3');

        $this->assertTrue($mukim->fresh()->is_mukim);
        $this->assertFalse($laju->fresh()->is_mukim);
        $this->assertNull($belum->fresh()->is_mukim);
        tenancy()->end();
    }

    // (d) Smoke: halaman render < 500 untuk user login.
    public function test_halaman_kesiapan_emis_render(): void
    {
        $this->boot('emisd');
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => tenant('id'), 'name' => 'Admin Emis',
            'email' => 'emis@panel.test', 'password' => 'password123',
        ]);
        if (Schema::connection('tenant')->hasTable('roles')
            && DB::connection('tenant')->table('roles')->where('name', 'super_admin')->exists()) {
            $user->assignRole('super_admin');
        }
        Auth::login($user);

        $host = tenant('id').'.localhost';
        $req = Request::create("http://{$host}/admin/kesiapan-emis", 'GET');
        $req->headers->set('Host', $host);
        $status = app(Kernel::class)->handle($req)->getStatusCode();

        $this->assertLessThan(500, $status, "Render /admin/kesiapan-emis mengembalikan {$status}.");
        tenancy()->end();
    }
}
