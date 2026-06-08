<?php

namespace Modules\Ledger\Listeners;

use Modules\Keuangan\Events\TabunganTransaksiDicatat;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Services\JurnalService;
use Throwable;

/**
 * Auto-jurnal tabungan santri:
 *   Setor: D Kas / K Titipan Tabungan Santri (2-3000)
 *   Tarik: D Titipan / K Kas
 * Idempoten via (ref_type=TabunganTransaksi, ref_id).
 */
class PostingTabunganListener
{
    public function __construct(private readonly JurnalService $jurnalService)
    {
    }

    public function handle(TabunganTransaksiDicatat $event): void
    {
        $trx = $event->transaksi;

        $kas = Akun::kasBank()->where('is_postable', true)->orderBy('kode')->first();
        $titipan = Akun::where('kode', '2-3000')->first();
        if ($kas === null || $titipan === null) {
            return; // COA belum siap → jangan paksa.
        }

        $nominal = round((float) $trx->nominal, 2);
        if ($nominal <= 0) {
            return;
        }

        $setor = $trx->tipe === 'setor';
        $kasBaris = ['akun_id' => $kas->id, 'debit' => $setor ? $nominal : 0, 'kredit' => $setor ? 0 : $nominal, 'memo' => 'Kas tabungan'];
        $titipanBaris = ['akun_id' => $titipan->id, 'debit' => $setor ? 0 : $nominal, 'kredit' => $setor ? $nominal : 0, 'memo' => 'Titipan tabungan santri'];

        try {
            $this->jurnalService->postJurnal([
                'tanggal' => $trx->tanggal ?? now(),
                'deskripsi' => 'Tabungan santri (' . $trx->tipe . ') #' . $trx->id,
                'tipe_sumber' => 'tabungan',
                'ref_type' => 'TabunganTransaksi',
                'ref_id' => (string) $trx->id,
                'dibuat_oleh' => $trx->created_by,
                'baris' => [$kasBaris, $titipanBaris],
            ]);
        } catch (Throwable $e) {
            report($e); // jangan patahkan transaksi tabungan.
        }
    }
}
