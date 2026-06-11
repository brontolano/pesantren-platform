<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\JurnalMengajar;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\PenilaianUstadz;
use Modules\Payroll\Models\PresensiStaf;
use Modules\Payroll\Services\RaporUstadzService;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-20 — Rapor Ustadz Tahunan.
 *
 * (a) Angka rekap cocok query manual: seed jurnal (2 terlaksana + 1 kosong
 *     tahun uji + 1 tahun lain), 1 slot jadwal, 2 nilai input_by, presensi
 *     3 hadir + 1 alpa → persentase persis.
 * (b) Gate: non-mudir (tanpa manage_payroll/run_payroll) tak bisa menilai
 *     (AuthorizationException); mudir bisa (updateOrCreate per tahun).
 * (c) bolehLihat: mudir ya; pegawai ybs ya (read-only); user lain tidak.
 */
class RaporUstadzTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'employee', 'penilaian_ustadz',
            'jurnal_mengajar', 'jadwal_pelajaran', 'pelajaran', 'periode_akademik',
            'kelas', 'santri', 'nilai', 'presensi_staf', 'permissions',
        ]);

        $this->truncatePpdbTables([
            'penilaian_ustadz', 'jurnal_mengajar', 'nilai', 'jadwal_pelajaran',
            'pelajaran', 'periode_akademik', 'santri', 'kelas', 'presensi_staf',
            'employee', 'model_has_permissions', 'permissions', 'users',
            'domains', 'tenants',
        ]);
    }

    private function boot(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        return $tenant;
    }

    private function buatUser(string $nama, array $permissions = []): User
    {
        $user = User::withoutGlobalScopes()->create([
            'name' => $nama,
            'email' => str()->slug($nama).rand(100, 999).'@'.tenant('id').'.localhost',
            'password' => 'password',
            'tenant_id' => tenant('id'),
        ]);

        foreach ($permissions as $izin) {
            Permission::findOrCreate($izin, 'web');
            $user->givePermissionTo($izin);
        }

        return $user;
    }

    /** @return array{0: Employee, 1: User} */
    private function buatUstadz(): array
    {
        $guru = $this->buatUser('Ust Guru');
        $employee = Employee::create([
            'users_id' => $guru->id, 'nama' => 'Ust Guru', 'ptkp_status' => 'TK0', 'aktif' => true,
        ]);

        return [$employee, $guru];
    }

    // ---------------------------------------------------------------
    // (a) Rekap cocok query manual
    // ---------------------------------------------------------------

    public function test_rekap_cocok_dengan_query_manual(): void
    {
        $tenant = $this->boot('rpu1');
        [$employee, $guru] = $this->buatUstadz();
        $tahun = 2026;

        $kelas = Kelas::create(['tenant_id' => $tenant->id, 'nama_kelas' => 'I-A', 'tingkat' => '1']);
        $periode = PeriodeAkademik::create([
            'tenant_id' => $tenant->id, 'nama' => 'Ganjil 2026', 'tahun_akademik' => '2026/2027',
            'semester' => 'ganjil', 'tanggal_mulai' => '2026-07-01', 'tanggal_akhir' => '2026-12-31',
            'is_active' => true,
        ]);
        $pelajaran = Pelajaran::create([
            'tenant_id' => $tenant->id, 'nama' => 'Nahwu', 'kode' => 'NHW-1', 'sks' => 2,
            'guru_pengampu_id' => $guru->id,
        ]);
        $jadwal = JadwalPelajaran::create([
            'tenant_id' => $tenant->id, 'kelas_id' => $kelas->id, 'pelajaran_id' => $pelajaran->id,
            'periode_akademik_id' => $periode->id, 'hari' => 'senin',
            'jam_mulai' => '07:30', 'jam_selesai' => '08:30',
        ]);

        // 3 jurnal tahun uji (2 terlaksana, 1 kosong) + 1 jurnal tahun lain.
        foreach ([['2026-01-05', 'terlaksana'], ['2026-01-12', 'terlaksana'], ['2026-01-19', 'kosong']] as [$tgl, $status]) {
            JurnalMengajar::create([
                'tenant_id' => $tenant->id, 'guru_id' => $guru->id,
                'jadwal_pelajaran_id' => $jadwal->id, 'tanggal' => $tgl, 'status' => $status,
            ]);
        }
        JurnalMengajar::create([
            'tenant_id' => $tenant->id, 'guru_id' => $guru->id,
            'jadwal_pelajaran_id' => $jadwal->id, 'tanggal' => '2025-12-01', 'status' => 'terlaksana',
        ]);

        // 2 nilai diinput guru ini + 1 oleh guru lain.
        $guruLain = $this->buatUser('Ust Lain');
        $santriA = Santri::create(['tenant_id' => $tenant->id, 'nis' => 'RPU01', 'nama' => 'Santri A',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif', 'kelas_id' => $kelas->id]);
        $santriB = Santri::create(['tenant_id' => $tenant->id, 'nis' => 'RPU02', 'nama' => 'Santri B',
            'tanggal_lahir' => '2012-02-01', 'jenis_kelamin' => 'P', 'status' => 'aktif', 'kelas_id' => $kelas->id]);
        foreach ([[$santriA, $guru->id], [$santriB, $guru->id]] as [$santri, $inputBy]) {
            Nilai::create([
                'tenant_id' => $tenant->id, 'santri_id' => $santri->id, 'pelajaran_id' => $pelajaran->id,
                'periode_akademik_id' => $periode->id, 'kelas_id' => $kelas->id,
                'nilai_akhir' => 80, 'input_by' => $inputBy,
            ]);
        }
        $pelajaranLain = Pelajaran::create(['tenant_id' => $tenant->id, 'nama' => 'Shorof', 'kode' => 'SRF-1',
            'sks' => 1, 'guru_pengampu_id' => $guruLain->id]);
        Nilai::create([
            'tenant_id' => $tenant->id, 'santri_id' => $santriA->id, 'pelajaran_id' => $pelajaranLain->id,
            'periode_akademik_id' => $periode->id, 'kelas_id' => $kelas->id,
            'nilai_akhir' => 70, 'input_by' => $guruLain->id,
        ]);

        // Presensi staf: 3 hadir + 1 alpa tahun uji + 1 hadir tahun lain.
        foreach ([['2026-01-05', 'hadir'], ['2026-01-06', 'hadir'], ['2026-01-07', 'hadir'], ['2026-01-08', 'alpa']] as [$tgl, $status]) {
            PresensiStaf::create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id,
                'tanggal' => $tgl, 'status' => $status]);
        }
        PresensiStaf::create(['tenant_id' => $tenant->id, 'employee_id' => $employee->id,
            'tanggal' => '2025-06-01', 'status' => 'hadir']);

        $rekap = app(RaporUstadzService::class)->rekap($employee, $tahun);

        // Query manual sebagai pembanding.
        $this->assertSame(3, $rekap['jurnal_total'], 'jurnal tahun lain tidak ikut');
        $this->assertSame(2, $rekap['jurnal_terlaksana']);
        $this->assertSame(66.67, $rekap['kehadiran_sesi_pct']);

        $this->assertSame(1, $rekap['slot_jadwal_mingguan']);
        $this->assertSame(52, $rekap['jurnal_ekspektasi']);
        $this->assertSame(round(3 / 52 * 100, 2), $rekap['jurnal_terisi_pct']);

        $this->assertSame(2, $rekap['nilai_input_total'], 'nilai guru lain tidak ikut');

        $this->assertSame(4, $rekap['presensi_total']);
        $this->assertSame(3, $rekap['presensi_hadir']);
        $this->assertSame(75.0, $rekap['presensi_pct']);

        $this->assertNull($rekap['penilaian'], 'belum ada penilaian mudir');
    }

    public function test_rekap_employee_tanpa_user_aman_nol(): void
    {
        $this->boot('rpu2');
        $employee = Employee::create(['nama' => 'Staf Tanpa Akun', 'ptkp_status' => 'TK0', 'aktif' => true]);

        $rekap = app(RaporUstadzService::class)->rekap($employee, 2026);

        $this->assertSame(0, $rekap['jurnal_total']);
        $this->assertNull($rekap['kehadiran_sesi_pct']);
        $this->assertNull($rekap['jurnal_terisi_pct']);
        $this->assertSame(0, $rekap['nilai_input_total']);
    }

    // ---------------------------------------------------------------
    // (b) Gate penilaian
    // ---------------------------------------------------------------

    public function test_non_mudir_tidak_bisa_menilai(): void
    {
        $this->boot('rpu3');
        [$employee] = $this->buatUstadz();
        $stafBiasa = $this->buatUser('Staf Biasa'); // tanpa izin payroll

        $this->expectException(AuthorizationException::class);
        app(RaporUstadzService::class)->simpanPenilaian($employee, 2026, ['Keteladanan' => 90], null, $stafBiasa);
    }

    public function test_mudir_bisa_menilai_dan_update_per_tahun(): void
    {
        $this->boot('rpu4');
        [$employee] = $this->buatUstadz();
        $mudir = $this->buatUser('Mudir', ['run_payroll']);

        $p1 = app(RaporUstadzService::class)->simpanPenilaian(
            $employee, 2026, ['Keteladanan' => 90, 'Kedisiplinan' => 80], 'Bagus', $mudir,
        );
        $this->assertSame(85.0, $p1->rataRata());
        $this->assertSame($mudir->id, $p1->dinilai_oleh);

        // Nilai ulang tahun sama → update (unique per pegawai per tahun).
        app(RaporUstadzService::class)->simpanPenilaian($employee, 2026, ['Keteladanan' => 95], 'Revisi', $mudir);
        $this->assertSame(1, PenilaianUstadz::query()->where('employee_id', $employee->id)->count());
        $this->assertSame('Revisi', PenilaianUstadz::query()->firstOrFail()->catatan);

        // Penilaian muncul di rekap.
        $rekap = app(RaporUstadzService::class)->rekap($employee, 2026);
        $this->assertNotNull($rekap['penilaian']);
    }

    // ---------------------------------------------------------------
    // (c) Akses lihat rapor
    // ---------------------------------------------------------------

    public function test_boleh_lihat_mudir_dan_pegawai_ybs_saja(): void
    {
        $this->boot('rpu5');
        [$employee, $guru] = $this->buatUstadz();
        $mudir = $this->buatUser('Mudir Lihat', ['manage_payroll']);
        $orangLain = $this->buatUser('Orang Lain');

        $svc = app(RaporUstadzService::class);
        $this->assertTrue($svc->bolehLihat($employee, $mudir), 'mudir boleh');
        $this->assertTrue($svc->bolehLihat($employee, $guru), 'pegawai ybs boleh lihat rapornya sendiri');
        $this->assertFalse($svc->bolehLihat($employee, $orangLain), 'user lain 403');
    }
}
