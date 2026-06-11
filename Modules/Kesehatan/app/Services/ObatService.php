<?php

namespace Modules\Kesehatan\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Kesehatan\Models\Obat;
use Modules\Kesehatan\Models\ObatMutasi;

/**
 * WP-28 — Mutasi stok obat UKS.
 *
 * Semua perubahan kolom obat.stok WAJIB lewat service ini:
 * - keluar() pakai lockForUpdate dalam transaksi → stok TAK BISA minus
 *   (dua pengeluaran paralel tidak bisa sama-sama lolos cek stok).
 * - Setiap mutasi tercatat append-only di obat_mutasi (jejak audit).
 */
class ObatService
{
    public function masuk(Obat $obat, int $jumlah, array $opts = []): ObatMutasi
    {
        if ($jumlah <= 0) {
            throw new InvalidArgumentException('Jumlah stok masuk harus lebih dari 0.');
        }

        return DB::transaction(function () use ($obat, $jumlah, $opts): ObatMutasi {
            $locked = Obat::query()->lockForUpdate()->findOrFail($obat->getKey());
            $locked->update(['stok' => $locked->stok + $jumlah]);

            return $this->catatMutasi($locked, 'masuk', $jumlah, $opts);
        });
    }

    /**
     * Keluarkan stok — TOLAK bila stok tidak cukup (tak bisa minus).
     *
     * @param  Model|null  $ref  rujukan asal pengeluaran (mis. KunjunganUks)
     *
     * @throws InvalidArgumentException
     */
    public function keluar(Obat $obat, int $jumlah, ?Model $ref = null, array $opts = []): ObatMutasi
    {
        if ($jumlah <= 0) {
            throw new InvalidArgumentException('Jumlah stok keluar harus lebih dari 0.');
        }

        return DB::transaction(function () use ($obat, $jumlah, $ref, $opts): ObatMutasi {
            $locked = Obat::query()->lockForUpdate()->findOrFail($obat->getKey());

            if ($locked->stok < $jumlah) {
                throw new InvalidArgumentException(
                    "Stok {$locked->nama} tidak cukup (sisa {$locked->stok} {$locked->satuan}, diminta {$jumlah})."
                );
            }

            $locked->update(['stok' => $locked->stok - $jumlah]);

            if ($ref !== null) {
                $opts['ref_type'] = $ref->getMorphClass();
                $opts['ref_id'] = $ref->getKey();
            }

            return $this->catatMutasi($locked, 'keluar', $jumlah, $opts);
        });
    }

    private function catatMutasi(Obat $obat, string $jenis, int $jumlah, array $opts): ObatMutasi
    {
        return ObatMutasi::create([
            'tenant_id' => $obat->tenant_id,
            'obat_id' => $obat->getKey(),
            'jenis' => $jenis,
            'jumlah' => $jumlah,
            'ref_type' => $opts['ref_type'] ?? null,
            'ref_id' => $opts['ref_id'] ?? null,
            'keterangan' => $opts['keterangan'] ?? null,
            'user_id' => $opts['user_id'] ?? null,
        ]);
    }
}
