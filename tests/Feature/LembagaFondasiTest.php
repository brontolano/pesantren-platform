<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Modules\Akademik\Database\Seeders\LembagaSeeder;
use Modules\Akademik\Models\Lembaga;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\PresensiSantri;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Modules\Ledger\Models\DivisiUnit;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-01 — Model Lembaga (Blueprint Akademik A-0 poin 1-2).
 *
 * (a) Seeder default idempoten: 1 lembaga "Pondok" per tenant.
 * (b) Unique periode per lembaga: beda lembaga sah; sama lembaga ditolak;
 *     NULL-lembaga ganda tetap ditolak (perilaku lama terjaga via partial index).
 * (c) Kelas lama (lembaga NULL) tetap berfungsi penuh — nilai/presensi jalan.
 * (d) Relasi divisi_unit opsional (sambungan laporan keuangan per unit).
 *
 * Pola baku: createRuntimeTenant (DB fisik per tenant, migrasi otomatis).
 */
class LembagaFondasiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri', 'kelas',
            'lembaga', 'periode_akademik', 'pelajaran', 'nilai',
            'presensi_santri', 'divisi_unit',
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

    // ---------------------------------------------------------------
    // (a) Seeder default idempoten
    // ---------------------------------------------------------------

    public function test_seeder_default_idempoten_satu_lembaga_pondok(): void
    {
        $this->bootTenant('lbsd');

        Artisan::call('db:seed', ['--class' => LembagaSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => LembagaSeeder::class, '--force' => true]);

        $this->assertSame(1, Lembaga::query()->count(), 'Seeder dipanggil dua kali tidak boleh dobel');

        $pondok = Lembaga::query()->first();
        $this->assertSame('Pondok', $pondok->nama);
        $this->assertSame('pondok', $pondok->jenis);
        $this->assertTrue($pondok->aktif);
    }

    public function test_seeder_tidak_dobel_dengan_backfill_migrasi(): void
    {
        // Simulasi tenant lama: backfill migrasi sudah membuat "Pondok" via
        // DB::table (tanpa Eloquent/BelongsToTenant). Seeder reprovision
        // dengan firstOrCreate kunci sama TIDAK boleh menambah baris kedua.
        $tenant = $this->bootTenant('lbbf');

        \Illuminate\Support\Facades\DB::table('lembaga')->insert([
            'tenant_id' => $tenant->id,
            'nama' => 'Pondok',
            'jenis' => 'pondok',
            'aktif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::call('db:seed', ['--class' => LembagaSeeder::class, '--force' => true]);

        $this->assertSame(1, Lembaga::query()->where('nama', 'Pondok')->count(), 'Backfill migrasi + seeder reprovision tidak boleh menghasilkan dua "Pondok"');
    }

    public function test_aktivasi_periode_per_lembaga_tidak_mematikan_lembaga_lain(): void
    {
        // WP-01: aturan "satu periode aktif" kini PER LEMBAGA. Mengaktifkan
        // periode lewat PeriodeAkademikService (jalur Filament Create/Edit)
        // tidak boleh menonaktifkan periode aktif lembaga lain / NULL.
        $this->bootTenant('lbsv');

        $formal = Lembaga::create(['nama' => 'MTs', 'jenis' => 'formal']);
        $diniyah = Lembaga::create(['nama' => 'Madin', 'jenis' => 'diniyah']);

        $svc = app(\Modules\Akademik\Services\PeriodeAkademikService::class);

        $pNull = $svc->simpan([
            'nama' => 'Ganjil Pondok', 'tahun_akademik' => '2026/2027', 'semester' => 'ganjil',
            'tanggal_mulai' => '2026-07-01', 'tanggal_akhir' => '2026-12-31', 'is_active' => true,
        ]);
        $pFormal = $svc->simpan([
            'nama' => 'Ganjil MTs', 'tahun_akademik' => '2026/2027', 'semester' => 'ganjil',
            'tanggal_mulai' => '2026-07-01', 'tanggal_akhir' => '2026-12-31',
            'is_active' => true, 'lembaga_id' => $formal->id,
        ]);
        $pDiniyah = $svc->simpan([
            'nama' => 'Ganjil Madin', 'tahun_akademik' => '2026/2027', 'semester' => 'ganjil',
            'tanggal_mulai' => '2026-07-01', 'tanggal_akhir' => '2026-12-31',
            'is_active' => true, 'lembaga_id' => $diniyah->id,
        ]);

        $this->assertTrue($pNull->fresh()->is_active, 'Periode NULL-lembaga tidak boleh mati saat lembaga lain aktif');
        $this->assertTrue($pFormal->fresh()->is_active);
        $this->assertTrue($pDiniyah->fresh()->is_active);

        // Dalam SATU lembaga aturan lama tetap: aktifkan genap → ganjil mati.
        $pFormalGenap = $svc->simpan([
            'nama' => 'Genap MTs', 'tahun_akademik' => '2026/2027', 'semester' => 'genap',
            'tanggal_mulai' => '2027-01-01', 'tanggal_akhir' => '2027-06-30',
            'is_active' => true, 'lembaga_id' => $formal->id,
        ]);

        $this->assertTrue($pFormalGenap->fresh()->is_active);
        $this->assertFalse($pFormal->fresh()->is_active, 'Dalam lembaga yang sama, hanya satu periode aktif');
        $this->assertTrue($pNull->fresh()->is_active, 'Aktivasi lembaga formal tidak menyentuh periode NULL-lembaga');
        $this->assertTrue($pDiniyah->fresh()->is_active, 'Aktivasi lembaga formal tidak menyentuh lembaga lain');

        // Edit tanpa menyertakan lembaga_id (jalur aktifkan()) tetap scoped.
        $svc->aktifkan($pFormal->fresh());
        $this->assertFalse($pFormalGenap->fresh()->is_active);
        $this->assertTrue($pDiniyah->fresh()->is_active);
        $this->assertTrue($pNull->fresh()->is_active);
    }

    public function test_jenis_lembaga_di_luar_daftar_ditolak_check_constraint(): void
    {
        $this->bootTenant('lbck');

        $this->expectException(QueryException::class);

        Lembaga::create(['nama' => 'Aneh', 'jenis' => 'sekolah_sihir']);
    }

    // ---------------------------------------------------------------
    // (b) Unique periode per lembaga
    // ---------------------------------------------------------------

    public function test_dua_periode_sama_tahun_semester_beda_lembaga_sah(): void
    {
        $this->bootTenant('lbpd');

        $formal = Lembaga::create(['nama' => 'MTs Al-Hikmah', 'jenis' => 'formal', 'jenjang' => 'MTs']);
        $diniyah = Lembaga::create(['nama' => 'Madin Wustha', 'jenis' => 'diniyah', 'jenjang' => 'wustha']);

        PeriodeAkademik::create([
            'nama' => 'Ganjil Formal', 'tahun_akademik' => '2026/2027', 'semester' => 'ganjil',
            'tanggal_mulai' => '2026-07-01', 'tanggal_akhir' => '2026-12-31',
            'is_active' => true, 'lembaga_id' => $formal->id,
        ]);

        $periodeDiniyah = PeriodeAkademik::create([
            'nama' => 'Ganjil Diniyah', 'tahun_akademik' => '2026/2027', 'semester' => 'ganjil',
            'tanggal_mulai' => '2026-07-01', 'tanggal_akhir' => '2026-12-31',
            'is_active' => true, 'lembaga_id' => $diniyah->id,
            'kalender' => 'hijriyah', 'tahun_hijriyah' => '1448/1449',
        ]);

        $this->assertSame(2, PeriodeAkademik::query()->where('is_active', true)->count(), 'Periode aktif GANDA sah asal beda lembaga');
        $this->assertSame('hijriyah', $periodeDiniyah->fresh()->kalender);
        $this->assertSame($diniyah->id, $periodeDiniyah->lembaga->id);
    }

    public function test_dua_periode_sama_tahun_semester_lembaga_sama_ditolak(): void
    {
        $this->bootTenant('lbpx');

        $formal = Lembaga::create(['nama' => 'MA Al-Hikmah', 'jenis' => 'formal']);

        PeriodeAkademik::create([
            'nama' => 'Ganjil', 'tahun_akademik' => '2026/2027', 'semester' => 'ganjil',
            'tanggal_mulai' => '2026-07-01', 'tanggal_akhir' => '2026-12-31',
            'lembaga_id' => $formal->id,
        ]);

        $this->expectException(QueryException::class);

        PeriodeAkademik::create([
            'nama' => 'Ganjil (dobel)', 'tahun_akademik' => '2026/2027', 'semester' => 'ganjil',
            'tanggal_mulai' => '2026-07-01', 'tanggal_akhir' => '2026-12-31',
            'lembaga_id' => $formal->id,
        ]);
    }

    public function test_dua_periode_tanpa_lembaga_sama_tahun_semester_tetap_ditolak(): void
    {
        // Perilaku LAMA terjaga: tenant tanpa lembaga di periode (lembaga_id
        // NULL) tetap tidak boleh dobel tahun+semester — dijaga partial
        // unique index WHERE lembaga_id IS NULL (unique biasa PG lolos NULL).
        $this->bootTenant('lbpn');

        PeriodeAkademik::create([
            'nama' => 'Ganjil', 'tahun_akademik' => '2026/2027', 'semester' => 'ganjil',
            'tanggal_mulai' => '2026-07-01', 'tanggal_akhir' => '2026-12-31',
        ]);

        $this->expectException(QueryException::class);

        PeriodeAkademik::create([
            'nama' => 'Ganjil (dobel tanpa lembaga)', 'tahun_akademik' => '2026/2027', 'semester' => 'ganjil',
            'tanggal_mulai' => '2026-07-01', 'tanggal_akhir' => '2026-12-31',
        ]);
    }

    public function test_kalender_di_luar_masehi_hijriyah_ditolak(): void
    {
        $this->bootTenant('lbkl');

        $this->expectException(QueryException::class);

        PeriodeAkademik::create([
            'nama' => 'Aneh', 'tahun_akademik' => '2026/2027', 'semester' => 'ganjil',
            'tanggal_mulai' => '2026-07-01', 'tanggal_akhir' => '2026-12-31',
            'kalender' => 'jawa',
        ]);
    }

    // ---------------------------------------------------------------
    // (c) Kelas lama (lembaga NULL) tetap berfungsi penuh
    // ---------------------------------------------------------------

    public function test_kelas_tanpa_lembaga_nilai_dan_presensi_tetap_jalan(): void
    {
        $this->bootTenant('lbkc');

        $kelas = Kelas::create([
            'nama_kelas' => 'VII-A', 'tingkat' => '7', 'capacity' => 30, 'is_active' => true,
        ]);
        $this->assertNull($kelas->lembaga_id);
        $this->assertNull($kelas->lembaga); // relasi aman saat NULL

        $santri = Santri::create([
            'nis' => 'LB001', 'nama' => 'Santri LB001',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L',
            'status' => 'aktif', 'kelas_id' => $kelas->id,
        ]);

        $guru = User::create(['name' => 'Guru LB', 'email' => 'guru-lb@test.local', 'password' => 'password']);

        $periode = PeriodeAkademik::create([
            'nama' => 'Ganjil', 'tahun_akademik' => '2026/2027', 'semester' => 'ganjil',
            'tanggal_mulai' => '2026-07-01', 'tanggal_akhir' => '2026-12-31', 'is_active' => true,
        ]);
        $this->assertSame('masehi', $periode->fresh()->kalender, 'Default kalender = masehi (perilaku lama)');

        $pelajaran = Pelajaran::create([
            'nama' => 'Fiqih', 'kode' => 'FQH-7', 'sks' => 2, 'guru_pengampu_id' => $guru->id,
        ]);

        $nilai = Nilai::create([
            'santri_id' => $santri->id, 'pelajaran_id' => $pelajaran->id,
            'periode_akademik_id' => $periode->id, 'kelas_id' => $kelas->id,
            'nilai_harian' => 80, 'nilai_uts' => 75, 'nilai_uas' => 90,
            'input_by' => $guru->id,
        ]);

        $presensi = PresensiSantri::create([
            'santri_id' => $santri->id, 'kelas_id' => $kelas->id,
            'tanggal' => '2026-08-01', 'status' => 'hadir', 'dicatat_oleh' => $guru->id,
        ]);

        $this->assertNotNull($nilai->id);
        $this->assertNotNull($presensi->id);
        $this->assertSame(1, Nilai::query()->where('kelas_id', $kelas->id)->count());
        $this->assertSame(1, PresensiSantri::query()->where('kelas_id', $kelas->id)->count());
    }

    public function test_kelas_bisa_ditautkan_ke_lembaga(): void
    {
        $this->bootTenant('lbkt');

        $lembaga = Lembaga::create(['nama' => 'MTs', 'jenis' => 'formal']);
        $kelas = Kelas::create([
            'nama_kelas' => 'VIII-B', 'tingkat' => '8', 'capacity' => 30,
            'lembaga_id' => $lembaga->id,
        ]);

        $this->assertSame($lembaga->id, $kelas->fresh()->lembaga->id);
        $this->assertSame(1, $lembaga->kelas()->count());
    }

    // ---------------------------------------------------------------
    // (d) Relasi divisi_unit opsional
    // ---------------------------------------------------------------

    public function test_relasi_divisi_unit_opsional(): void
    {
        $this->bootTenant('lbdu');

        $tanpaDivisi = Lembaga::create(['nama' => 'Madin', 'jenis' => 'diniyah']);
        $this->assertNull($tanpaDivisi->divisi_unit_id);
        $this->assertNull($tanpaDivisi->divisiUnit);

        $divisi = DivisiUnit::create([
            'kode' => 'MTS', 'nama_divisi' => 'Madrasah Tsanawiyah',
            'tipe_layanan' => 'Formal', 'is_active' => true,
        ]);

        $lembaga = Lembaga::create([
            'nama' => 'MTs Berdivisi', 'jenis' => 'formal', 'divisi_unit_id' => $divisi->id,
        ]);

        $this->assertSame($divisi->id, $lembaga->fresh()->divisiUnit->id);

        // Divisi dihapus (soft delete tak memicu FK; force delete → null).
        $divisi->forceDelete();
        $this->assertNull($lembaga->fresh()->divisi_unit_id, 'FK nullOnDelete — lembaga selamat saat divisi dihapus');
    }
}
