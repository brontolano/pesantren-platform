<?php

namespace Tests\Feature;

use Modules\Akademik\Models\Lembaga;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Services\EmisExportService;
use Modules\Payroll\Models\Employee;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * WP-22 — Ekspor EMIS per kategori (santri/pegawai/lembaga).
 * Data kurang → kolom KOSONG + daftar peringatan; ekspor TIDAK gagal.
 */
class EmisExportKategoriTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'santri', 'employee', 'lembaga', 'tagihan',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function bootTenant(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    public function test_baris_santri_kolom_emis_terisi_dan_data_kurang_jadi_peringatan(): void
    {
        $this->bootTenant('em1');

        // Lengkap.
        Santri::create([
            'nis' => 'S-100', 'nama' => 'Lengkap', 'status' => 'aktif',
            'tanggal_lahir' => '2012-03-05', 'tempat_lahir' => 'Sumedang',
            'jenis_kelamin' => 'L', 'nik' => '3211000000000001', 'nisn' => '0051234567',
            'alamat' => 'Jl. Pesantren 1', 'is_mukim' => true,
        ]);
        // Kurang NIK + NISN → kolom kosong + peringatan, BUKAN gagal.
        Santri::create([
            'nis' => 'S-101', 'nama' => 'Kurang', 'status' => 'aktif',
            'tanggal_lahir' => '2012-04-06', 'jenis_kelamin' => 'P',
        ]);

        $svc = app(EmisExportService::class);
        $rows = $svc->barisSantri();

        $this->assertCount(2, $rows);
        $this->assertCount(count(EmisExportService::HEADER), $rows[0]);

        $iNik = array_search('NIK', EmisExportService::HEADER, true);
        $iTglLahir = array_search('TANGGAL_LAHIR', EmisExportService::HEADER, true);
        $iMukim = array_search('MUKIM', EmisExportService::HEADER, true);

        [$kurang, $lengkap] = $rows[0][2] === 'Kurang' ? [$rows[0], $rows[1]] : [$rows[1], $rows[0]];
        $this->assertSame('3211000000000001', $lengkap[$iNik]);
        $this->assertSame('2012-03-05', $lengkap[$iTglLahir]);
        $this->assertSame('MUKIM', $lengkap[$iMukim]);
        $this->assertSame('', $kurang[$iNik]); // kosong, tidak meledak

        $peringatan = $svc->peringatan('santri');
        $this->assertCount(1, $peringatan);
        $this->assertStringContainsString('Kurang', $peringatan[0]);
        $this->assertStringContainsString('NIK kosong', $peringatan[0]);
        $this->assertStringContainsString('NISN kosong', $peringatan[0]);
    }

    public function test_baris_pegawai_dan_peringatan_nuptk(): void
    {
        $this->bootTenant('em2');

        Employee::create([
            'nama' => 'Ust. Lengkap', 'nip' => 'P-001', 'jabatan' => 'Guru', 'aktif' => true,
            'nik' => '3211000000000009', 'nuptk' => '1234567890123456',
            'status_kepegawaian' => 'tetap', 'tanggal_masuk' => '2020-07-01', 'is_mukim' => true,
        ]);
        Employee::create(['nama' => 'Ust. Kurang', 'jabatan' => 'Guru', 'aktif' => true]);
        Employee::create(['nama' => 'Nonaktif', 'aktif' => false]); // TIDAK diekspor

        $svc = app(EmisExportService::class);
        $rows = $svc->barisPegawai();

        $this->assertCount(2, $rows);
        $this->assertCount(count(EmisExportService::HEADER_PEGAWAI), $rows[0]);

        $peringatan = $svc->peringatan('pegawai');
        $this->assertCount(1, $peringatan);
        $this->assertStringContainsString('Ust. Kurang', $peringatan[0]);
        $this->assertStringContainsString('NUPTK kosong', $peringatan[0]);
    }

    public function test_baris_lembaga_dan_peringatan_legalitas(): void
    {
        $this->bootTenant('em3');

        Lembaga::create([
            'nama' => 'MTs Al-Falah', 'jenis' => 'formal', 'jenjang' => 'mts',
            'nomor_statistik' => '121232110001', 'izin_operasional' => 'SK-001/2020',
            'tanggal_izin' => '2020-01-15', 'aktif' => true,
        ]);
        Lembaga::create(['nama' => 'Madin Tanpa Izin', 'jenis' => 'diniyah', 'aktif' => true]);

        $svc = app(EmisExportService::class);
        $rows = $svc->barisLembaga();

        $this->assertCount(2, $rows);
        $this->assertCount(count(EmisExportService::HEADER_LEMBAGA), $rows[0]);
        $iNsm = array_search('NOMOR_STATISTIK_NSM', EmisExportService::HEADER_LEMBAGA, true);
        $mts = $rows[0][0] === 'MTs Al-Falah' ? $rows[0] : $rows[1];
        $this->assertSame('121232110001', $mts[$iNsm]);

        $peringatan = $svc->peringatan('lembaga');
        $this->assertCount(1, $peringatan);
        $this->assertStringContainsString('Madin Tanpa Izin', $peringatan[0]);
    }

    public function test_header_for_dan_baris_for_konsisten_per_kategori(): void
    {
        $this->bootTenant('em4');

        foreach (EmisExportService::KATEGORI as $kategori) {
            $header = EmisExportService::headerFor($kategori);
            $this->assertNotEmpty($header);
            // Ekspor data kosong tetap jalan (array kosong, BUKAN exception).
            $this->assertIsArray(app(EmisExportService::class)->barisFor($kategori));
        }
    }
}
