<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Modules\Akademik\Models\KalenderAkademik;
use Modules\Akademik\Services\KalenderImportService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Importer kalender akademik: idempoten skip-if-exists (tanggal_mulai+keterangan),
 * tanggal_selesai default = mulai, jenis default libur_pondok, keterangan wajib,
 * jenis divalidasi, dry-run tak menulis.
 */
class KalenderImportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'kalender_akademik']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('kld', 'Pesantren Kalender');
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
        $path = tempnam(sys_get_temp_dir(), 'kld').'.csv';
        file_put_contents($path, implode("\n", $baris));

        return $path;
    }

    public function test_import_idempoten(): void
    {
        $file = $this->csv([
            'tanggal_mulai,tanggal_selesai,jenis,keterangan',
            '2026-07-01,2026-07-14,libur_pondok,Libur akhir tahun ajaran',
        ]);

        $hasil = app(KalenderImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, $hasil['failed']);

        $k = KalenderAkademik::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->first();
        $this->assertSame('libur_pondok', $k->jenis);
        $this->assertSame('2026-07-14', $k->tanggal_selesai->toDateString());

        $ulang = app(KalenderImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(0, $ulang['imported']);
        $this->assertSame(1, $ulang['skipped']);
        $this->assertSame(1, KalenderAkademik::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }

    public function test_selesai_dan_jenis_default(): void
    {
        $file = $this->csv(['tanggal_mulai,keterangan', '2026-08-17,Upacara HUT RI']);
        app(KalenderImportService::class)->import($this->tenant, $file, false);

        $k = KalenderAkademik::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->first();
        $this->assertSame('2026-08-17', $k->tanggal_selesai->toDateString(), 'selesai default = mulai');
        $this->assertSame('libur_pondok', $k->jenis);

        @unlink($file);
    }

    public function test_keterangan_wajib_dan_jenis_invalid_ditolak(): void
    {
        $file = $this->csv([
            'tanggal_mulai,jenis,keterangan',
            '2026-09-01,libur_pondok,',          // keterangan kosong → gagal
            '2026-09-02,bukan_jenis,Acara X',    // jenis tak valid → gagal
        ]);

        $hasil = app(KalenderImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(2, $hasil['failed']);

        @unlink($file);
    }

    public function test_dry_run_tidak_menulis(): void
    {
        $file = $this->csv(['tanggal_mulai,keterangan', '2026-10-01,Pasaran Maulid']);
        $hasil = app(KalenderImportService::class)->import($this->tenant, $file, true);

        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, KalenderAkademik::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        @unlink($file);
    }
}
