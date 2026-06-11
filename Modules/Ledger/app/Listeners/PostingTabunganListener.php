<?php

namespace Modules\Ledger\Listeners;

use App\Models\Setting;
use Modules\Keuangan\Events\TabunganTransaksiDicatat;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Services\JurnalService;
use RuntimeException;
use Throwable;

/**
 * Auto-jurnal tabungan santri:
 *   Setor: D Kas / K Titipan Tabungan Santri (2-3000)
 *   Tarik: D Titipan / K Kas
 *   Belanja kantin (2b): D Titipan / K Pendapatan Unit Usaha (4-5000)
 *     — tanpa arus kas (saldo titipan berpindah jadi pendapatan kantin);
 *     divisi unit dari Setting 'kantin.divisi_unit_id' (nullable).
 *   Refund kantin: kebalikan belanja (D Pendapatan / K Titipan).
 * Idempoten via (ref_type=TabunganTransaksi, ref_id) — TIDAK pernah null.
 */
class PostingTabunganListener
{
    public function __construct(private readonly JurnalService $jurnalService) {}

    public function handle(TabunganTransaksiDicatat $event): void
    {
        $trx = $event->transaksi;

        $titipan = Akun::where('kode', '2-3000')->first();
        if ($titipan === null) {
            // COA belum siap → jangan paksa, tapi JANGAN diam: uang nyata
            // bergerak tanpa jejak buku besar = temuan rekonsiliasi.
            report(new RuntimeException("PostingTabungan dilewati: akun 2-3000 tidak ada (trx #{$trx->id}, tipe {$trx->tipe})."));

            return;
        }

        $nominal = round((float) $trx->nominal, 2);
        if ($nominal <= 0) {
            return;
        }

        if (in_array($trx->tipe, ['belanja_kantin', 'refund_kantin'], true)) {
            $pendapatan = Akun::where('kode', '4-5000')->first();
            if ($pendapatan === null) {
                report(new RuntimeException("PostingTabungan kantin dilewati: akun 4-5000 tidak ada (trx #{$trx->id}) — pendapatan kantin tak terbukukan."));

                return;
            }

            $divisiId = Setting::getTenantValue('kantin.divisi_unit_id');
            $divisiId = is_numeric($divisiId) ? (int) $divisiId : null;

            $belanja = $trx->tipe === 'belanja_kantin';
            $kasBaris = ['akun_id' => $titipan->id, 'debit' => $belanja ? $nominal : 0, 'kredit' => $belanja ? 0 : $nominal, 'memo' => 'Titipan tabungan santri', 'divisi_id' => $divisiId];
            $titipanBaris = ['akun_id' => $pendapatan->id, 'debit' => $belanja ? 0 : $nominal, 'kredit' => $belanja ? $nominal : 0, 'memo' => $belanja ? 'Pendapatan kantin' : 'Refund kantin', 'divisi_id' => $divisiId];
        } else {
            $kas = Akun::kasBank()->where('is_postable', true)->orderBy('kode')->first();
            if ($kas === null) {
                return;
            }

            $setor = $trx->tipe === 'setor';
            $kasBaris = ['akun_id' => $kas->id, 'debit' => $setor ? $nominal : 0, 'kredit' => $setor ? 0 : $nominal, 'memo' => 'Kas tabungan'];
            $titipanBaris = ['akun_id' => $titipan->id, 'debit' => $setor ? 0 : $nominal, 'kredit' => $setor ? $nominal : 0, 'memo' => 'Titipan tabungan santri'];
        }

        try {
            $this->jurnalService->postJurnal([
                'tanggal' => $trx->tanggal ?? now(),
                'deskripsi' => 'Tabungan santri ('.$trx->tipe.') #'.$trx->id,
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
