<?php

namespace Modules\Payroll\Services;

use Illuminate\Support\Facades\DB;
use Modules\Payroll\Models\KontrakKerja;
use RuntimeException;

/**
 * WP-16 — Operasi kontrak/SK.
 *
 * Perpanjang = INSERT baris baru bertaut parent_id; baris lama hanya berubah
 * status → 'diperpanjang'. Kolom file lama TIDAK disentuh (arsip utuh).
 */
class KontrakService
{
    /**
     * @param  array{nomor:string, mulai:string|\DateTimeInterface, selesai?:string|\DateTimeInterface|null, file?:string|null, periode_khidmat?:string|null, lembaga_pengirim?:string|null}  $data
     */
    public function perpanjang(KontrakKerja $lama, array $data): KontrakKerja
    {
        return DB::connection($lama->getConnectionName())->transaction(function () use ($lama, $data): KontrakKerja {
            // Lock + re-cek DI DALAM transaksi: dua admin memperpanjang
            // bersamaan tidak boleh menghasilkan rantai bercabang.
            $lama = KontrakKerja::query()->whereKey($lama->id)->lockForUpdate()->firstOrFail();

            if ($lama->status !== 'aktif') {
                throw new RuntimeException('Hanya kontrak berstatus aktif yang bisa diperpanjang.');
            }

            $baru = KontrakKerja::create([
                'tenant_id' => $lama->tenant_id,
                'employee_id' => $lama->employee_id,
                'jenis' => $lama->jenis,
                'nomor' => $data['nomor'],
                'mulai' => $data['mulai'],
                'selesai' => $data['selesai'] ?? null,
                'file' => $data['file'] ?? null, // arsip BARU — file lama tetap di baris lama
                'status' => 'aktif',
                'parent_id' => $lama->id,
                'periode_khidmat' => $data['periode_khidmat'] ?? $lama->periode_khidmat,
                'lembaga_pengirim' => $data['lembaga_pengirim'] ?? $lama->lembaga_pengirim,
            ]);

            // HANYA status yang berubah — jangan sentuh file/nomor (arsip utuh).
            $lama->forceFill(['status' => 'diperpanjang'])->save();

            return $baru;
        });
    }

    /** Purna pengabdian: isi tanggal_purna + tandai berakhir. TANPA auto-create penugasan (WP-03 manual). */
    public function purna(KontrakKerja $kontrak, \DateTimeInterface|string $tanggal): KontrakKerja
    {
        $kontrak->forceFill([
            'tanggal_purna' => $tanggal,
            'status' => 'berakhir',
        ])->save();

        return $kontrak;
    }
}
