<?php

namespace Modules\Ledger\Services;

use Modules\Keuangan\Events\PembayaranDiverifikasi;
use Modules\Keuangan\Events\TabunganTransaksiDicatat;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\TabunganTransaksi;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Jurnal;
use RuntimeException;

/**
 * Rekonsiliasi Ledger (Blueprint Arsitektur §16 — "utang prioritas").
 *
 * Listener posting jurnal sengaja FAIL-SILENT (pembayaran tidak boleh gagal
 * gara-gara akuntansi). Akibatnya buku besar bisa "drift": ada transaksi sumber
 * terverifikasi TANPA jurnal pasangannya — mis. COA belum siap saat posting, atau
 * jalur lama yang melewati event (kasus BayarDariSaldo). Service ini mendeteksi
 * sumber tanpa jurnal (via ref_type/ref_id) dan, bila diminta, RE-POST idempoten
 * dengan memancarkan ulang event aslinya — listener dedup lewat unique
 * (ref_type, ref_id), jadi re-post aman diulang.
 *
 * Membaca data tenant aktif — WAJIB dalam konteks tenant (dipanggil $tenant->run).
 */
class LedgerReconciliationService
{
    /**
     * @return array{
     *   skip?:string, pembayaran_hilang:int, tabungan_hilang:int,
     *   reposted:int, butuh_manual:int, contoh:list<array{ref_type:string,ref_id:int}>
     * }
     */
    public function auditJurnalHilang(bool $repost = false): array
    {
        $this->ensureTenantContext();

        $hasil = [
            'pembayaran_hilang' => 0,
            'tabungan_hilang' => 0,
            'reposted' => 0,
            'butuh_manual' => 0,
            'contoh' => [],
        ];

        // COA belum di-seed → posting memang mustahil; jangan laporkan sebagai
        // "hilang" (akan banjir false-positive). Modul Ledger non-aktif = no-op.
        if (Akun::query()->doesntExist()) {
            return ['skip' => 'COA belum di-seed (modul Ledger non-aktif?)'] + $hasil;
        }

        /** Himpunan ref_id yang SUDAH ber-jurnal untuk satu ref_type (set utk lookup O(1)). */
        $refTerposting = static fn (string $type): array => array_flip(
            Jurnal::query()->where('ref_type', $type)->pluck('ref_id')
                ->map(static fn ($r): int => (int) $r)->all()
        );

        // 1) Setiap Pembayaran terverifikasi WAJIB punya jurnal (D Kas / K Pendapatan).
        $adaPembayaran = $refTerposting('Pembayaran');
        Pembayaran::query()->where('is_verified', true)
            ->chunkById(500, function ($rows) use (&$hasil, $adaPembayaran, $repost): void {
                foreach ($rows as $pembayaran) {
                    if (isset($adaPembayaran[(int) $pembayaran->id])) {
                        continue;
                    }
                    $hasil['pembayaran_hilang']++;
                    $this->tambahContoh($hasil, 'Pembayaran', (int) $pembayaran->id);

                    if (! $repost) {
                        continue;
                    }
                    // Event butuh verifikator (int). Pembayaran terverifikasi tanpa
                    // verified_by = anomali tersendiri → tandai manual, jangan paksa.
                    if ($pembayaran->verified_by !== null) {
                        PembayaranDiverifikasi::dispatch($pembayaran, (int) $pembayaran->verified_by);
                        $hasil['reposted']++;
                    } else {
                        $hasil['butuh_manual']++;
                    }
                }
            });

        // 2) Setiap TabunganTransaksi WAJIB punya jurnal (mutasi titipan/kas).
        $adaTabungan = $refTerposting('TabunganTransaksi');
        TabunganTransaksi::query()
            ->chunkById(500, function ($rows) use (&$hasil, $adaTabungan, $repost): void {
                foreach ($rows as $trx) {
                    if (isset($adaTabungan[(int) $trx->id])) {
                        continue;
                    }
                    $hasil['tabungan_hilang']++;
                    $this->tambahContoh($hasil, 'TabunganTransaksi', (int) $trx->id);

                    if ($repost) {
                        TabunganTransaksiDicatat::dispatch($trx);
                        $hasil['reposted']++;
                    }
                }
            });

        return $hasil;
    }

    private function tambahContoh(array &$hasil, string $type, int $id): void
    {
        if (count($hasil['contoh']) < 10) {
            $hasil['contoh'][] = ['ref_type' => $type, 'ref_id' => $id];
        }
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
