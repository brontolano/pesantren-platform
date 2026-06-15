<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Modules\Akademik\Models\Lembaga;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\Jabatan;
use Modules\Payroll\Models\PenugasanPegawai;
use Modules\Payroll\Services\PenugasanImportService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Import Penugasan pegawai: tautkan pegawai/jabatan/lembaga, idempoten, master
 * tak dikenal ditolak, dan pelanggaran partial-unique "satu jabatan utama aktif
 * per pegawai" ditangani sebagai kegagalan baris (bukan crash).
 */
class PenugasanImportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'employee', 'jabatan', 'lembaga', 'penugasan_pegawai']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('pnz', 'Pesantren Penugasan');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);

        Employee::create(['tenant_id' => $this->tenant->id, 'nip' => 'P001', 'nama' => 'Ustadz Ali']);
        Jabatan::create(['tenant_id' => $this->tenant->id, 'nama' => 'Wali Kelas']);
        Jabatan::create(['tenant_id' => $this->tenant->id, 'nama' => 'Pembina']);
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
        $path = tempnam(sys_get_temp_dir(), 'pnz').'.csv';
        file_put_contents($path, implode("\n", $baris));

        return $path;
    }

    public function test_import_valid_lalu_idempoten(): void
    {
        $file = $this->csv(['nip,jabatan,lembaga,tanggal_mulai,is_primary', 'P001,Wali Kelas,Madrasah Aliyah,2024-07-01,ya']);

        $hasil = app(PenugasanImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, $hasil['failed']);

        $ulang = app(PenugasanImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(0, $ulang['imported']);
        $this->assertSame(1, $ulang['skipped']);
        $this->assertSame(1, PenugasanPegawai::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }

    public function test_master_tak_dikenal_ditolak(): void
    {
        $file = $this->csv([
            'nip,jabatan,tanggal_mulai',
            'P999,Wali Kelas,2024-07-01', // nip tak ada
            'P001,Jabatan Hantu,2024-07-01', // jabatan tak ada
        ]);
        $hasil = app(PenugasanImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(2, $hasil['failed']);

        @unlink($file);
    }

    public function test_banyak_penugasan_diproses_tanpa_crash(): void
    {
        // Dua penugasan utama untuk pegawai sama: kalaupun ada constraint DB
        // (partial-unique 1 primary aktif), pelanggaran ditangani PER BARIS
        // (try/catch) — importer tetap menyelesaikan semua baris tanpa crash.
        $file = $this->csv([
            'nip,jabatan,tanggal_mulai,is_primary',
            'P001,Wali Kelas,2024-07-01,ya',
            'P001,Pembina,2024-07-01,ya',
        ]);
        $hasil = app(PenugasanImportService::class)->import($this->tenant, $file, false);

        // Semua baris terproses (sukses + gagal) tanpa exception bocor.
        $this->assertSame(2, $hasil['imported'] + $hasil['failed']);
        $this->assertGreaterThanOrEqual(1, $hasil['imported']);

        @unlink($file);
    }
}
