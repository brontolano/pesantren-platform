<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Modules\Pembiayaan\Models\Aset;
use Modules\Pembiayaan\Services\AsetImportService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Importer master Aset: parsing nilai format Indonesia + derivasi nilai_perolehan
 * (hook model), invarian wakaf (metode dipaksa none), idempoten skip-if-exists
 * (sertifikat / nama+tanggal), dry-run tak menulis, nama wajib.
 */
class AsetImportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'aset']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('ast', 'Pesantren Aset');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
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
        $path = tempnam(sys_get_temp_dir(), 'ast').'.csv';
        file_put_contents($path, implode("\n", $baris));

        return $path;
    }

    private function aset(): Aset
    {
        return Aset::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();
    }

    public function test_nilai_format_id_dan_nilai_perolehan_diturunkan_lalu_idempoten(): void
    {
        $file = $this->csv([
            'nama_aset,jenis,tanggal_perolehan,nilai',
            'Tanah Pondok,tanah,2018-05-10,1.500.000.000',
        ]);

        $hasil = app(AsetImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, $hasil['failed']);

        $aset = $this->aset();
        $this->assertEqualsWithDelta(1500000000.0, (float) $aset->nilai, 0.01);
        // Hook model menurunkan nilai_perolehan dari nilai saat belum diisi.
        $this->assertEqualsWithDelta(1500000000.0, (float) $aset->nilai_perolehan, 0.01);

        // Re-run → idempoten (nama+tanggal sama dilewati).
        $ulang = app(AsetImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(0, $ulang['imported']);
        $this->assertSame(1, $ulang['skipped']);
        $this->assertSame(1, Aset::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }

    public function test_wakaf_dipaksa_tanpa_penyusutan(): void
    {
        $file = $this->csv([
            'nama_aset,is_wakaf,metode_penyusutan,nilai',
            'Tanah Wakaf,ya,garis_lurus,500.000.000',
        ]);

        app(AsetImportService::class)->import($this->tenant, $file, false);

        $aset = $this->aset();
        $this->assertTrue((bool) $aset->is_wakaf);
        $this->assertSame('none', $aset->metode_penyusutan, 'wakaf tidak boleh disusutkan');

        @unlink($file);
    }

    public function test_dedup_by_nomor_sertifikat(): void
    {
        $file = $this->csv([
            'nama_aset,nomor_sertifikat,nilai',
            'Mobil Operasional,BPKB-001,200.000.000',
            'Mobil Operasional (duplikat entri),BPKB-001,200.000.000', // sertifikat sama → dilewati
        ]);

        $hasil = app(AsetImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(1, $hasil['skipped']);
        $this->assertSame(1, Aset::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }

    public function test_nama_wajib_dan_dry_run(): void
    {
        $gagal = $this->csv(['nama_aset,nilai', ',1.000.000']); // nama kosong + ada nilai → gagal
        $h1 = app(AsetImportService::class)->import($this->tenant, $gagal, false);
        $this->assertSame(0, $h1['imported']);
        $this->assertSame(1, $h1['failed']);

        $ok = $this->csv(['nama_aset,nilai', 'Laptop TU,8.000.000']);
        $h2 = app(AsetImportService::class)->import($this->tenant, $ok, true);
        $this->assertSame(1, $h2['imported']);
        $this->assertSame(0, Aset::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        @unlink($gagal);
        @unlink($ok);
    }
}
