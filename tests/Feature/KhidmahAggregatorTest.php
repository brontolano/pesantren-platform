<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\JurnalMengajar;
use Modules\Akademik\Models\Lembaga;
use Modules\Akademik\Models\Pelajaran;
use Modules\Kesantrian\Models\Kelas;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\Jabatan;
use Modules\Payroll\Models\Khidmah;
use Modules\Payroll\Models\PenugasanPegawai;
use Modules\Payroll\Models\PresensiStaf;
use Modules\Payroll\Models\SlipGaji;
use Modules\Payroll\Models\TarifKhidmah;
use Modules\Payroll\Services\KhidmahAggregatorService;
use Modules\Tahfidz\Models\Halaqah;
use RuntimeException;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-13 — Loop Bisyaroh: KhidmahAggregatorService (MONEY path ⚠️).
 *
 * (a) Agregasi 2× idempoten — jumlah baris & total poin sama; baris manual selamat.
 * (b) Jurnal status 'kosong' tidak menghasilkan poin.
 * (c) Sesi 'diganti' → poin ke pengganti, guru asal 0, tidak dobel.
 * (d) Guru 2 penugasan 2 jabatan → tarif per penugasan (jenis sesi) benar.
 * (e) Periode dengan slip FINAL → agregasi ditolak (exception).
 * (f) 2 tenant tarif beda → poin beda (policy-first).
 */
class KhidmahAggregatorTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private const PERIODE = '2026-06';

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'employee', 'jabatan', 'penugasan_pegawai',
            'halaqah', 'jurnal_mengajar', 'presensi_staf', 'khidmah', 'tarif_khidmah', 'slip_gaji',
            'lembaga', 'kelas', 'pelajaran', 'jadwal_pelajaran',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function bootTenant(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        return $tenant;
    }

    /** @return array{0: Employee, 1: User} */
    private function buatPegawai(Tenant $tenant, string $suffix): array
    {
        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Ustadz '.$suffix,
            'email' => "ustadz-{$suffix}@test.local",
            'password' => 'password123',
        ]);

        $employee = Employee::create([
            'nama' => 'Ustadz '.$suffix,
            'nip' => 'NIP-'.$suffix,
            'users_id' => $user->id,
        ]);

        return [$employee, $user];
    }

    private function tugaskan(Employee $employee, string $namaJabatan, bool $primary = false, ?int $lembagaId = null): Jabatan
    {
        $jabatan = Jabatan::firstOrCreate(['nama' => $namaJabatan], ['kategori' => 'pengajar', 'role_spatie' => null]);
        PenugasanPegawai::create([
            'employee_id' => $employee->id,
            'jabatan_id' => $jabatan->id,
            'lembaga_id' => $lembagaId,
            'tanggal_mulai' => '2026-01-01',
            'is_primary' => $primary,
        ]);

        return $jabatan;
    }

    /** Jadwal pelajaran formal di kelas milik sebuah lembaga (sumber jam_formal). */
    private function buatJadwalDiLembaga(string $namaLembaga): array
    {
        $lembaga = Lembaga::create(['nama' => $namaLembaga, 'jenis' => 'formal']);
        $kelas = Kelas::create(['nama_kelas' => 'VII-'.$namaLembaga, 'tingkat' => '7', 'lembaga_id' => $lembaga->id]);
        $pelajaran = Pelajaran::firstOrCreate(['nama' => 'Fiqih'], ['kode' => 'FQH']);
        $jadwal = JadwalPelajaran::create([
            'kelas_id' => $kelas->id,
            'pelajaran_id' => $pelajaran->id,
            'hari' => 'senin',
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:00',
        ]);

        return [$lembaga, $jadwal];
    }

    private function buatHalaqah(User $musyrif, string $jenis = 'tahfidz'): Halaqah
    {
        return Halaqah::create([
            'nama' => 'Halaqah '.$jenis.' '.$musyrif->id.'-'.str()->random(4),
            'musyrif_id' => $musyrif->id,
            'jenis' => $jenis,
            'aktif' => true,
        ]);
    }

    private function buatJurnal(User $guru, Halaqah $halaqah, string $tanggal, string $status = 'terlaksana', ?int $penggantiId = null): JurnalMengajar
    {
        return JurnalMengajar::create([
            'guru_id' => $guru->id,
            'halaqah_id' => $halaqah->id,
            'tanggal' => $tanggal,
            'status' => $status,
            'pengganti_id' => $penggantiId,
            'materi' => 'Materi uji',
        ]);
    }

    private function agregator(): KhidmahAggregatorService
    {
        return app(KhidmahAggregatorService::class);
    }

    // ---------------------------------------------------------------
    // (a) Idempoten + baris manual selamat (+ presensi hadir_kantor)
    // ---------------------------------------------------------------

    public function test_agregasi_dua_kali_idempoten_dan_baris_manual_selamat(): void
    {
        $tenant = $this->bootTenant('khid');
        [$emp, $user] = $this->buatPegawai($tenant, 'idem');
        $jabatan = $this->tugaskan($emp, 'Mustahiq Tahfidz', primary: true);

        TarifKhidmah::create(['jabatan_id' => $jabatan->id, 'jenis_sesi' => 'sesi_tahfidz', 'poin' => '2.50']);
        TarifKhidmah::create(['jabatan_id' => null, 'jenis_sesi' => 'hadir_kantor', 'poin' => '1.00']); // default tenant

        $halaqah = $this->buatHalaqah($user);
        $this->buatJurnal($user, $halaqah, '2026-06-01');
        $this->buatJurnal($user, $halaqah, '2026-06-02');
        PresensiStaf::create(['employee_id' => $emp->id, 'tanggal' => '2026-06-01', 'status' => 'hadir']);
        PresensiStaf::create(['employee_id' => $emp->id, 'tanggal' => '2026-06-03', 'status' => 'izin']); // bukan hadir → tanpa poin

        // Baris manual bendahara — TIDAK boleh disentuh agregasi.
        $manual = Khidmah::create(['employee_id' => $emp->id, 'periode' => self::PERIODE, 'poin' => '7.25', 'keterangan' => 'Bonus manual']);
        $this->assertTrue($manual->fresh()->is_manual, 'Create biasa default is_manual=true');

        $hasil1 = $this->agregator()->agregasi(self::PERIODE);
        $hasil2 = $this->agregator()->agregasi(self::PERIODE);

        $this->assertSame($hasil1['baris'], $hasil2['baris'], 'Agregasi 2× — jumlah baris sama');
        $this->assertSame($hasil1['total_poin'], $hasil2['total_poin'], 'Agregasi 2× — total poin sama');
        $this->assertSame(3, $hasil2['baris'], '2 jurnal tahfidz + 1 presensi hadir');
        $this->assertSame('6.00', $hasil2['total_poin'], '2×2.50 + 1×1.00');

        $rows = Khidmah::where('periode', self::PERIODE)->get();
        $this->assertCount(4, $rows, '3 agregasi + 1 manual');
        $this->assertNotNull($manual->fresh(), 'Baris manual selamat dari agregasi ulang');
        $this->assertSame('7.25', (string) $manual->fresh()->poin);
        $rows->where('is_manual', false)->each(function (Khidmah $k): void {
            $this->assertNotNull($k->sumber_type, 'Baris agregasi tertelusur ke sumber');
            $this->assertNotNull($k->sumber_id);
        });
    }

    // ---------------------------------------------------------------
    // (b) Jurnal 'kosong' tidak menghasilkan poin
    // ---------------------------------------------------------------

    public function test_jurnal_kosong_tidak_menghasilkan_poin(): void
    {
        $tenant = $this->bootTenant('khko');
        [$emp, $user] = $this->buatPegawai($tenant, 'kosong');
        $jabatan = $this->tugaskan($emp, 'Mustahiq Tahfidz');
        TarifKhidmah::create(['jabatan_id' => $jabatan->id, 'jenis_sesi' => 'sesi_tahfidz', 'poin' => '2.00']);

        $this->buatJurnal($user, $this->buatHalaqah($user), '2026-06-05', status: 'kosong');

        $hasil = $this->agregator()->agregasi(self::PERIODE);

        $this->assertSame(0, $hasil['baris']);
        $this->assertSame(0, Khidmah::where('periode', self::PERIODE)->count());
    }

    // ---------------------------------------------------------------
    // (c) Sesi 'diganti' → poin ke pengganti, guru asal 0, tidak dobel
    // ---------------------------------------------------------------

    public function test_sesi_diganti_poin_ke_pengganti_bukan_guru_asal_tidak_dobel(): void
    {
        $tenant = $this->bootTenant('khdg');
        [$empAsal, $userAsal] = $this->buatPegawai($tenant, 'asal');
        [$empPengganti, $userPengganti] = $this->buatPegawai($tenant, 'pengganti');
        $jabatan = $this->tugaskan($empAsal, 'Mustahiq Tahfidz');
        $this->tugaskan($empPengganti, 'Mustahiq Tahfidz');
        TarifKhidmah::create(['jabatan_id' => $jabatan->id, 'jenis_sesi' => 'sesi_tahfidz', 'poin' => '3.00']);

        $this->buatJurnal($userAsal, $this->buatHalaqah($userAsal), '2026-06-10', status: 'diganti', penggantiId: $userPengganti->id);

        $hasil = $this->agregator()->agregasi(self::PERIODE);

        $this->assertSame(1, $hasil['baris'], 'Satu sesi = satu baris — tidak dobel');
        $this->assertSame(0, Khidmah::where('employee_id', $empAsal->id)->count(), 'Guru asal 0 poin');
        $poinPengganti = Khidmah::where('employee_id', $empPengganti->id)->get();
        $this->assertCount(1, $poinPengganti);
        $this->assertSame('3.00', (string) $poinPengganti->first()->poin);
    }

    // ---------------------------------------------------------------
    // (d) Guru 2 penugasan 2 jabatan → tarif per penugasan
    // ---------------------------------------------------------------

    public function test_guru_dua_penugasan_dua_jabatan_tarif_per_penugasan(): void
    {
        $tenant = $this->bootTenant('khdj');
        [$emp, $user] = $this->buatPegawai($tenant, 'rangkap');
        $mustahiq = $this->tugaskan($emp, 'Mustahiq Tahfidz', primary: true);
        $guruDiniyah = $this->tugaskan($emp, 'Guru Diniyah');

        TarifKhidmah::create(['jabatan_id' => $mustahiq->id, 'jenis_sesi' => 'sesi_tahfidz', 'poin' => '4.00']);
        TarifKhidmah::create(['jabatan_id' => $guruDiniyah->id, 'jenis_sesi' => 'sesi_diniyah', 'poin' => '1.50']);

        $this->buatJurnal($user, $this->buatHalaqah($user, 'tahfidz'), '2026-06-08');
        $this->buatJurnal($user, $this->buatHalaqah($user, 'diniyah'), '2026-06-09');

        $this->agregator()->agregasi(self::PERIODE);

        $rows = Khidmah::where('employee_id', $emp->id)->get()->keyBy('jenis_sesi');
        $this->assertCount(2, $rows);
        $this->assertSame('4.00', (string) $rows['sesi_tahfidz']->poin, 'Sesi tahfidz pakai tarif jabatan Mustahiq');
        $this->assertSame('1.50', (string) $rows['sesi_diniyah']->poin, 'Sesi diniyah pakai tarif jabatan Guru Diniyah');
    }

    // ---------------------------------------------------------------
    // (d2) Guru 2 LEMBAGA 2 tarif — penugasan dicocokkan ke lembaga sumber
    // ---------------------------------------------------------------

    public function test_guru_dua_lembaga_tarif_per_lembaga_sumber(): void
    {
        $tenant = $this->bootTenant('khlb');
        [$emp, $user] = $this->buatPegawai($tenant, 'dualembaga');

        [$lembagaMts, $jadwalMts] = $this->buatJadwalDiLembaga('MTs');
        [$lembagaMa, $jadwalMa] = $this->buatJadwalDiLembaga('MA');

        // PRIMARY di MTs — tanpa pencocokan lembaga sumber, sesi MA akan
        // salah memakai tarif MTs (temuan adversarial WP-13).
        $guruMts = $this->tugaskan($emp, 'Guru MTs', primary: true, lembagaId: $lembagaMts->id);
        $guruMa = $this->tugaskan($emp, 'Guru MA', lembagaId: $lembagaMa->id);

        TarifKhidmah::create(['jabatan_id' => $guruMts->id, 'jenis_sesi' => 'jam_formal', 'poin' => '2.00']);
        TarifKhidmah::create(['jabatan_id' => $guruMa->id, 'jenis_sesi' => 'jam_formal', 'poin' => '5.00']);

        JurnalMengajar::create(['guru_id' => $user->id, 'jadwal_pelajaran_id' => $jadwalMts->id, 'tanggal' => '2026-06-01', 'status' => 'terlaksana']);
        JurnalMengajar::create(['guru_id' => $user->id, 'jadwal_pelajaran_id' => $jadwalMa->id, 'tanggal' => '2026-06-02', 'status' => 'terlaksana']);

        $this->agregator()->agregasi(self::PERIODE);

        $rows = Khidmah::where('employee_id', $emp->id)->orderBy('sumber_id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('2.00', (string) $rows[0]->poin, 'Sesi di MTs pakai tarif jabatan lembaga MTs');
        $this->assertSame('5.00', (string) $rows[1]->poin, 'Sesi di MA pakai tarif jabatan lembaga MA — bukan tarif primary');
    }

    // ---------------------------------------------------------------
    // (e) Periode dengan slip FINAL → agregasi ditolak
    // ---------------------------------------------------------------

    public function test_periode_dengan_slip_final_agregasi_ditolak(): void
    {
        $tenant = $this->bootTenant('khfn');
        [$emp, $user] = $this->buatPegawai($tenant, 'final');

        SlipGaji::create([
            'users_id' => $user->id, 'periode' => self::PERIODE,
            'honor_pokok' => '1000000.00', 'bruto' => '1000000.00', 'neto' => '1000000.00',
            'status' => 'final',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('terkunci');

        $this->agregator()->agregasi(self::PERIODE);
    }

    // ---------------------------------------------------------------
    // (f) 2 tenant tarif beda → poin beda (policy-first)
    // ---------------------------------------------------------------

    public function test_dua_tenant_tarif_beda_poin_beda(): void
    {
        $jalankan = function (string $prefix, string $tarifPoin): string {
            $tenant = $this->bootTenant($prefix);
            [$emp, $user] = $this->buatPegawai($tenant, $prefix);
            $jabatan = $this->tugaskan($emp, 'Mustahiq Tahfidz '.$prefix);
            TarifKhidmah::create(['jabatan_id' => $jabatan->id, 'jenis_sesi' => 'sesi_tahfidz', 'poin' => $tarifPoin]);
            $this->buatJurnal($user, $this->buatHalaqah($user), '2026-06-15');

            $hasil = $this->agregator()->agregasi(self::PERIODE);
            tenancy()->end();

            return $hasil['total_poin'];
        };

        $poinA = $jalankan('khta', '2.00');
        $poinB = $jalankan('khtb', '5.00');

        $this->assertSame('2.00', $poinA);
        $this->assertSame('5.00', $poinB);
        $this->assertNotSame($poinA, $poinB, 'Policy-first: tarif per tenant → poin beda');
    }

    // ---------------------------------------------------------------
    // (g) Integrasi slip: SlipGaji.poin = Σ khidmah periode
    // ---------------------------------------------------------------

    public function test_generate_slip_membaca_total_poin_khidmah(): void
    {
        $tenant = $this->bootTenant('khgs');
        [$emp, $user] = $this->buatPegawai($tenant, 'slip');
        $jabatan = $this->tugaskan($emp, 'Mustahiq Tahfidz');
        TarifKhidmah::create(['jabatan_id' => $jabatan->id, 'jenis_sesi' => 'sesi_tahfidz', 'poin' => '3.00']);

        $halaqah = $this->buatHalaqah($user);
        $this->buatJurnal($user, $halaqah, '2026-06-01');
        $this->buatJurnal($user, $halaqah, '2026-06-02');

        $this->agregator()->agregasi(self::PERIODE);
        $hasil = $this->agregator()->generateSlip(self::PERIODE);

        $this->assertSame(1, $hasil['dibuat']);
        $slip = SlipGaji::where('users_id', $user->id)->where('periode', self::PERIODE)->first();
        $this->assertNotNull($slip);
        $this->assertSame('draft', $slip->status, 'Slip hasil generate = DRAFT (finalisasi jalur lama)');
        $this->assertSame(6, (int) $slip->poin, 'SlipGaji.poin = Σ khidmah periode (mesin slip casting int — tak diubah)');
    }

    // ---------------------------------------------------------------
    // (h) Σ poin pecahan → slip DIBULATKAN (10.75 → 11), bukan truncate (10)
    // ---------------------------------------------------------------

    public function test_generate_slip_total_pecahan_dibulatkan_bukan_truncate(): void
    {
        $tenant = $this->bootTenant('khrd');
        [$emp, $user] = $this->buatPegawai($tenant, 'pecahan');
        $jabatan = $this->tugaskan($emp, 'Mustahiq Tahfidz');
        TarifKhidmah::create(['jabatan_id' => $jabatan->id, 'jenis_sesi' => 'sesi_tahfidz', 'poin' => '2.15']);

        $halaqah = $this->buatHalaqah($user);
        foreach (['01', '02', '03', '04', '05'] as $d) {
            $this->buatJurnal($user, $halaqah, '2026-06-'.$d); // 5 × 2.15 = 10.75
        }

        $hasil = $this->agregator()->agregasi(self::PERIODE);
        $this->assertSame('10.75', $hasil['total_poin']);

        $this->agregator()->generateSlip(self::PERIODE);

        $slip = SlipGaji::where('users_id', $user->id)->where('periode', self::PERIODE)->first();
        $this->assertSame(11, (int) $slip->poin, 'Σ 10.75 → 11 (round half-up), BUKAN 10 (truncate = kurang bayar)');
    }

    // ---------------------------------------------------------------
    // (i) Draft basi: generateSlip ulang dengan poin berubah → ditolak keras
    // ---------------------------------------------------------------

    public function test_generate_slip_draft_basi_poin_berubah_ditolak(): void
    {
        $tenant = $this->bootTenant('khst');
        [$emp, $user] = $this->buatPegawai($tenant, 'basi');
        $jabatan = $this->tugaskan($emp, 'Mustahiq Tahfidz');
        TarifKhidmah::create(['jabatan_id' => $jabatan->id, 'jenis_sesi' => 'sesi_tahfidz', 'poin' => '3.00']);

        $this->buatJurnal($user, $this->buatHalaqah($user), '2026-06-01');
        $this->agregator()->agregasi(self::PERIODE);
        $this->agregator()->generateSlip(self::PERIODE); // draft poin = 3

        // Poin berubah SETELAH draft dibuat (bonus manual bendahara).
        Khidmah::create(['employee_id' => $emp->id, 'periode' => self::PERIODE, 'poin' => '5.00', 'keterangan' => 'Bonus manual']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('poin lama');

        $this->agregator()->generateSlip(self::PERIODE); // 3 ≠ 8 → tolak, jangan kembalikan draft basi diam-diam
    }

    // ---------------------------------------------------------------
    // (j) Pengganti tanpa Employee → dihitung & dilaporkan, bukan diam
    // ---------------------------------------------------------------

    public function test_pengganti_tanpa_employee_dilaporkan_di_hasil_agregasi(): void
    {
        $tenant = $this->bootTenant('khne');
        [$emp, $user] = $this->buatPegawai($tenant, 'punyaemp');
        $jabatan = $this->tugaskan($emp, 'Mustahiq Tahfidz');
        TarifKhidmah::create(['jabatan_id' => $jabatan->id, 'jenis_sesi' => 'sesi_tahfidz', 'poin' => '2.00']);

        // User pengganti TANPA baris Employee.
        $tanpaEmp = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Tanpa Employee',
            'email' => 'tanpa-emp@test.local', 'password' => 'password123',
        ]);

        $this->buatJurnal($user, $this->buatHalaqah($user), '2026-06-01', status: 'diganti', penggantiId: $tanpaEmp->id);

        $hasil = $this->agregator()->agregasi(self::PERIODE);

        $this->assertSame(0, $hasil['baris']);
        $this->assertSame(1, $hasil['dilewati_tanpa_employee'], 'Sesi pengganti tanpa Employee dilaporkan, bukan hilang diam-diam');
    }
}
