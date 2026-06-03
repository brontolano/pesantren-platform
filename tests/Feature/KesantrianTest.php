<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Kesantrian\Models\Alumni;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\PenempatanKelas;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Models\StatusSantri;
use Modules\Kesantrian\Services\PenempatanKelasService;
use Modules\Kesantrian\Services\SantriService;
use Tests\TestCase;

class KesantrianTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        // Buat 2 tenant untuk test isolasi
        $this->tenantA = Tenant::create(['id' => 'tenant-a', 'name' => 'Tenant A']);
        $this->tenantA->domains()->create(['domain' => 'tenant-a']);

        $this->tenantB = Tenant::create(['id' => 'tenant-b', 'name' => 'Tenant B']);
        $this->tenantB->domains()->create(['domain' => 'tenant-b']);
    }

    // =====================================================
    // T1.4.1 — NIS Unique per Tenant
    // =====================================================

    public function test_nis_unique_within_same_tenant(): void
    {
        tenancy()->initialize($this->tenantA);

        $kelas = Kelas::create([
            'tenant_id' => 'tenant-a',
            'nama_kelas' => 'VII-A',
            'tingkat' => '7',
            'capacity' => 35,
        ]);

        Santri::create([
            'tenant_id' => 'tenant-a',
            'nis' => 'S001',
            'nama' => 'Ahmad',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);

        // NIS yang sama di tenant yang sama harus gagal (unique constraint)
        $this->expectException(\Illuminate\Database\QueryException::class);

        Santri::create([
            'tenant_id' => 'tenant-a',
            'nis' => 'S001',
            'nama' => 'Umar',
            'tanggal_lahir' => '2012-06-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);

        tenancy()->end();
    }

    public function test_nis_can_be_same_across_different_tenants(): void
    {
        // Tenant A
        tenancy()->initialize($this->tenantA);
        Santri::create([
            'tenant_id' => 'tenant-a',
            'nis' => 'S001',
            'nama' => 'Ahmad Tenant A',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);
        tenancy()->end();

        // Tenant B — NIS sama, harus sukses
        tenancy()->initialize($this->tenantB);
        $santriB = Santri::create([
            'tenant_id' => 'tenant-b',
            'nis' => 'S001',
            'nama' => 'Ahmad Tenant B',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);
        tenancy()->end();

        $this->assertNotNull($santriB->id);
        $this->assertEquals('tenant-b', $santriB->tenant_id);
    }

    // =====================================================
    // T1.4.2 — Engine Mu'adalah vs Linear
    // =====================================================

    public function test_engine_linear_kenaikan_kelas(): void
    {
        tenancy()->initialize($this->tenantA);

        // Set mode linear
        Setting::create(['tenant_id' => 'tenant-a', 'key' => 'kelas_mode', 'value' => 'linear']);

        $kelas7 = Kelas::create(['tenant_id' => 'tenant-a', 'nama_kelas' => 'VII', 'tingkat' => '7', 'capacity' => 35]);
        $kelas8 = Kelas::create(['tenant_id' => 'tenant-a', 'nama_kelas' => 'VIII', 'tingkat' => '8', 'capacity' => 35]);

        $santri = Santri::create([
            'tenant_id' => 'tenant-a',
            'nis' => 'L001',
            'nama' => 'Ahmad Linear',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'kelas_id' => $kelas7->id,
        ]);

        $service = new PenempatanKelasService();
        $hasil = $service->prosesKenaikanKelas('2027/2028', [$kelas7->id => $kelas8->id]);

        $this->assertEquals(1, $hasil['berhasil']);
        $this->assertEquals($kelas8->id, $santri->fresh()->kelas_id);

        tenancy()->end();
    }

    public function test_engine_muadahah_dengan_override(): void
    {
        tenancy()->initialize($this->tenantA);

        // Set mode muadahah
        Setting::create(['tenant_id' => 'tenant-a', 'key' => 'kelas_mode', 'value' => 'muadahah']);

        $kelas7 = Kelas::create(['tenant_id' => 'tenant-a', 'nama_kelas' => 'VII-M', 'tingkat' => '7', 'capacity' => 35]);
        $kelas8 = Kelas::create(['tenant_id' => 'tenant-a', 'nama_kelas' => 'VIII-M', 'tingkat' => '8', 'capacity' => 35]);
        $kelas9 = Kelas::create(['tenant_id' => 'tenant-a', 'nama_kelas' => 'IX-M', 'tingkat' => '9', 'capacity' => 35]);

        $santriNormal = Santri::create([
            'tenant_id' => 'tenant-a', 'nis' => 'M001', 'nama' => 'Normal',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L',
            'status' => 'aktif', 'kelas_id' => $kelas7->id,
        ]);

        $santriLoncat = Santri::create([
            'tenant_id' => 'tenant-a', 'nis' => 'M002', 'nama' => 'Loncat',
            'tanggal_lahir' => '2012-06-01', 'jenis_kelamin' => 'P',
            'status' => 'aktif', 'kelas_id' => $kelas7->id,
        ]);

        $service = new PenempatanKelasService();
        $hasil = $service->prosesKenaikanKelas(
            '2027/2028',
            [$kelas7->id => $kelas8->id],
            [$santriLoncat->id => $kelas9->id] // Override: loncat ke kelas 9
        );

        $this->assertEquals(2, $hasil['berhasil']);
        $this->assertEquals($kelas8->id, $santriNormal->fresh()->kelas_id); // Normal → VIII
        $this->assertEquals($kelas9->id, $santriLoncat->fresh()->kelas_id); // Loncat → IX

        tenancy()->end();
    }

    // =====================================================
    // T1.4.3 — Mutasi santri tidak double-count
    // =====================================================

    public function test_mutasi_santri_tidak_double_count(): void
    {
        tenancy()->initialize($this->tenantA);

        $kelasA = Kelas::create(['tenant_id' => 'tenant-a', 'nama_kelas' => 'VII-A-DC', 'tingkat' => '7', 'capacity' => 35]);
        $kelasB = Kelas::create(['tenant_id' => 'tenant-a', 'nama_kelas' => 'VII-B-DC', 'tingkat' => '7', 'capacity' => 35]);

        $santri = Santri::create([
            'tenant_id' => 'tenant-a', 'nis' => 'DC001', 'nama' => 'Mutasi Test',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L',
            'status' => 'aktif', 'kelas_id' => $kelasA->id,
        ]);

        $service = new PenempatanKelasService();

        // Penempatan awal
        $service->tempatkan($santri, $kelasA, '2026/2027');

        // Mutasi ke kelas B
        $service->mutasi($santri, $kelasB, '2026/2027', 'Pindah kelas');

        // Santri hanya ada di 1 kelas (kelas B)
        $this->assertEquals($kelasB->id, $santri->fresh()->kelas_id);

        // Hanya 1 penempatan aktif
        $penempatanAktif = PenempatanKelas::where('santri_id', $santri->id)
            ->where('is_current', true)
            ->count();
        $this->assertEquals(1, $penempatanAktif);

        // Total riwayat = 2
        $totalPenempatan = PenempatanKelas::where('santri_id', $santri->id)->count();
        $this->assertEquals(2, $totalPenempatan);

        tenancy()->end();
    }

    // =====================================================
    // T1.4.4 — Report santri per kelas akurat
    // =====================================================

    public function test_report_santri_per_kelas_akurat(): void
    {
        tenancy()->initialize($this->tenantA);

        $kelas = Kelas::create(['tenant_id' => 'tenant-a', 'nama_kelas' => 'VII-RPT', 'tingkat' => '7', 'capacity' => 35]);

        for ($i = 1; $i <= 5; $i++) {
            Santri::create([
                'tenant_id' => 'tenant-a',
                'nis' => "RPT00{$i}",
                'nama' => "Santri Report {$i}",
                'tanggal_lahir' => '2012-01-01',
                'jenis_kelamin' => $i <= 3 ? 'L' : 'P',
                'status' => $i <= 4 ? 'aktif' : 'cuti',
                'kelas_id' => $kelas->id,
            ]);
        }

        $service = new SantriService();
        $listAktif = $service->listPerKelas($kelas->id);

        $this->assertCount(4, $listAktif); // 4 aktif, 1 cuti
        $this->assertEquals(4, $kelas->jumlahSantriAktif());
        $this->assertEquals(31, $kelas->sisaKapasitas()); // 35 - 4

        tenancy()->end();
    }

    // =====================================================
    // T1.4.5 — Isolasi tenant: santri Tenant A tidak terlihat di Tenant B
    // =====================================================

    public function test_santri_tenant_a_tidak_terlihat_di_tenant_b(): void
    {
        // Buat santri di Tenant A
        tenancy()->initialize($this->tenantA);
        Santri::create([
            'tenant_id' => 'tenant-a', 'nis' => 'ISO001', 'nama' => 'Santri Tenant A',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);
        $countA = Santri::count();
        tenancy()->end();

        // Buat santri di Tenant B
        tenancy()->initialize($this->tenantB);
        Santri::create([
            'tenant_id' => 'tenant-b', 'nis' => 'ISO002', 'nama' => 'Santri Tenant B',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'P', 'status' => 'aktif',
        ]);
        $countB = Santri::count();
        tenancy()->end();

        $this->assertEquals(1, $countA);
        $this->assertEquals(1, $countB);

        // Verifikasi dari Tenant A tidak bisa lihat Tenant B
        tenancy()->initialize($this->tenantA);
        $santriFromA = Santri::all();
        $this->assertCount(1, $santriFromA);
        $this->assertEquals('Santri Tenant A', $santriFromA->first()->nama);
        tenancy()->end();
    }

    public function test_kelas_tenant_a_tidak_terlihat_di_tenant_b(): void
    {
        tenancy()->initialize($this->tenantA);
        Kelas::create(['tenant_id' => 'tenant-a', 'nama_kelas' => 'VII-ISO-A', 'tingkat' => '7', 'capacity' => 30]);
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        Kelas::create(['tenant_id' => 'tenant-b', 'nama_kelas' => 'VII-ISO-B', 'tingkat' => '7', 'capacity' => 30]);
        $kelasTenantB = Kelas::all();
        tenancy()->end();

        $this->assertCount(1, $kelasTenantB);
        $this->assertEquals('VII-ISO-B', $kelasTenantB->first()->nama_kelas);
    }

    // =====================================================
    // Test tambahan: SantriService flow lengkap
    // =====================================================

    public function test_santri_service_ubah_status_cuti_dan_reaktivasi(): void
    {
        tenancy()->initialize($this->tenantA);

        $santri = Santri::create([
            'tenant_id' => 'tenant-a', 'nis' => 'CUTI001', 'nama' => 'Santri Cuti',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);

        $service = new SantriService();

        // Cuti
        $service->ubahStatus($santri, 'cuti', 'Sakit', 30);
        $this->assertEquals('cuti', $santri->fresh()->status);
        $this->assertEquals(1, StatusSantri::where('santri_id', $santri->id)->where('status', 'cuti')->count());

        // Reaktivasi
        $service->reaktivasi($santri);
        $this->assertEquals('aktif', $santri->fresh()->status);

        tenancy()->end();
    }

    public function test_santri_service_luluskan(): void
    {
        tenancy()->initialize($this->tenantA);

        $santri = Santri::create([
            'tenant_id' => 'tenant-a', 'nis' => 'LULUS001', 'nama' => 'Santri Lulus',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'P', 'status' => 'aktif',
        ]);

        $service = new SantriService();
        $service->luluskan($santri, '2026', 'Kuliah', 'UIN Bandung');

        $this->assertEquals('lulus', $santri->fresh()->status);
        $alumni = Alumni::where('santri_id', $santri->id)->first();
        $this->assertNotNull($alumni);
        $this->assertEquals('2026', $alumni->tahun_lulus);
        $this->assertEquals('UIN Bandung', $alumni->sekolah_tujuan);

        tenancy()->end();
    }
}
