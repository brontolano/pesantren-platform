<?php

namespace Tests\Feature;

use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\SumberDana;
use Modules\Pajak\Database\Seeders\PajakSeeder;
use Modules\Pajak\Services\MesinPajakService;
use Modules\Pajak\Support\KebijakanPajakKehalalan;
use Modules\Pengadaan\Services\BelanjaService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * G1-04 — Halal/Syubhat Tax Policy. Hak negara atas pajak hanya dari transaksi
 * HALAL; dana syubhat/non-halal → PPN/PPh = 0. Membuktikan exempt + normal +
 * non-regresi (halal tetap kena pajak) + BC default param.
 */
class PajakKehalalanTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'akun', 'sumber_dana', 'tarif_pajak',
            'jurnal', 'jurnal_baris', 'belanja', 'periode_akuntansi',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new LedgerSeeder)->run();
        (new PajakSeeder)->run();
    }

    public function test_policy_klasifikasi(): void
    {
        $k = new KebijakanPajakKehalalan;
        $this->assertTrue($k->bebasPajakNegara('syubhat'));
        $this->assertTrue($k->bebasPajakNegara('non_halal'));
        $this->assertFalse($k->bebasPajakNegara('halal'));
        $this->assertFalse($k->bebasPajakNegara(null));
    }

    public function test_hitung_syubhat_dan_non_halal_bebas_pajak(): void
    {
        $this->boot('pkh1');
        $svc = app(MesinPajakService::class);

        foreach (['syubhat', 'non_halal'] as $kehalalan) {
            $hasil = $svc->hitung(3000000, 'barang', danaBos: false, vendorNpwp: true, kehalalan: $kehalalan);
            $this->assertEqualsWithDelta(0, $hasil['ppn'], 0.01, "ppn $kehalalan");
            $this->assertEqualsWithDelta(0, $hasil['pph'], 0.01, "pph $kehalalan");
            $this->assertNull($hasil['pph_jenis']);
        }
        tenancy()->end();
    }

    public function test_hitung_halal_normal_dan_default_bc(): void
    {
        $this->boot('pkh2');
        $svc = app(MesinPajakService::class);

        // Eksplisit halal.
        $h = $svc->hitung(3000000, 'barang', danaBos: false, vendorNpwp: true, kehalalan: 'halal');
        $this->assertEqualsWithDelta(330000, $h['ppn'], 0.01); // 11%
        $this->assertEqualsWithDelta(45000, $h['pph'], 0.01);  // PPh22 1,5%

        // Tanpa argumen kehalalan (BC) → default 'halal' → sama.
        $d = $svc->hitung(3000000, 'barang', danaBos: false, vendorNpwp: true);
        $this->assertEqualsWithDelta(330000, $d['ppn'], 0.01);
        $this->assertEqualsWithDelta(45000, $d['pph'], 0.01);
        tenancy()->end();
    }

    public function test_belanja_dari_sumber_syubhat_tanpa_pajak(): void
    {
        $this->boot('pkh3');
        $syubhat = SumberDana::where('kode_dana', '90')->value('id'); // Bunga Bank (syubhat)

        $belanja = app(BelanjaService::class)->buat([
            'uraian' => 'Beli dari dana syubhat', 'jenis' => 'barang',
            'sumber_dana_id' => $syubhat, 'dpp' => 3000000, 'vendor_npwp' => true,
        ]);

        $this->assertEqualsWithDelta(0, (float) $belanja->ppn_dipungut, 0.01);
        $this->assertEqualsWithDelta(0, (float) $belanja->pph_dipungut, 0.01);
        $this->assertNull($belanja->pph_jenis);
        $this->assertEqualsWithDelta(3000000, (float) $belanja->total, 0.01); // total = dpp (tanpa ppn)
        tenancy()->end();
    }

    public function test_belanja_dari_sumber_halal_tetap_kena_pajak(): void
    {
        $this->boot('pkh4');
        $halal = SumberDana::where('kode_dana', '03')->value('id'); // SPP/Swadaya (halal, komersial)

        $belanja = app(BelanjaService::class)->buat([
            'uraian' => 'Beli dari dana halal', 'jenis' => 'barang',
            'sumber_dana_id' => $halal, 'dpp' => 3000000, 'vendor_npwp' => true,
        ]);

        $this->assertEqualsWithDelta(330000, (float) $belanja->ppn_dipungut, 0.01);
        $this->assertEqualsWithDelta(45000, (float) $belanja->pph_dipungut, 0.01);
        tenancy()->end();
    }
}
