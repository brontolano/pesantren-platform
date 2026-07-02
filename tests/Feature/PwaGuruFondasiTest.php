<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Modules\Akademik\Database\Seeders\KomponenNilaiSeeder;
use Modules\Akademik\Models\CapaianKitab;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\JurnalMengajar;
use Modules\Akademik\Models\KajianKitab;
use Modules\Akademik\Models\Kitab;
use Modules\Akademik\Models\KomponenNilai;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Services\NilaiService;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Modules\Tahfidz\Models\Halaqah;
use Modules\Tahfidz\Models\HalaqahSantri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F0-PWA — Fondasi data PWA Guru: halaqah (soft-close), jurnal mengajar
 * (XOR jadwal/halaqah + anti-dobel), kajian kitab (sorogan/bandongan),
 * komponen nilai konfigurabel (NilaiService satu-satunya rumus).
 *
 * Pola baku: createRuntimeTenant (DB fisik per tenant, migrasi otomatis).
 */
class PwaGuruFondasiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri', 'kelas',
            'halaqah', 'halaqah_santri', 'jurnal_mengajar',
            'kitab', 'kajian_kitab', 'capaian_kitab', 'komponen_nilai',
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
    // (a) Halaqah + keanggotaan soft-close
    // ---------------------------------------------------------------

    public function test_halaqah_anggota_keluar_di_soft_close_bukan_dihapus(): void
    {
        $this->bootTenant('pwahq');

        $musyrif = $this->createGuru('musyrif@test.local');
        $halaqah = Halaqah::create([
            'nama' => 'Halaqah Al-Fatih',
            'musyrif_id' => $musyrif->id,
            'jenis' => 'tahfidz',
            'aktif' => true,
        ]);

        $santri = $this->createSantri('HQ001');

        $anggota = HalaqahSantri::create([
            'halaqah_id' => $halaqah->id,
            'santri_id' => $santri->id,
            'joined_at' => '2026-06-01',
        ]);

        $this->assertCount(1, $halaqah->keanggotaanAktif()->get());
        $this->assertCount(1, $halaqah->santriAktif()->get());

        // Keluar dari halaqah = soft-close, riwayat TIDAK dihapus.
        $anggota->tutup(now());

        $this->assertCount(0, $halaqah->keanggotaanAktif()->get());
        $this->assertCount(1, $halaqah->keanggotaan()->get(), 'Riwayat keanggotaan harus tetap tersimpan');
        $this->assertNotNull($anggota->fresh()->left_at);

        // Setelah soft-close, boleh bergabung lagi (keanggotaan aktif baru).
        HalaqahSantri::create([
            'halaqah_id' => $halaqah->id,
            'santri_id' => $santri->id,
            'joined_at' => '2026-07-01',
        ]);
        $this->assertCount(2, $halaqah->keanggotaan()->get());
        $this->assertCount(1, $halaqah->keanggotaanAktif()->get());
    }

    public function test_satu_keanggotaan_aktif_per_santri_per_halaqah(): void
    {
        $this->bootTenant('pwahq2');

        $musyrif = $this->createGuru('musyrif-uq@test.local');
        $halaqah = Halaqah::create([
            'nama' => 'Halaqah B',
            'musyrif_id' => $musyrif->id,
            'jenis' => 'tahfidz',
        ]);
        $santri = $this->createSantri('HQ002');

        HalaqahSantri::create([
            'halaqah_id' => $halaqah->id,
            'santri_id' => $santri->id,
            'joined_at' => '2026-06-01',
        ]);

        $this->expectException(QueryException::class);

        HalaqahSantri::create([
            'halaqah_id' => $halaqah->id,
            'santri_id' => $santri->id,
            'joined_at' => '2026-06-02',
        ]);
    }

    // ---------------------------------------------------------------
    // (b) Jurnal mengajar: XOR jadwal/halaqah + anti-dobel
    // ---------------------------------------------------------------

    public function test_jurnal_tanpa_konteks_ditolak(): void
    {
        $this->bootTenant('pwaj0');
        $guru = $this->createGuru('guru-j0@test.local');

        $this->expectException(ValidationException::class);

        JurnalMengajar::create([
            'guru_id' => $guru->id,
            'tanggal' => '2026-06-11',
            'status' => 'terlaksana',
        ]);
    }

    public function test_jurnal_dengan_dua_konteks_sekaligus_ditolak(): void
    {
        $this->bootTenant('pwaj2');
        $guru = $this->createGuru('guru-j2@test.local');
        $jadwal = $this->createJadwalFixture($guru);
        $halaqah = Halaqah::create([
            'nama' => 'Halaqah J2',
            'musyrif_id' => $guru->id,
            'jenis' => 'tahfidz',
        ]);

        $this->expectException(ValidationException::class);

        JurnalMengajar::create([
            'guru_id' => $guru->id,
            'jadwal_pelajaran_id' => $jadwal->id,
            'halaqah_id' => $halaqah->id,
            'tanggal' => '2026-06-11',
            'status' => 'terlaksana',
        ]);
    }

    public function test_jurnal_jadwal_yang_sama_di_hari_yang_sama_ditolak_unik(): void
    {
        $this->bootTenant('pwajuq');
        $guru = $this->createGuru('guru-juq@test.local');
        $jadwal = $this->createJadwalFixture($guru);

        JurnalMengajar::create([
            'guru_id' => $guru->id,
            'jadwal_pelajaran_id' => $jadwal->id,
            'tanggal' => '2026-06-11',
            'materi' => 'Bab 1',
            'status' => 'terlaksana',
        ]);

        $this->expectException(QueryException::class);

        JurnalMengajar::create([
            'guru_id' => $guru->id,
            'jadwal_pelajaran_id' => $jadwal->id,
            'tanggal' => '2026-06-11',
            'materi' => 'Bab 1 (dobel — retry sinyal jelek)',
            'status' => 'terlaksana',
        ]);
    }

    public function test_jurnal_halaqah_yang_sama_di_hari_yang_sama_ditolak_unik(): void
    {
        $this->bootTenant('pwajhq');
        $guru = $this->createGuru('guru-jhq@test.local');
        $halaqah = Halaqah::create([
            'nama' => 'Halaqah JHQ',
            'musyrif_id' => $guru->id,
            'jenis' => 'tahfidz',
        ]);

        JurnalMengajar::create([
            'guru_id' => $guru->id,
            'halaqah_id' => $halaqah->id,
            'tanggal' => '2026-06-11',
            'status' => 'terlaksana',
        ]);

        $this->expectException(QueryException::class);

        JurnalMengajar::create([
            'guru_id' => $guru->id,
            'halaqah_id' => $halaqah->id,
            'tanggal' => '2026-06-11',
            'status' => 'terlaksana',
        ]);
    }

    public function test_jurnal_dua_halaqah_berbeda_di_hari_yang_sama_sah(): void
    {
        $this->bootTenant('pwajok');
        $guru = $this->createGuru('guru-jok@test.local');

        foreach (['A', 'B'] as $nama) {
            $halaqah = Halaqah::create([
                'nama' => "Halaqah {$nama}",
                'musyrif_id' => $guru->id,
                'jenis' => 'tahfidz',
            ]);
            JurnalMengajar::create([
                'guru_id' => $guru->id,
                'halaqah_id' => $halaqah->id,
                'tanggal' => '2026-06-11',
                'status' => 'terlaksana',
            ]);
        }

        $this->assertSame(2, JurnalMengajar::query()->count());
    }

    // ---------------------------------------------------------------
    // (c) Capaian kitab: sorogan per santri & bandongan kelompok
    // ---------------------------------------------------------------

    public function test_capaian_kitab_sorogan_per_santri_dan_bandongan_kelompok(): void
    {
        $this->bootTenant('pwakt');

        $pengampu = $this->createGuru('pengampu@test.local');
        $santri = $this->createSantri('KT001');

        $kitab = Kitab::create([
            'nama' => 'Fathul Qarib',
            'pengarang' => 'Ibnu Qasim Al-Ghazzi',
            'kategori' => 'fiqih',
            'jumlah_halaman' => 120,
        ]);

        $sorogan = KajianKitab::create([
            'kitab_id' => $kitab->id,
            'pengampu_id' => $pengampu->id,
            'metode' => 'sorogan',
            'aktif' => true,
        ]);

        $bandongan = KajianKitab::create([
            'kitab_id' => $kitab->id,
            'pengampu_id' => $pengampu->id,
            'metode' => 'bandongan',
            'aktif' => true,
        ]);

        // Sorogan: capaian PER SANTRI.
        $capaianSorogan = CapaianKitab::create([
            'kajian_kitab_id' => $sorogan->id,
            'santri_id' => $santri->id,
            'posisi' => 'Bab Thaharah hal. 12',
            'tanggal' => '2026-06-11',
            'status' => 'lanjut',
            'dicatat_oleh' => $pengampu->id,
        ]);

        // Bandongan: santri_id NULL = posisi KELOMPOK.
        $capaianKelompok = CapaianKitab::create([
            'kajian_kitab_id' => $bandongan->id,
            'santri_id' => null,
            'posisi' => 'Hal. 45',
            'tanggal' => '2026-06-11',
            'status' => 'lanjut',
            'dicatat_oleh' => $pengampu->id,
        ]);

        $this->assertFalse($capaianSorogan->isKelompok());
        $this->assertTrue($capaianKelompok->isKelompok());
        $this->assertSame(1, $sorogan->capaian()->whereNotNull('santri_id')->count());
        $this->assertSame(1, $bandongan->capaian()->whereNull('santri_id')->count());

        // Khatam tercatat sebagai status (riwayat capaian bertambah, bukan ditimpa).
        CapaianKitab::create([
            'kajian_kitab_id' => $sorogan->id,
            'santri_id' => $santri->id,
            'posisi' => 'Selesai',
            'tanggal' => '2026-07-01',
            'status' => 'khatam',
            'dicatat_oleh' => $pengampu->id,
        ]);

        $this->assertSame(1, $sorogan->capaian()->where('status', 'khatam')->count());
        $this->assertSame(2, $sorogan->capaian()->count());
    }

    // ---------------------------------------------------------------
    // (d) NilaiService: bobot custom vs fallback rumus lama
    // ---------------------------------------------------------------

    public function test_nilai_akhir_bobot_custom_berbeda_dari_default(): void
    {
        $this->bootTenant('pwanl');

        $service = app(NilaiService::class);

        // Fallback rumus lama (tanpa komponen, tanpa nilai_komponen).
        $fallback = $service->hitungNilaiAkhir([
            'nilai_harian' => 80,
            'nilai_uts' => 70,
            'nilai_uas' => 90,
        ]);

        // Komponen custom: Tugas 70 / Ujian 30.
        $tugas = KomponenNilai::create([
            'pelajaran_id' => null,
            'nama' => 'Tugas',
            'bobot' => 70,
            'urutan' => 1,
        ]);
        $ujian = KomponenNilai::create([
            'pelajaran_id' => null,
            'nama' => 'Ujian',
            'bobot' => 30,
            'urutan' => 2,
        ]);

        $custom = $service->hitungNilaiAkhir([
            'nilai_komponen' => [
                (string) $tugas->id => 80,
                (string) $ujian->id => 90,
            ],
        ]);

        // 80×0.7 + 90×0.3 = 83.0
        $this->assertSame(83.0, $custom);
        $this->assertNotSame($fallback, $custom, 'Bobot custom harus menghasilkan nilai berbeda dari rumus default');
    }

    public function test_tenant_tanpa_komponen_nilai_tetap_pakai_rumus_lama(): void
    {
        $this->bootTenant('pwanl2');

        $service = app(NilaiService::class);

        // Tenant lama: tidak ada baris komponen_nilai — bahkan bila payload
        // menyertakan nilai_komponen, fallback rumus lama tetap berlaku.
        $hasil = $service->hitungNilaiAkhir([
            'nilai_harian' => 80,
            'nilai_uts' => 70,
            'nilai_uas' => 90,
            'nilai_komponen' => ['999' => 100],
        ]);

        // gap-85: default tunggal (Setting kosong) kini harian 40 / uts 30 / uas 30
        // (= KomponenNilaiSeeder::DEFAULTS; double-default 30/30/40 dihapus).
        $this->assertSame(round((80 * 40 + 70 * 30 + 90 * 30) / 100, 2), $hasil);
    }

    public function test_komponen_per_pelajaran_mengalahkan_default_tenant(): void
    {
        $this->bootTenant('pwanl3');

        $guru = $this->createGuru('guru-nl3@test.local');
        $pelajaran = Pelajaran::create([
            'nama' => 'Nahwu',
            'kode' => 'NHW-1',
            'sks' => 2,
            'guru_pengampu_id' => $guru->id,
        ]);

        $this->seed(KomponenNilaiSeeder::class); // default tenant 40/30/30

        $praktik = KomponenNilai::create([
            'pelajaran_id' => $pelajaran->id,
            'nama' => 'Praktik',
            'bobot' => 100,
            'urutan' => 1,
        ]);

        $service = app(NilaiService::class);

        $hasil = $service->hitungNilaiAkhir([
            'pelajaran_id' => $pelajaran->id,
            'nilai_komponen' => [(string) $praktik->id => 88],
        ]);

        $this->assertSame(88.0, $hasil, 'Komponen spesifik pelajaran harus dipakai, bukan default tenant');
    }

    // ---------------------------------------------------------------
    // (e) Seeder komponen default idempoten
    // ---------------------------------------------------------------

    public function test_seeder_komponen_nilai_default_idempoten(): void
    {
        $this->bootTenant('pwasd');

        $this->seed(KomponenNilaiSeeder::class);
        $this->seed(KomponenNilaiSeeder::class); // run kedua — tidak boleh dobel

        $komponen = KomponenNilai::query()->whereNull('pelajaran_id')->orderBy('urutan')->get();

        $this->assertCount(3, $komponen);
        $this->assertSame(['Harian', 'UTS', 'UAS'], $komponen->pluck('nama')->all());
        $this->assertSame([40, 30, 30], $komponen->pluck('bobot')->all());
        $this->assertSame(100, (int) $komponen->sum('bobot'));

        // Bobot yang sudah diubah tenant TIDAK ditimpa run berikutnya.
        $komponen->firstWhere('nama', 'Harian')->update(['bobot' => 50]);
        $this->seed(KomponenNilaiSeeder::class);
        $this->assertSame(50, KomponenNilai::query()->whereNull('pelajaran_id')->where('nama', 'Harian')->first()->bobot);
    }

    // ---------------------------------------------------------------
    // (f) Penjagaan id komponen liar pada simpan()
    // ---------------------------------------------------------------

    public function test_simpan_menolak_id_komponen_yang_tidak_dikenal(): void
    {
        $this->bootTenant('pwanl4');

        $guru = $this->createGuru('guru-nl4@test.local');
        $jadwal = $this->createJadwalFixture($guru);

        $santri = $this->createSantri('NL4001');
        $santri->update(['kelas_id' => $jadwal->kelas_id]);

        $this->seed(KomponenNilaiSeeder::class); // tenant PUNYA komponen

        $service = app(NilaiService::class);

        // Id komponen liar (usang / pelajaran lain / klien PWA rusak) —
        // tanpa guard, skor diabaikan diam-diam dan nilai_akhir ≈ 0.
        $this->expectException(ValidationException::class);

        $service->simpan([
            'santri_id' => $santri->id,
            'pelajaran_id' => $jadwal->pelajaran_id,
            'periode_akademik_id' => $jadwal->periode_akademik_id,
            'kelas_id' => $jadwal->kelas_id,
            'nilai_komponen' => ['999999' => 95],
        ]);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function createGuru(string $email): User
    {
        return User::create([
            'name' => 'Guru PWA',
            'email' => $email,
            'password' => 'password',
        ]);
    }

    private function createSantri(string $nis): Santri
    {
        return Santri::create([
            'nis' => $nis,
            'nama' => 'Santri '.$nis,
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);
    }

    private function createJadwalFixture(User $guru): JadwalPelajaran
    {
        $kelas = Kelas::create([
            'nama_kelas' => 'VII-A',
            'tingkat' => '7',
            'capacity' => 30,
            'is_active' => true,
        ]);

        $periode = PeriodeAkademik::create([
            'nama' => 'Semester Ganjil',
            'tahun_akademik' => '2026/2027',
            'semester' => 'ganjil',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_akhir' => '2026-12-31',
            'is_active' => true,
        ]);

        $pelajaran = Pelajaran::create([
            'nama' => 'Fiqih',
            'kode' => 'FQH-7',
            'sks' => 2,
            'guru_pengampu_id' => $guru->id,
        ]);

        return JadwalPelajaran::create([
            'kelas_id' => $kelas->id,
            'pelajaran_id' => $pelajaran->id,
            'periode_akademik_id' => $periode->id,
            'hari' => 'kamis',
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:30',
        ]);
    }
}
