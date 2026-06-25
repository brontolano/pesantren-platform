<?php

namespace Tests\Feature;

use Modules\Keuangan\Models\BankStatement;
use Modules\Keuangan\Services\BankReconService;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\JurnalBaris;
use Modules\Ledger\Services\JurnalService;
use RuntimeException;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F6 — Reconciliation Engine (Bank Reconciliation). Menguji import, auto-match
 * ke jurnal_baris kas/bank, idempotensi, manual match/unmatch, lock bulanan,
 * dan isolasi tenant.
 */
class BankReconTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'akun', 'jurnal', 'jurnal_baris',
            'bank_statement', 'bank_statement_line',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        (new LedgerSeeder)->run();
        $this->seedTenantRow($tenant);
    }

    private function akunId(string $kode): int
    {
        return (int) Akun::query()->where('kode', $kode)->value('id');
    }

    /** Posting "uang masuk" ke bank: Debit Bank, Kredit Pendapatan. */
    private function postMasukBank(float $nominal, ?string $tanggal = null): void
    {
        app(JurnalService::class)->postJurnal([
            'tanggal' => $tanggal ?? now()->toDateString(),
            'deskripsi' => 'Donasi via transfer',
            'tipe_sumber' => 'manual',
            'allow_duplicate' => true,
            'baris' => [
                ['akun_id' => $this->akunId('1-1100'), 'debit' => $nominal, 'kredit' => 0],
                ['akun_id' => $this->akunId('4-1000'), 'debit' => 0, 'kredit' => $nominal],
            ],
        ]);
    }

    public function test_import_lalu_auto_match(): void
    {
        $this->boot('bank1');
        $this->postMasukBank(500000);
        $svc = app(BankReconService::class);

        $stmt = $svc->import($this->akunId('1-1100'), ['nama' => 'BSI Juni'], [
            ['tanggal' => now()->toDateString(), 'nominal' => 500000, 'arah' => 'masuk', 'keterangan' => 'Transfer masuk'],
            ['tanggal' => now()->toDateString(), 'nominal' => 777, 'arah' => 'masuk', 'keterangan' => 'Tak ada padanan'],
        ]);
        $this->assertCount(2, $stmt->lines);

        $ring = $svc->autoMatch($stmt);
        $this->assertSame(1, $ring['cocok']);
        $this->assertSame(1, $ring['belum_cocok']);

        $cocok = $stmt->fresh('lines')->lines->firstWhere('status', 'cocok');
        $this->assertNotNull($cocok->jurnal_baris_id);
        tenancy()->end();
    }

    public function test_auto_match_idempoten_tidak_dobel(): void
    {
        $this->boot('bank2');
        $this->postMasukBank(250000);
        $svc = app(BankReconService::class);

        // Dua baris identik, tapi hanya satu jurnal_baris → hanya satu yang boleh cocok.
        $stmt = $svc->import($this->akunId('1-1100'), ['nama' => 'Dobel'], [
            ['tanggal' => now()->toDateString(), 'nominal' => 250000, 'arah' => 'masuk'],
            ['tanggal' => now()->toDateString(), 'nominal' => 250000, 'arah' => 'masuk'],
        ]);

        $r1 = $svc->autoMatch($stmt);
        $r2 = $svc->autoMatch($stmt->fresh('lines')); // ulang → tak berubah
        $this->assertSame(1, $r1['cocok']);
        $this->assertSame($r1, $r2);

        $jbIds = $stmt->fresh('lines')->lines->whereNotNull('jurnal_baris_id')->pluck('jurnal_baris_id');
        $this->assertSame(1, $jbIds->unique()->count());
        tenancy()->end();
    }

    public function test_manual_match_dan_batal(): void
    {
        $this->boot('bank3');
        $this->postMasukBank(120000);
        $svc = app(BankReconService::class);
        $jbId = (int) JurnalBaris::query()
            ->where('akun_id', $this->akunId('1-1100'))->where('debit', 120000)->value('id');

        $stmt = $svc->import($this->akunId('1-1100'), ['nama' => 'Manual'], [
            ['tanggal' => now()->subDays(30)->toDateString(), 'nominal' => 120000, 'arah' => 'masuk'], // di luar toleransi auto
        ]);
        $line = $stmt->lines->first();

        // Auto tidak cocok (selisih 30 hari > toleransi) → manual.
        $svc->autoMatch($stmt);
        $this->assertSame('belum_cocok', $line->fresh()->status);

        $svc->cocokkanManual($line, $jbId);
        $this->assertSame('cocok', $line->fresh()->status);

        $svc->batalkanCocok($line->fresh());
        $this->assertSame('belum_cocok', $line->fresh()->status);
        $this->assertNull($line->fresh()->jurnal_baris_id);
        tenancy()->end();
    }

    public function test_lock_mencegah_perubahan(): void
    {
        $this->boot('bank4');
        $svc = app(BankReconService::class);
        $stmt = $svc->import($this->akunId('1-1100'), ['nama' => 'Kunci'], [
            ['tanggal' => now()->toDateString(), 'nominal' => 1000, 'arah' => 'keluar'],
        ]);

        $svc->kunci($stmt);
        $this->expectException(RuntimeException::class);
        $svc->autoMatch($stmt->fresh());
        tenancy()->end();
    }

    public function test_isolasi_tenant(): void
    {
        $this->boot('bankA');
        app(BankReconService::class)->import($this->akunId('1-1100'), ['nama' => 'A'], [
            ['tanggal' => now()->toDateString(), 'nominal' => 1, 'arah' => 'masuk'],
        ]);
        $this->assertSame(1, BankStatement::query()->count());
        tenancy()->end();

        $this->boot('bankB');
        $this->assertSame(0, BankStatement::query()->count());
        tenancy()->end();
    }
}
