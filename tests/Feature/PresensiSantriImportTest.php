<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Modules\Akademik\Models\PresensiSantri;
use Modules\Akademik\Services\PresensiSantriImportService;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Importer presensi santri: idempoten skip-if-exists pada (santri, tanggal) —
 * tak menimpa, status divalidasi (default hadir), nis tak dikenal ditolak,
 * dry-run tak menulis.
 */
class PresensiSantriImportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'santri', 'presensi_santri']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('prs', 'Pesantren Presensi');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);

        Santri::create([
            'tenant_id' => $this->tenant->id, 'nis' => '001', 'nama' => 'Ahmad',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'agama' => 'Islam', 'status' => 'aktif',
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
        $path = tempnam(sys_get_temp_dir(), 'prs').'.csv';
        file_put_contents($path, implode("\n", $baris));

        return $path;
    }

    public function test_import_idempoten_tak_menimpa(): void
    {
        $file = $this->csv(['nis,tanggal,status', '001,2026-01-15,izin']);

        $hasil = app(PresensiSantriImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, $hasil['failed']);

        $p = PresensiSantri::query()->where('tenant_id', $this->tenant->id)->first();
        $this->assertSame('izin', $p->status);

        // Re-run dgn status berbeda → DILEWATI, status lama TIDAK ditimpa.
        $file2 = $this->csv(['nis,tanggal,status', '001,2026-01-15,hadir']);
        $ulang = app(PresensiSantriImportService::class)->import($this->tenant, $file2, false);
        $this->assertSame(0, $ulang['imported']);
        $this->assertSame(1, $ulang['skipped']);
        $this->assertSame('izin', $p->fresh()->status, 'status lama tidak ditimpa');
        $this->assertSame(1, PresensiSantri::query()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
        @unlink($file2);
    }

    public function test_default_status_hadir(): void
    {
        $file = $this->csv(['nis,tanggal', '001,2026-02-01']);
        app(PresensiSantriImportService::class)->import($this->tenant, $file, false);

        $p = PresensiSantri::query()->where('tenant_id', $this->tenant->id)->first();
        $this->assertSame('hadir', $p->status);

        @unlink($file);
    }

    public function test_status_invalid_dan_nis_tak_dikenal_ditolak(): void
    {
        $file = $this->csv([
            'nis,tanggal,status',
            '001,2026-03-01,bolos',  // status tak valid → gagal
            '999,2026-03-01,hadir',  // nis tak ada → gagal
        ]);

        $hasil = app(PresensiSantriImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(2, $hasil['failed']);

        @unlink($file);
    }

    public function test_dry_run_tidak_menulis(): void
    {
        $file = $this->csv(['nis,tanggal,status', '001,2026-04-01,sakit']);
        $hasil = app(PresensiSantriImportService::class)->import($this->tenant, $file, true);

        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, PresensiSantri::query()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }
}
