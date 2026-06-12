<?php

namespace Modules\Pajak\Services;

use Carbon\Carbon;
use Modules\Ledger\Services\LaporanService;
use Modules\Pajak\Models\AlokasiReinvestasi;
use Modules\Pajak\Models\SisaLebihYayasan;

/**
 * Sisa lebih yayasan pendidikan — PMK 68/2020.
 *
 * Surplus dari kegiatan pendidikan BEBAS PPh Badan jika diinvestasikan
 * ke sarana/prasarana pendidikan dalam 4 tahun. Jika tidak, menjadi
 * penghasilan kena pajak di tahun ke-5.
 */
class SisaLebihService
{
    public function __construct(private readonly LaporanService $laporan) {}

    /**
     * Catat sisa lebih tahun fiskal (biasanya dipanggil setelah tutup buku).
     */
    public function catatSurplus(int $tahunFiskal, float $surplusPendidikan, ?string $catatan = null): SisaLebihYayasan
    {
        return SisaLebihYayasan::updateOrCreate(
            ['tahun_fiskal' => $tahunFiskal],
            [
                'surplus_pendidikan' => $surplusPendidikan,
                'batas_reinvestasi' => Carbon::create($tahunFiskal + 4, 12, 31),
                'catatan' => $catatan,
            ],
        );
    }

    /**
     * Alokasikan reinvestasi ke sisa lebih tertentu.
     */
    public function alokasi(SisaLebihYayasan $sisaLebih, float $nominal, string $keterangan, ?string $refType = null, ?int $refId = null): AlokasiReinvestasi
    {
        $alokasi = AlokasiReinvestasi::create([
            'sisa_lebih_id' => $sisaLebih->id,
            'tanggal' => now(),
            'nominal' => $nominal,
            'keterangan' => $keterangan,
            'ref_type' => $refType,
            'ref_id' => $refId,
        ]);

        $totalAlokasi = round((float) $sisaLebih->alokasi()->sum('nominal'), 2);
        $sisaLebih->update([
            'reinvestasi_teralokasi' => $totalAlokasi,
            'status' => $totalAlokasi >= (float) $sisaLebih->surplus_pendidikan ? 'terpenuhi' : 'terbuka',
        ]);

        return $alokasi;
    }

    /**
     * Periksa dan tandai sisa lebih yang sudah lewat batas jadi kena_pajak.
     *
     * @return int jumlah record yang berubah status
     */
    public function periksaBatasWaktu(): int
    {
        return SisaLebihYayasan::where('status', 'terbuka')
            ->where('batas_reinvestasi', '<', now())
            ->update(['status' => 'kena_pajak']);
    }

    /**
     * Dashboard sisa lebih — semua tahun yang masih terbuka.
     */
    public function ringkasan(): array
    {
        $this->periksaBatasWaktu();

        $items = SisaLebihYayasan::orderBy('tahun_fiskal', 'desc')
            ->with('alokasi')
            ->get();

        return $items->map(fn (SisaLebihYayasan $s) => [
            'tahun_fiskal' => $s->tahun_fiskal,
            'surplus' => (float) $s->surplus_pendidikan,
            'teralokasi' => (float) $s->reinvestasi_teralokasi,
            'sisa' => $s->sisaBelumTeralokasi(),
            'batas' => $s->batas_reinvestasi->toDateString(),
            'hari_tersisa' => $s->hariBatasWaktu(),
            'status' => $s->status,
            'alokasi' => $s->alokasi->map(fn ($a) => [
                'tanggal' => $a->tanggal->toDateString(),
                'nominal' => (float) $a->nominal,
                'keterangan' => $a->keterangan,
            ])->all(),
        ])->all();
    }

    /**
     * Hitung potensi PPh Badan dari sisa lebih kena pajak.
     */
    public function potensiPajakBadan(): array
    {
        $kenaPajak = SisaLebihYayasan::where('status', 'kena_pajak')->get();

        $totalKenaPajak = round((float) $kenaPajak->sum(fn ($s) => $s->sisaBelumTeralokasi()), 2);
        $pphBadan = round($totalKenaPajak * 0.22, 2); // 22% tarif PPh Badan

        return [
            'jumlah_tahun' => $kenaPajak->count(),
            'total_sisa_belum_reinvestasi' => $totalKenaPajak,
            'tarif_pph_badan' => 22,
            'estimasi_pph_badan' => $pphBadan,
        ];
    }
}
