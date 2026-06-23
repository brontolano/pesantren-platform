<?php

namespace Modules\Pengadaan\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Ledger\Services\AnggaranGuardService;
use Modules\Pengadaan\Models\PurchaseOrder;
use RuntimeException;

/**
 * G2-09 — Procurement chain. Supplier → PO → GRN (terima, 3-way qty match) →
 * Belanja (posting Ledger). Anti-tumpang-tindih: approval lewat F1 (trait
 * Approvable), budget lewat AnggaranGuardService, posting lewat BelanjaService.
 */
class PurchaseOrderService
{
    /**
     * Buat PO draft + item. Total dihitung dari item.
     *
     * @param  array<int, array{uraian:string, qty:float, harga_satuan:float}>  $items
     * @param  array{tanggal?:string, divisi_id?:?int, sumber_dana_id?:?int, akun_beban_id?:?int, jenis?:string, keterangan?:?string, dibuat_oleh?:?int}  $meta
     */
    public function buat(int $supplierId, array $items, array $meta = []): PurchaseOrder
    {
        $this->ensureTenantContext();
        if ($items === []) {
            throw new InvalidArgumentException('PO harus punya minimal 1 item.');
        }

        return DB::transaction(function () use ($supplierId, $items, $meta): PurchaseOrder {
            $po = PurchaseOrder::create([
                'nomor' => $this->generateNomor(),
                'supplier_id' => $supplierId,
                'tanggal' => $meta['tanggal'] ?? now()->toDateString(),
                'divisi_id' => $meta['divisi_id'] ?? null,
                'sumber_dana_id' => $meta['sumber_dana_id'] ?? null,
                'akun_beban_id' => $meta['akun_beban_id'] ?? null,
                'jenis' => $meta['jenis'] ?? 'barang',
                'keterangan' => $meta['keterangan'] ?? null,
                'status' => 'draft',
                'total' => 0,
                'dibuat_oleh' => $meta['dibuat_oleh'] ?? auth()->id(),
            ]);

            $total = 0.0;
            foreach ($items as $row) {
                $qty = $this->qtyDari($row);
                $harga = $this->hargaDari($row);
                $subtotal = $this->subtotalItem($qty, $harga);
                $total += $subtotal;
                $po->items()->create([
                    'uraian' => $row['uraian'] ?? 'Item',
                    'qty' => $qty,
                    'harga_satuan' => $harga,
                    'subtotal' => $subtotal,
                    'qty_diterima' => 0,
                ]);
            }
            $po->update(['total' => $this->bulatkanTotal($total)]);

            return $po->fresh('items');
        });
    }

    /**
     * gap-95 — Ganti seluruh item PO yang masih DRAFT lalu hitung ulang total
     * dengan rumus YANG SAMA seperti buat() (tidak ada rumus baru). PO non-draft
     * ditolak agar nilai yang sudah disetujui/diterima tidak berubah.
     *
     * @param  array<int, array{uraian?:string, qty?:mixed, harga_satuan?:mixed}>  $items
     */
    public function ubahItem(PurchaseOrder $po, array $items): PurchaseOrder
    {
        $this->ensureTenantContext();
        if ($po->status !== 'draft') {
            throw new InvalidArgumentException('Item hanya dapat diubah selama PO masih draft.');
        }
        if ($items === []) {
            throw new InvalidArgumentException('PO harus punya minimal 1 item.');
        }

        return DB::transaction(function () use ($po, $items): PurchaseOrder {
            $po->items()->delete();

            $total = 0.0;
            foreach ($items as $row) {
                $qty = $this->qtyDari($row);
                $harga = $this->hargaDari($row);
                $subtotal = $this->subtotalItem($qty, $harga);
                $total += $subtotal;
                $po->items()->create([
                    'uraian' => $row['uraian'] ?? 'Item',
                    'qty' => $qty,
                    'harga_satuan' => $harga,
                    'subtotal' => $subtotal,
                    'qty_diterima' => 0,
                ]);
            }
            $po->update(['total' => $this->bulatkanTotal($total)]);

            return $po->fresh('items');
        });
    }

    private function qtyDari(array $row): float
    {
        return (float) ($row['qty'] ?? 1);
    }

