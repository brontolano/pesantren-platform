<?php

namespace Modules\Keuangan\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Keuangan\Models\InventoryBatch;
use Modules\Keuangan\Models\InventoryItem;
use Modules\Keuangan\Models\InventoryLokasi;
use Modules\Keuangan\Models\InventoryMutasi;
use Modules\Keuangan\Models\InventoryStokLokasi;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Services\JurnalService;
use RuntimeException;

/**
 * G2-14 — Inventory multi-lokasi + batch/lot (FEFO) + opname bernilai.
 *
 * Layer KUANTITAS per (item, lokasi) & batch DI ATAS valuasi item-level yang
 * sudah teruji (InventoryService, moving average). Total stok & harga_pokok TETAP
 * di inventory_item (sumber kebenaran valuasi); service ini menjaga rincian lokasi,
 * lot/kedaluwarsa, transfer, dan opname yang DIPOSTING ke Buku Besar.
 *
 *  - terimaDiLokasi : reuse InventoryService::terima (avg cost) + stok lokasi + batch.
 *  - keluarFefo     : ambil batch paling cepat kedaluwarsa dulu + reuse ::keluar (HPP).
 *  - transfer       : pindah qty antar lokasi; total & nilai TIDAK berubah (tanpa jurnal).
 *  - opnameLokasi   : sesuaikan stok lokasi + total; selisih NILAI diposting ke Ledger.
 */
