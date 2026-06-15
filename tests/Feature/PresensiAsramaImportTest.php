<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Modules\Kesantrian\Models\PresensiAsrama;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\PresensiAsramaImportService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Importer presensi asrama: idempoten skip-if-exists pada (santri, tanggal, slot)
 * tanpa menimpa, 'alpa'→'alpha', default hadir, slot wajib, status divalidasi,
 * pencatat_id wajib saat tulis, dry-run tak menulis.
 */
class PresensiAsramaImportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    private int $pencatatId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users', 'santri', 'presensi_asrama']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('pra', 'Pesantren Presensi Asrama');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);

        $pencatat = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Musyrif Asrama',
            'email' => 'musyrif.asrama@test.local', 'password' => 'x12345678',
        ]);
        $this->pencatatId = $pencatat->id;

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
        $path = tempnam(sys_get_temp_dir(), 'pra').'.csv';
        file_put_contents($path, implode("\n", $baris));

        return $path;
    }

    public function test_idempoten_tak_menimpa_dan_pencatat_terisi(): void
    {
        $file = $this->csv(['nis,tanggal,slot,status', '001,2026-01-15,subuh,izin']);

        $hasil = app(PresensiAsramaImportService::class)->import($this->tenant, $file, false, $this->pencatatId);
        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, $hasil['failed']);

        $p = PresensiAsrama::query()->where('tenant_id', $this->tenant->id)->first();
        $this->assertSame('izin', $p->status);
        $this->assertSame($this->pencatatId, (int) $p->pencatat_id);

        // Re-run beda status → DILEWATI (triplet sama), status lama tak ditimpa.
        $file2 = $this->csv(['nis,tanggal,slot,status', '001,2026-01-15,subuh,hadir']);
        $ulang = app(PresensiAsramaImportService::class)->import($this->tenant, $file2, false, $this->pencatatId);
        $this->assertSame(0, $ulang['imported']);
        $this->assertSame(1, $ulang['skipped']);
        $this->assertSame('izin', $p->fresh()->status);
        $this->assertSame(1, PresensiAsrama::query()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
        @unlink($file2);
    }

    public function test_alpa_alias_dan_default_hadir_beda_slot(): void
    {
        $file = $this->csv([
            'nis,tanggal,slot,status',
            '001,2026-02-01,subuh,alpa',   // alias → alpha
            '001,2026-02-01,maghrib,',     // default → hadir
        ]);

        $hasil = app(PresensiAsramaImportService::class)->import($this->tenant, $file, false, $this->pencatatId);
        $this->assertSame(2, $hasil['imported']);

        $subuh = PresensiAsrama::query()->where('tenant_id', $this->tenant->id)->where('slot', 'subuh')->first();
        $maghrib = PresensiAsrama::query()->where('tenant_id', $this->tenant->id)->where('slot', 'maghrib')->first();
        $this->assertSame('alpha', $subuh->status);
        $this->assertSame('hadir', $maghrib->status);

        @unlink($file);
    }

    public function test_slot_wajib_dan_status_invalid_ditolak(): void
    {
        $file = $this->csv([
            'nis,tanggal,slot,status',
            '001,2026-03-01,,hadir',       // slot kosong → gagal
            '001,2026-03-01,subuh,bolos',  // status tak valid → gagal
        ]);

        $hasil = app(PresensiAsramaImportService::class)->import($this->tenant, $file, false, $this->pencatatId);
        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(2, $hasil['failed']);

        @unlink($file);
    }

    public function test_dry_run_tanpa_pencatat_tidak_menulis(): void
    {
        // Dry-run tak butuh pencatat_id (tak menyentuh DB).
        $file = $this->csv(['nis,tanggal,slot,status', '001,2026-04-01,subuh,sakit']);
        $hasil = app(PresensiAsramaImportService::class)->import($this->tenant, $file, true);

        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, PresensiAsrama::query()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }
}
