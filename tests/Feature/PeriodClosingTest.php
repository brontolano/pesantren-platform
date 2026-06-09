<?php

namespace Tests\Feature;

use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Exceptions\PeriodeTerkunciException;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\JurnalBaris;
use Modules\Ledger\Models\PeriodeAkuntansi;
use Modules\Ledger\Services\JurnalService;
use Modules\Ledger\Services\PeriodClosingService;
use RuntimeException;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * G1-06 — Tutup Buku. Menguji jurnal penutup (nol-kan nominal → surplus ke
 * ekuitas), kunci periode, idempotensi, defisit, dan periode tanpa aktivitas.
 */
class PeriodClosingTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'akun', 'jurnal', 'jurnal_baris',
            'periode_akuntansi', 'sumber_dana', 'divisi_unit',
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

    private function periode(): PeriodeAkuntansi
    {
        return PeriodeAkuntansi::create([
            'nama' => 'TA 2025', 'tgl_mulai' => '2025-01-01', 'tgl_selesai' => '2025-12-31', 'status' => 'open',
        ]);
    }

    private function akunId(string $kode): int
    {
        return (int) Akun::where('kode', $kode)->value('id');
    }

    private function catat(string $tanggal, string $debitKode, string $kreditKode, float $nominal): void
    {
        app(JurnalService::class)->postJurnal([
            'tanggal' => $tanggal, 'deskripsi' => 'Uji', 'tipe_sumber' => 'manual',
            'baris' => [
                ['akun_id' => $this->akunId($debitKode), 'debit' => $nominal, 'kredit' => 0],
                ['akun_id' => $this->akunId($kreditKode), 'debit' => 0, 'kredit' => $nominal],
            ],
        ]);
    }

    /** Saldo gerak (Σdebit−Σkredit) akun dalam rentang periode. */
    private function movement(string $kode): float
    {
        return (float) JurnalBaris::where('akun_id', $this->akunId($kode))
            ->whereHas('jurnal', fn ($j) => $j->where('status', 'posted')
                ->whereBetween('tanggal', ['2025-01-01 00:00:00', '2025-12-31 23:59:59']))
            ->sum(\Illuminate\Support\Facades\DB::raw('debit - kredit'));
    }

    public function test_tutup_buku_surplus_dan_nol_kan_nominal(): void
    {
        $this->boot('pc1');
        $p = $this->periode();
        $this->catat('2025-06-01', '1-1000', '4-1000', 1000000); // pendapatan SPP 1jt
        $this->catat('2025-06-02', '5-1000', '1-1000', 300000);  // beban operasional 300rb

        $hasil = app(PeriodClosingService::class)->tutupBuku($p, null);

        $this->assertEqualsWithDelta(700000, $hasil['surplus_defisit'], 0.01);
        $this->assertNotNull($hasil['jurnal']);
        // Jurnal penutup seimbang.
        $j = $hasil['jurnal'];
        $this->assertEqualsWithDelta($j->baris->sum('debit'), $j->baris->sum('kredit'), 0.01);
        // Nominal ter-nol-kan; ekuitas naik 700rb (kredit → movement −700rb).
        $this->assertEqualsWithDelta(0, $this->movement('4-1000'), 0.01);
        $this->assertEqualsWithDelta(0, $this->movement('5-1000'), 0.01);
        $this->assertEqualsWithDelta(-700000, $this->movement('3-1000'), 0.01);
        // Periode terkunci.
        $this->assertTrue($p->fresh()->isClosed());
        $this->assertNotNull($p->fresh()->closed_at);
        tenancy()->end();
    }

    public function test_periode_closed_menolak_posting(): void
    {
        $this->boot('pc2');
        $p = $this->periode();
        $this->catat('2025-06-01', '1-1000', '4-1000', 500000);
        app(PeriodClosingService::class)->tutupBuku($p, null);

        $this->expectException(PeriodeTerkunciException::class);
        $this->catat('2025-07-01', '1-1000', '4-1000', 100000); // dalam periode tertutup
        tenancy()->end();
    }

    public function test_idempoten_tutup_dua_kali_ditolak(): void
    {
        $this->boot('pc3');
        $p = $this->periode();
        $this->catat('2025-06-01', '1-1000', '4-1000', 500000);
        app(PeriodClosingService::class)->tutupBuku($p, null);

        $this->expectException(RuntimeException::class);
        app(PeriodClosingService::class)->tutupBuku($p->fresh(), null);
        tenancy()->end();
    }

    public function test_defisit_mengurangi_ekuitas(): void
    {
        $this->boot('pc4');
        $p = $this->periode();
        $this->catat('2025-06-01', '1-1000', '4-1000', 200000); // pendapatan 200rb
        $this->catat('2025-06-02', '5-1000', '1-1000', 500000); // beban 500rb → defisit 300rb

        $hasil = app(PeriodClosingService::class)->tutupBuku($p, null);
        $this->assertEqualsWithDelta(-300000, $hasil['surplus_defisit'], 0.01);
        // Defisit → ekuitas didebit (movement +300rb).
        $this->assertEqualsWithDelta(300000, $this->movement('3-1000'), 0.01);
        tenancy()->end();
    }

    public function test_periode_tanpa_aktivitas_hanya_dikunci(): void
    {
        $this->boot('pc5');
        $p = $this->periode();
        $hasil = app(PeriodClosingService::class)->tutupBuku($p, null);
        $this->assertNull($hasil['jurnal']);
        $this->assertEqualsWithDelta(0, $hasil['surplus_defisit'], 0.01);
        $this->assertTrue($p->fresh()->isClosed());
        tenancy()->end();
    }
}