class InventoryLokasiService
{
    private const EPS = 0.0005;

    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly JurnalService $jurnalService,
    ) {
    }

    /** Penerimaan ke satu lokasi (+ batch opsional). Valuasi via InventoryService. */
    public function terimaDiLokasi(InventoryItem|int $item, int $lokasiId, float $qty, float $hargaBeli, array $opts = []): InventoryMutasi
    {
        $this->ensureTenantContext();
        $item = $this->resolveItem($item);
        $this->assertLokasiAktif($lokasiId);

        return DB::transaction(function () use ($item, $lokasiId, $qty, $hargaBeli, $opts): InventoryMutasi {
            $item = $this->itemTerkunci($item->id); // serialisasi mutasi item (anti lost-update)

            // Valuasi item-level (guard qty/harga di dalam) — sumber kebenaran nilai.
            $mutasi = $this->inventoryService->terima($item, $qty, $hargaBeli, $opts);

            $row = $this->stokRowTerkunci($item->id, $lokasiId);
            $row->update(['stok' => round((float) $row->stok + $qty, 3)]);

            $batchId = null;
            if (! empty($opts['nomor_batch'])) {
                // Kunci batch MENCAKUP tanggal_kedaluwarsa → lot dengan kedaluwarsa
                // berbeda TIDAK digabung; tanggal historis TIDAK ditimpa (FEFO sahih).
                $batch = InventoryBatch::firstOrCreate(
                    [
                        'item_id' => $item->id,
                        'lokasi_id' => $lokasiId,
                        'nomor_batch' => trim((string) $opts['nomor_batch']),
                        'tanggal_kedaluwarsa' => $opts['tanggal_kedaluwarsa'] ?? null,
                    ],
                    ['qty' => 0, 'is_active' => true],
                );
                $batch->update(['qty' => round((float) $batch->qty + $qty, 3)]);
                $batchId = $batch->id;
            }

            $mutasi->update(['lokasi_id' => $lokasiId, 'batch_id' => $batchId]);

            return $mutasi->fresh();
        });
    }

    /**
     * Pengeluaran dari lokasi dengan FEFO (first-expired-first-out).
     *
     * @return array{mutasi: InventoryMutasi, konsumsi: array<int, array{batch_id:int, nomor_batch:string, qty:float}>}
     */
    public function keluarFefo(InventoryItem|int $item, int $lokasiId, float $qty, array $opts = []): array
    {
        $this->ensureTenantContext();
        $item = $this->resolveItem($item);
        $this->assertLokasiAktif($lokasiId);
        if ($qty <= 0) {
            throw new InvalidArgumentException('Qty pengeluaran harus lebih dari 0.');
        }

        return DB::transaction(function () use ($item, $lokasiId, $qty, $opts): array {
            $item = $this->itemTerkunci($item->id);
            $row = $this->stokRowTerkunci($item->id, $lokasiId);
            if ((float) $row->stok < $qty - self::EPS) {
                throw new InvalidArgumentException("Stok {$item->nama} di lokasi tidak cukup (tersisa {$row->stok}).");
            }

            // FEFO: konsumsi batch dengan kedaluwarsa paling awal lebih dulu (NULL terakhir).
            $konsumsi = [];
            $sisa = $qty;
            $batches = InventoryBatch::where('item_id', $item->id)
                ->where('lokasi_id', $lokasiId)
                ->where('is_active', true)
                ->where('qty', '>', 0)
                ->orderByRaw('tanggal_kedaluwarsa IS NULL')
                ->orderBy('tanggal_kedaluwarsa')
                ->lockForUpdate()
                ->get();
            foreach ($batches as $batch) {
                if ($sisa <= self::EPS) {
                    break;
                }
                $ambil = min((float) $batch->qty, $sisa);
                $batch->update(['qty' => round((float) $batch->qty - $ambil, 3)]);
                $sisa = round($sisa - $ambil, 3);
                $konsumsi[] = ['batch_id' => $batch->id, 'nomor_batch' => $batch->nomor_batch, 'qty' => round($ambil, 3)];
            }
            // Sisa yang tak tertutup batch (stok lokasi dari penerimaan tanpa lot) —
            // disurfacekan JUJUR, bukan diklaim sebagai konsumsi FEFO penuh.
            if (round($sisa, 3) > self::EPS) {
                $konsumsi[] = ['batch_id' => null, 'nomor_batch' => '(tanpa batch)', 'qty' => round($sisa, 3)];
            }

            // Valuasi & total stok via InventoryService (HPP rata-rata berjalan).
            $mutasi = $this->inventoryService->keluar($item, $qty, $opts);
            $row->update(['stok' => round((float) $row->stok - $qty, 3)]);
            $mutasi->update(['lokasi_id' => $lokasiId, 'batch_id' => $konsumsi[0]['batch_id'] ?? null]);

            return ['mutasi' => $mutasi->fresh(), 'konsumsi' => $konsumsi];
        });
    }

    /** Transfer qty antar lokasi. Total & nilai persediaan TIDAK berubah → tanpa jurnal. */
    public function transfer(InventoryItem|int $item, int $dariLokasiId, int $keLokasiId, float $qty, array $opts = []): array
    {
        $this->ensureTenantContext();
        $item = $this->resolveItem($item);
        if ($dariLokasiId === $keLokasiId) {
            throw new InvalidArgumentException('Lokasi asal dan tujuan tidak boleh sama.');
        }
        if ($qty <= 0) {
            throw new InvalidArgumentException('Qty transfer harus lebih dari 0.');
        }
        $this->assertLokasiAktif($keLokasiId);

        return DB::transaction(function () use ($item, $dariLokasiId, $keLokasiId, $qty, $opts): array {
            $dari = $this->stokRowTerkunci($item->id, $dariLokasiId);
            if ((float) $dari->stok < $qty - self::EPS) {
                throw new InvalidArgumentException("Stok di lokasi asal tidak cukup (tersisa {$dari->stok}).");
            }
            $ke = $this->stokRowTerkunci($item->id, $keLokasiId);

            $dari->update(['stok' => round((float) $dari->stok - $qty, 3)]);
            $ke->update(['stok' => round((float) $ke->stok + $qty, 3)]);

            $hpp = round((float) $item->harga_pokok, 2);
            $keluar = $this->catat($item, 'transfer_keluar', $qty, $hpp, (float) $dari->fresh()->stok, $dariLokasiId, $opts);
            $masuk = $this->catat($item, 'transfer_masuk', $qty, $hpp, (float) $ke->fresh()->stok, $keLokasiId, $opts);

            return ['keluar' => $keluar, 'masuk' => $masuk];
        });
    }

    /**
     * Opname (stok fisik) per lokasi → sesuaikan stok lokasi & total item, lalu
     * POSTING selisih NILAI ke Buku Besar (D/K Persediaan vs Beban Selisih 5-9000).
     */
    public function opnameLokasi(InventoryItem|int $item, int $lokasiId, float $stokFisik, array $opts = []): InventoryMutasi
    {
        $this->ensureTenantContext();
        $item = $this->resolveItem($item);
        $this->assertLokasiAktif($lokasiId);
        if ($stokFisik < 0) {
            throw new InvalidArgumentException('Stok hasil opname tidak boleh negatif.');
        }

        return DB::transaction(function () use ($item, $lokasiId, $stokFisik, $opts): InventoryMutasi {
            $item = $this->itemTerkunci($item->id);
            $row = $this->stokRowTerkunci($item->id, $lokasiId);
            $selisih = round($stokFisik - (float) $row->stok, 3);

            $row->update(['stok' => round($stokFisik, 3)]);
            $item->update(['stok' => round((float) $item->stok + $selisih, 3)]);

            $mutasi = $this->catat($item, 'penyesuaian', $selisih, (float) $item->harga_pokok, $stokFisik, $lokasiId, $opts);

            // Posting selisih nilai bila ada perubahan nilai.
            $nilai = round(abs($selisih) * (float) $item->harga_pokok, 2);
            if ($nilai > 0.001) {
                $persediaan = $item->akunPersediaan ?? Akun::where('kode', '1-1300')->first();
                $selisihAkun = Akun::where('kode', '5-9000')->first() ?? $item->akunHpp;
                if ($persediaan === null || $selisihAkun === null) {
                    throw new RuntimeException('Akun persediaan (1-1300) / selisih (5-9000) belum di-seed.');
                }

                $baris = $selisih < 0
                    // Susut: beban naik (debit), persediaan turun (kredit).
                    ? [
                        ['akun_id' => $selisihAkun->id, 'debit' => $nilai, 'kredit' => 0, 'memo' => 'Selisih kurang persediaan'],
                        ['akun_id' => $persediaan->id, 'debit' => 0, 'kredit' => $nilai, 'memo' => 'Penyesuaian persediaan'],
                    ]
                    // Lebih: persediaan naik (debit), beban berkurang (kredit).
                    : [
                        ['akun_id' => $persediaan->id, 'debit' => $nilai, 'kredit' => 0, 'memo' => 'Penyesuaian persediaan'],
                        ['akun_id' => $selisihAkun->id, 'debit' => 0, 'kredit' => $nilai, 'memo' => 'Selisih lebih persediaan'],
                    ];

                $this->jurnalService->postJurnal([
                    'tanggal' => $opts['tanggal'] ?? Carbon::now(),
                    'deskripsi' => 'Opname persediaan — ' . $item->nama,
                    'tipe_sumber' => 'inventory_opname',
                    'ref_type' => 'InventoryOpname',
                    'ref_id' => (string) $mutasi->id,
                    'dibuat_oleh' => $opts['dibuat_oleh'] ?? null,
                    'baris' => $baris,
                ]);
            }

            return $mutasi->fresh();
        });
    }

    /** Kunci baris inventory_item (serialisasi mutasi total/HPP lintas lokasi). */
    private function itemTerkunci(int $itemId): InventoryItem
    {
        return InventoryItem::whereKey($itemId)->lockForUpdate()->firstOrFail();
    }

    /** Ambil/buat baris stok lokasi dengan penguncian baris (anti-race). */
    private function stokRowTerkunci(int $itemId, int $lokasiId): InventoryStokLokasi
    {
        $row = InventoryStokLokasi::where('item_id', $itemId)
            ->where('lokasi_id', $lokasiId)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            $row = InventoryStokLokasi::create(['item_id' => $itemId, 'lokasi_id' => $lokasiId, 'stok' => 0]);
        }

        return $row;
    }

    private function catat(InventoryItem $item, string $jenis, float $qty, float $hargaSatuan, float $stokSesudah, ?int $lokasiId, array $opts): InventoryMutasi
    {
        return InventoryMutasi::create([
            'item_id' => $item->id,
            'lokasi_id' => $lokasiId,
            'jenis' => $jenis,
            'qty' => round($qty, 3),
            'harga_satuan' => round($hargaSatuan, 2),
            'stok_sesudah' => round($stokSesudah, 3), // untuk baris lokasi: stok lokasi sesudah
            'ref_type' => $opts['ref_type'] ?? null,
            'ref_id' => $opts['ref_id'] ?? null,
            'keterangan' => $opts['keterangan'] ?? null,
            'dibuat_oleh' => $opts['dibuat_oleh'] ?? null,
        ]);
    }

    private function assertLokasiAktif(int $lokasiId): void
    {
        $lokasi = InventoryLokasi::find($lokasiId);
        if ($lokasi === null || ! $lokasi->is_active) {
            throw new InvalidArgumentException('Lokasi inventory tidak ditemukan atau nonaktif.');
        }
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
