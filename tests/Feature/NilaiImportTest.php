<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Services\NilaiImportService;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Import Nilai: tautkan santri/pelajaran/periode/kelas, idempoten via
 * updateOrCreate (re-import memperbarui, bukan dobel), nilai kosong tak
 * menimpa nilai lama, default kelas dari santri, master tak dikenal ditolak.
 */
class NilaiImportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    private int $kelasId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'santri', 'kelas', 'pelajaran', 'periode_akademik', 'nilai',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('nli', 'Pesantren Nilai');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);

        $kelas = Kelas::create(['tenant_id' => $this->tenant->id, 'nama_kelas' => 'VII-A', 'tingkat' => '7']);
        $this->kelasId = (int) $kelas->id;
        Pelajaran::create(['tenant_id' => $this->tenant->id, 'nama' => 'Nahwu', 'kode' => 'NHW']);
        PeriodeAkademik::create([
            'tenant_id' => $this->tenant->id, 'nama' => '2026/2027 Ganjil',
            'tahun_akademik' => '2026/2027', 'semester' => 'ganjil', 'is_active' => true,
            'tanggal_mulai' => '2026-07-14', 'tanggal_akhir' => '2026-12-31',
        ]);

        Santri::create([
            'tenant_id' => $this->tenant->id, 'nis' => '001', 'nama' => 'Ahmad',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'agama' => 'Islam',
            'status' => 'aktif', 'kelas_id' => $this->kelasId,
        ]);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    /** @param list<string> $baris */
    private function csv(array $baris): string
    {
        $path = tempnam(sys_get_temp_dir(), 'nli').'.csv';
        file_put_contents($path, implode("\n", $baris));

        return $path;
    }

    public function test_import_valid_pakai_kelas_santri_lalu_idempoten_update(): void
    {
        $file = $this->csv([
            'nis,pelajaran,nilai_harian,nilai_uts,nilai_uas,nilai_akhir',
            '001,NHW,80,75,90,82',
        ]);

        $hasil = app(NilaiImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, $hasil['failed']);

        $nilai = Nilai::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($nilai);
        $this->assertSame($this->kelasId, (int) $nilai->kelas_id, 'kelas default dari santri');
        $this->assertEquals(82.0, (float) $nilai->nilai_akhir);

        // Re-import dgn nilai berubah → update, bukan dobel.
        $file2 = $this->csv(['nis,pelajaran,nilai_akhir', '001,NHW,95']);
        $ulang = app(NilaiImportService::class)->import($this->tenant, $file2, false);
        $this->assertSame(0, $ulang['imported']);
        $this->assertSame(1, $ulang['updated']);
        $this->assertSame(1, Nilai::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
        $this->assertEquals(95.0, (float) $nilai->fresh()->nilai_akhir);
        // Nilai lama yg tak diisi tetap (tidak ditimpa null).
        $this->assertEquals(80.0, (float) $nilai->fresh()->nilai_harian);

        @unlink($file);
        @unlink($file2);
    }

    public function test_master_tak_dikenal_ditolak(): void
    {
        $file = $this->csv([
            'nis,pelajaran,nilai_akhir',
            '999,NHW,80',   // NIS tak ada
            '001,XXX,80',   // pelajaran tak ada
        ]);

        $hasil = app(NilaiImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(2, $hasil['failed']);

        @unlink($file);
    }

    public function test_dry_run_tidak_menulis(): void
    {
        $file = $this->csv(['nis,pelajaran,nilai_akhir', '001,NHW,88']);

        $hasil = app(NilaiImportService::class)->import($this->tenant, $file, true);
        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, Nilai::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }
}
