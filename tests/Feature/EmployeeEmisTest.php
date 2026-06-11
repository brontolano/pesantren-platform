<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\Jabatan;
use Modules\Payroll\Models\PenugasanPegawai;
use Modules\Payroll\Services\PayrollService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-04 — Perluasan data pegawai (EMIS-ready). Menguji:
 * (a) migrasi data status lama termap benar (tetap→gty, honorer→gtt_honorer,
 *     kontrak tetap, nilai tak dikenal dibiarkan);
 * (b) PayrollService.buatSlip jalan untuk status baru (pengabdian, pns_dpk);
 * (c) sinkron satu arah tanggal_selesai → aktif=false (aktif tetap sumber
 *     kebenaran; hapus tanggal_selesai TIDAK auto-aktif);
 * (d) kolom identitas EMIS tersimpan.
 */
class EmployeeEmisTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'akun', 'sumber_dana', 'periode_akuntansi',
            'slip_gaji', 'employee', 'komponen_gaji', 'jurnal', 'jurnal_baris',
            'roles', 'permissions', 'model_has_roles', 'jabatan', 'penugasan_pegawai',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new LedgerSeeder)->run();
    }

    private function user(): User
    {
        return User::withoutGlobalScopes()->create([
            'name' => 'Ustadz', 'email' => 'u'.str()->random(5).'@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ]);
    }

    private function employeeDenganStruktur(string $status, ?int $usersId): Employee
    {
        $e = Employee::create([
            'users_id' => $usersId, 'nama' => 'Ustadz '.$status,
            'status_kepegawaian' => $status, 'ptkp_status' => 'TK0', 'aktif' => true,
        ]);
        $e->komponenGaji()->createMany([
            ['nama' => 'Honor Pokok', 'tipe' => 'tetap', 'nominal' => 1500000, 'aktif' => true],
            ['nama' => 'Tunjangan', 'tipe' => 'tunjangan', 'nominal' => 300000, 'aktif' => true],
        ]);

        return $e->fresh('komponenGaji');
    }

    // (a) Migrasi data: status lama termap, nilai baru/tak dikenal dibiarkan.
    public function test_migrasi_map_status_lama(): void
    {
        $this->boot('emis1');
        $now = now();
        $baris = fn (string $nama, string $status) => [
            'tenant_id' => tenant('id'), 'nama' => $nama, 'status_kepegawaian' => $status,
            'ptkp_status' => 'TK0', 'aktif' => true, 'created_at' => $now, 'updated_at' => $now,
        ];
        // Baris "lama" via DB raw — sebelum logic mapping dijalankan.
        DB::table('employee')->insert([
            $baris('A Tetap', 'tetap'),
            $baris('B Honorer', 'honorer'),
            $baris('C Kontrak', 'kontrak'),
            $baris('D Sudah Baru', 'pengabdian'),
            $baris('E Aneh', 'magang'), // nilai tak dikenal: JANGAN dirusak
        ]);

        $migrasi = require base_path('Modules/Payroll/database/migrations/2026_06_25_100000_extend_employee_emis_fields.php');
        $migrasi->mapStatusKepegawaianLama();

        $status = fn (string $nama) => DB::table('employee')->where('nama', $nama)->value('status_kepegawaian');
        $this->assertSame('gty', $status('A Tetap'));
        $this->assertSame('gtt_honorer', $status('B Honorer'));
        $this->assertSame('kontrak', $status('C Kontrak'));
        $this->assertSame('pengabdian', $status('D Sudah Baru'));
        $this->assertSame('magang', $status('E Aneh'));

        // Idempoten: jalan dua kali hasil sama.
        $migrasi->mapStatusKepegawaianLama();
        $this->assertSame('gty', $status('A Tetap'));
        tenancy()->end();
    }

    // (b) Slip gaji jalan untuk status baru.
    public function test_buat_slip_untuk_status_pengabdian_dan_pns_dpk(): void
    {
        $this->boot('emis2');
        $svc = app(PayrollService::class);

        foreach (['pengabdian', 'pns_dpk'] as $status) {
            $e = $this->employeeDenganStruktur($status, $this->user()->id);
            $slip = $svc->buatSlipUntukEmployee($e, '2026-06');

            $this->assertEqualsWithDelta(1500000, (float) $slip->honor_pokok, 0.01, $status);
            $this->assertEqualsWithDelta(1800000, (float) $slip->bruto, 0.01, $status);
            $this->assertSame('draft', $slip->status, $status);
        }
        tenancy()->end();
    }

    // (c) tanggal_selesai diisi → aktif false; hapus tanggal_selesai TIDAK auto-aktif.
    public function test_tanggal_selesai_menonaktifkan_pegawai(): void
    {
        $this->boot('emis3');
        $e = Employee::create(['nama' => 'Purna', 'status_kepegawaian' => 'gty', 'aktif' => true]);

        $e->update(['tanggal_selesai' => '2026-06-30']);
        $this->assertFalse($e->fresh()->aktif, 'isi tanggal_selesai harus menonaktifkan');

        // Hapus tanggal_selesai: aktif TETAP false (admin mengaktifkan sengaja).
        $e->refresh()->update(['tanggal_selesai' => null]);
        $this->assertFalse($e->fresh()->aktif);

        // Admin aktifkan manual (tanpa sentuh tanggal_selesai): boleh.
        $e->refresh()->update(['aktif' => true]);
        $this->assertTrue($e->fresh()->aktif);
        tenancy()->end();
    }

    // (d) Kolom identitas EMIS tersimpan & ter-cast.
    public function test_kolom_identitas_emis_tersimpan(): void
    {
        $this->boot('emis4');
        $e = Employee::create([
            'nama' => 'Lengkap', 'status_kepegawaian' => 'gty',
            'nik' => '3201010101010001', 'nuptk' => '1234567890123456', 'nrg' => 'NRG-99',
            'is_mukim' => true, 'pendidikan_terakhir' => 'S1 PAI', 'asal_alumni' => true,
        ])->fresh();

        $this->assertSame('3201010101010001', $e->nik);
        $this->assertSame('1234567890123456', $e->nuptk);
        $this->assertSame('NRG-99', $e->nrg);
        $this->assertTrue($e->is_mukim);
        $this->assertTrue($e->asal_alumni);
        $this->assertSame('S1 PAI', $e->pendidikan_terakhir);
        $this->assertArrayHasKey($e->status_kepegawaian, Employee::STATUS_KEPEGAWAIAN);
        tenancy()->end();
    }

    // (e) LUBANG AKSES (review adversarial): pegawai dinonaktifkan →
    //     penugasan aktif DITUTUP otomatis dan role Spatie DICABUT.
    public function test_nonaktif_menutup_penugasan_dan_mencabut_role(): void
    {
        $this->boot('emis5');
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $user = $this->user();
        $e = Employee::create(['nama' => 'Purna Role', 'status_kepegawaian' => 'gty', 'users_id' => $user->id, 'aktif' => true]);
        $jabatan = Jabatan::create(['nama' => 'Guru Mapel E', 'kategori' => 'pengajar', 'role_spatie' => 'guru']);
        $p = PenugasanPegawai::create(['employee_id' => $e->id, 'jabatan_id' => $jabatan->id, 'tanggal_mulai' => '2026-01-01', 'is_primary' => true]);

        $this->assertTrue($user->fresh()->hasRole('guru'), 'prasyarat: penugasan aktif memberi role');

        // Jalur 1: nonaktif via tanggal_selesai (resign/purna).
        $e->update(['tanggal_selesai' => '2026-06-30']);
        $e->refresh();
        $this->assertFalse($e->aktif);
        $this->assertNotNull($p->fresh()->tanggal_selesai, 'penugasan aktif harus ikut ditutup');
        $this->assertSame('2026-06-30', $p->fresh()->tanggal_selesai->toDateString());
        $this->assertFalse($user->fresh()->hasRole('guru'), 'pegawai purna TIDAK boleh tetap pegang role');

        // Jalur 2: toggle aktif=false langsung (tanpa tanggal_selesai).
        $user2 = $this->user();
        $e2 = Employee::create(['nama' => 'Nonaktif Manual', 'status_kepegawaian' => 'kontrak', 'users_id' => $user2->id, 'aktif' => true]);
        $p2 = PenugasanPegawai::create(['employee_id' => $e2->id, 'jabatan_id' => $jabatan->id, 'tanggal_mulai' => '2026-01-01', 'is_primary' => true]);
        $this->assertTrue($user2->fresh()->hasRole('guru'));

        $e2->update(['aktif' => false]);
        $this->assertNotNull($p2->fresh()->tanggal_selesai);
        $this->assertFalse($user2->fresh()->hasRole('guru'));
        tenancy()->end();
    }

    // (f) NIK unik NULL-safe: dua NIK kosong sah, duplikat NIK terisi ditolak DB
    //     (impor Excel bypass form — constraint DB wajib).
    public function test_nik_unik_null_safe(): void
    {
        $this->boot('emis6');
        Employee::create(['nama' => 'Tanpa NIK 1', 'status_kepegawaian' => 'gty']);
        Employee::create(['nama' => 'Tanpa NIK 2', 'status_kepegawaian' => 'gty']); // NULL ganda sah
        Employee::create(['nama' => 'Ber-NIK', 'status_kepegawaian' => 'gty', 'nik' => '3201010101010001']);

        try {
            Employee::create(['nama' => 'Duplikat', 'status_kepegawaian' => 'gty', 'nik' => '3201010101010001']);
            $this->fail('duplikat NIK seharusnya ditolak constraint unik DB');
        } catch (QueryException $ex) {
            $this->assertStringContainsStringIgnoringCase('nik', $ex->getMessage());
        }
        tenancy()->end();
    }
}
