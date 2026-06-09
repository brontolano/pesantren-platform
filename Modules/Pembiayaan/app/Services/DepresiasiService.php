<?php

namespace Modules\Pembiayaan\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Services\JurnalService;
use Modules\Pembiayaan\Models\Aset;
use Modules\Pembiayaan\Models\JadwalPenyusutan;
use RuntimeException;

/**
 * G2-13 — Mesin penyusutan aset tetap.
 *
 * Metode: garis lurus & saldo menurun ganda. WAKAF / tanah (metode 'none') TIDAK
 * disusutkan (UU 41/2004 — pokok wakaf tak boleh berkurang). Posting jurnal lewat
 * JurnalService (idempoten, seimbang): D Beban Penyusutan (5-7000) / K Akumulasi
 * Penyusutan (1-2900). Nilai buku & akumulasi pada Aset diperbarui saat posting.
 *
 * CATATAN: ini default konfigurabel — metode pajak fiskal (UU PPh Ps 11) dapat
 * berbeda; tinjau kebijakan akuntansi pesantren.
 */
class DepresiasiService
{
    public function __construct(private readonly JurnalService $jurnalService)
    {
    }

    /**
     * Bangun jadwal penyusutan untuk satu aset (idempoten).
     * WAKAF/none → kosong. Bila sudah ada baris POSTED → kembalikan apa adanya
     * (tak menimpa histori). Bila hanya draft → regenerasi.
     *
     * @return Collection<int, JadwalPenyusutan>
     */
    public function buatJadwal(Aset $aset, ?string $mulaiPeriode = null): Collection
    {
        $this->ensureTenantContext();

        if (! $aset->bolehDisusutkan()) {
            return collect(); // wakaf / tanah / tanpa umur manfaat
        }

        $sudahPosted = $aset->jadwalPenyusutan()->where('status', 'posted')->exists();
        if ($sudahPosted) {
            return $aset->jadwalPenyusutan()->orderBy('urutan')->get();
        }

        $aset->jadwalPenyusutan()->where('status', 'draft')->delete();

        $dasar = (float) ($aset->nilai_perolehan ?: $aset->nilai);
        $residu = (float) $aset->nilai_residu;
        $umur = (int) $aset->umur_manfaat_bulan;
        if ($dasar <= $residu || $umur <= 0) {
            return collect();
        }

        $baris = $aset->metode_penyusutan === 'saldo_menurun'
            ? $this->barisSaldoMenurun($dasar, $residu, $umur)
            : $this->barisGarisLurus($dasar, $residu, $umur);

        $mulai = $mulaiPeriode !== null
            ? Carbon::createFromFormat('Y-m', $mulaiPeriode)->startOfMonth()
            : ($aset->tanggal_perolehan ? $aset->tanggal_perolehan->copy()->startOfMonth() : Carbon::now()->startOfMonth());

        $rows = collect();
        foreach ($baris as $i => $b) {
            $rows->push(JadwalPenyusutan::create([
                'aset_id' => $aset->id,
                'urutan' => $i + 1,
                'periode' => $mulai->copy()->addMonths($i)->format('Y-m'),
                'nilai_awal' => $b['nilai_awal'],
                'beban_penyusutan' => $b['beban'],
                'akumulasi' => $b['akumulasi'],
                'nilai_akhir' => $b['nilai_akhir'],
                'status' => 'draft',
            ]));
        }

        return $rows;
    }

    /** Garis lurus: beban rata; baris terakhir menyerap sisa pembulatan → nilai buku = residu. */
    private function barisGarisLurus(float $dasar, float $residu, int $umur): array
    {
        $depreciable = round($dasar - $residu, 2);
        $perBulan = round($depreciable / $umur, 2);

        $rows = [];
        $akum = 0.0;
        $nilaiAwal = $dasar;
        for ($n = 1; $n <= $umur; $n++) {
            $beban = $n === $umur ? round($depreciable - $akum, 2) : $perBulan;
            $beban = max($beban, 0.0); // jangan negatif pada sisa pembulatan
            $akum = round($akum + $beban, 2);
            $nilaiAkhir = round($dasar - $akum, 2);
            $rows[] = ['nilai_awal' => $nilaiAwal, 'beban' => $beban, 'akumulasi' => $akum, 'nilai_akhir' => $nilaiAkhir];
            $nilaiAwal = $nilaiAkhir;
        }

        return $rows;
    }

