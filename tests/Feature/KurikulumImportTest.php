<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Modules\Akademik\Models\Kurikulum;
use Modules\Akademik\Models\Lembaga;
use Modules\Akademik\Services\KurikulumImportService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Import Kurikulum: tautkan ke Lembaga (by nama), idempoten (lembaga+nama),
 * lembaga tak dikenal & nama kosong ditolak, dry-run tak menulis.
 */
class KurikulumImportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'lembaga', 'kurikulum']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('kur', 'Pesantren Kurikulum');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);

        Lembaga::create(['tenant_id' => $this->tenant->id, 'nama' => 'Madrasah Aliyah']);
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
        $path = tempnam(sys_get_temp_dir(), 'kur').'.csv';
        file_put_contents($path, implode("\n", $baris));

        return $path;
    }

    public function test_import_valid_lalu_idempoten(): void
    {
        $file = $this->csv(['nama,lembaga', 'Kurikulum 2024,Madrasah Aliyah']);

        $hasil = app(KurikulumImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, $hasil['failed']);
        $this->assertSame(1, Kurikulum::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        $ulang = app(KurikulumImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(0, $ulang['imported']);
        $this->assertSame(1, $ulang['skipped']);

        @unlink($file);
    }

    public function test_lembaga_tak_dikenal_dan_nama_wajib(): void
    {
        $file = $this->csv(['nama,lembaga', 'Kur X,Lembaga Hantu', ',Madrasah Aliyah']);
        $hasil = app(KurikulumImportService::class)->import($this->tenant, $file, false);

        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(2, $hasil['failed']);

        @unlink($file);
    }

    public function test_dry_run_tidak_menulis(): void
    {
        $file = $this->csv(['nama,lembaga', 'Kurikulum 2025,Madrasah Aliyah']);
        $hasil = app(KurikulumImportService::class)->import($this->tenant, $file, true);

        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, Kurikulum::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }
}
