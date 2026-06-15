<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Modules\Keuangan\Services\TabunganSaldoImportService;
use Modules\Keuangan\Services\TabunganService;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Import saldo awal tabungan: pakai TabunganService::setor (reuse), idempoten
 * (santri yg sudah punya saldo awal dilewati), nis tak dikenal ditolak, dry-run
 * tak menulis. Tabel `akun`+`settings` disertakan agar listener auto-jurnal
 * Ledger berjalan toleran (COA kosong → dilewati, tak melempar).
 */
class TabunganSaldoImportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'settings', 'santri', 'tabungan', 'tabungan_transaksi', 'akun',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('tab', 'Pesantren Tabungan');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);

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
        $path = tempnam(sys_get_temp_dir(), 'tab').'.csv';
        file_put_contents($path, implode("\n", $baris));

        return $path;
    }

    public function test_import_saldo_lalu_idempoten(): void
    {
        $file = $this->csv(['nis,saldo_awal,keterangan', '001,150000,Saldo migrasi']);

        $hasil = app(TabunganSaldoImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(1, $hasil['imported']);
        $this->assertSame(0, $hasil['failed']);

        $santri = Santri::withoutGlobalScopes()->where('nis', '001')->first();
        $this->assertEqualsWithDelta(150000.0, app(TabunganService::class)->getSaldo($santri), 0.01);

        // Re-run → idempoten (tidak menambah saldo lagi).
        $ulang = app(TabunganSaldoImportService::class)->import($this->tenant, $file, false);
        $this->assertSame(0, $ulang['imported']);
        $this->assertSame(1, $ulang['skipped']);
        $this->assertEqualsWithDelta(150000.0, app(TabunganService::class)->getSaldo($santri), 0.01);

        @unlink($file);
    }

    public function test_nis_tak_dikenal_ditolak(): void
    {
        $file = $this->csv(['nis,saldo_awal', '999,50000']);
        $hasil = app(TabunganSaldoImportService::class)->import($this->tenant, $file, false);

        $this->assertSame(0, $hasil['imported']);
        $this->assertSame(1, $hasil['failed']);

        @unlink($file);
    }

    public function test_dry_run_tidak_menulis(): void
    {
        $file = $this->csv(['nis,saldo_awal', '001,75000']);
        $hasil = app(TabunganSaldoImportService::class)->import($this->tenant, $file, true);

        $this->assertSame(1, $hasil['imported']);
        $santri = Santri::withoutGlobalScopes()->where('nis', '001')->first();
        $this->assertEqualsWithDelta(0.0, app(TabunganService::class)->getSaldo($santri), 0.01);

        @unlink($file);
    }
}
