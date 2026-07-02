<?php

namespace Modules\Ledger\Services;

use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\JurnalBaris;
use Modules\Ledger\Models\SumberDana;

/**
 * F3 — sumber-kebenaran tunggal klasifikasi kehalalan + pelaporan.
 * Melengkapi KehalalanGuard (runtime, anti-mix) dengan: classifier terpusat,
 * ringkasan pendapatan per klasifikasi (halal/syubhat/non_halal), dan %halal.
 * Modul lain MEMANGGIL service ini, tidak menyalin aturan kehalalan.
 */
class KehalalanService
{
    public function __construct(private readonly DanaKebajikanService $danaKebajikan) {}

    /** Klasifikasi kehalalan dari sumber dana (default halal bila tak ada sumber). */
    public function klasifikasi(?int $sumberDanaId): string
    {
        if (empty($sumberDanaId)) {
            return 'halal';
        }

        return SumberDana::query()->whereKey($sumberDanaId)->value('kehalalan') ?? 'halal';
    }

    public function bukanHalal(?int $sumberDanaId): bool
    {
        return in_array($this->klasifikasi($sumberDanaId), ['syubhat', 'non_halal'], true);
    }

    /**
     * Ringkasan PENDAPATAN per klasifikasi kehalalan dalam periode.
     * Pendapatan = (kredit − debit) pada akun bertipe 'pendapatan'.
     *
     * @return array{halal:float, syubhat:float, non_halal:float, total:float}
     */
    public function ringkasanPendapatan(?string $from = null, ?string $to = null): array
    {
        $akunPendapatan = Akun::query()->where('tipe', 'PENDAPATAN')->pluck('id')->all();

        $ringkas = ['halal' => 0.0, 'syubhat' => 0.0, 'non_halal' => 0.0];

        if ($akunPendapatan !== []) {
            $baris = JurnalBaris::query()
                ->whereIn('akun_id', $akunPendapatan)
                ->whereHas('jurnal', function ($j) use ($from, $to): void {
                    $j->where('status', 'posted');
                    if ($from) {
                        $j->whereDate('tanggal', '>=', $from);
                    }
                    if ($to) {
                        $j->whereDate('tanggal', '<=', $to);
                    }
                })
                ->with('sumberDana:id,kehalalan')
                ->get(['id', 'sumber_dana_id', 'debit', 'kredit']);

            foreach ($baris as $b) {
                $klas = $b->sumberDana?->kehalalan ?? 'halal';
                if (! array_key_exists($klas, $ringkas)) {
                    $klas = 'halal';
                }
                $ringkas[$klas] += (float) $b->kredit - (float) $b->debit;
            }
        }

        $ringkas = array_map(fn ($v) => round($v, 2), $ringkas);
        $ringkas['total'] = round($ringkas['halal'] + $ringkas['syubhat'] + $ringkas['non_halal'], 2);

        return $ringkas;
    }

    /** Persentase pendapatan halal (0–100). */
    public function persenHalal(?string $from = null, ?string $to = null): float
    {
        $r = $this->ringkasanPendapatan($from, $to);

        return $r['total'] > 0 ? round($r['halal'] / $r['total'] * 100, 2) : 100.0;
    }

    /** Saldo Dana Kebajikan berjalan (dari dana syubhat/non-halal). */
    public function saldoDanaKebajikan(): float
    {
        // Toleran untuk pelaporan: tenant yang belum men-seed akun Dana Kebajikan
        // (2-2000) cukup dianggap saldo 0 — jangan sampai laporan/sertifikat 500.
        // Jalur posting (penerimaan/penyaluran) tetap menjaga guard akunnya sendiri.
        if (! Akun::query()->where('kode', DanaKebajikanService::AKUN_DANA_KEBAJIKAN)->exists()) {
            return 0.0;
        }

        return $this->danaKebajikan->saldo();
    }

    /**
     * Laporan kehalalan lengkap untuk satu periode (dipakai page & sertifikat).
     *
     * @return array{periode:array, pendapatan:array, persen_halal:float, dana_kebajikan:float, status:string}
     */
    public function laporan(?string $from = null, ?string $to = null): array
    {
        $pendapatan = $this->ringkasanPendapatan($from, $to);
        $persen = $pendapatan['total'] > 0 ? round($pendapatan['halal'] / $pendapatan['total'] * 100, 2) : 100.0;

        return [
            'periode' => ['from' => $from, 'to' => $to],
            'pendapatan' => $pendapatan,
            'persen_halal' => $persen,
            'dana_kebajikan' => $this->saldoDanaKebajikan(),
            'status' => $pendapatan['syubhat'] + $pendapatan['non_halal'] < 0.005 ? 'bersih' : 'ada_syubhat',
        ];
    }
}
