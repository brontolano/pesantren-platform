<?php

namespace Modules\Keuangan\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tabungan;
use Modules\Keuangan\Models\TabunganTransaksi;
use Modules\Keuangan\Models\Tagihan;
use RuntimeException;

/**
 * Bayar tagihan menggunakan saldo tabungan santri (Demo PWA Wali W3).
 *
 * Operasi WAJIB atomik: dibungkus DB::transaction dan baris tabungan
 * dikunci dengan lockForUpdate sebelum saldo dibaca/dimodifikasi.
 *
 * Boleh bayar sebagian sesuai saldo yang tersedia (selama <= sisa tagihan).
 * Membuat:
 *   1. TabunganTransaksi tipe 'tarik' (mutasi keluar) + update saldo tabungan.
 *   2. Pembayaran metode 'saldo_tabungan' yang AUTO-VERIFIED.
 *   3. Update status tagihan via PembayaranService::updateStatusTagihan()
 *      (single source of truth — TIDAK set status secara manual).
 */
class BayarDariSaldoService
{
    public function __construct(
        private PembayaranService $pembayaranService = new PembayaranService()
    ) {
    }

    /**
     * Bayar tagihan memakai saldo tabungan. Atomik + lock baris tabungan.
     *
     * @param  Santri|int  $santri   Santri pemilik tabungan & tagihan.
     * @param  Tagihan|int $tagihan  Tagihan yang akan dibayar.
     * @param  float       $nominal  Nominal yang dibayar (> 0, <= sisa tagihan, <= saldo).
     * @param  int|null    $createdBy ID user pemicu (wali) untuk audit trail.
     */
    public function bayar(Santri|int $santri, Tagihan|int $tagihan, float $nominal, ?int $createdBy = null): Pembayaran
    {
        $this->ensureTenantContext();

        if ($nominal <= 0) {
            throw new InvalidArgumentException('Nominal harus lebih dari 0.');
        }

        $tagihan = $tagihan instanceof Tagihan
            ? $tagihan
            : Tagihan::query()->findOrFail($tagihan);

        if (in_array($tagihan->status, ['lunas', 'batal'], true)) {
            throw new InvalidArgumentException('Tagihan tidak bisa dibayar.');
        }

        $sisa = $tagihan->sisa_tagihan;
        if ($nominal > $sisa) {
            throw new InvalidArgumentException("Nominal melebihi sisa tagihan ({$sisa}).");
        }

        $santriId = $santri instanceof Santri ? $santri->id : $santri;

        return DB::transaction(function () use ($tagihan, $nominal, $createdBy, $santriId): Pembayaran {
            // 1. LOCK baris tabungan (lockForUpdate) sebelum baca saldo.
            $tabungan = Tabungan::query()
                ->where('santri_id', $santriId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($tabungan->is_frozen) {
                throw new InvalidArgumentException('Tabungan dibekukan.');
            }

            $saldoSebelum = (float) $tabungan->saldo;
            if ($saldoSebelum < $nominal) {
                throw new InvalidArgumentException('Saldo tidak cukup.');
            }

            $saldoSesudah = $saldoSebelum - $nominal;

            // 2. Catat mutasi keluar (tipe 'tarik') + update saldo.
            TabunganTransaksi::create([
                'tabungan_id' => $tabungan->id,
                'tipe' => 'tarik',
                'nominal' => $nominal,
                'saldo_sebelum' => $saldoSebelum,
                'saldo_sesudah' => $saldoSesudah,
                'keterangan' => "Bayar tagihan #{$tagihan->id} ({$tagihan->jenis_tagihan})",
                'tanggal' => now()->toDateString(),
                'created_by' => $createdBy,
            ]);

            $tabungan->update(['saldo' => $saldoSesudah]);

            // 3. Buat Pembayaran metode 'saldo_tabungan' AUTO-VERIFIED.
            $pembayaran = Pembayaran::create([
                'tagihan_id' => $tagihan->id,
                'santri_id' => $santriId,
                'nominal' => $nominal,
                'metode' => 'saldo_tabungan',
                'tanggal_pembayaran' => now()->toDateString(),
                'catatan' => 'Pembayaran otomatis dari saldo tabungan',
                'is_verified' => true,
                'verified_by' => $createdBy,
                'verified_at' => now(),
                'created_by' => $createdBy,
            ]);

            // 4. Recalculate status tagihan (single source of truth).
            $this->pembayaranService->updateStatusTagihan($tagihan->fresh());

            return $pembayaran;
        });
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
