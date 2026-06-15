<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Modules\Kesehatan\Models\Obat;
use Modules\Kesehatan\Services\ObatImportService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Import master Obat: valid, dry-run tak menulis, idempoten (nama sama
 * dilewati), nama wajib, stok negatif ditolak.
 */
class ObatImportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'obat']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('obt', 'Pesantren Obat');
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
        $path = tempnam(sys_get_temp_dir(), 'obt').'.csv';
        file_put_contents($path, implode("\n", $baris));

        return $path;
    }

    public function test_import_valid_lalu_idempoten(): void
    {
        $file = $this->csv([
            'nama,satuan,stok,ambang_menipis',
            'Paracetamol,tablet,100,20',
            'Betadine,botol,10,3',
        ]);

        $hasil = app(ObatImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(2, $hasil['imported']);
        $this->assertSame(0, $hasil['failed']);

        $pcm = Obat::withoutGlobalScopes()->where('nama', 'Paracetamol')->first();
        $this->assertNotNull($pcm);
        $this->assertSame(100, (int) $pcm->stok);
        $this->assertSame('tablet', $pcm->satuan);

        $ulang = app(ObatImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(0, $ulang['imported']);
        $this->assertSame(2, $ulang['skipped']);
        $this->assertSame(2, Obat::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }

    public function test_dry_run_tidak_menulis(): void
    {
        $file = $this->csv(['nama', 'Amoxicillin']);
        $hasil = app(ObatImportService::class)->import($this->tenant, $file, true);

        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, Obat::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }

    public function test_nama_wajib_dan_stok_negatif_ditolak(): void
    {
        $file = $this->csv(['nama,stok', ',10', 'Obat X,-5']);
        $hasil = app(ObatImportService::class)->import($this->tenant, $file, false);

        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(2, $hasil['failed']);

        @unlink($file);
    }
}
