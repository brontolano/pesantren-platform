<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Pembiayaan\Models\Aset;
use Modules\Pembiayaan\Services\DscrService;
use Modules\Pembiayaan\Services\PembiayaanService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PembiayaanTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurePpdbTestingDatabase();
        if (! Schema::connection('pgsql')->hasTable('pengajuan_pembiayaan')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(): void
    {
        $tenant = $this->createRuntimeTenant('biaya', 'Pesantren Pembiayaan');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new LedgerSeeder)->run();
    }

    public function test_dscr_ambang_125_dan_zona(): void
    {
        $this->boot();
        $svc = app(DscrService::class);

        // DSCR 1,5 → aman
        $this->assertSame('aman', $svc->hitung(150000000, 80000000, 20000000)['zona']); // 150/100=1.5
        // DSCR 1,1 → waspada (BUKAN ditolak)
        $r = $svc->hitung(110000000, 80000000, 20000000); // 110/100=1.1
        $this->assertEqualsWithDelta(1.10, $r['dscr'], 0.01);
        $this->assertSame('waspada', $r['zona']);
        $this->assertEqualsWithDelta(1.25, $r['ambang'], 0.01);
        // DSCR 0,8 → bahaya
        $this->assertSame('bahaya', $svc->hitung(80000000, 80000000, 20000000)['zona']);
    }

    public function test_aset_wakaf_ditolak_sebagai_agunan(): void
    {
        $this->boot();
        $wakaf = Aset::create(['nama_aset' => 'Tanah Wakaf', 'nilai' => 500000000, 'is_wakaf' => true]);

        $this->expectException(InvalidArgumentException::class);
        app(PembiayaanService::class)->ajukan([
            'bank_tujuan' => 'BSI', 'plafon' => 100000000, 'tenor' => 36,
            'margin' => 20000000, 'ebitda_tahunan' => 150000000, 'angsuran_pokok' => 80000000,
            'aset_agunan_id' => $wakaf->id,
        ]);
    }

    public function test_alur_pembina_butuh_esignature(): void
    {
        $this->boot();
        $nonWakaf = Aset::create(['nama_aset' => 'Ruko', 'nilai' => 800000000, 'is_wakaf' => false]);

        $svc = app(PembiayaanService::class);
        $p = $svc->ajukan([
            'bank_tujuan' => 'Mega Syariah', 'plafon' => 100000000, 'tenor' => 36,
            'margin' => 20000000, 'ebitda_tahunan' => 150000000, 'angsuran_pokok' => 80000000,
            'aset_agunan_id' => $nonWakaf->id,
        ]);
        $this->assertSame('aman', $p->zona);

        $p = $svc->mintaPersetujuanPembina($p);
        $this->assertSame('menunggu_pembina', $p->status);

        $p = $svc->setujuiPembina($p, 'TTE:pembina-hash-123');
        $this->assertSame('disetujui', $p->status);
        $this->assertSame('TTE:pembina-hash-123', $p->esignature_pembina);
    }
}
