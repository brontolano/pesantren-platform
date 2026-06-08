<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Models\SumberDana;
use Modules\Pajak\Database\Seeders\PajakSeeder;
use Modules\Pajak\Services\MesinPajakService;
use Modules\Pengadaan\Models\Belanja;
use Modules\Pengadaan\Services\BelanjaService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PajakBelanjaTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePpdbTestingDatabase();

        if (! Schema::connection('pgsql')->hasTable('belanja') || ! Schema::connection('pgsql')->hasTable('tarif_pajak')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(): void
    {
        $tenant = $this->createRuntimeTenant('pajak', 'Pesantren Pajak');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new LedgerSeeder)->run();
        (new PajakSeeder)->run();
    }

    public function test_barang_bos_15jt_kena_ppn_tanpa_pph22(): void
    {
        $this->boot();
        $hasil = app(MesinPajakService::class)->hitung(1500000, 'barang', danaBos: true, vendorNpwp: true);

        $this->assertEqualsWithDelta(165000, $hasil['ppn'], 0.01); // 11%
        $this->assertEqualsWithDelta(0, $hasil['pph'], 0.01);      // barang BOS dikecualikan PPh22
    }

    public function test_jasa_500rb_npwp_pph23_2persen_tanpa_ambang(): void
    {
        $this->boot();
        $hasil = app(MesinPajakService::class)->hitung(500000, 'jasa', danaBos: false, vendorNpwp: true);

        $this->assertEqualsWithDelta(0, $hasil['ppn'], 0.01);       // < Rp1jt → tanpa PPN
        $this->assertSame('pph23', $hasil['pph_jenis']);
        $this->assertEqualsWithDelta(10000, $hasil['pph'], 0.01);   // 2%
    }

    public function test_jasa_non_npwp_pph23_4persen(): void
    {
        $this->boot();
        $hasil = app(MesinPajakService::class)->hitung(1000000, 'jasa', danaBos: false, vendorNpwp: false);

        $this->assertEqualsWithDelta(40000, $hasil['pph'], 0.01);   // 4%
    }

    public function test_sewa_final_42_10persen(): void
    {
        $this->boot();
        $hasil = app(MesinPajakService::class)->hitung(2000000, 'sewa', danaBos: false, vendorNpwp: true);

        $this->assertSame('pph_final_42', $hasil['pph_jenis']);
        $this->assertEqualsWithDelta(200000, $hasil['pph'], 0.01);  // 10%
    }

    public function test_barang_non_bos_3jt_npwp_ppn_dan_pph22(): void
    {
        $this->boot();
        $hasil = app(MesinPajakService::class)->hitung(3000000, 'barang', danaBos: false, vendorNpwp: true);

        $this->assertEqualsWithDelta(330000, $hasil['ppn'], 0.01);  // 11%
        $this->assertEqualsWithDelta(45000, $hasil['pph'], 0.01);   // PPh22 1,5%
    }

    public function test_belanja_posting_jurnal_seimbang_dan_idempoten(): void
    {
        $this->boot();
        $sumberKomersial = SumberDana::where('kode_dana', '03')->value('id');

        $svc = app(BelanjaService::class);
        $belanja = $svc->buat([
            'uraian' => 'Beli laptop',
            'jenis' => 'barang',
            'sumber_dana_id' => $sumberKomersial,
            'dpp' => 3000000,
            'vendor_npwp' => true,
        ]);

        $this->assertSame('diajukan', $belanja->status_approval);
        $this->assertEqualsWithDelta(330000, (float) $belanja->ppn_dipungut, 0.01);
        $this->assertEqualsWithDelta(45000, (float) $belanja->pph_dipungut, 0.01);

        $belanja = $svc->setujui($belanja, approvedBy: null);
        $this->assertSame('dicairkan', $belanja->status_approval);
        $this->assertNotNull($belanja->jurnal_id);

        $jurnal = Jurnal::find($belanja->jurnal_id);
        $totalDebit = (float) $jurnal->baris()->sum('debit');
        $totalKredit = (float) $jurnal->baris()->sum('kredit');
        $this->assertEqualsWithDelta($totalDebit, $totalKredit, 0.01, 'Jurnal belanja harus seimbang.');
        $this->assertEqualsWithDelta(3330000, $totalDebit, 0.01); // dpp + ppn

        // Idempoten: posting ulang transaksi sumber yang sama tidak menggandakan jurnal.
        $svc->posting($belanja->fresh());
        $this->assertSame(1, Jurnal::where('ref_type', 'Belanja')->where('ref_id', (string) $belanja->id)->count());
    }
}