    /** Saldo menurun ganda: rate 2/umur atas nilai buku; baris terakhir dipaksa ke residu. */
    private function barisSaldoMenurun(float $dasar, float $residu, int $umur): array
    {
        $rate = 2.0 / $umur;
        $rows = [];
        $akum = 0.0;
        $nilaiAwal = $dasar;
        for ($n = 1; $n <= $umur; $n++) {
            if ($n === $umur) {
                $beban = round($nilaiAwal - $residu, 2); // sisa → residu
            } else {
                $beban = round($nilaiAwal * $rate, 2);
                // jangan menembus nilai residu
                if (round($nilaiAwal - $beban, 2) < $residu) {
                    $beban = round($nilaiAwal - $residu, 2);
                }
            }
            $beban = max($beban, 0.0);
            $akum = round($akum + $beban, 2);
            $nilaiAkhir = round($dasar - $akum, 2);
            $rows[] = ['nilai_awal' => $nilaiAwal, 'beban' => $beban, 'akumulasi' => $akum, 'nilai_akhir' => $nilaiAkhir];
            $nilaiAwal = $nilaiAkhir;
        }

        return $rows;
    }

    /**
     * Posting satu baris jadwal ke buku besar (idempoten & seimbang).
     * D Beban Penyusutan (5-7000) / K Akumulasi Penyusutan (1-2900).
     */
    public function postingPeriode(JadwalPenyusutan $jadwal, ?int $dibuatOleh = null): JadwalPenyusutan
    {
        $this->ensureTenantContext();

        // Baris yang sudah posted tak diposting ulang (baris berbeban punya
        // jurnal_id; baris beban<=0 ditandai posted tanpa jurnal) — idempoten.
        if ($jadwal->status === 'posted') {
            return $jadwal;
        }

        // Query eksplisit (bukan lazy-load) agar aman di strict mode preventLazyLoading.
        $aset = $jadwal->relationLoaded('aset') ? $jadwal->aset : Aset::find($jadwal->aset_id);
        if ($aset === null) {
            throw new RuntimeException('Aset jadwal penyusutan tidak ditemukan.');
        }
        if ($aset->is_wakaf) {
            throw new InvalidArgumentException('Aset wakaf tidak disusutkan (UU 41/2004).');
        }

        $beban = round((float) $jadwal->beban_penyusutan, 2);
        if ($beban <= 0) {
            // Tidak ada beban → tandai posted tanpa jurnal (idempoten).
            $jadwal->update(['status' => 'posted']);

            return $jadwal->fresh();
        }

        $bebanAkun = Akun::where('kode', '5-7000')->first();
        $akumAkun = Akun::where('kode', '1-2900')->first();
        if ($bebanAkun === null || $akumAkun === null) {
            throw new RuntimeException('Akun penyusutan (5-7000 / 1-2900) belum di-seed.');
        }

        return DB::transaction(function () use ($jadwal, $aset, $beban, $bebanAkun, $akumAkun, $dibuatOleh): JadwalPenyusutan {
            $jurnal = $this->jurnalService->postJurnal([
                'tanggal' => Carbon::createFromFormat('Y-m', $jadwal->periode)->endOfMonth(),
                'deskripsi' => 'Penyusutan ' . $jadwal->periode . ' — ' . $aset->nama_aset,
                'tipe_sumber' => 'penyusutan',
                'ref_type' => 'JadwalPenyusutan',
                'ref_id' => (string) $jadwal->id,
                'sumber_dana_id' => $aset->sumber_dana_id,
                'dibuat_oleh' => $dibuatOleh,
                'baris' => [
                    ['akun_id' => $bebanAkun->id, 'debit' => $beban, 'kredit' => 0, 'memo' => 'Beban penyusutan'],
                    ['akun_id' => $akumAkun->id, 'debit' => 0, 'kredit' => $beban, 'memo' => 'Akumulasi penyusutan'],
                ],
            ]);

            $jadwal->update(['status' => 'posted', 'jurnal_id' => $jurnal->id]);

            // Nilai buku DIHITUNG dari akumulasi (bukan snapshot nilai_akhir jadwal)
            // agar tetap konsisten meski baris diposting tak berurutan.
            $akumBaru = round((float) $aset->akumulasi_penyusutan + $beban, 2);
            $dasar = (float) ($aset->nilai_perolehan ?: $aset->nilai);
            $aset->update([
                'akumulasi_penyusutan' => $akumBaru,
                'nilai' => round($dasar - $akumBaru, 2),
            ]);

            return $jadwal->fresh();
        });
    }

    /**
     * Posting seluruh baris draft yang jatuh tempo s/d periode tertentu (inklusif).
     *
     * @return int jumlah baris yang diposting
     */
    public function postingHinggaPeriode(Aset $aset, string $periode, ?int $dibuatOleh = null): int
    {
        $this->ensureTenantContext();

        $jatuhTempo = $aset->jadwalPenyusutan()
            ->where('status', 'draft')
            ->where('periode', '<=', $periode)
            ->orderBy('urutan')
            ->get();

        $n = 0;
        foreach ($jatuhTempo as $jadwal) {
            $this->postingPeriode($jadwal, $dibuatOleh);
            $n++;
        }

        return $n;
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
