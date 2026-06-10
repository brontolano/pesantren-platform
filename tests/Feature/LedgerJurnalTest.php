<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Exceptions\JurnalTidakSeimbangException;
use Modules\Ledger\Exceptions\PeriodeTerkunciException;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\DivisiUnit;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Models\PeriodeAkuntansi;
use Modules\Ledger\Models\SumberDana;
use Modules\Ledger\Services\BukuBesarService;
use Modules\Ledger\Services\JurnalService;
use Modules\Ledger\Services\PeriodeService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class LedgerJurnalTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePpdbTestingDatabase();

        if (! Schema::connection('pgsql')->hasTable('jurnal_baris')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        $this->truncatePpdbTables([
            'domains',
            'tenants',
        ]);
    }

    /** Posting jurnal manual seimbang (debit Kas / kredit Pendapatan SPP). */
    private function postContoh(JurnalService $svc, float $nominal = 100000, ?string $tanggal = null, ?string $ref = null): Jurnal
    {
        return $svc->postJurnal([
            'tanggal' => $tanggal ?? '2026-06-10',
            'deskripsi' => 'Uji posting',
            'ref_type' => $ref !== null ? 'Uji' : null,
            'ref_id' => $ref,
            'allow_duplicate' => $ref === null, // fixture tanpa ref sengaja boleh ganda
            'baris' => [
                [
                    'akun_id' => Akun::where('kode', '1-1000')->value('id'),
                    'divisi_id' => DivisiUnit::where('kode', 'MA')->value('id'),
                    'sumber_dana_id' => SumberDana::where('kode_dana', '03')->value('id'),
                    'debit' => $nominal,
                ],
                [
                    'akun_id' => Akun::where('kode', '4-1000')->value('id'),
                    'divisi_id' => DivisiUnit::where('kode', 'MA')->value('id'),
                    'sumber_dana_id' => SumberDana::where('kode_dana', '03')->value('id'),
                    'kredit' => $nominal,
                ],
            ],
        ]);
    }

    public function test_seed_coa_membentuk_hierarki_dan_dimensi_dana(): void
    {
        $tenant = $this->createRuntimeTenant('led', 'Pesantren Seed');
        tenancy()->initialize($tenant);

        (new LedgerSeeder)->run();

        $kas = Akun::where('kode', '1-1000')->first();
        $this->assertSame('ASET', $kas->tipe);
        $this->assertSame('debit', $kas->saldo_normal);
        $this->assertTrue($kas->is_kas_bank);

        $ppn = Akun::where('kode', '2-1110')->first();
        $utangPajak = Akun::where('kode', '2-1100')->first();
        $this->assertSame($utangPajak->id, $ppn->parent_id, 'Utang PPN harus anak dari Utang Pajak.');

        $wakaf = SumberDana::where('kode_dana', '04')->first();
        $this->assertTrue($wakaf->is_wakaf, 'Sumber dana Wakaf wajib bertanda is_wakaf.');

        // Koreksi #2: BOS Madrasah dan BOP Pesantren sebagai rezim terpisah.
        $this->assertSame('bos_madrasah', SumberDana::where('kode_dana', '01')->value('rezim'));
        $this->assertSame('bop_pesantren', SumberDana::where('kode_dana', '02')->value('rezim'));

        $this->assertGreaterThanOrEqual(6, DivisiUnit::count());
    }

    public function test_jurnal_tidak_seimbang_ditolak(): void
    {
        $tenant = $this->createRuntimeTenant('led', 'Pesantren Balance');
        tenancy()->initialize($tenant);
        (new LedgerSeeder)->run();

        $this->expectException(JurnalTidakSeimbangException::class);

        app(JurnalService::class)->postJurnal([
            'tanggal' => '2026-06-10',
            'baris' => [
                ['akun_id' => Akun::where('kode', '1-1000')->value('id'), 'debit' => 100000],
                ['akun_id' => Akun::where('kode', '4-1000')->value('id'), 'kredit' => 90000],
            ],
        ]);
    }

    public function test_posting_idempoten_tidak_menggandakan(): void
    {
        $tenant = $this->createRuntimeTenant('led', 'Pesantren Idem');
        tenancy()->initialize($tenant);
        (new LedgerSeeder)->run();

        $svc = app(JurnalService::class);
        $a = $this->postContoh($svc, 50000, ref: 'PMB-1');
        $b = $this->postContoh($svc, 50000, ref: 'PMB-1');

        $this->assertSame($a->id, $b->id, 'Posting dengan ref sama harus mengembalikan jurnal yang sama.');
        $this->assertSame(1, Jurnal::count());
        $this->assertSame(2, $a->baris()->count());
    }

    public function test_jurnal_menyimpan_tiga_dimensi(): void
    {
        $tenant = $this->createRuntimeTenant('led', 'Pesantren Dimensi');
        tenancy()->initialize($tenant);
        (new LedgerSeeder)->run();

        $jurnal = $this->postContoh(app(JurnalService::class));
        $baris = $jurnal->baris()->first();

        $this->assertNotNull($baris->akun_id);
        $this->assertNotNull($baris->divisi_id);
        $this->assertNotNull($baris->sumber_dana_id);
    }

    public function test_posting_ke_periode_closed_ditolak(): void
    {
        $tenant = $this->createRuntimeTenant('led', 'Pesantren Periode');
        tenancy()->initialize($tenant);
        (new LedgerSeeder)->run();

        $periode = PeriodeAkuntansi::create([
            'nama' => 'TA 2025/2026 Ganjil',
            'tgl_mulai' => '2026-06-01',
            'tgl_selesai' => '2026-06-30',
            'status' => 'open',
        ]);
        app(PeriodeService::class)->tutupBuku($periode);

        $this->expectException(PeriodeTerkunciException::class);
        $this->postContoh(app(JurnalService::class), 100000, tanggal: '2026-06-15');
    }

    public function test_koreksi_hanya_via_pembalik_dan_terlihat_keduanya(): void
    {
        $tenant = $this->createRuntimeTenant('led', 'Pesantren Reversal');
        tenancy()->initialize($tenant);
        (new LedgerSeeder)->run();

        $svc = app(JurnalService::class);
        $asli = $this->postContoh($svc, 75000);
        $pembalik = $svc->reverseJurnal($asli, 'salah input');

        $asli->refresh();
        $this->assertSame('reversed', $asli->status);
        $this->assertSame($pembalik->id, $asli->reversed_by_id);
        $this->assertSame($asli->id, $pembalik->reversal_of_id);

        // Debit/kredit ditukar pada pembalik.
        $barisAsli = $asli->baris()->orderBy('id')->first();
        $barisPembalik = $pembalik->baris()->where('akun_id', $barisAsli->akun_id)->first();
        $this->assertEquals($barisAsli->debit, $barisPembalik->kredit);
        $this->assertEquals($barisAsli->kredit, $barisPembalik->debit);

        // Keduanya tampak (tidak dihapus) — immutability.
        $this->assertSame(2, Jurnal::count());
    }

    public function test_bku_rekonsiliasi_dengan_akumulasi_kas(): void
    {
        $tenant = $this->createRuntimeTenant('led', 'Pesantren BKU');
        tenancy()->initialize($tenant);
        (new LedgerSeeder)->run();

        $svc = app(JurnalService::class);
        $this->postContoh($svc, 100000, tanggal: '2026-06-05', ref: 'A');
        $this->postContoh($svc, 250000, tanggal: '2026-06-07', ref: 'B');

        $bku = app(BukuBesarService::class)->bku('2026-06-01', '2026-06-30');

        $this->assertEqualsWithDelta(350000, $bku['total_masuk'], 0.01);
        $this->assertEqualsWithDelta(0, $bku['total_keluar'], 0.01);
        $this->assertEqualsWithDelta(350000, $bku['saldo_akhir'], 0.01);
        $this->assertCount(2, $bku['mutasi']);
    }

    public function test_neraca_saldo_seimbang(): void
    {
        $tenant = $this->createRuntimeTenant('led', 'Pesantren Trial');
        tenancy()->initialize($tenant);
        (new LedgerSeeder)->run();

        $this->postContoh(app(JurnalService::class), 120000, ref: 'X');

        $rows = app(BukuBesarService::class)->neracaSaldo();
        $totalDebit = $rows->sum('debit');
        $totalKredit = $rows->sum('kredit');

        $this->assertEqualsWithDelta($totalDebit, $totalKredit, 0.01, 'Neraca saldo wajib seimbang (Σdebit = Σkredit).');
        $this->assertGreaterThan(0, $totalDebit);
    }

    public function test_isolasi_tenant_jurnal_tidak_bocor(): void
    {
        $tenantA = $this->createRuntimeTenant('led', 'Pesantren A');
        $tenantB = $this->createRuntimeTenant('led', 'Pesantren B');

        tenancy()->initialize($tenantA);
        (new LedgerSeeder)->run();
        $this->postContoh(app(JurnalService::class), 100000, ref: 'A1');
        $jumlahA = Jurnal::count();
        tenancy()->end();

        tenancy()->initialize($tenantB);
        (new LedgerSeeder)->run();

        // DB-per-tenant: isolasi fisik — tenant B punya database sendiri,
        // jurnal tenant A tidak terlihat sama sekali.
        $this->assertSame(1, $jumlahA);
        $this->assertSame(0, Jurnal::count(), 'Tenant B tidak boleh melihat jurnal tenant A.');
    }
}
