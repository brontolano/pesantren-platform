<?php

namespace Modules\Keuangan\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Keuangan\Models\PosKategori;
use Modules\Keuangan\Models\PosProfil;
use Modules\Keuangan\Models\PosSesi;
use Modules\Keuangan\Models\PosTransaksi;
use RuntimeException;

/**
 * G3-21 — POS enrichment di ATAS PosKasService (math teruji TIDAK diubah).
 *
 *  - Profil loket (akun kas/QRIS, tarif poin).
 *  - Diskon "harga adil": netto = harga_kotor − diskon dipakai sebagai NOMINAL
 *    posting → jurnal SELALU seimbang (diskon tak mengkorup total).
 *  - Loyalty point transparan (non-uang; poin = floor(netto / tarif_poin)).
 *  - Mode of payment per transaksi + ringkasan setoran per metode (rekon F6).
 *  - Data struk (untuk cetak thermal via F8).
 */
class PosEnrichmentService
{
    public const METODE = ['tunai', 'transfer', 'qris'];

    public function __construct(private readonly PosKasService $posKas)
    {
    }

    /** Buka sesi memakai profil loket (pos_jenis + profil_id). */
    public function bukaSesiProfil(PosProfil|int $profil, array $data = []): PosSesi
    {
        $this->ensureTenantContext();
        $p = $profil instanceof PosProfil ? $profil : PosProfil::query()->findOrFail($profil);

        $sesi = $this->posKas->bukaSesi(array_merge($data, ['pos_jenis' => $p->pos_jenis]));
        $sesi->update(['profil_id' => $p->id]);

        return $sesi->fresh();
    }

    /**
     * Transaksi ritel: terapkan diskon → posting NETTO via PosKasService::catat
     * (seimbang), lalu lengkapi metode bayar, diskon, dan poin.
     *
     * @param  array{harga_kotor?:float, nominal?:float, diskon?:float, metode_bayar?:string, santri_id?:int, tagihan_id?:int, keterangan?:string, dibuat_oleh?:int, tarif_poin?:float}  $payload
     */
    public function catatRitel(PosSesi|int $sesi, PosKategori|int $kategori, array $payload = []): PosTransaksi
    {
        $this->ensureTenantContext();
        $sesi = $sesi instanceof PosSesi ? $sesi : PosSesi::query()->findOrFail($sesi);

        $hargaKotor = round((float) ($payload['harga_kotor'] ?? $payload['nominal'] ?? 0), 2);
        $diskon = round((float) ($payload['diskon'] ?? 0), 2);
        if ($hargaKotor <= 0) {
            throw new InvalidArgumentException('Harga kotor harus lebih dari 0.');
        }
        if ($diskon < 0) {
            throw new InvalidArgumentException('Diskon tidak boleh negatif.');
        }
        if ($diskon >= $hargaKotor) {
            throw new InvalidArgumentException('Diskon tidak boleh ≥ harga kotor (netto harus > 0).');
        }
        $netto = round($hargaKotor - $diskon, 2);

        $metode = $payload['metode_bayar'] ?? 'tunai';
        if (! in_array($metode, self::METODE, true)) {
            throw new InvalidArgumentException('Metode bayar tidak valid (tunai|transfer|qris).');
        }

        $tarifPoin = $this->tarifPoin($sesi, $payload);
        // round(...,6) sebelum floor → cegah off-by-one IEEE-754 (mis. 2.9999999).
        $poin = $tarifPoin > 0 ? floor(round($netto / $tarifPoin, 6)) : 0.0;

        return DB::transaction(function () use ($sesi, $kategori, $payload, $netto, $hargaKotor, $diskon, $metode, $poin): PosTransaksi {
            // Posting via service teruji — NOMINAL = netto → jurnal seimbang.
            $tx = $this->posKas->catat($sesi, $kategori, array_merge($payload, ['nominal' => $netto]));

            $tx->update([
                'metode_bayar' => $metode,
                'harga_kotor' => $hargaKotor,
                'diskon' => $diskon,
                'poin' => $poin,
            ]);

            return $tx->fresh();
        });
    }

