<?php

namespace Tests\Feature;

use InvalidArgumentException;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\PosKategori;
use Modules\Keuangan\Models\PosProfil;
use Modules\Keuangan\Services\PosEnrichmentService;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Jurnal;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * G3-21 — POS enrichment: diskon (netto→posting seimbang), loyalty poin, metode
 * bayar + ringkasan, struk. Math PosKasService TIDAK diubah.
 */
class PosEnrichmentTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'santri', 'akun', 'jurnal', 'jurnal_baris', 'sumber_dana',
            'pos_kategori', 'pos_sesi', 'pos_transaksi', 'pos_profil', 'audit_log',
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

    private function svc(): PosEnrichmentService
    {
        return app(PosEnrichmentService::class);
    }

    private function kategoriKoperasi(): PosKategori
    {
        return PosKategori::create([
            'kode' => 'KOP', 'nama' => 'Kantin', 'jenis' => 'pemasukan',
            'handler' => 'koperasi', 'akun_id' => Akun::where('kode', '4-5000')->value('id'), 'aktif' => true,
        ]);
    }

    private function profil(float $tarifPoin = 0): PosProfil
    {
        return PosProfil::create(['nama' => 'Koperasi', 'pos_jenis' => 'koperasi', 'tarif_poin' => $tarifPoin, 'is_active' => true]);
    }

    public function test_diskon_netto_jurnal_seimbang_dan_poin(): void
    {
        $this->boot('pe1');
        $kat = $this->kategoriKoperasi();
        $sesi = $this->svc()->bukaSesiProfil($this->profil(10000), ['saldo_awal' => 0]);

        // Harga 50rb − diskon 5rb = netto 45rb. Poin = floor(45rb/10rb) = 4.
        $tx = $this->svc()->catatRitel($sesi, $kat, ['harga_kotor' => 50000, 'diskon' => 5000, 'metode_bayar' => 'tunai']);

        $this->assertEqualsWithDelta(50000, (float) $tx->harga_kotor, 0.01);
        $this->assertEqualsWithDelta(5000, (float) $tx->diskon, 0.01);
        $this->assertEqualsWithDelta(45000, (float) $tx->nominal, 0.01); // netto
        $this->assertEqualsWithDelta(4, (float) $tx->poin, 0.01);

        // Jurnal seimbang pada NETTO (diskon tak mengkorup total).
        $jurnal = Jurnal::with('baris')->find($tx->jurnal_id);
        $this->assertEqualsWithDelta($jurnal->baris->sum('debit'), $jurnal->baris->sum('kredit'), 0.01);
        $this->assertEqualsWithDelta(45000, (float) $jurnal->baris->sum('debit'), 0.01);
        tenancy()->end();
    }

    public function test_diskon_melebihi_harga_ditolak(): void
    {
        $this->boot('pe2');
        $kat = $this->kategoriKoperasi();
        $sesi = $this->svc()->bukaSesiProfil($this->profil(), ['saldo_awal' => 0]);

        $this->expectException(InvalidArgumentException::class);
        $this->svc()->catatRitel($sesi, $kat, ['harga_kotor' => 20000, 'diskon' => 20000]); // netto 0
        tenancy()->end();
    }

    public function test_metode_invalid_ditolak(): void
    {
        $this->boot('pe3');
        $kat = $this->kategoriKoperasi();
        $sesi = $this->svc()->bukaSesiProfil($this->profil(), ['saldo_awal' => 0]);

        $this->expectException(InvalidArgumentException::class);
        $this->svc()->catatRitel($sesi, $kat, ['harga_kotor' => 10000, 'metode_bayar' => 'kartu_kredit']);
        tenancy()->end();
    }

    public function test_ringkasan_metode_per_sesi(): void
    {
        $this->boot('pe4');
        $kat = $this->kategoriKoperasi();
        $sesi = $this->svc()->bukaSesiProfil($this->profil(), ['saldo_awal' => 0]);

        $this->svc()->catatRitel($sesi, $kat, ['harga_kotor' => 45000, 'metode_bayar' => 'tunai']);
        $this->svc()->catatRitel($sesi, $kat, ['harga_kotor' => 30000, 'metode_bayar' => 'qris']);

        $ring = $this->svc()->ringkasanMetode($sesi);
        $this->assertEqualsWithDelta(45000, $ring['tunai'], 0.01);
        $this->assertEqualsWithDelta(30000, $ring['qris'], 0.01);
        $this->assertEqualsWithDelta(0, $ring['transfer'], 0.01);
        tenancy()->end();
    }

    public function test_loyalty_poin_terakumulasi_per_santri(): void
    {
        $this->boot('pe5');
        $kat = $this->kategoriKoperasi();
        $sesi = $this->svc()->bukaSesiProfil($this->profil(10000), ['saldo_awal' => 0]);
        $s = Santri::create(['nis' => 'PE-'.str()->random(5), 'nama' => 'Santri', 'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif']);

        $this->svc()->catatRitel($sesi, $kat, ['harga_kotor' => 30000, 'santri_id' => $s->id]); // poin 3
        $this->svc()->catatRitel($sesi, $kat, ['harga_kotor' => 50000, 'santri_id' => $s->id]); // poin 5

        $this->assertEqualsWithDelta(8, $this->svc()->loyaltyPoin($s->id), 0.01);
        tenancy()->end();
    }

    public function test_struk_data_dan_html(): void
    {
        $this->boot('pe6');
        $kat = $this->kategoriKoperasi();
        $sesi = $this->svc()->bukaSesiProfil($this->profil(10000), ['saldo_awal' => 0]);
        $tx = $this->svc()->catatRitel($sesi, $kat, ['harga_kotor' => 50000, 'diskon' => 5000, 'metode_bayar' => 'qris']);

        $d = $this->svc()->strukData($tx);
        $this->assertEqualsWithDelta(45000, $d['total'], 0.01);
        $this->assertEqualsWithDelta(5000, $d['diskon'], 0.01);
        $this->assertSame('qris', $d['metode']);

        $html = $this->svc()->strukHtml($tx);
        $this->assertStringContainsString('TOTAL', $html);
        $this->assertStringContainsString('45.000', $html);
        tenancy()->end();
    }

    public function test_tutup_sesi_tunai_tak_anggap_qris_sebagai_selisih(): void
    {
        $this->boot('pe7');
        $kat = $this->kategoriKoperasi();
        $sesi = $this->svc()->bukaSesiProfil($this->profil(), ['saldo_awal' => 0]);

        $this->svc()->catatRitel($sesi, $kat, ['harga_kotor' => 50000, 'metode_bayar' => 'tunai']);
        $this->svc()->catatRitel($sesi, $kat, ['harga_kotor' => 30000, 'metode_bayar' => 'qris']);
        // saldo_sistem menggelembung ke 80rb, tapi laci fisik hanya 50rb (tunai).

        // tutupSesiTunai dgn kas fisik 50rb → selisih HARUS 0 (qris tak dianggap kurang).
        $closed = $this->svc()->tutupSesiTunai($sesi, 50000);
        $this->assertEqualsWithDelta(0, (float) $closed->selisih, 0.01);
        $this->assertSame('tutup', $closed->status);
        tenancy()->end();
    }

    public function test_isolasi_tenant_pos_profil(): void
    {
        $this->boot('peA');
        $this->profil();
        $this->assertSame(1, PosProfil::query()->count());
        tenancy()->end();

        $this->boot('peB');
        $this->assertSame(0, PosProfil::query()->count(), 'Profil loket tenant lain tak boleh terlihat.');
        tenancy()->end();
    }
}
