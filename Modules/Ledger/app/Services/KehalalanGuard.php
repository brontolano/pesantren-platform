<?php

namespace Modules\Ledger\Services;

use Modules\Ledger\Exceptions\PelanggaranKehalalanException;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\KebijakanKehalalan;
use Modules\Ledger\Models\SumberDana;
use Modules\Ledger\Support\FiturKeuangan;

/**
 * Penjaga pemisahan dana halal vs syubhat/non-halal.
 *
 * Dipanggil di dalam JurnalService::postJurnal() — karena posting hanya lewat satu
 * service, semua jalur (manual, belanja, payroll, PWA, dana kebajikan) terlindungi.
 *
 * Aturan (aktif HANYA bila setting `keuangan.pemisahan_kehalalan_aktif` = true):
 *  1. No-mix      : satu jurnal tak boleh mencampur baris dana halal dengan syubhat/non-halal.
 *  2. No-income   : dana syubhat/non-halal tak boleh mengkredit akun PENDAPATAN
 *                   (wajib jadi Dana Kebajikan, bukan pendapatan/pahala).
 *  3. Blacklist   : dilarang untuk akun peruntukan `ibadah` (mis. pembangunan masjid).
 *  4. Whitelist   : penggunaan (debit BEBAN) dana syubhat hanya ke akun
 *                   `boleh_dana_kebajikan=true` & peruntukan diizinkan kebijakan tenant.
 */
class KehalalanGuard
{
    /**
     * @param  array<int, array{akun_id:int, sumber_dana_id:?int, debit:float, kredit:float}>  $baris
     */
    public function assert(array $baris): void
    {
        if (! FiturKeuangan::pemisahanKehalalanAktif()) {
            return; // fitur non-aktif → tidak ada penjagaan (keuangan biasa).
        }

        $akunIds = array_filter(array_column($baris, 'akun_id'));
        $sumberIds = array_filter(array_column($baris, 'sumber_dana_id'));

        $akunMap = Akun::whereIn('id', $akunIds)->get()->keyBy('id');
        $sumberMap = SumberDana::whereIn('id', $sumberIds)->get()->keyBy('id');

        $adaHalal = false;
        $adaBukanHalal = false;

        foreach ($baris as $i => $row) {
            $sumber = isset($row['sumber_dana_id']) ? $sumberMap->get($row['sumber_dana_id']) : null;
            if ($sumber === null) {
                continue; // baris netral tanpa sumber dana — abaikan untuk klasifikasi.
            }

            if ($sumber->bukanHalal()) {
                $adaBukanHalal = true;
                $this->assertPenggunaanSah($akunMap->get($row['akun_id']), $sumber, $row, $i);
            } else {
                $adaHalal = true;
            }
        }

        // Aturan 1: No-mix.
        if ($adaHalal && $adaBukanHalal) {
            throw new PelanggaranKehalalanException(
                'Satu jurnal tidak boleh mencampur dana halal dengan dana syubhat/non-halal. Catat terpisah.'
            );
        }
    }

    private function assertPenggunaanSah(?Akun $akun, SumberDana $sumber, array $row, int $i): void
    {
        if ($akun === null) {
            return;
        }

        $debit = (float) ($row['debit'] ?? 0);
        $kredit = (float) ($row['kredit'] ?? 0);

        // Aturan 2: No-income — dana syubhat tak boleh jadi pendapatan.
        if ($akun->tipe === 'PENDAPATAN' && $kredit > 0) {
            throw new PelanggaranKehalalanException(
                "Baris #{$i}: dana {$sumber->kehalalan} tidak boleh dicatat sebagai pendapatan. " .
                'Gunakan akun "Dana Kebajikan / Pembersihan Harta".'
            );
        }

        // Aturan 3 & 4 berlaku pada akun bertujuan (peruntukan terisi) — titik penyaluran.
        // Akun kas/bank & titipan Dana Kebajikan (tanpa peruntukan) bukan titik penyaluran.
        if ($akun->peruntukan === null) {
            return;
        }

        // Aturan 3: Blacklist ibadah/masjid.
        if ($akun->peruntukan === 'ibadah') {
            throw new PelanggaranKehalalanException(
                "Baris #{$i}: dana {$sumber->kehalalan} DILARANG untuk sarana ibadah/masjid. " .
                'Pembangunan masjid wajib dari dana 100% halal.'
            );
        }

        // Aturan 4: Whitelist — akun penyaluran harus diizinkan.
        if (! $akun->boleh_dana_kebajikan) {
            throw new PelanggaranKehalalanException(
                "Baris #{$i}: akun \"{$akun->nama}\" tidak ditandai boleh menerima dana kebajikan/syubhat."
            );
        }

        $diizinkan = KebijakanKehalalan::query()
            ->where('kehalalan_sumber', $sumber->kehalalan)
            ->where('peruntukan_tujuan', $akun->peruntukan ?? 'umum')
            ->value('diizinkan');

        if (! $diizinkan) {
            throw new PelanggaranKehalalanException(
                "Baris #{$i}: penyaluran dana {$sumber->kehalalan} ke peruntukan " .
                "\"{$akun->peruntukan}\" tidak diizinkan kebijakan kehalalan tenant."
            );
        }
    }
}
