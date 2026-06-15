<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\PresensiStaf;
use Modules\Payroll\Services\PresensiStafImportService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Importer presensi staf: idempoten skip-if-exists (employee, tanggal) tanpa
 * menimpa, resolve pegawai via NIP, status divalidasi (default hadir), nip tak
 * dikenal ditolak, dry-run tak menulis.
 */
class PresensiStafImportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'employee', 'presensi_staf']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('pst', 'Pesantren Presensi Staf');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);

        Employee::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'nip' => 'P001', 'nama' => 'Ustadz Fulan',
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
        $path = tempnam(sys_get_temp_dir(), 'pst').'.csv';
        file_put_contents($path, implode("\n", $baris));

        return $path;
    }

    public function test_idempoten_tak_menimpa(): void
    {
        $file = $this->csv(['nip,tanggal,status', 'P001,2026-01-15,izin']);

        $hasil = app(PresensiStafImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, $hasil['failed']);

        $p = PresensiStaf::query()->where('tenant_id', $this->tenant->id)->first();
        $this->assertSame('izin', $p->status);

        $file2 = $this->csv(['nip,tanggal,status', 'P001,2026-01-15,hadir']);
        $ulang = app(PresensiStafImportService::class)->import($this->tenant, $file2, false);
        $this->assertSame(0, $ulang['imported']);
        $this->assertSame(1, $ulang['skipped']);
        $this->assertSame('izin', $p->fresh()->status, 'status lama tidak ditimpa');
        $this->assertSame(1, PresensiStaf::query()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
        @unlink($file2);
    }

    public function test_default_hadir_dan_jam(): void
    {
        $file = $this->csv(['nip,tanggal,jam_masuk,jam_keluar', 'P001,2026-02-01,07:00,14:00']);
        app(PresensiStafImportService::class)->import($this->tenant, $file, false);

        $p = PresensiStaf::query()->where('tenant_id', $this->tenant->id)->first();
        $this->assertSame('hadir', $p->status);
        $this->assertStringStartsWith('07:00', (string) $p->jam_masuk); // kolom time → '07:00:00'

        @unlink($file);
    }

    public function test_nip_tak_dikenal_dan_status_invalid_ditolak(): void
    {
        $file = $this->csv([
            'nip,tanggal,status',
            'P999,2026-03-01,hadir',   // nip tak ada → gagal
            'P001,2026-03-01,bolos',   // status tak valid → gagal
        ]);

        $hasil = app(PresensiStafImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(2, $hasil['failed']);

        @unlink($file);
    }

    public function test_dry_run_tidak_menulis(): void
    {
        $file = $this->csv(['nip,tanggal,status', 'P001,2026-04-01,cuti']);
        $hasil = app(PresensiStafImportService::class)->import($this->tenant, $file, true);

        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, PresensiStaf::query()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }
}
