<?php

namespace Modules\Ledger\Listeners;

use Modules\Keuangan\Events\PembayaranDiverifikasi;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\SumberDana;
use Modules\Ledger\Services\JurnalService;
use Throwable;

/**
 * Auto-jurnal saat pembayaran SPP terverifikasi:
 *   Debit  Kas / Bank            (akun is_kas_bank)
 *   Kredit Pendapatan Syahriyah  (akun 4-1000)
 *
 * Idempoten via (ref_type=Pembayaran, ref_id). Bila COA belum di-seed,
 * listener menahan diri (tidak mematahkan alur pembayaran modul Keuangan).
 */
class PostingPembayaranListener
{
    public function __construct(private readonly JurnalService $jurnalService) {}

    public function handle(PembayaranDiverifikasi $event): void
    {
        $pembayaran = $event->pembayaran;

        $akunKas = Akun::kasBank()->where('is_postable', true)->orderBy('kode')->first();
        $akunPendapatan = Akun::where('kode', '4-1000')->first();

        // COA belum siap → jangan paksa; biarkan pembayaran tetap valid.
        if ($akunKas === null || $akunPendapatan === null) {
            return;
        }

        // Dimensi: sumber dana dari tagihan bila ada, default SPP/Swadaya (03).
        $sumberDanaId = $pembayaran->tagihan?->sumber_dana_id
            ?? SumberDana::where('kode_dana', '03')->value('id');
        $divisiId = $pembayaran->tagihan?->divisi_unit_id;

        $nominal = round((float) $pembayaran->nominal, 2);
        if ($nominal <= 0) {
            return;
        }

        try {
            $this->jurnalService->postJurnal([
                'tanggal' => $pembayaran->verified_at ?? $pembayaran->tanggal_pembayaran ?? now(),
                'deskripsi' => 'Pembayaran SPP/syahriyah — bukti #'.$pembayaran->id,
                'tipe_sumber' => 'syahriyah',
                'ref_type' => 'Pembayaran',
                'ref_id' => (string) $pembayaran->id,
                'dibuat_oleh' => $event->verifiedBy,
                'baris' => [
                    [
                        'akun_id' => $akunKas->id,
                        'sumber_dana_id' => $sumberDanaId,
                        'divisi_id' => $divisiId,
                        'debit' => $nominal,
                        'kredit' => 0,
                        'memo' => 'Kas masuk pembayaran SPP',
                    ],
                    [
                        'akun_id' => $akunPendapatan->id,
                        'sumber_dana_id' => $sumberDanaId,
                        'divisi_id' => $divisiId,
                        'debit' => 0,
                        'kredit' => $nominal,
                        'memo' => 'Pendapatan SPP',
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            // Jangan patahkan verifikasi pembayaran karena kegagalan posting ledger.
            report($e);
        }
    }
}
