<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Services\LedgerSaldoAwalImportService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Import saldo awal buku besar — ADVERSARIAL: mencoba mematahkan integritas
 * uang. Yang dijaga: hanya posting bila SEIMBANG & semua akun valid (gagal-aman),
 * SATU jurnal pembuka, idempoten (tak ganda), dry-run tak menulis, baris
 * tabrakan/negatif ditolak. Posting lewat JurnalService (tak tulis baris langsung).
 */
class LedgerSaldoAwalImportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'settings', 'akun', 'jurnal', 'jurnal_baris', 'periode_akuntansi',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('led', 'Pesantren Ledger');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);

        Akun::create(['tenant_id' => $this->tenant->id, 'kode' => '1-1000', 'nama' => 'Kas', 'tipe' => 'ASET', 'saldo_normal' => 'debit', 'is_postable' => true]);
        Akun::create(['tenant_id' => $this->tenant->id, 'kode' => '3-1000', 'nama' => 'Modal', 'tipe' => 'EKUITAS', 'saldo_normal' => 'kredit', 'is_postable' => true]);
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
        $path = tempnam(sys_get_temp_dir(), 'led').'.csv';
        file_put_contents($path, implode("\n", $baris));

        return $path;
    }

    public function test_seimbang_memposting_satu_jurnal_yang_balance(): void
    {
        $file = $this->csv([
            'kode_akun,debit,kredit,keterangan',
            '1-1000,5000000,,Kas awal',
            '3-1000,,5000000,Modal awal',
        ]);

        $hasil = app(LedgerSaldoAwalImportService::class)->import($this->tenant, $file, false);

        $this->assertSame(2, $hasil['imported']);
        $this->assertSame(0, $hasil['failed']);
        $this->assertTrue($hasil['seimbang']);

        $jurnal = Jurnal::query()->where('idempotency_key', 'saldo-awal:'.$this->tenant->id)->with('baris')->first();
        $this->assertNotNull($jurnal);
        $this->assertSame('posted', $jurnal->status);
        // Integritas: total debit == total kredit pada jurnal tersimpan.
        $this->assertEqualsWithDelta(
            (float) $jurnal->baris->sum('debit'),
            (float) $jurnal->baris->sum('kredit'),
            0.01,
        );
        $this->assertEqualsWithDelta(5000000.0, (float) $jurnal->baris->sum('debit'), 0.01);

        @unlink($file);
    }

    public function test_tidak_seimbang_tidak_memposting_apa_pun(): void
    {
        $file = $this->csv([
            'kode_akun,debit,kredit',
            '1-1000,5000000,',
            '3-1000,,4000000',
        ]);

        $hasil = app(LedgerSaldoAwalImportService::class)->import($this->tenant, $file, false);

        $this->assertSame(0, $hasil['imported']);
        $this->assertFalse($hasil['seimbang']);
        $this->assertGreaterThan(0, $hasil['failed']);
        $this->assertSame(0, Jurnal::query()->count(), 'tidak boleh ada jurnal saat tak seimbang');

        @unlink($file);
    }

    public function test_akun_tak_dikenal_gagal_aman(): void
    {
        $file = $this->csv([
            'kode_akun,debit,kredit',
            '1-1000,5000000,',
            '9-9999,,5000000', // akun tak ada
        ]);

        $hasil = app(LedgerSaldoAwalImportService::class)->import($this->tenant, $file, false);

        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(1, $hasil['failed']);
        $this->assertSame(0, Jurnal::query()->count());

        @unlink($file);
    }

    public function test_idempoten_tidak_ganda(): void
    {
        $file = $this->csv(['kode_akun,debit,kredit', '1-1000,1000000,', '3-1000,,1000000']);

        app(LedgerSaldoAwalImportService::class)->import($this->tenant, $file, false);
        $ulang = app(LedgerSaldoAwalImportService::class)->import($this->tenant, $file, false);

        $this->assertSame(0, $ulang['imported']);
        $this->assertGreaterThan(0, $ulang['skipped']);
        $this->assertSame(1, Jurnal::query()->count(), 'jurnal pembuka tak boleh ganda');

        @unlink($file);
    }

    public function test_dry_run_tidak_menulis(): void
    {
        $file = $this->csv(['kode_akun,debit,kredit', '1-1000,2000000,', '3-1000,,2000000']);

        $hasil = app(LedgerSaldoAwalImportService::class)->import($this->tenant, $file, true);

        $this->assertSame(2, $hasil['imported']);
        $this->assertTrue($hasil['seimbang']);
        $this->assertSame(0, Jurnal::query()->count());

        @unlink($file);
    }

    public function test_baris_debit_dan_kredit_sekaligus_ditolak(): void
    {
        $file = $this->csv(['kode_akun,debit,kredit', '1-1000,1000,500', '3-1000,,500']);

        $hasil = app(LedgerSaldoAwalImportService::class)->import($this->tenant, $file, false);

        $this->assertSame(0, $hasil['imported']);
        $this->assertGreaterThan(0, $hasil['failed']);
        $this->assertSame(0, Jurnal::query()->count());

        @unlink($file);
    }
}
