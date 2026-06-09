<?php

namespace Tests\Feature;

use App\Models\User;
use Modules\Core\Exceptions\PersetujuanBelumLengkapException;
use Modules\Core\Models\ApprovalRule;
use Modules\Core\Services\ApprovalService;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Exceptions\AnggaranTerlampauiException;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Anggaran;
use Modules\Pengadaan\Models\Belanja;
use Modules\Pengadaan\Models\PurchaseOrder;
use Modules\Pengadaan\Models\Supplier;
use Modules\Pengadaan\Services\PurchaseOrderService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * G2-09 — Procurement chain. Menguji buat PO (total), setujui (gate F1 + guard
 * anggaran), terima/GRN (3-way qty match + auto-Belanja), parsial, isolasi.
 */
class PurchaseOrderTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'akun', 'jurnal', 'jurnal_baris', 'sumber_dana',
            'divisi_unit', 'periode_akuntansi', 'belanja', 'anggaran', 'tarif_pajak',
            'approval_rule', 'approval_request', 'supplier', 'purchase_order', 'purchase_order_item',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new LedgerSeeder)->run();
        $this->userId = (int) User::withoutGlobalScopes()->create([
            'name' => 'Admin', 'email' => 'a'.str()->random(5).'@'.tenant('id').'.localhost',
            'password' => 'password', 'tenant_id' => tenant('id'),
        ])->id;
    }

    private function supplier(): Supplier
    {
        return Supplier::create(['nama' => 'CV Mitra', 'is_pkp' => true, 'kehalalan' => 'halal', 'aktif' => true]);
    }

    private function poDua(array $meta = []): PurchaseOrder
    {
        return app(PurchaseOrderService::class)->buat($this->supplier()->id, [
            ['uraian' => 'Laptop', 'qty' => 2, 'harga_satuan' => 5000000],   // 10jt
            ['uraian' => 'Mouse', 'qty' => 10, 'harga_satuan' => 100000],    // 1jt
        ], $meta);
    }

    public function test_buat_po_hitung_total(): void
    {
        $this->boot('po1');
        $po = $this->poDua();
        $this->assertSame('draft', $po->status);
        $this->assertCount(2, $po->items);
        $this->assertEqualsWithDelta(11000000, (float) $po->total, 0.01);
        tenancy()->end();
    }

    public function test_setujui_tanpa_rule_dan_tanpa_anggaran_lolos(): void
    {
        $this->boot('po2');
        $po = app(PurchaseOrderService::class)->setujui($this->poDua(), $this->userId);
        $this->assertSame('disetujui', $po->status);
        $this->assertNotNull($po->disetujui_pada);
        tenancy()->end();
    }

    public function test_setujui_terblokir_anggaran(): void
    {
        $this->boot('po3');
        $beban = (int) Akun::where('kode', '5-1000')->value('id');
        Anggaran::create(['tahun_anggaran' => (string) now()->year, 'akun_id' => $beban,
            'nominal_anggaran' => 1000000, 'mode' => 'blokir']); // pagu 1jt < PO 11jt
        $po = $this->poDua(['akun_beban_id' => $beban]);

        $this->expectException(AnggaranTerlampauiException::class);
        app(PurchaseOrderService::class)->setujui($po, $this->userId);
        tenancy()->end();
    }

    public function test_setujui_dengan_rule_wajib_terblokir_lalu_lolos(): void
    {
        $this->boot('po4');
        ApprovalRule::create(['approvable_type' => PurchaseOrder::class, 'nama' => 'PO besar',
            'field' => 'total', 'operator' => '>=', 'nilai' => 5000000, 'approver_role' => 'bendahara',
            'mode' => 'wajib', 'urutan' => 1, 'aktif' => true]);
        $po = $this->poDua();
        $svc = app(PurchaseOrderService::class);

        try {
            $svc->setujui($po, $this->userId);
            $this->fail('PO besar harus terblokir approval.');
        } catch (PersetujuanBelumLengkapException) {
            $this->assertSame('draft', $po->fresh()->status);
        }

        $req = $po->fresh()->ajukanPersetujuan($this->userId);
        app(ApprovalService::class)->setujui($req, $this->userId);
        $po = $svc->setujui($po->fresh(), $this->userId);
        $this->assertSame('disetujui', $po->status);
        tenancy()->end();
    }

    public function test_terima_penuh_buat_belanja(): void
    {
        $this->boot('po5');
        $beban = (int) Akun::where('kode', '5-1000')->value('id');
        $po = app(PurchaseOrderService::class)->setujui($this->poDua(['akun_beban_id' => $beban]), $this->userId);

        $po = app(PurchaseOrderService::class)->terima($po, [], $this->userId); // terima penuh
        $this->assertSame('diterima', $po->status);
        $this->assertNotNull($po->belanja_id);
        $belanja = Belanja::find($po->belanja_id);
        $this->assertEqualsWithDelta(11000000, (float) $belanja->dpp, 0.01);
        tenancy()->end();
    }

    public function test_terima_qty_melebihi_po_ditolak(): void
    {
        $this->boot('po6');
        $po = app(PurchaseOrderService::class)->setujui($this->poDua(), $this->userId);
        $item = $po->items->first();

        $this->expectException(\InvalidArgumentException::class);
        app(PurchaseOrderService::class)->terima($po, [$item->id => 999], $this->userId);
        tenancy()->end();
    }

    public function test_terima_sebagian_belum_buat_belanja(): void
    {
        $this->boot('po7');
        $po = app(PurchaseOrderService::class)->setujui($this->poDua(), $this->userId);
        $items = $po->items;
        // Hanya item pertama diterima sebagian.
        $po = app(PurchaseOrderService::class)->terima($po, [$items[0]->id => 1, $items[1]->id => 0], $this->userId);
        $this->assertSame('disetujui', $po->status); // belum penuh
        $this->assertNull($po->belanja_id);
        tenancy()->end();
    }

    public function test_isolasi_tenant(): void
    {
        $this->boot('poA');
        $this->poDua();
        $this->assertSame(1, PurchaseOrder::query()->count());
        tenancy()->end();

        $this->boot('poB');
        $this->assertSame(0, PurchaseOrder::query()->count());
        tenancy()->end();
    }
}
