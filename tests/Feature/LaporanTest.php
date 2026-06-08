<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\SumberDana;
use Modules\Ledger\Services\DanaKebajikanService;
use Modules\Ledger\Services\JurnalService;
use Modules\Ledger\Services\LaporanService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class LaporanTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurePpdbTestingDatabase();
        if (! Schema::connection('pgsql')->hasTable('jurnal_baris')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(): void
    {
        $tenant = $this->createRuntimeTenant('lap', 'Pesantren Laporan');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new LedgerSeeder)->run();
    }

    private function akun(string $kode): int
    {
        return Akun::where('kode', $kode)->value('id');
    }

    private function sumber(string $kode): int
    {
        return SumberDana::where('kode_dana', $kode)->value('id');
    }

    /** Pemasukan SPP 1jt lalu belanja 400rb → surplus 600rb, neraca seimbang. */
    private function postingDasar(): void
    {
        $svc = app(JurnalService::class);
        $svc->postJurnal([
            'tanggal' => '2026-06-05', 'deskripsi' => 'SPP', 'ref_type' => 'T', 'ref_id' => 'spp',
            'baris' => [
                ['akun_id' => $this->akun('1-1000'), 'sumber_dana_id' => $this->sumber('03'), 'debit' => 1000000],
                ['akun_id' => $this->akun('4-1000'), 'sumber_dana_id' => $this->sumber('03'), 'kredit' => 1000000],
            ],
        ]);
        $svc->postJurnal([
            'tanggal' => '2026-06-07', 'deskripsi' => 'Belanja', 'ref_type' => 'T', 'ref_id' => 'bel',
            'baris' => [
                ['akun_id' => $this->akun('5-4000'), 'sumber_dana_id' => $this->sumber('03'), 'debit' => 400000],
                ['akun_id' => $this->akun('1-1000'), 'sumber_dana_id' => $this->sumber('03'), 'kredit' => 400000],
            ],
        ]);
    }

    public function test_laba_rugi_surplus_benar(): void
    {
        $this->boot();
        $this->postingDasar();

        $lr = app(LaporanService::class)->labaRugi();
        $this->assertEqualsWithDelta(1000000, $lr['total_pendapatan'], 0.01);
        $this->assertEqualsWithDelta(400000, $lr['total_beban'], 0.01);
        $this->assertEqualsWithDelta(600000, $lr['surplus_defisit'], 0.01);
    }

    public function test_neraca_seimbang(): void
    {
        $this->boot();
        $this->postingDasar();

        $neraca = app(LaporanService::class)->neraca();
        $this->assertTrue($neraca['seimbang'], 'Neraca harus seimbang (Aset = Kewajiban + Ekuitas + surplus).');
        $this->assertEqualsWithDelta(600000, $neraca['total_aset'], 0.01); // kas 1jt - 400rb
        $this->assertEqualsWithDelta(600000, $neraca['surplus_berjalan'], 0.01);
    }

    public function test_dana_kebajikan_terpisah_dari_surplus(): void
    {
        $this->boot();
        $this->postingDasar();

        $dk = app(DanaKebajikanService::class);
        $dk->terima(300000, '90');
        $dk->salurkan(100000, '2-2010', '90');

        $laporan = app(LaporanService::class)->danaKebajikan();
        $this->assertEqualsWithDelta(300000, $laporan['penerimaan'], 0.01);
        $this->assertEqualsWithDelta(100000, $laporan['total_penyaluran'], 0.01);
        $this->assertEqualsWithDelta(200000, $laporan['saldo'], 0.01);

        // Dana kebajikan TIDAK menambah surplus operasional.
        $lr = app(LaporanService::class)->labaRugi();
        $this->assertEqualsWithDelta(600000, $lr['surplus_defisit'], 0.01);
    }

    public function test_dana_bos_bop_penerimaan_penggunaan(): void
    {
        $this->boot();
        $svc = app(JurnalService::class);
        // Penerimaan BOS Madrasah (01) 2jt
        $svc->postJurnal([
            'tanggal' => '2026-06-03', 'ref_type' => 'T', 'ref_id' => 'bos-in',
            'baris' => [
                ['akun_id' => $this->akun('1-1000'), 'sumber_dana_id' => $this->sumber('01'), 'debit' => 2000000],
                ['akun_id' => $this->akun('4-2000'), 'sumber_dana_id' => $this->sumber('01'), 'kredit' => 2000000],
            ],
        ]);
        // Penggunaan BOS 700rb
        $svc->postJurnal([
            'tanggal' => '2026-06-06', 'ref_type' => 'T', 'ref_id' => 'bos-out',
            'baris' => [
                ['akun_id' => $this->akun('5-1000'), 'sumber_dana_id' => $this->sumber('01'), 'debit' => 700000],
                ['akun_id' => $this->akun('1-1000'), 'sumber_dana_id' => $this->sumber('01'), 'kredit' => 700000],
            ],
        ]);

        $rows = collect(app(LaporanService::class)->danaBosBop());
        $bos = $rows->firstWhere('kode', '01');
        $this->assertEqualsWithDelta(2000000, $bos['penerimaan'], 0.01);
        $this->assertEqualsWithDelta(700000, $bos['penggunaan'], 0.01);
        $this->assertEqualsWithDelta(1300000, $bos['sisa'], 0.01);
        $this->assertSame('bos_madrasah', $bos['rezim']);
    }
}
