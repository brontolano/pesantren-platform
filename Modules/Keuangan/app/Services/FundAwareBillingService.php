<?php

namespace Modules\Keuangan\Services;

use Modules\Keuangan\Models\Tagihan;
use Modules\Ledger\Models\SumberDana;
use Modules\Ledger\Services\KehalalanService;
use RuntimeException;

/**
 * G2-15 — Fund-aware Billing. Aturan penagihan sadar-dana:
 *  - Dana NEGARA (BOS/BOP) = ditanggung institusi → TIDAK ditagihkan ke wali.
 *  - Ringkasan piutang per klasifikasi kehalalan (halal/syubhat/non_halal).
 *
 * Anti-tumpang-tindih: alokasi pembayaran (LIVE) tetap; ini sisi penagihan.
 * Amortisasi penuh SPP dibayar-di-muka (deferred) menyusul; versi ini menandai
 * `is_dibayar_dimuka` saja.
 */
class FundAwareBillingService
{
    /** Apakah biaya dari sumber dana ini boleh dibebankan ke wali? (false utk dana negara) */
    public function bolehDibebankanKeWali(?int $sumberDanaId): bool
    {
        if ($sumberDanaId === null) {
            return true;
        }

        return SumberDana::query()->whereKey($sumberDanaId)->value('kategori') !== 'negara';
    }

    /**
     * Ringkasan piutang (tagihan belum lunas) per klasifikasi kehalalan sumber dana.
     *
     * @return array{halal:float, syubhat:float, non_halal:float, total:float}
     */
    public function ringkasanPiutangPerKehalalan(): array
    {
        $this->ensureTenantContext();

        $kh = app(KehalalanService::class);
        $out = ['halal' => 0.0, 'syubhat' => 0.0, 'non_halal' => 0.0];

        Tagihan::query()
            ->whereIn('status', ['belum_bayar', 'sebagian'])
            ->get()
            ->each(function (Tagihan $t) use (&$out, $kh): void {
                $klas = $kh->klasifikasi($t->sumber_dana_id);
                $out[$klas] = ($out[$klas] ?? 0.0) + (float) $t->sisa_tagihan;
            });

        foreach ($out as $k => $v) {
            $out[$k] = round((float) $v, 2);
        }
        $out['total'] = round(array_sum($out), 2);

        return $out;
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk billing.');
        }
    }
}
