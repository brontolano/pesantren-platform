<?php

namespace Tests\Feature;

use InvalidArgumentException;
use Modules\Keuangan\Models\InventoryItem;
use Modules\Keuangan\Models\InventoryMutasi;
use Modules\Keuangan\Services\InventoryService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Paket C (adaptasi Stock Ledger ERPNext): inventory koperasi/kantin —
 * valuasi average cost, pengeluaran pada HPP berjalan, guard stok, opname.
 */
class InventoryServiceTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'akun', 'inventory_item', 'inventory_mutasi',
        ]);

        $this->truncatePpdbTables(['inventory_mutasi', 'inventory_item', 'domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    private function item(string $kode = 'KOP-001'): InventoryItem
    {
        return InventoryItem::create([
            'kode' => $kode, 'nama' => 'Snack', 'satuan' => 'pcs',
            'harga_jual' => 3000, 'harga_pokok' => 0, 'stok' => 0, 'aktif' => true,
        ]);
    }

    public function test_terima_menghitung_average_cost_bergerak(): void
    {
        $this->boot('inv1');
        $svc = app(InventoryService::class);
        $item = $this->item();

        $svc->terima($item, 10, 1000); // 10 @ 1.000
        $svc->terima($item, 10, 2000); // 10 @ 2.000

        $item->refresh();
        $this->assertEqualsWithDelta(20, (float) $item->stok, 0.001);
        // (10*1000 + 10*2000) / 20 = 1.500
        $this->assertEqualsWithDelta(1500, (float) $item->harga_pokok, 0.01);
        $this->assertEqualsWithDelta(30000, $item->nilai_persediaan, 0.01);

        tenancy()->end();
    }

    public function test_keluar_pada_hpp_berjalan_dan_kurangi_stok(): void
    {
        $this->boot('inv2');
        $svc = app(InventoryService::class);
        $item = $this->item();
        $svc->terima($item, 10, 1000);
        $svc->terima($item, 10, 2000); // hpp = 1.500

        $mutasi = $svc->keluar($item, 5, ['keterangan' => 'Penjualan kantin']);

        $this->assertSame('keluar', $mutasi->jenis);
        $this->assertEqualsWithDelta(1500, (float) $mutasi->harga_satuan, 0.01); // HPP/unit
        $this->assertEqualsWithDelta(15, (float) $item->fresh()->stok, 0.001);
    }

    public function test_keluar_melebihi_stok_ditolak(): void
    {
        $this->boot('inv3');
        $svc = app(InventoryService::class);
        $item = $this->item();
        $svc->terima($item, 3, 1000);

        $this->expectException(InvalidArgumentException::class);
        $svc->keluar($item, 5);
    }

    public function test_penyesuaian_opname_set_stok_dan_catat_selisih(): void
    {
        $this->boot('inv4');
        $svc = app(InventoryService::class);
        $item = $this->item();
        $svc->terima($item, 15, 1000);

        $mutasi = $svc->penyesuaian($item, 12); // opname fisik 12

        $this->assertEqualsWithDelta(12, (float) $item->fresh()->stok, 0.001);
        $this->assertSame('penyesuaian', $mutasi->jenis);
        $this->assertEqualsWithDelta(-3, (float) $mutasi->qty, 0.001);
    }

    public function test_isolasi_tenant_inventory(): void
    {
        $this->boot('invA');
        $this->item('A-1');
        $this->assertSame(1, InventoryItem::query()->count());

        tenancy()->end();

        $this->boot('invB');
        $this->assertSame(0, InventoryItem::query()->count(), 'Item tenant lain tak boleh terlihat.');
    }
}
