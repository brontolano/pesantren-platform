<?php

namespace Modules\Keuangan\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Events\PembayaranDiverifikasi;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tagihan;
use RuntimeException;

/**
 * Bayar tagihan menggunakan saldo tabungan santri (PWA Wali W3).
 *
 * Operasi WAJIB atomik: dibungkus DB::transaction. Mutasi tabungan dan
 * pengakuan pendapatan TIDAK ditulis manual di sini — keduanya lewat jalur
 * kanonik (single business logic → event → Ledger), jadi buku besar SELALU
 * terbukukan:
 *   1. TabunganService::tarik()  → TabunganTransaksiDicatat
 *        → Ledger: D Titipan Tabungan (2-3000) / K Kas.
 *   2. Pembayaran metode 'saldo_tabungan' AUTO-VERIFIED + PembayaranDiverifikasi
 *        → Ledger: D Kas / K Pendapatan SPP (4-1000).
 *   Net dua jurnal: D Titipan Tabungan / K Pendapatan SPP (Kas saling hapus).
 *   3. Status tagihan via PembayaranService::updateStatusTagihan()
 *        (single source of truth — TIDAK set status manual).
 *
 * Kedua event di-dispatch DI DALAM transaksi → jurnal ikut atomik: bila langkah
 * mana pun gagal, penarikan saldo + kedua jurnal ikut rollback. Idempoten via
 * ref (TabunganTransaksi:id, Pembayaran:id) yang tak pernah null.
 */
class BayarDariSaldoService
{
    public function __construct(
        private PembayaranService $pembayaranService = new PembayaranService(),
        private TabunganService $tabunganService = new TabunganService(),
    ) {
    }

    /**
     * Bayar tagihan memakai saldo tabungan. Atomik + lock baris tagihan/tabungan.
     *
     * @param  Santri|int  $santri    Santri pemilik tabungan & tagihan.
     * @param  Tagihan|int $tagihan   Tagihan yang akan dibayar.
     * @param  float       $nominal   Nominal yang dibayar (> 0, <= sisa tagihan, <= saldo).
     * @param  int|null    $createdBy ID user pemicu (wali) untuk audit trail.
     */
    public function bayar(Santri|int $santri, Tagihan|int $tagihan, float $nominal, ?int $createdBy = null): Pembayaran
    {
        $this->ensureTenantContext();

        if ($nominal <= 0) {
            throw new InvalidArgumentException('Nominal harus lebih dari 0.');
        }

        $tagihanId = $tagihan instanceof Tagihan ? $tagihan->id : $tagihan;
        $santriId = $santri instanceof Santri ? $santri->id : $santri;

        return DB::transaction(function () use ($tagihanId, $nominal, $createdBy, $santriId): Pembayaran {
            // 0. LOCK baris tagihan lalu validasi status & sisa DI DALAM transaksi.
            //    Validasi di luar transaksi rawan balapan: dua pembayaran bersamaan
            //    sama-sama lolos cek sisa → total pembayaran MELEBIHI tagihan.
            $tagihan = Tagihan::query()->whereKey($tagihanId)->lockForUpdate()->firstOrFail();

            if (in_array($tagihan->status, ['lunas', 'batal'], true)) {
                throw new InvalidArgumentException('Tagihan tidak bisa dibayar.');
            }

            $sisa = $tagihan->sisa_tagihan;
            if ($nominal > $sisa) {
                throw new InvalidArgumentException("Nominal melebihi sisa tagihan ({$sisa}).");
            }

            // 1. Tarik saldo lewat TabunganService (single source of truth).
            //    Service mengunci baris tabungan + memvalidasi saldo/beku/limit,
            //    mencatat TabunganTransaksi, lalu memancarkan TabunganTransaksiDicatat
            //    → modul Ledger membukukan D Titipan Tabungan (2-3000) / K Kas.
            //    Lock tabungan terjadi SETELAH lock tagihan → urutan konsisten
            //    (Tagihan → tabungan/pembayaran), sama dgn PembayaranService.
            $this->tabunganService->tarik($santriId, $nominal, [
                'keterangan' => "Bayar tagihan #{$tagihan->id} ({$tagihan->jenis_tagihan})",
                'created_by' => $createdBy,
            ]);

            // 2. Buat Pembayaran metode 'saldo_tabungan' AUTO-VERIFIED.
            $pembayaran = new Pembayaran([
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

            // Pagar persetujuan (F1). NO-OP tanpa ApprovalRule wajib 'Pembayaran';
            // throw membatalkan SELURUH transaksi (termasuk penarikan saldo di atas).
            $pembayaran->pastikanBolehLanjut();
            $pembayaran->save();

            // 3. Pancarkan event verifikasi → modul Ledger membukukan
            //    D Kas / K Pendapatan SPP (4-1000). Idempoten via ref Pembayaran:id.
            //    Dispatch DI DALAM transaksi agar jurnal atomik dengan penarikan.
            PembayaranDiverifikasi::dispatch($pembayaran, $createdBy);

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
