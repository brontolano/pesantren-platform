<?php

namespace Tests\Feature;

use App\Filament\Pages\KalkulatorPajak;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Pajak\Database\Seeders\PajakSeeder;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * G1-05 — Kalkulator Pajak. Menguji glue hasil() (state form → MesinPajakService),
 * termasuk kebijakan kehalalan G1-04 (syubhat bebas pajak).
 */
class KalkulatorPajakTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'akun', 'sumber_dana', 'tarif_pajak',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function page(string $prefix): KalkulatorPajak
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new LedgerSeeder)->run();
        (new PajakSeeder)->run();

        return new KalkulatorPajak;
    }

    public function test_hasil_halal_normal(): void
    {
        $page = $this->page('klp1');
        $page->data = ['dpp' => 3000000, 'jenis' => 'barang', 'dana_bos' => false, 'vendor_npwp' => true, 'kehalalan' => 'halal'];

        $h = $page->hasil();
        $this->assertEqualsWithDelta(330000, $h['ppn'], 0.01);
        $this->assertEqualsWithDelta(45000, $h['pph'], 0.01);
        $this->assertEqualsWithDelta(3330000, $h['total'], 0.01);
        tenancy()->end();
    }

    public function test_hasil_syubhat_bebas_pajak(): void
    {
        $page = $this->page('klp2');
        $page->data = ['dpp' => 3000000, 'jenis' => 'barang', 'dana_bos' => false, 'vendor_npwp' => true, 'kehalalan' => 'syubhat'];

        $h = $page->hasil();
        $this->assertEqualsWithDelta(0, $h['ppn'], 0.01);
        $this->assertEqualsWithDelta(0, $h['pph'], 0.01);
        $this->assertNull($h['pph_jenis']);
        $this->assertEqualsWithDelta(3000000, $h['total'], 0.01);
        tenancy()->end();
    }

    public function test_hasil_dpp_nol(): void
    {
        $page = $this->page('klp3');
        $page->data = ['dpp' => 0, 'jenis' => 'barang', 'dana_bos' => false, 'vendor_npwp' => true, 'kehalalan' => 'halal'];

        $h = $page->hasil();
        $this->assertEqualsWithDelta(0, $h['ppn'], 0.01);
        $this->assertEqualsWithDelta(0, $h['total'], 0.01);
        tenancy()->end();
    }
}
