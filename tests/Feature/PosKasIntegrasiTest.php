<?php

namespace Tests\Feature;

use InvalidArgumentException;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\PosKategori;
use Modules\Keuangan\Models\PosTransaksi;
use Modules\Keuangan\Services\PosKasService;
use Modules\Keuangan\Services\TabunganService;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Services\JurnalService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * POS-4 — catat() routing + posting Ledger.
 * Bukti: kontinuitas posting (direct → jurnal seimbang; tabungan via service),
 * menahan diri tanpa COA, guard saldo tabungan, idempotensi, isolasi tenant.
 */
class PosKasIntegrasiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri', 'tabungan', 'tabungan_transaksi',
            'akun', 'jurnal', 'jurnal_baris', 'sumber_dana',
            'pos_kategori', 'pos_sesi', 'pos_transaksi',
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

    private function kategori(string $jenis, string $handler, ?int $akunId = null, string $kode = 'KAT'): PosKategori
    {
        return PosKategori::create([
            'kode' => $kode, 'nama' => 'Kategori '.$kode, 'jenis' => $jenis,
            'handler' => $handler, 'akun_id' => $akunId, 'aktif' => true,
        ]);
    }

    private function akunId(string $kode): ?int
    {
        return Akun::query()->where('kode', $kode)->value('id');
    }

    private function santri(): Santri
    {
        return Santri::create([
            'nis' => 'POS-'.str()->random(5), 'nama' => 'Santri POS',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L',
        ]);
    }

    public function test_koperasi_posting_langsung_debit_kas_kredit_pendapatan(): void
    {
        $this->boot('posk1');
        $svc = app(PosKasService::class);
        $kat = $this->kategori('pemasukan', 'koperasi', $this->akunId('4-5000'), 'KOP');

        $sesi = $svc->bukaSesi(['saldo_awal' => 0]);
        $tx = $svc->catat($sesi, $kat, ['nominal' => 50000, 'keterangan' => 'Penjualan kantin']);

        $this->assertSame('pemasukan', $tx->jenis);
        $this->assertNotNull($tx->jurnal_id, 'Transaksi direct harus terjurnal.');

        $jurnal = Jurnal::query()->find($tx->jurnal_id);
        $this->assertEqualsWithDelta(50000, (float) $jurnal->baris()->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(50000, (float) $jurnal->baris()->sum('kredit'), 0.01);
        $this->assertEqualsWithDelta(50000, (float) $jurnal->baris()->where('akun_id', $this->akunId('1-1000'))->sum('debit'), 0.01);

        $this->assertEqualsWithDelta(50000, $svc->recomputeSaldoSistem($sesi->fresh()), 0.01);
    }

    public function test_pengeluaran_posting_langsung_debit_beban_kredit_kas(): void
    {
        $this->boot('posk2');
        $svc = app(PosKasService::class);
        $kat = $this->kategori('pengeluaran', 'pengeluaran', $this->akunId('5-1000'), 'PNG');

        $sesi = $svc->bukaSesi(['saldo_awal' => 100000]);
        $tx = $svc->catat($sesi, $kat, ['nominal' => 30000, 'keterangan' => 'Beli ATK']);

        $jurnal = Jurnal::query()->find($tx->jurnal_id);
        $this->assertEqualsWithDelta(30000, (float) $jurnal->baris()->where('akun_id', $this->akunId('5-1000'))->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(30000, (float) $jurnal->baris()->where('akun_id', $this->akunId('1-1000'))->sum('kredit'), 0.01);

        // Kas berkurang: 100.000 − 30.000 = 70.000
        $this->assertEqualsWithDelta(70000, $svc->recomputeSaldoSistem($sesi->fresh()), 0.01);
    }

    public function test_tanpa_akun_lawan_rekam_tunai_tanpa_jurnal(): void
    {
        $this->boot('posk3');
        $svc = app(PosKasService::class);
        $kat = $this->kategori('pemasukan', 'koperasi', null, 'KOPN'); // akun_id null

        $sesi = $svc->bukaSesi(['saldo_awal' => 0]);
        $tx = $svc->catat($sesi, $kat, ['nominal' => 20000]);

        $this->assertNull($tx->jurnal_id, 'Tanpa akun lawan, posting menahan diri (tak gagal).');
        $this->assertSame(1, PosTransaksi::query()->count());
        $this->assertEqualsWithDelta(20000, $svc->recomputeSaldoSistem($sesi->fresh()), 0.01);
    }

    public function test_tabungan_setor_via_service_menambah_saldo_santri(): void
    {
        $this->boot('posk4');
        $svc = app(PosKasService::class);
        $santri = $this->santri();
        $kat = $this->kategori('pemasukan', 'tabungan_setor', null, 'TS');

        $sesi = $svc->bukaSesi(['saldo_awal' => 0]);
        $tx = $svc->catat($sesi, $kat, ['nominal' => 25000, 'santri_id' => $santri->id]);

        $this->assertSame('TabunganTransaksi', $tx->ref_type);
        $this->assertEqualsWithDelta(25000, app(TabunganService::class)->getSaldo($santri), 0.01);
        $this->assertEqualsWithDelta(25000, $svc->recomputeSaldoSistem($sesi->fresh()), 0.01);
    }

    public function test_tabungan_tarik_melebihi_saldo_ditolak(): void
    {
        $this->boot('posk5');
        $svc = app(PosKasService::class);
        $santri = $this->santri();
        $kat = $this->kategori('pengeluaran', 'tabungan_tarik', null, 'TT');

        $sesi = $svc->bukaSesi(['saldo_awal' => 100000]);

        $this->expectException(InvalidArgumentException::class);
        $svc->catat($sesi, $kat, ['nominal' => 10000, 'santri_id' => $santri->id]);
    }

    public function test_posting_direct_idempoten_via_ref_pos_transaksi(): void
    {
        $this->boot('posk6');
        $svc = app(PosKasService::class);
        $kat = $this->kategori('pemasukan', 'infaq', $this->akunId('4-4000'), 'INF');

        $sesi = $svc->bukaSesi(['saldo_awal' => 0]);
        $tx = $svc->catat($sesi, $kat, ['nominal' => 15000]);

        $this->assertSame(1, Jurnal::query()->where('ref_type', 'PosTransaksi')->where('ref_id', (string) $tx->id)->count());

        // Re-post ref yang sama → JurnalService mengembalikan jurnal yang ada (tak gandakan).
        app(JurnalService::class)->postJurnal([
            'tanggal' => now(), 'tipe_sumber' => 'pos',
            'ref_type' => 'PosTransaksi', 'ref_id' => (string) $tx->id,
            'baris' => [
                ['akun_id' => $this->akunId('1-1000'), 'debit' => 15000, 'kredit' => 0],
                ['akun_id' => $this->akunId('4-4000'), 'debit' => 0, 'kredit' => 15000],
            ],
        ]);

        $this->assertSame(1, Jurnal::query()->where('ref_type', 'PosTransaksi')->where('ref_id', (string) $tx->id)->count());
    }

    private function akunSelisih(): Akun
    {
        // 5-9100 "Selisih Kas" di-seed LedgerSeeder (G3-21). firstOrCreate aman.
        return Akun::firstOrCreate(
            ['kode' => '5-9100'],
            [
                'nama' => 'Selisih Kas',
                'tipe' => 'beban',
                'saldo_normal' => Akun::SALDO_NORMAL['beban'] ?? 'debit',
                'is_postable' => true,
            ],
        );
    }

    public function test_tutup_sesi_selisih_kurang_posting_debit_selisih_kredit_kas(): void
    {
        $this->boot('posS1');
        $svc = app(PosKasService::class);
        $this->akunSelisih();

        $sesi = $svc->bukaSesi(['saldo_awal' => 100000]);
        // Tutup dengan kas fisik kurang Rp5.000 dari sistem (100.000).
        $closed = $svc->tutupSesi($sesi, 95000);

        $this->assertEqualsWithDelta(-5000, (float) $closed->selisih, 0.01);

        $jurnal = Jurnal::query()->where('ref_type', 'PosSesiSelisih')->where('ref_id', (string) $sesi->id)->first();
        $this->assertNotNull($jurnal, 'Selisih kas harus terjurnal saat tutup sesi.');
        $this->assertEqualsWithDelta(5000, (float) $jurnal->baris()->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(5000, (float) $jurnal->baris()->sum('kredit'), 0.01);
        $this->assertEqualsWithDelta(5000, (float) $jurnal->baris()->where('akun_id', $this->akunId('5-9100'))->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(5000, (float) $jurnal->baris()->where('akun_id', $this->akunId('1-1000'))->sum('kredit'), 0.01);
    }

    public function test_tutup_sesi_selisih_lebih_posting_debit_kas_kredit_selisih(): void
    {
        $this->boot('posS2');
        $svc = app(PosKasService::class);
        $this->akunSelisih();

        $sesi = $svc->bukaSesi(['saldo_awal' => 50000]);
        $closed = $svc->tutupSesi($sesi, 53000); // lebih Rp3.000

        $this->assertEqualsWithDelta(3000, (float) $closed->selisih, 0.01);

        $jurnal = Jurnal::query()->where('ref_type', 'PosSesiSelisih')->where('ref_id', (string) $sesi->id)->first();
        $this->assertNotNull($jurnal);
        $this->assertEqualsWithDelta(3000, (float) $jurnal->baris()->where('akun_id', $this->akunId('1-1000'))->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(3000, (float) $jurnal->baris()->where('akun_id', $this->akunId('5-9100'))->sum('kredit'), 0.01);
    }

    public function test_tutup_sesi_pas_tanpa_jurnal_selisih(): void
    {
        $this->boot('posS3');
        $svc = app(PosKasService::class);
        $this->akunSelisih();

        $sesi = $svc->bukaSesi(['saldo_awal' => 75000]);
        $closed = $svc->tutupSesi($sesi, 75000); // pas

        $this->assertEqualsWithDelta(0, (float) $closed->selisih, 0.01);
        $this->assertSame(0, Jurnal::query()->where('ref_type', 'PosSesiSelisih')->count());
    }

    public function test_tutup_sesi_tanpa_akun_selisih_menahan_diri(): void
    {
        $this->boot('posS4');
        $svc = app(PosKasService::class);
        // Hapus akun "Selisih Kas" (di-seed LedgerSeeder) untuk menguji fallback
        // "menahan diri" saat akun selisih tak tersedia.
        Akun::query()->where('nama', 'like', '%Selisih Kas%')->delete();

        $sesi = $svc->bukaSesi(['saldo_awal' => 40000]);
        $closed = $svc->tutupSesi($sesi, 38000); // kurang 2.000

        $this->assertEqualsWithDelta(-2000, (float) $closed->selisih, 0.01);
        $this->assertSame(0, Jurnal::query()->where('ref_type', 'PosSesiSelisih')->count(), 'Tanpa akun selisih, posting menahan diri (tutup sesi tetap berhasil).');
    }

    public function test_isolasi_tenant_transaksi_pos(): void
    {
        $this->boot('posA');
        $svc = app(PosKasService::class);
        $kat = $this->kategori('pemasukan', 'umum', null, 'U');
        $svc->catat($svc->bukaSesi(['saldo_awal' => 0]), $kat, ['nominal' => 5000]);
        $this->assertSame(1, PosTransaksi::query()->count());

        tenancy()->end();

        $this->boot('posB');
        $this->assertSame(0, PosTransaksi::query()->count(), 'Transaksi POS tenant A tak boleh terlihat di B.');
    }
}
