<?php

namespace Tests\Feature;

use InvalidArgumentException;
use Modules\Keuangan\Models\InventoryBatch;
use Modules\Keuangan\Models\InventoryItem;
use Modules\Keuangan\Models\InventoryLokasi;
use Modules\Keuangan\Services\InventoryLokasiService;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Jurnal;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * G2-14 — Inventory multi-lokasi + batch/lot (FEFO) + opname bernilai.
 * Menguji stok per lokasi, transfer atomik (total tetap), FEFO, dan jurnal opname.
 */
class InventoryLokasiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'akun', 'sumber_dana', 'periode_akuntansi',
            'jurnal', 'jurnal_baris', 'inventory_item', 'inventory_mutasi',
            'inventory_lokasi', 'inventory_stok_lokasi', 'inventory_batch', 'audit_log',
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

    private function item(string $kode = 'KOP-001'): InventoryItem
    {
        return InventoryItem::create([
            'kode' => $kode, 'nama' => 'Snack', 'satuan' => 'pcs',
            'harga_jual' => 3000, 'harga_pokok' => 0, 'stok' => 0, 'aktif' => true,
        ]);
    }

    private function lokasi(string $kode): InventoryLokasi
    {
        return InventoryLokasi::create(['kode' => $kode, 'nama' => 'Lokasi '.$kode, 'tipe' => 'gudang', 'is_active' => true]);
    }

    public function test_terima_di_lokasi_update_total_lokasi_dan_batch(): void
    {
        $this->boot('loc1');
        $svc = app(InventoryLokasiService::class);
        $item = $this->item();
        $g = $this->lokasi('G1');

        $svc->terimaDiLokasi($item, $g->id, 10, 1000, ['nomor_batch' => 'B1', 'tanggal_kedaluwarsa' => '2026-12-31']);

        $item->refresh();
        $this->assertEqualsWithDelta(10, (float) $item->stok, 0.001);          // total
        $this->assertEqualsWithDelta(1000, (float) $item->harga_pokok, 0.01);  // avg cost (reuse)
        $this->assertEqualsWithDelta(10, $item->stokDiLokasi($g->id), 0.001);  // per lokasi
        $this->assertEqualsWithDelta(10, (float) InventoryBatch::where('nomor_batch', 'B1')->value('qty'), 0.001);
        tenancy()->end();
    }

    public function test_transfer_pindah_qty_total_tetap(): void
    {
        $this->boot('loc2');
        $svc = app(InventoryLokasiService::class);
        $item = $this->item();
        $a = $this->lokasi('A');
        $b = $this->lokasi('B');
        $svc->terimaDiLokasi($item, $a->id, 10, 1000);

        $svc->transfer($item, $a->id, $b->id, 4);

        $item->refresh();
        $this->assertEqualsWithDelta(6, $item->stokDiLokasi($a->id), 0.001);
        $this->assertEqualsWithDelta(4, $item->stokDiLokasi($b->id), 0.001);
        $this->assertEqualsWithDelta(10, (float) $item->stok, 0.001); // total TIDAK berubah
        tenancy()->end();
    }

    public function test_transfer_melebihi_stok_lokasi_ditolak(): void
    {
        $this->boot('loc3');
        $svc = app(InventoryLokasiService::class);
        $item = $this->item();
        $a = $this->lokasi('A');
        $b = $this->lokasi('B');
        $svc->terimaDiLokasi($item, $a->id, 3, 1000);

        $this->expectException(InvalidArgumentException::class);
        $svc->transfer($item, $a->id, $b->id, 5);
    }

    public function test_keluar_fefo_ambil_batch_kedaluwarsa_terdekat(): void
    {
        $this->boot('loc4');
        $svc = app(InventoryLokasiService::class);
        $item = $this->item();
        $g = $this->lokasi('G');
        // OLD lebih cepat kedaluwarsa daripada NEW (urutan input dibalik agar FEFO teruji).
        $svc->terimaDiLokasi($item, $g->id, 5, 1000, ['nomor_batch' => 'NEW', 'tanggal_kedaluwarsa' => '2026-12-31']);
        $svc->terimaDiLokasi($item, $g->id, 5, 1000, ['nomor_batch' => 'OLD', 'tanggal_kedaluwarsa' => '2026-06-30']);

        $hasil = $svc->keluarFefo($item, $g->id, 6); // 5 dari OLD + 1 dari NEW

        $this->assertSame('OLD', $hasil['konsumsi'][0]['nomor_batch']);
        $this->assertEqualsWithDelta(5, $hasil['konsumsi'][0]['qty'], 0.001);
        $this->assertEqualsWithDelta(1, $hasil['konsumsi'][1]['qty'], 0.001);
        $this->assertEqualsWithDelta(0, (float) InventoryBatch::where('nomor_batch', 'OLD')->value('qty'), 0.001);
        $this->assertEqualsWithDelta(4, (float) InventoryBatch::where('nomor_batch', 'NEW')->value('qty'), 0.001);
        $this->assertEqualsWithDelta(4, $item->fresh()->stokDiLokasi($g->id), 0.001);
        tenancy()->end();
    }

    public function test_keluar_fefo_melebihi_stok_lokasi_ditolak(): void
    {
        $this->boot('loc5');
        $svc = app(InventoryLokasiService::class);
        $item = $this->item();
        $g = $this->lokasi('G');
        $svc->terimaDiLokasi($item, $g->id, 3, 1000);

        $this->expectException(InvalidArgumentException::class);
        $svc->keluarFefo($item, $g->id, 5);
    }

    public function test_opname_susut_posting_jurnal_seimbang(): void
    {
        $this->boot('loc6');
        $svc = app(InventoryLokasiService::class);
        $item = $this->item();
        $g = $this->lokasi('G');
        $svc->terimaDiLokasi($item, $g->id, 10, 1000); // total 10 @ 1.000, nilai 10.000

        $mutasi = $svc->opnameLokasi($item, $g->id, 8); // susut 2 → nilai 2.000

        $this->assertEqualsWithDelta(-2, (float) $mutasi->qty, 0.001);
        $this->assertEqualsWithDelta(8, $item->fresh()->stokDiLokasi($g->id), 0.001);
        $this->assertEqualsWithDelta(8, (float) $item->fresh()->stok, 0.001);

        // Jurnal D Beban Selisih (5-9000) / K Persediaan (1-1300) = 2.000.
        $jurnal = Jurnal::with('baris')->where('ref_type', 'InventoryOpname')->latest('id')->first();
        $this->assertNotNull($jurnal);
        $this->assertEqualsWithDelta($jurnal->baris->sum('debit'), $jurnal->baris->sum('kredit'), 0.01);
        $this->assertEqualsWithDelta(2000, (float) $jurnal->baris->sum('debit'), 0.01);
        tenancy()->end();
    }

    public function test_opname_lebih_posting_jurnal_seimbang(): void
    {
        $this->boot('loc7');
        $svc = app(InventoryLokasiService::class);
        $item = $this->item();
        $g = $this->lokasi('G');
        $svc->terimaDiLokasi($item, $g->id, 10, 1000);

        $svc->opnameLokasi($item, $g->id, 13); // lebih 3 → nilai 3.000

        $this->assertEqualsWithDelta(13, (float) $item->fresh()->stok, 0.001);
        $jurnal = Jurnal::with('baris')->where('ref_type', 'InventoryOpname')->latest('id')->first();
        $this->assertEqualsWithDelta($jurnal->baris->sum('debit'), $jurnal->baris->sum('kredit'), 0.01);
        $this->assertEqualsWithDelta(3000, (float) $jurnal->baris->sum('kredit'), 0.01);
        tenancy()->end();
    }

    public function test_batch_nomor_sama_exp_beda_tidak_digabung(): void
    {
        $this->boot('loc8');
        $svc = app(InventoryLokasiService::class);
        $item = $this->item();
        $g = $this->lokasi('G');
        // Nomor batch SAMA tapi kedaluwarsa BEDA → harus jadi 2 baris (FEFO sahih).
        $svc->terimaDiLokasi($item, $g->id, 5, 1000, ['nomor_batch' => 'LOT-A', 'tanggal_kedaluwarsa' => '2026-12-31']);
        $svc->terimaDiLokasi($item, $g->id, 5, 1000, ['nomor_batch' => 'LOT-A', 'tanggal_kedaluwarsa' => '2026-06-30']);

        $this->assertSame(2, InventoryBatch::where('nomor_batch', 'LOT-A')->count());

        // FEFO ambil yang kedaluwarsa lebih awal (2026-06-30) dulu.
        $hasil = $svc->keluarFefo($item, $g->id, 5);
        $batchTerpakai = InventoryBatch::find($hasil['konsumsi'][0]['batch_id']);
        $this->assertEquals('2026-06-30', $batchTerpakai->tanggal_kedaluwarsa->format('Y-m-d'));
        tenancy()->end();
    }

    public function test_keluar_fefo_surface_sisa_non_batch(): void
    {
        $this->boot('loc9');
        $svc = app(InventoryLokasiService::class);
        $item = $this->item();
        $g = $this->lokasi('G');
        $svc->terimaDiLokasi($item, $g->id, 3, 1000, ['nomor_batch' => 'B1', 'tanggal_kedaluwarsa' => '2026-09-30']);
        $svc->terimaDiLokasi($item, $g->id, 5, 1000); // tanpa batch → stok lokasi 8, batch hanya 3

        $hasil = $svc->keluarFefo($item, $g->id, 6); // 3 dari B1 + 3 tanpa batch

        $this->assertSame('B1', $hasil['konsumsi'][0]['nomor_batch']);
        $this->assertSame('(tanpa batch)', $hasil['konsumsi'][1]['nomor_batch']);
        $this->assertEqualsWithDelta(3, $hasil['konsumsi'][1]['qty'], 0.001);
        // stok lokasi & total turun penuh & konsisten.
        $this->assertEqualsWithDelta(2, $item->fresh()->stokDiLokasi($g->id), 0.001);
        tenancy()->end();
    }

    public function test_isolasi_tenant_lokasi(): void
    {
        $this->boot('locA');
        $this->lokasi('X');
        $this->assertSame(1, InventoryLokasi::query()->count());
        tenancy()->end();

        $this->boot('locB');
        $this->assertSame(0, InventoryLokasi::query()->count(), 'Lokasi tenant lain tak boleh terlihat.');
        tenancy()->end();
    }
}
