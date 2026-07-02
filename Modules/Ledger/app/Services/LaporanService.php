<?php

namespace Modules\Ledger\Services;

use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\JurnalBaris;
use Modules\Ledger\Models\SumberDana;

/**
 * Mesin laporan — semua laporan adalah PROYEKSI dari jurnal_baris (Pagar #1).
 * Tidak ada angka tersimpan terpisah; tiap angka dapat ditelusuri ke jurnal.
 */
class LaporanService
{
    public function __construct(private readonly BukuBesarService $bukuBesar) {}

    /**
     * L6 — Neraca (posisi keuangan). Aset = Kewajiban + Ekuitas (+ surplus berjalan).
     *
     * @return array{aset: array, kewajiban: array, ekuitas: array, total_aset: float, total_kewajiban_ekuitas: float, surplus_berjalan: float, seimbang: bool}
     */
    public function neraca(?string $to = null): array
    {
        $aset = $this->saldoPerTipe('ASET', $to);
        $kewajiban = $this->saldoPerTipe('KEWAJIBAN', $to);
        $ekuitas = $this->saldoPerTipe('EKUITAS', $to);

        // Surplus/defisit berjalan ikut menambah ekuitas (belum ditutup ke aset neto).
        $surplus = $this->labaRugi($to)['surplus_defisit'];

        $totalAset = round(array_sum(array_column($aset, 'saldo')), 2);
        $totalKewajiban = round(array_sum(array_column($kewajiban, 'saldo')), 2);
        $totalEkuitas = round(array_sum(array_column($ekuitas, 'saldo')), 2);
        $totalKewajibanEkuitas = round($totalKewajiban + $totalEkuitas + $surplus, 2);

        return [
            'aset' => $aset,
            'kewajiban' => $kewajiban,
            'ekuitas' => $ekuitas,
            'total_aset' => $totalAset,
            'total_kewajiban_ekuitas' => $totalKewajibanEkuitas,
            'surplus_berjalan' => $surplus,
            'seimbang' => abs($totalAset - $totalKewajibanEkuitas) < 0.01,
        ];
    }

    /**
     * L6 — Laba Rugi / Surplus-Defisit = Pendapatan − Beban.
     *
     * @return array{pendapatan: array, beban: array, total_pendapatan: float, total_beban: float, surplus_defisit: float}
     */
    public function labaRugi(?string $from = null, ?string $to = null): array
    {
        // Catatan: from null = sejak awal (akumulatif). Untuk L-R periodik isi from.
        $pendapatan = $this->saldoPerTipe('PENDAPATAN', $to, $from);
        $beban = $this->saldoPerTipe('BEBAN', $to, $from);

        $totalP = round(array_sum(array_column($pendapatan, 'saldo')), 2);
        $totalB = round(array_sum(array_column($beban, 'saldo')), 2);

        return [
            'pendapatan' => $pendapatan,
            'beban' => $beban,
            'total_pendapatan' => $totalP,
            'total_beban' => $totalB,
            'surplus_defisit' => round($totalP - $totalB, 2),
        ];
    }

    /** L6 — Arus Kas ringkas: saldo & mutasi akun kas/bank (BKU). */
    public function arusKas(?string $from = null, ?string $to = null): array
    {
        return $this->bukuBesar->bku($from, $to);
    }

    /**
     * L2 — Laporan Dana BOS/BOP: realisasi per sumber dana kategori 'negara'
     * (penerimaan vs penggunaan), terpisah BOS Madrasah vs BOP Pesantren via rezim.
     *
     * @return array<int, array{kode:string, nama:string, rezim:?string, penerimaan:float, penggunaan:float, sisa:float}>
     */
    public function danaBosBop(?string $from = null, ?string $to = null): array
    {
        return SumberDana::where('kategori', 'negara')->orderBy('kode_dana')->get()
            ->map(function (SumberDana $s) use ($from, $to) {
                // Penerimaan = kredit pada akun PENDAPATAN; penggunaan = debit pada akun BEBAN.
                $penerimaan = round((float) $this->sumberTipe($s->id, 'PENDAPATAN', $from, $to)->sum('kredit'), 2);
                $penggunaan = round((float) $this->sumberTipe($s->id, 'BEBAN', $from, $to)->sum('debit'), 2);

                return [
                    'kode' => $s->kode_dana,
                    'nama' => $s->nama_sumber,
                    'rezim' => $s->rezim,
                    'penerimaan' => $penerimaan,
                    'penggunaan' => $penggunaan,
                    'sisa' => round($penerimaan - $penggunaan, 2),
                ];
            })->all();
    }

    /**
     * Laporan Sumber & Penggunaan Dana Kebajikan (uang syubhat) — TERPISAH dari surplus.
     *
     * @return array{penerimaan: float, penyaluran: array, total_penyaluran: float, saldo: float}
     */
    public function danaKebajikan(?string $from = null, ?string $to = null): array
    {
        $induk = Akun::where('kode', '2-2000')->first();
        if ($induk === null) {
            return ['penerimaan' => 0.0, 'penyaluran' => [], 'total_penyaluran' => 0.0, 'saldo' => 0.0];
        }

        $penerimaan = round((float) $this->barisAkun($induk->id, $from, $to)->sum('kredit'), 2);

        $penyaluran = $induk->children()->orderBy('kode')->get()->map(function (Akun $a) use ($from, $to) {
            $jml = round((float) $this->barisAkun($a->id, $from, $to)->sum('debit'), 2);

            return ['kode' => $a->kode, 'nama' => $a->nama, 'peruntukan' => $a->peruntukan, 'jumlah' => $jml];
        })->filter(fn ($r) => $r['jumlah'] != 0)->values()->all();

        $totalPenyaluran = round(array_sum(array_column($penyaluran, 'jumlah')), 2);

        return [
            'penerimaan' => $penerimaan,
            'penyaluran' => $penyaluran,
            'total_penyaluran' => $totalPenyaluran,
            'saldo' => round($penerimaan - $totalPenyaluran, 2),
        ];
    }

