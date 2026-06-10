<?php

namespace Tests\Feature;

use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Anggaran;
use Modules\Ledger\Services\AnggaranVarianceService;
use Modules\Ledger\Services\JurnalService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * G1-08 — Varians Anggaran (RKAM) vs Realisasi (read-only). Menguji perhitungan
 * pagu/realisasi/sisa/%, status, proyeksi linear, reuse realisasi(), isolasi.
 */
class AnggaranVarianceTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'akun', 'jurnal', 'jurnal_baris',
            'sumber_dana', 'divisi_unit', 'anggaran', 'periode_akuntansi',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new LedgerSeeder)->run();
    }

    private function akunId(string $kode): int
    {
        return (int) Akun::where('kode', $kode)->value('id');
    }

    private function anggaran(string $kodeAkun, float $pagu): Anggaran
    {
        return Anggaran::create([
            'tahun_anggaran' => (string) now()->year,
            'akun_id' => $this->akunId($kodeAkun),
            'divisi_id' => null, 'sumber_dana_id' => null,
            'nominal_anggaran' => $pagu, 'mode' => 'peringatan',
        ]);
    }

    private function realisasikan(string $kodeAkun, float $nominal): void
    {
        app(JurnalService::class)->postJurnal([
            'tanggal' => now()->toDateString(), 'deskripsi' => 'Beban', 'tipe_sumber' => 'manual',
            'allow_duplicate' => true,
            'baris' => [
                ['akun_id' => $this->akunId($kodeAkun), 'debit' => $nominal, 'kredit' => 0],
                ['akun_id' => $this->akunId('1-1000'), 'debit' => 0, 'kredit' => $nominal],
            ],
        ]);
    }

    public function test_status_thresholds(): void
    {
        $svc = new AnggaranVarianceService;
        $this->assertSame('aman', $svc->status(60, 600, 1000));
        $this->assertSame('waspada', $svc->status(80, 800, 1000));
        $this->assertSame('kritis', $svc->status(95, 950, 1000));
        $this->assertSame('lampaui', $svc->status(110, 1100, 1000));
    }

    public function test_laporan_varians_dan_proyeksi(): void
    {
        $this->boot('var1');
        $this->anggaran('5-2000', 1000000);  // pagu 1jt
        $this->realisasikan('5-2000', 600000); // realisasi 600rb

        $lap = app(AnggaranVarianceService::class)->laporan((string) now()->year, bulanBerjalan: 6);
        $this->assertCount(1, $lap['baris']);
        $b = $lap['baris'][0];
        $this->assertEqualsWithDelta(1000000, $b['pagu'], 0.01);
        $this->assertEqualsWithDelta(600000, $b['realisasi'], 0.01);
        $this->assertEqualsWithDelta(400000, $b['sisa'], 0.01);
        $this->assertEqualsWithDelta(60.0, $b['persen'], 0.1);
        $this->assertSame('aman', $b['status']);
        // Proyeksi linear: 600rb / 6 bln × 12 = 1,2jt → melebihi pagu 1jt.
        $this->assertEqualsWithDelta(1200000, $b['forecast'], 0.01);
        $this->assertTrue($b['forecast_lampaui']);

        $this->assertEqualsWithDelta(1000000, $lap['total']['pagu'], 0.01);
        $this->assertEqualsWithDelta(600000, $lap['total']['realisasi'], 0.01);
        tenancy()->end();
    }

    public function test_status_lampaui_saat_realisasi_lebih_dari_pagu(): void
    {
        $this->boot('var2');
        $this->anggaran('5-2000', 500000);
        $this->realisasikan('5-2000', 700000); // > pagu

        $b = app(AnggaranVarianceService::class)->laporan((string) now()->year, 12)['baris'][0];
        $this->assertSame('lampaui', $b['status']);
        $this->assertEqualsWithDelta(-200000, $b['sisa'], 0.01);
        tenancy()->end();
    }

    public function test_isolasi_tenant(): void
    {
        $this->boot('varA');
        $this->anggaran('5-2000', 1000000);
        $this->assertCount(1, app(AnggaranVarianceService::class)->laporan((string) now()->year, 12)['baris']);
        tenancy()->end();

        $this->boot('varB');
        $this->assertCount(0, app(AnggaranVarianceService::class)->laporan((string) now()->year, 12)['baris']);
        tenancy()->end();
    }
}
