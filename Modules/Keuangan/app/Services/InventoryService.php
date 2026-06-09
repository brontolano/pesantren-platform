<?php

namespace Modules\Keuangan\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Keuangan\Models\InventoryItem;
use Modules\Keuangan\Models\InventoryMutasi;
use RuntimeException;

/**
 * Layanan inventory koperasi/kantin (adaptasi Stock Ledger ERPNext).
 *
 * Valuasi: AVERAGE COST bergerak (moving average).
 *   terima(qty, hargaBeli) → harga_pokok = (nilai_lama + qty×hargaBeli) / stok_baru
 *   keluar(qty)            → keluar pada harga_pokok berjalan (HPP = qty × harga_pokok)
 *   penyesuaian(stokFisik) → set stok ke hasil opname (selisih dicatat)
 *
 * Lapis ini menjaga STOK + valuasi + jejak mutasi. Posting Ledger (Persediaan/
 * HPP) ditangani pemanggil (mis. POS) memakai harga_satuan mutasi 'keluar'.
 */
class InventoryService
{
    public function terima(InventoryItem|int $item, float $qty, float $hargaBeli, array $opts = []): InventoryMutasi
    {
        $this->ensureTenantContext();
        $item = $this->resolveItem($item);

        if ($qty <= 0) {
            throw new InvalidArgumentException('Qty penerimaan harus lebih dari 0.');
        }
        if ($hargaBeli < 0) {
            throw new InvalidArgumentException('Harga beli tidak boleh negatif.');
        }

        return DB::transaction(function () use ($item, $qty, $hargaBeli, $opts): InventoryMutasi {
            $stokLama = (float) $item->stok;
            $nilaiLama = $stokLama * (float) $item->harga_pokok;
            $stokBaru = round($stokLama + $qty, 3);
            $hargaPokokBaru = $stokBaru > 0 ? round(($nilaiLama + $qty * $hargaBeli) / $stokBaru, 2) : 0.0;

            $item->update(['stok' => $stokBaru, 'harga_pokok' => $hargaPokokBaru]);

            return $this->catatMutasi($item, 'masuk', $qty, round($hargaBeli, 2), $stokBaru, $opts);
        });
    }

    public function keluar(InventoryItem|int $item, float $qty, array $opts = []): InventoryMutasi
    {
        $this->ensureTenantContext();
        $item = $this->resolveItem($item);

        if ($qty <= 0) {
            throw new InvalidArgumentException('Qty pengeluaran harus lebih dari 0.');
        }
        if ((float) $item->stok < $qty - 0.0005) {
            throw new InvalidArgumentException("Stok {$item->nama} tidak cukup (tersisa {$item->stok}).");
        }

        return DB::transaction(function () use ($item, $qty, $opts): InventoryMutasi {
            $hpp = round((float) $item->harga_pokok, 2);
            $stokBaru = round((float) $item->stok - $qty, 3);

            $item->update(['stok' => $stokBaru]);

            return $this->catatMutasi($item, 'keluar', $qty, $hpp, $stokBaru, $opts);
        });
    }

    public function penyesuaian(InventoryItem|int $item, float $stokFisik, array $opts = []): InventoryMutasi
    {
        $this->ensureTenantContext();
        $item = $this->resolveItem($item);

        if ($stokFisik < 0) {
            throw new InvalidArgumentException('Stok hasil opname tidak boleh negatif.');
        }

        return DB::transaction(function () use ($item, $stokFisik, $opts): InventoryMutasi {
            $stokBaru = round($stokFisik, 3);
            $selisih = round($stokBaru - (float) $item->stok, 3);

            $item->update(['stok' => $stokBaru]);

            return $this->catatMutasi($item, 'penyesuaian', $selisih, (float) $item->harga_pokok, $stokBaru, $opts);
        });
    }

    private function catatMutasi(InventoryItem $item, string $jenis, float $qty, float $hargaSatuan, float $stokSesudah, array $opts): InventoryMutasi
    {
        return InventoryMutasi::create([
            'item_id' => $item->id,
            'jenis' => $jenis,
            'qty' => round($qty, 3),
            'harga_satuan' => round($hargaSatuan, 2),
            'stok_sesudah' => round($stokSesudah, 3),
            'ref_type' => $opts['ref_type'] ?? null,
            'ref_id' => $opts['ref_id'] ?? null,
            'keterangan' => $opts['keterangan'] ?? null,
            'dibuat_oleh' => $opts['dibuat_oleh'] ?? null,
        ]);
    }

    private function resolveItem(InventoryItem|int $item): InventoryItem
    {
        return $item instanceof InventoryItem ? $item : InventoryItem::query()->findOrFail($item);
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk inventory.');
        }
    }
}
