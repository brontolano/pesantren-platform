<?php

namespace Tests\Feature;

use Modules\Keuangan\Models\PosKategori;
use Modules\Keuangan\Models\PosSesi;
use Modules\Keuangan\Models\PosTransaksi;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * POS-1/2 — fondasi: migrasi tabel POS + model + relasi.
 */
class PosSchemaTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'pos_kategori', 'pos_sesi', 'pos_transaksi',
        ]);

        $this->truncatePpdbTables(['pos_transaksi', 'pos_sesi', 'pos_kategori', 'domains', 'tenants']);

        $tenant = $this->createRuntimeTenant('posschema', 'Pesantren POS Schema');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    public function test_tabel_pos_terbentuk_dan_relasi_jalan(): void
    {
        $kategori = PosKategori::create([
            'kode' => 'TAB_SETOR',
            'nama' => 'Setor Tabungan',
            'jenis' => 'pemasukan',
            'handler' => 'tabungan_setor',
            'aktif' => true,
            'sort' => 1,
        ]);

        $sesi = PosSesi::create([
            'pos_jenis' => 'kantor_keuangan',
            'saldo_awal' => 100000,
            'saldo_sistem' => 100000,
            'status' => 'buka',
            'dibuka_pada' => now(),
        ]);

        $tx = PosTransaksi::create([
            'sesi_id' => $sesi->id,
            'kategori_id' => $kategori->id,
            'jenis' => 'pemasukan',
            'nominal' => 50000,
            'keterangan' => 'Setoran uji',
        ]);

        $this->assertTrue($sesi->isBuka());
        $this->assertSame(1, $sesi->transaksi()->count());
        $this->assertSame($kategori->id, $tx->kategori->id);
        $this->assertEqualsWithDelta(50000, (float) $tx->nominal, 0.01);
        $this->assertSame(1, PosKategori::aktif()->count());
    }
}
