<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Services\PegawaiImportService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Import master Pegawai: buat baris valid, dry-run tak menulis, idempoten (nip
 * sama dilewati), email menautkan akun (users_id), nip & nama wajib.
 */
class PegawaiImportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users', 'employee']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('peg', 'Pesantren Pegawai');
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
        $path = tempnam(sys_get_temp_dir(), 'peg').'.csv';
        file_put_contents($path, implode("\n", $baris));

        return $path;
    }

    public function test_import_valid_dan_tautkan_akun_via_email(): void
    {
        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Ustadz Ali',
            'email' => 'ali@guru.test', 'password' => 'x12345678',
        ]);

        $file = $this->csv([
            'nip,nama,status_kepegawaian,jabatan,email',
            'P001,Ustadz Ali,tetap,Guru,ali@guru.test',
            'P002,Ustadz Budi,honorer,Guru,',
        ]);

        $hasil = app(PegawaiImportService::class)->import($this->tenant, $file, false);

        $this->assertSame(2, $hasil['imported']);
        $this->assertSame(0, $hasil['failed']);

        $ali = Employee::withoutGlobalScopes()->where('nip', 'P001')->first();
        $this->assertNotNull($ali);
        $this->assertSame('tetap', $ali->status_kepegawaian);
        $this->assertSame($user->id, (int) $ali->users_id);

        @unlink($file);
    }

    public function test_dry_run_tidak_menulis(): void
    {
        $file = $this->csv(['nip,nama', 'P010,Fulan']);
        $hasil = app(PegawaiImportService::class)->import($this->tenant, $file, true);

        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, Employee::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }

    public function test_idempoten_nip_sama_dilewati(): void
    {
        $file = $this->csv(['nip,nama', 'P020,Hasan']);
        app(PegawaiImportService::class)->import($this->tenant, $file, false);
        $ulang = app(PegawaiImportService::class)->import($this->tenant, $file, false);

        $this->assertSame(0, $ulang['imported']);
        $this->assertSame(1, $ulang['skipped']);
        $this->assertSame(1, Employee::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }

    public function test_nip_dan_nama_wajib(): void
    {
        $file = $this->csv(['nip,nama', ',Tanpa NIP', 'P030,']);
        $hasil = app(PegawaiImportService::class)->import($this->tenant, $file, false);

        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(2, $hasil['failed']);

        @unlink($file);
    }
}
