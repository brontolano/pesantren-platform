<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Modules\Pengadaan\Models\Supplier;
use Modules\Pengadaan\Services\SupplierImportService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Importer master Supplier: idempoten by nama (per tenant, dilewati bila ada —
 * TIDAK menimpa), is_pkp diturunkan dari ada/tidaknya NPWP, kehalalan divalidasi,
 * dry-run tak menulis.
 */
class SupplierImportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'supplier']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('sup', 'Pesantren Supplier');
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
        $path = tempnam(sys_get_temp_dir(), 'sup').'.csv';
        file_put_contents($path, implode("\n", $baris));

        return $path;
    }

    public function test_import_idempoten_dan_is_pkp_dari_npwp(): void
    {
        $file = $this->csv([
            'nama,telepon,npwp,kehalalan',
            'CV Berkah,0811,01.234.567.8-901.000,halal',   // ber-NPWP → is_pkp true
            'Toko Amanah,0822,,syubhat',                    // tanpa NPWP → is_pkp false
        ]);

        $hasil = app(SupplierImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(2, $hasil['imported']);
        $this->assertSame(0, $hasil['failed']);

        $berkah = Supplier::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->whereRaw('LOWER(nama) = ?', ['cv berkah'])->first();
        $this->assertTrue((bool) $berkah->is_pkp);
        $this->assertSame('halal', $berkah->kehalalan);

        $amanah = Supplier::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->whereRaw('LOWER(nama) = ?', ['toko amanah'])->first();
        $this->assertFalse((bool) $amanah->is_pkp);
        $this->assertSame('syubhat', $amanah->kehalalan);

        // Re-run → idempoten (nama sama dilewati, tak ditimpa).
        $ulang = app(SupplierImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(0, $ulang['imported']);
        $this->assertSame(2, $ulang['skipped']);
        $this->assertSame(2, Supplier::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }

    public function test_nama_wajib_dan_kehalalan_tak_valid_ditolak(): void
    {
        $file = $this->csv([
            'nama,kehalalan',
            ',halal',          // nama kosong tapi ada kehalalan → baris bermasalah → gagal (nama wajib)
            'PT Salah,bukan',  // kehalalan tak valid → gagal
        ]);

        $hasil = app(SupplierImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(2, $hasil['failed']);
        $this->assertSame(0, $hasil['skipped']);
        $this->assertSame(0, Supplier::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }

    public function test_dry_run_tidak_menulis(): void
    {
        $file = $this->csv(['nama', 'CV Coba']);
        $hasil = app(SupplierImportService::class)->import($this->tenant, $file, true);

        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, Supplier::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }
}
