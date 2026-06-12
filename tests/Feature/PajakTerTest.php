<?php

namespace Tests\Feature;

use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Pajak\Database\Seeders\TerPph21Seeder;
use Modules\Pajak\Services\PajakPenghasilanService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * TER PPh 21 (PP 58/2023) — lookup + Desember recalc.
 */
class PajakTerTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private PajakPenghasilanService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'akun', 'sumber_dana', 'periode_akuntansi',
            'slip_gaji', 'employee', 'komponen_gaji', 'settings', 'ter_pph21',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $tenant = $this->createRuntimeTenant('ter', 'Pesantren TER');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new LedgerSeeder)->run();
        (new TerPph21Seeder)->run();

        $this->svc = app(PajakPenghasilanService::class);
    }

    public function test_kategori_mapping(): void
    {
        $this->assertEquals('A', $this->svc->kategoriTer('TK0'));
        $this->assertEquals('A', $this->svc->kategoriTer('TK1'));
        $this->assertEquals('B', $this->svc->kategoriTer('K0'));
        $this->assertEquals('B', $this->svc->kategoriTer('K1'));
        $this->assertEquals('C', $this->svc->kategoriTer('K2'));
        $this->assertEquals('C', $this->svc->kategoriTer('K3'));
    }

    public function test_ter_bruto_di_bawah_ptkp_nol_persen(): void
    {
        // Kategori A, bruto 4jt → tarif 0%
        $hasil = $this->svc->hitungBulananTer(4000000, 'TK0', 1);
        $this->assertEquals('ter', $hasil['metode']);
        $this->assertEquals(0.0, $hasil['tarif_ter']);
        $this->assertEquals(0.0, $hasil['pph21']);
    }

    public function test_ter_bruto_10jt_kategori_a(): void
    {
        // 10.050.000 ≤ bruto < 10.350.000 → TER 2.25%
        $hasil = $this->svc->hitungBulananTer(10200000, 'TK0', 3);
        $this->assertEquals('ter', $hasil['metode']);
        $this->assertEquals('A', $hasil['kategori_ter']);
        $this->assertEquals(2.25, (float) $hasil['tarif_ter']);
        $this->assertEquals(round(10200000 * 2.25 / 100), $hasil['pph21']);
    }

    public function test_ter_kategori_b(): void
    {
        // Kategori B (K/0), bruto 15jt → 14.950.000..16.400.000 = 6%
        $hasil = $this->svc->hitungBulananTer(15000000, 'K0', 5);
        $this->assertEquals('B', $hasil['kategori_ter']);
        $this->assertEquals(6.00, (float) $hasil['tarif_ter']);
        $this->assertEquals(round(15000000 * 6 / 100), $hasil['pph21']);
    }

    public function test_ter_kategori_c(): void
    {
        // Kategori C (K/2), bruto 20jt → 19.500.000..22.700.000 = 8%
        $hasil = $this->svc->hitungBulananTer(20000000, 'K2', 7);
        $this->assertEquals('C', $hasil['kategori_ter']);
        $this->assertEquals(8.00, (float) $hasil['tarif_ter']);
        $this->assertEquals(round(20000000 * 8 / 100), $hasil['pph21']);
    }

    public function test_desember_recalc_progresif(): void
    {
        // Simulasi: bruto 10jt/bulan, TK0, bulan 12
        // Akumulasi Jan-Nov: 11 × 10jt = 110jt bruto
        // PPh TER Jan-Nov: TER 2.00% (9.650.000..10.050.000) × 10jt = 200.000/bulan × 11 = 2.200.000
        $akBruto = 110000000.0;
        $akPph = 2200000.0;

        $hasil = $this->svc->hitungBulananTer(10000000, 'TK0', 12, $akBruto, $akPph);
        $this->assertEquals('progresif_desember', $hasil['metode']);
        $this->assertEquals(120000000.0, $hasil['bruto_tahunan']);

        // biaya jabatan = min(120jt × 5%, 6jt) = 6.000.000
        $this->assertEquals(6000000.0, $hasil['biaya_jabatan']);

        // netto tahunan = 120jt - 6jt = 114jt
        // pkp = 114jt - 54jt (TK0) = 60jt → bulatkan = 60.000.000
        $this->assertEquals(60000000.0, $hasil['pkp']);

        // PPh tahunan = 60jt × 5% = 3.000.000
        $this->assertEquals(3000000.0, $hasil['pph21_tahunan']);

        // PPh Des = 3.000.000 - 2.200.000 = 800.000
        $this->assertEquals(800000.0, $hasil['pph21']);
    }

    public function test_backward_compat_pph21bulanan(): void
    {
        // Metode lama masih berfungsi
        $pph = $this->svc->pph21Bulanan(10000000, 'TK0');
        $this->assertIsFloat($pph);
        $this->assertGreaterThanOrEqual(0, $pph);
    }
}
