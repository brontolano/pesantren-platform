<?php

namespace Modules\Keuangan\Services;

use Illuminate\Support\Facades\DB;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Models\TabunganTransaksi;
use Modules\Keuangan\Support\SyahriahKomponen;
use RuntimeException;

/**
 * Setor otomatis porsi komponen `masuk_tabungan` ke Tabungan santri saat
 * tagihan LUNAS (#332 — mis. komponen "Tabungan wajib" dalam syahriah).
 *
 * IDEMPOTEN (jalur uang): penanda `tagihan.tabungan_disetor_at` dicek & di-set
 * pada baris tagihan ter-LOCK dalam satu transaksi — event pelunasan yang
 * terpicu ulang (re-verifikasi, recalculation status berulang, dua jalur
 * Pembayaran/BayarDariSaldo) TIDAK menyetor ganda.
 *
 * Setoran memakai TabunganService::setor (jalur teruji): saldo ter-lock,
 * TabunganTransaksi tercatat, event TabunganTransaksiDicatat → auto-jurnal
 * Ledger dengan ref transaksi (tidak pernah null).
 */
class TagihanKomponenTabunganService
{
    /**
     * @return TabunganTransaksi|null null = tidak ada porsi / belum lunas / sudah disetor.
     */
    public function setorPorsiTabungan(Tagihan|int $tagihan): ?TabunganTransaksi
    {
        $this->ensureTenantContext();

        $tagihanId = $tagihan instanceof Tagihan ? $tagihan->id : $tagihan;

        return DB::transaction(function () use ($tagihanId): ?TabunganTransaksi {
            /** @var Tagihan $locked */
            $locked = Tagihan::query()->whereKey($tagihanId)->lockForUpdate()->firstOrFail();

            // Hanya saat lunas, sekali saja (guard idempotensi pada baris ter-lock).
            if ($locked->status !== 'lunas' || $locked->tabungan_disetor_at !== null) {
                return null;
            }

            $porsi = SyahriahKomponen::porsiTabungan($locked->komponen);
            if ($porsi <= 0) {
                // Tandai juga: tagihan lunas tanpa porsi tak perlu dievaluasi ulang.
                $locked->forceFill(['tabungan_disetor_at' => now()])->save();

                return null;
            }

            $transaksi = app(TabunganService::class)->setor($locked->santri_id, $porsi, [
                'keterangan' => sprintf(
                    'Setoran otomatis porsi tabungan dari pelunasan tagihan #%d (%s %s)',
                    $locked->id,
                    $locked->jenis_tagihan,
                    $locked->bulan_masehi ?? '',
                ),
                'created_by' => $locked->created_by,
            ]);

            $locked->forceFill(['tabungan_disetor_at' => now()])->save();

            return $transaksi;
        });
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
