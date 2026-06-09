<?php

namespace Tests\Feature;

use InvalidArgumentException;
use Modules\Keuangan\Database\Seeders\PosKategoriSeeder;
use Modules\Keuangan\Models\PosKategori;
use Modules\Keuangan\Services\PosKasService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * POS-2/POS-3 — seeder kategori + sesi kasir (rekonsiliasi + state-machine).
 */
class PosKasSesiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'pos_kategori', 'pos_sesi', 'pos_transaksi',
        ]);

        $this->truncatePpdbTables(['pos_transaksi', 'pos_sesi', 'pos_kategori', 'domains', 'tenants']);

        $tenant = $this->createRuntimeTenant('posses', 'Pesantren POS Sesi');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    private function kategori(string $jenis, string $kode = 'UMUM'): PosKategori
    {
        return PosKategori::create([
            'kode' => $kode,
            'nama' => 'Kategori '.$kode,
            'jenis' => $jenis,
            'handler' => 'umum',
            'aktif' => true,
        ]);
    }

    public function test_seeder_kategori_default_idempoten(): void
    {
        $this->seed(PosKategoriSeeder::class);
        $this->seed(PosKategoriSeeder::class);

        $this->assertSame(count(PosKategoriSeeder::DEFAULTS), PosKategori::query()->count());
        $this->assertTrue(PosKategori::query()->where('kode', 'TAB_SETOR')->where('handler', 'tabungan_setor')->exists());
        $this->assertTrue(PosKategori::query()->where('kode', 'KOPERASI')->exists());
    }

    public function test_buka_sesi_berstatus_buka_dengan_saldo_awal(): void
    {
        $sesi = app(PosKasService::class)->bukaSesi(['saldo_awal' => 100000]);

        $this->assertTrue($sesi->isBuka());
        $this->assertEqualsWithDelta(100000, (float) $sesi->saldo_awal, 0.01);
        $this->assertEqualsWithDelta(100000, (float) $sesi->saldo_sistem, 0.01);
    }

    public function test_rekonsiliasi_saldo_sistem(): void
    {
        $svc = app(PosKasService::class);
        $sesi = $svc->bukaSesi(['saldo_awal' => 100000]);

        $masuk = $this->kategori('pemasukan', 'IN');
        $keluar = $this->kategori('pengeluaran', 'OUT');

        $svc->catatManual($sesi, $masuk, 50000);
        $svc->catatManual($sesi, $keluar, 30000);

        // 100.000 + 50.000 − 30.000 = 120.000
        $this->assertEqualsWithDelta(120000, $svc->recomputeSaldoSistem($sesi->fresh()), 0.01);
    }

    public function test_tutup_sesi_menghitung_selisih(): void
    {
        $svc = app(PosKasService::class);
        $sesi = $svc->bukaSesi(['saldo_awal' => 100000]);
        $svc->catatManual($sesi, $this->kategori('pemasukan', 'IN'), 50000);

        // saldo_sistem = 150.000; fisik dihitung 145.000 → selisih −5.000 (kurang).
        $tutup = $svc->tutupSesi($sesi, 145000);

        $this->assertSame('tutup', $tutup->status);
        $this->assertEqualsWithDelta(150000, (float) $tutup->saldo_sistem, 0.01);
        $this->assertEqualsWithDelta(-5000, (float) $tutup->selisih, 0.01);
        $this->assertNotNull($tutup->ditutup_pada);
    }

    public function test_tidak_bisa_catat_pada_sesi_tutup(): void
    {
        $svc = app(PosKasService::class);
        $sesi = $svc->bukaSesi(['saldo_awal' => 0]);
        $svc->tutupSesi($sesi, 0);

        $this->expectException(InvalidArgumentException::class);
        $svc->catatManual($sesi->fresh(), $this->kategori('pemasukan', 'IN'), 10000);
    }

    public function test_tidak_bisa_tutup_dua_kali(): void
    {
        $svc = app(PosKasService::class);
        $sesi = $svc->bukaSesi(['saldo_awal' => 0]);
        $svc->tutupSesi($sesi, 0);

        $this->expectException(InvalidArgumentException::class);
        $svc->tutupSesi($sesi->fresh(), 0);
    }

    public function test_saldo_awal_negatif_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(PosKasService::class)->bukaSesi(['saldo_awal' => -1000]);
    }

    public function test_nominal_transaksi_nol_ditolak(): void
    {
        $svc = app(PosKasService::class);
        $sesi = $svc->bukaSesi(['saldo_awal' => 0]);

        $this->expectException(InvalidArgumentException::class);
        $svc->catatManual($sesi, $this->kategori('pemasukan', 'IN'), 0);
    }
}
