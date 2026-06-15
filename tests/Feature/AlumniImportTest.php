<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Modules\Kesantrian\Models\Alumni;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\AlumniImportService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Import Alumni: tautkan ke santri (NIS), idempoten (1 alumni/santri), nis tak
 * dikenal & tahun_lulus kosong ditolak, dry-run tak menulis.
 */
class AlumniImportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'santri', 'alumni']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('alm', 'Pesantren Alumni');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);

        Santri::create([
            'tenant_id' => $this->tenant->id, 'nis' => '001', 'nama' => 'Ahmad',
            'tanggal_lahir' => '2008-01-01', 'jenis_kelamin' => 'L', 'agama' => 'Islam', 'status' => 'lulus',
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
        $path = tempnam(sys_get_temp_dir(), 'alm').'.csv';
        file_put_contents($path, implode("\n", $baris));

        return $path;
    }

    public function test_import_valid_lalu_idempoten(): void
    {
        $file = $this->csv(['nis,tahun_lulus,status_lanjutan,sekolah_tujuan', '001,2024,kuliah,UIN Bandung']);

        $hasil = app(AlumniImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, $hasil['failed']);
        $alumni = Alumni::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($alumni);
        $this->assertSame('2024', $alumni->tahun_lulus);

        $ulang = app(AlumniImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(0, $ulang['imported']);
        $this->assertSame(1, $ulang['skipped']);
        $this->assertSame(1, Alumni::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }

    public function test_nis_tak_dikenal_dan_tahun_wajib(): void
    {
        $file = $this->csv(['nis,tahun_lulus', '999,2024', '001,']);
        $hasil = app(AlumniImportService::class)->import($this->tenant, $file, false);

        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(2, $hasil['failed']);

        @unlink($file);
    }

    public function test_dry_run_tidak_menulis(): void
    {
        $file = $this->csv(['nis,tahun_lulus', '001,2023']);
        $hasil = app(AlumniImportService::class)->import($this->tenant, $file, true);

        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, Alumni::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }
}