    /**
     * L5 — Realisasi anggaran per divisi/kategori (akumulasi beban dari jurnal).
     * Pagu/anggaran menyusul (tabel anggaran belum dibuat) — realisasi auditable.
     *
     * @return array<int, array{divisi:string, realisasi:float}>
     */
    public function realisasiPerDivisi(?string $from = null, ?string $to = null): array
    {
        $bebanIds = Akun::where('tipe', 'BEBAN')->pluck('id')->all();

        $rows = JurnalBaris::query()
            ->whereIn('jurnal_baris.akun_id', $bebanIds)
            ->whereHas('jurnal', fn ($j) => $this->scopePosted($j, $from, $to))
            ->leftJoin('divisi_unit', 'divisi_unit.id', '=', 'jurnal_baris.divisi_id')
            ->selectRaw('COALESCE(divisi_unit.nama_divisi, \'(Tanpa Divisi)\') as divisi, SUM(jurnal_baris.debit - jurnal_baris.kredit) as realisasi')
            ->groupBy('divisi')
            ->get();

        return $rows->map(fn ($r) => ['divisi' => $r->divisi, 'realisasi' => round((float) $r->realisasi, 2)])->all();
    }

    /**
     * Segregation of funds (PSAK 112 wakaf & PSAK 109 ZIS) — dana terikat disajikan
     * terpisah per sumber dana. Penerimaan (kredit pendapatan) vs penggunaan (debit beban).
     *
     * @return array{wakaf: array, zis: array, lainnya_terikat: array}
     */
    public function laporanDanaTerikat(?string $from = null, ?string $to = null): array
    {
        $bucket = ['wakaf' => [], 'zis' => [], 'lainnya_terikat' => []];

        foreach (SumberDana::where('is_terikat', true)->orderBy('kode_dana')->get() as $s) {
            $penerimaan = round((float) $this->sumberTipe($s->id, 'PENDAPATAN', $from, $to)->sum('kredit'), 2);
            $penggunaan = round((float) $this->sumberTipe($s->id, 'BEBAN', $from, $to)->sum('debit'), 2);
            $row = [
                'kode' => $s->kode_dana,
                'nama' => $s->nama_sumber,
                'penerimaan' => $penerimaan,
                'penggunaan' => $penggunaan,
                'saldo' => round($penerimaan - $penggunaan, 2),
            ];

            if ($s->is_wakaf) {
                $bucket['wakaf'][] = $row;          // PSAK 112
            } elseif (str_contains(strtolower($s->nama_sumber), 'zakat') || str_contains(strtolower($s->nama_sumber), 'infak')) {
                $bucket['zis'][] = $row;            // PSAK 109
            } else {
                $bucket['lainnya_terikat'][] = $row;
            }
        }

        return $bucket;
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * Saldo per akun untuk satu tipe (ASET/KEWAJIBAN/EKUITAS/PENDAPATAN/BEBAN),
     * dinormalkan ke saldo positif sesuai saldo normalnya.
     */
    private function saldoPerTipe(string $tipe, ?string $to, ?string $from = null): array
    {
        return Akun::where('tipe', $tipe)->where('is_postable', true)->orderBy('kode')->get()
            ->map(function (Akun $a) use ($to, $from) {
                $q = $this->barisAkun($a->id, $from, $to);
                $net = round((float) $q->sum('debit') - (float) $q->sum('kredit'), 2); // debit - kredit
                $saldo = $a->saldo_normal === 'debit' ? $net : -$net;

                return ['kode' => $a->kode, 'nama' => $a->nama, 'saldo' => round($saldo, 2)];
            })
            ->filter(fn ($r) => abs($r['saldo']) > 0.001)->values()->all();
    }

    /** Baris jurnal untuk satu sumber dana yang akunnya bertipe tertentu. */
    private function sumberTipe(int $sumberDanaId, string $tipe, ?string $from, ?string $to)
    {
        $akunIds = Akun::where('tipe', $tipe)->pluck('id')->all();

        return JurnalBaris::where('sumber_dana_id', $sumberDanaId)
            ->whereIn('akun_id', $akunIds)
            ->whereHas('jurnal', fn ($j) => $this->scopePosted($j, $from, $to));
    }

    private function barisAkun(int $akunId, ?string $from, ?string $to)
    {
        return JurnalBaris::where('akun_id', $akunId)
            ->whereHas('jurnal', fn ($j) => $this->scopePosted($j, $from, $to));
    }

    private function scopePosted($j, ?string $from, ?string $to): void
    {
        $j->where('status', 'posted');
        if ($from) {
            $j->whereDate('tanggal', '>=', $from);
        }
        if ($to) {
            $j->whereDate('tanggal', '<=', $to);
        }
    }
}