    /**
     * Tutup sesi berbasis HITUNG TUNAI saja: selisih dibandingkan terhadap kas
     * laci (tunai) — transaksi non-tunai (qris/transfer) yang TIDAK masuk laci
     * dikeluarkan dari ekspektasi agar tak menimbulkan "selisih kas palsu".
     *
     * Trik: PosKasService::tutupSesi menghitung selisih = saldoFisik − saldo_sistem
     * (saldo_sistem mencakup semua pemasukan). Kita pasok saldoFisik EFEKTIF =
     * tunai_fisik + non_tunai_masuk − non_tunai_keluar, sehingga komponen non-tunai
     * saling meniadakan dan selisih = tunai_fisik − ekspektasi_tunai. PosKasService
     * (math teruji) TIDAK diubah.
     */
    public function tutupSesiTunai(PosSesi|int $sesi, float $saldoFisikTunai): PosSesi
    {
        $this->ensureTenantContext();
        $sesi = $sesi instanceof PosSesi ? $sesi : PosSesi::query()->findOrFail($sesi);

        $nonTunaiMasuk = (float) PosTransaksi::where('sesi_id', $sesi->id)
            ->where('jenis', 'pemasukan')->whereIn('metode_bayar', ['qris', 'transfer'])->sum('nominal');
        $nonTunaiKeluar = (float) PosTransaksi::where('sesi_id', $sesi->id)
            ->where('jenis', 'pengeluaran')->whereIn('metode_bayar', ['qris', 'transfer'])->sum('nominal');

        $saldoFisikEfektif = round($saldoFisikTunai + $nonTunaiMasuk - $nonTunaiKeluar, 2);

        return $this->posKas->tutupSesi($sesi, $saldoFisikEfektif);
    }

    /**
     * Ringkasan setoran per metode bayar untuk satu sesi (rekonsiliasi F6).
     *
     * @return array<string, float>
     */
    public function ringkasanMetode(PosSesi|int $sesi): array
    {
        $this->ensureTenantContext();
        $sesiId = $sesi instanceof PosSesi ? $sesi->id : (int) $sesi;

        $base = array_fill_keys(self::METODE, 0.0);
        $agg = PosTransaksi::where('sesi_id', $sesiId)
            ->where('jenis', 'pemasukan')
            ->selectRaw('metode_bayar, SUM(nominal) as total')
            ->groupBy('metode_bayar')
            ->pluck('total', 'metode_bayar')
            ->map(fn ($v): float => round((float) $v, 2))
            ->all();

        return array_merge($base, $agg);
    }

    /** Total poin loyalitas seorang santri (transparan). */
    public function loyaltyPoin(int $santriId): float
    {
        $this->ensureTenantContext();

        return round((float) PosTransaksi::where('santri_id', $santriId)->sum('poin'), 2);
    }

    /**
     * Data struk untuk cetak (thermal/F8).
     *
     * @return array{nomor:int, tanggal:string, kategori:?string, harga_kotor:float, diskon:float, total:float, metode:string, poin:float}
     */
    public function strukData(PosTransaksi|int $tx): array
    {
        $this->ensureTenantContext();
        $tx = $tx instanceof PosTransaksi ? $tx : PosTransaksi::query()->with('kategori')->findOrFail($tx);

        return [
            'nomor' => $tx->id,
            'tanggal' => (string) $tx->created_at,
            'kategori' => $tx->kategori?->nama,
            'harga_kotor' => round((float) ($tx->harga_kotor ?: $tx->nominal), 2),
            'diskon' => round((float) $tx->diskon, 2),
            'total' => round((float) $tx->nominal, 2),
            'metode' => (string) ($tx->metode_bayar ?? 'tunai'),
            'poin' => round((float) $tx->poin, 2),
        ];
    }

    /** Struk thermal sederhana (HTML/teks). Dapat di-serve via F8 ReportExport. */
    public function strukHtml(PosTransaksi|int $tx): string
    {
        $d = $this->strukData($tx);
        $rp = fn (float $v): string => 'Rp '.number_format($v, 0, ',', '.');

        return implode("\n", [
            '<pre style="font-family:monospace;font-size:12px;width:240px">',
            '       STRUK PEMBELIAN',
            'No   : #'.$d['nomor'],
            'Tgl  : '.$d['tanggal'],
            'Item : '.($d['kategori'] ?? '-'),
            '--------------------------------',
            'Harga    : '.$rp($d['harga_kotor']),
            'Diskon   : '.$rp($d['diskon']),
            'TOTAL    : '.$rp($d['total']),
            'Metode   : '.strtoupper($d['metode']),
            'Poin     : '.(int) $d['poin'],
            '--------------------------------',
            '   Terima kasih & barakallah',
            '</pre>',
        ]);
    }

    private function tarifPoin(PosSesi $sesi, array $payload): float
    {
        if (isset($payload['tarif_poin'])) {
            return max(0.0, (float) $payload['tarif_poin']);
        }
        if ($sesi->profil_id !== null) {
            $tarif = PosProfil::query()->whereKey($sesi->profil_id)->value('tarif_poin');

            return max(0.0, (float) ($tarif ?? 0));
        }

        return 0.0;
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
