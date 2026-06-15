<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Services\PelajaranImportService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Import master Pelajaran: buat baris valid, dry-run tak menulis, idempoten
 * (kode/nama sama dilewati), guru pengampu di-resolve via email, nama wajib.
 */
class PelajaranImportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users', 'pelajaran']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('plj', 'Pesantren Pelajaran');
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
        $path = tempnam(sys_get_temp_dir(), 'plj').'.csv';
        file_put_contents($path, implode("\n", $baris));

        return $path;
    }

    public function test_import_valid_membuat_pelajaran_dan_resolve_guru(): void
    {
        $guru = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ustadz Fulan',
            'email' => 'fulan@guru.test',
            'password' => 'password123',
        ]);

        $file = $this->csv([
            'nama,kode,sks,guru',
            'Nahwu,NHW,2,fulan@guru.test',
            'Shorof,SHR,2,',
        ]);

        $hasil = app(PelajaranImportService::class)->import($this->tenant, $file, false);

        $this->assertSame(2, $hasil['imported']);
        $this->assertSame(0, $hasil['failed']);
        $this->assertSame(2, Pelajaran::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        $nahwu = Pelajaran::withoutGlobalScopes()->where('kode', 'NHW')->first();
        $this->assertNotNull($nahwu);
        $this->assertSame($guru->id, $nahwu->guru_pengampu_id);

        @unlink($file);
    }

    public function test_dry_run_tidak_menulis(): void
    {
        $file = $this->csv(['nama,kode', 'Fiqih,FQH']);

        $hasil = app(PelajaranImportService::class)->import($this->tenant, $file, true);

        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, Pelajaran::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }

    public function test_idempoten_kode_sama_dilewati(): void
    {
        $file = $this->csv(['nama,kode', 'Tauhid,THD']);

        app(PelajaranImportService::class)->import($this->tenant, $file, false);
        $ulang = app(PelajaranImportService::class)->import($this->tenant, $file, false);

        $this->assertSame(0, $ulang['imported']);
        $this->assertSame(1, $ulang['skipped']);
        $this->assertSame(1, Pelajaran::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }

    public function test_nama_wajib_baris_gagal(): void
    {
        $file = $this->csv(['nama,kode', ',KOSONG']);

        $hasil = app(PelajaranImportService::class)->import($this->tenant, $file, false);

        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(1, $hasil['failed']);

        @unlink($file);
    }
}