    private function hargaDari(array $row): float
    {
        return (float) ($row['harga_satuan'] ?? 0);
    }

    /** Rumus subtotal per baris (dipakai buat() & ubahItem()). */
    private function subtotalItem(float $qty, float $harga): float
    {
        return round($qty * $harga, 2);
    }

    /** Rumus pembulatan total PO (dipakai buat() & ubahItem()). */
    private function bulatkanTotal(float $total): float
    {
        return round($total, 2);
    }

    /** Ajukan persetujuan (status draft→diajukan + F1; no-op tanpa rule). */
    public function ajukan(PurchaseOrder $po, ?int $userId = null): PurchaseOrder
    {
        $this->ensureTenantContext();
        if ($po->status !== 'draft') {
            throw new InvalidArgumentException('Hanya PO draft yang dapat diajukan.');
        }
        $po->update(['status' => 'diajukan']);
        $po->ajukanPersetujuan($userId);

        return $po->fresh();
    }

    /** Setujui: pagar approval F1 + guard anggaran (hard) → status disetujui. */
    public function setujui(PurchaseOrder $po, ?int $userId = null): PurchaseOrder
    {
        $this->ensureTenantContext();
        if (! in_array($po->status, ['draft', 'diajukan'], true)) {
            throw new InvalidArgumentException('PO tidak dalam status yang dapat disetujui.');
        }

        // F1: lempar bila wajib-approval belum disetujui (no-op tanpa rule).
        $po->pastikanBolehLanjut();

        // Budget RKAM (hard): blokir bila pagu mode 'blokir' terlampaui.
        if ($po->akun_beban_id) {
            app(AnggaranGuardService::class)->assertBoleh(
                (int) $po->akun_beban_id,
                $po->divisi_id ? (int) $po->divisi_id : null,
                $po->sumber_dana_id ? (int) $po->sumber_dana_id : null,
                (string) $po->tanggal->year,
                (float) $po->total,
            );
        }

        $po->update(['status' => 'disetujui', 'disetujui_oleh' => $userId, 'disetujui_pada' => now()]);

        return $po->fresh();
    }

    /**
     * Terima barang (GRN). 3-way match: qty_diterima ≤ qty PO. Saat diterima
     * PENUH → buat Belanja (invoice) yang akan memposting beban ke Ledger.
     *
     * @param  array<int, float>  $penerimaan  [po_item_id => qty_diterima]. Kosong = terima penuh.
     */
    public function terima(PurchaseOrder $po, array $penerimaan = [], ?int $userId = null): PurchaseOrder
    {
        $this->ensureTenantContext();
        if ($po->status !== 'disetujui') {
            throw new InvalidArgumentException('PO harus berstatus disetujui sebelum penerimaan.');
        }

        return DB::transaction(function () use ($po, $penerimaan, $userId): PurchaseOrder {
            foreach ($po->items as $item) {
                $qd = array_key_exists($item->id, $penerimaan) ? (float) $penerimaan[$item->id] : (float) $item->qty;
                if ($qd > (float) $item->qty + 0.0001) {
                    throw new InvalidArgumentException("Qty diterima ({$qd}) melebihi PO ({$item->qty}) untuk item #{$item->id}.");
                }
                $item->update(['qty_diterima' => $qd]);
            }

            $po->load('items');
            if ($po->diterimaPenuh()) {
                $belanja = app(BelanjaService::class)->buat([
                    'uraian' => 'PO '.$po->nomor.' — '.$po->supplier->nama,
                    'jenis' => $po->jenis,
                    'sumber_dana_id' => $po->sumber_dana_id,
                    'divisi_id' => $po->divisi_id,
                    'akun_beban_id' => $po->akun_beban_id,
                    'dpp' => (float) $po->total,
                    'vendor_npwp' => (bool) $po->supplier->is_pkp,
                    'created_by' => $userId,
                    'client_ref' => 'PO:'.$po->id,   // idempotensi: 1 PO → 1 Belanja
                ]);
                $po->update(['status' => 'diterima', 'belanja_id' => $belanja->id]);
            }

            return $po->fresh();
        });
    }

    private function generateNomor(): string
    {
        $prefix = 'PO/'.now()->format('Y/m').'/';
        $seq = PurchaseOrder::query()->where('nomor', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk procurement.');
        }
    }
}
