<?php

namespace Modules\Ledger\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\DivisiUnit;
use Modules\Ledger\Models\KebijakanKehalalan;
use Modules\Ledger\Models\SumberDana;

/**
 * Seed default Ledger untuk satu tenant (idempoten — aman dijalankan ulang).
 *
 * Mengisi:
 *  - sumber_dana: dimensi multi-fund (Koreksi #2: BOS Madrasah ≠ BOP Pesantren)
 *  - divisi_unit: unit pelaksana di bawah Yayasan
 *  - akun: Bagan Akun (COA) standar pesantren, hierarkis
 *
 * Semua nilai = DEFAULT yang dapat diubah tiap tenant (config over customization §14).
 */
class LedgerSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSumberDana();
        $this->seedDivisiUnit();
        $this->seedAkun();
        $this->seedKebijakanKehalalan();
    }

    private function seedSumberDana(): void
    {
        $data = [
            // kode, nama, kategori, rezim, is_wakaf, is_terikat, kehalalan, is_dana_kebajikan
            ['01', 'BOS Madrasah', 'negara', 'bos_madrasah', false, true, 'halal', false],
            ['02', 'BOP Pesantren', 'negara', 'bop_pesantren', false, true, 'halal', false],
            ['03', 'SPP / Swadaya', 'komersial', null, false, false, 'halal', false],
            ['04', 'Wakaf', 'sosial', null, true, true, 'halal', false],
            ['05', 'Zakat / Infak / Sedekah', 'sosial', null, false, true, 'halal', false],
            ['06', 'Unit Usaha', 'komersial', null, false, false, 'halal', false],
            // Dana syubhat — diperlakukan sebagai Dana Kebajikan/Pembersihan (bukan pendapatan).
            ['90', 'Bunga Bank (Syubhat)', 'sosial', null, false, true, 'syubhat', true],
        ];

        foreach ($data as [$kode, $nama, $kategori, $rezim, $isWakaf, $isTerikat, $kehalalan, $isKebajikan]) {
            SumberDana::updateOrCreate(
                ['kode_dana' => $kode],
                [
                    'nama_sumber' => $nama,
                    'kategori' => $kategori,
                    'rezim' => $rezim,
                    'is_wakaf' => $isWakaf,
                    'is_terikat' => $isTerikat,
                    'kehalalan' => $kehalalan,
                    'is_dana_kebajikan' => $isKebajikan,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Kebijakan default (sesuai 5 aturan syar'i). DAPAT diubah & wajib disahkan
     * otoritas syar'i pesantren (Kyai/Dewan Syariah) — sistem hanya guardrail.
     */
    private function seedKebijakanKehalalan(): void
    {
        $aturan = [
            // kehalalan_sumber, peruntukan_tujuan, diizinkan, dasar
            ['syubhat', 'fasilitas_umum', true, 'Fasilitas umum: MCK, jalan, jembatan, penerangan'],
            ['syubhat', 'dhuafa', true, 'Kaum dhuafa untuk kebutuhan pokok (bukan sedekah sunnah)'],
            ['syubhat', 'operasional', true, 'Operasional fasilitas non-ibadah (panti, sekolah umum, kebersihan)'],
            ['syubhat', 'ibadah', false, 'DILARANG: pembangunan masjid/ibadah wajib 100% harta halal'],
            ['syubhat', 'umum', false, 'Default tolak bila tidak masuk whitelist'],
            ['non_halal', 'fasilitas_umum', true, 'Idem syubhat'],
            ['non_halal', 'dhuafa', true, 'Idem syubhat'],
            ['non_halal', 'operasional', true, 'Idem syubhat'],
            ['non_halal', 'ibadah', false, 'DILARANG'],
            ['non_halal', 'umum', false, 'Default tolak'],
        ];

        foreach ($aturan as [$sumber, $tujuan, $izin, $dasar]) {
            KebijakanKehalalan::updateOrCreate(
                ['kehalalan_sumber' => $sumber, 'peruntukan_tujuan' => $tujuan],
                ['diizinkan' => $izin, 'dasar' => $dasar],
            );
        }
    }

    private function seedDivisiUnit(): void
    {
        $data = [
            // kode, nama, tipe_layanan, is_komersial
            ['MA', 'Madrasah Aliyah', 'Formal', false],
            ['MTS', 'Madrasah Tsanawiyah', 'Formal', false],
            ['MAHAD', "Ma'had Aly", 'Non-Formal', false],
            ['DAPUR', 'Unit Dapur', 'Non-Formal', false],
            ['SARPRAS', 'Sarana & Prasarana', 'Non-Formal', false],
            ['USAHA', 'Unit Usaha / Koperasi', 'Non-Formal', true],
        ];

        foreach ($data as [$kode, $nama, $tipe, $komersial]) {
            DivisiUnit::updateOrCreate(
                ['kode' => $kode],
                [
                    'nama_divisi' => $nama,
                    'tipe_layanan' => $tipe,
                    'is_komersial' => $komersial,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedAkun(): void
    {
        // [kode, nama, tipe, parent_kode|null, is_kas_bank, is_postable, peruntukan|null, boleh_dana_kebajikan]
        $akun = [
            // ASET
            ['1-0000', 'ASET', 'ASET', null, false, false, null, false],
            ['1-1000', 'Kas', 'ASET', '1-0000', true, true, null, false],
            ['1-1100', 'Bank', 'ASET', '1-0000', true, true, null, false],
            ['1-1200', 'Piutang Syahriyah (SPP)', 'ASET', '1-0000', false, true, null, false],
            ['1-1300', 'Persediaan', 'ASET', '1-0000', false, true, null, false],
            // WP-23: piutang kasbon pegawai — TERPISAH dari 1-1200 (piutang santri).
            ['1-1400', 'Piutang Pegawai (Kasbon)', 'ASET', '1-0000', false, true, null, false],
            ['1-2000', 'Aset Tetap', 'ASET', '1-0000', false, false, null, false],
            ['1-2100', 'Tanah', 'ASET', '1-2000', false, true, null, false],
            ['1-2200', 'Bangunan', 'ASET', '1-2000', false, true, null, false],
            ['1-2300', 'Peralatan & Inventaris', 'ASET', '1-2000', false, true, null, false],
            // Akumulasi penyusutan = kontra-aset (G2-13). Posting kredit menurunkan aset.
            ['1-2900', 'Akumulasi Penyusutan (kontra-aset)', 'ASET', '1-2000', false, true, null, false],

            // KEWAJIBAN
            ['2-0000', 'KEWAJIBAN', 'KEWAJIBAN', null, false, false, null, false],
            ['2-1000', 'Utang Usaha', 'KEWAJIBAN', '2-0000', false, true, null, false],
            ['2-1100', 'Utang Pajak', 'KEWAJIBAN', '2-0000', false, false, null, false],
            ['2-1110', 'Utang PPN', 'KEWAJIBAN', '2-1100', false, true, null, false],
            ['2-1120', 'Utang PPh 21', 'KEWAJIBAN', '2-1100', false, true, null, false],
            ['2-1130', 'Utang PPh 22', 'KEWAJIBAN', '2-1100', false, true, null, false],
            ['2-1140', 'Utang PPh 23', 'KEWAJIBAN', '2-1100', false, true, null, false],
            ['2-1150', 'Utang PPh Final Pasal 4(2)', 'KEWAJIBAN', '2-1100', false, true, null, false],
            ['2-1160', 'Utang BPJS (potongan pekerja)', 'KEWAJIBAN', '2-1100', false, true, null, false],
            // Dana Kebajikan = titipan untuk dibersihkan (BUKAN pendapatan/ekuitas).
            // Penyaluran = sub-akun KEWAJIBAN (mengurangi titipan) — TIDAK masuk surplus/defisit.
            ['2-2000', 'Dana Kebajikan / Pembersihan Harta', 'KEWAJIBAN', '2-0000', false, true, null, false],
            ['2-2010', 'Penyaluran Dana Kebajikan — Fasilitas Umum', 'KEWAJIBAN', '2-2000', false, true, 'fasilitas_umum', true],
            ['2-2020', 'Penyaluran Dana Kebajikan — Dhuafa', 'KEWAJIBAN', '2-2000', false, true, 'dhuafa', true],
            ['2-2030', 'Penyaluran Dana Kebajikan — Operasional Non-Ibadah', 'KEWAJIBAN', '2-2000', false, true, 'operasional', true],
            // Titipan tabungan santri (kewajiban pesantren ke santri).
            ['2-3000', 'Titipan Tabungan Santri', 'KEWAJIBAN', '2-0000', false, true, null, false],

            // EKUITAS / ASET NETO
            ['3-0000', 'EKUITAS / ASET NETO', 'EKUITAS', null, false, false, null, false],
            ['3-1000', 'Aset Neto Tidak Terikat', 'EKUITAS', '3-0000', false, true, null, false],
            ['3-2000', 'Aset Neto Terikat — Wakaf', 'EKUITAS', '3-0000', false, true, null, false],
            ['3-3000', 'Aset Neto Terikat — ZIS', 'EKUITAS', '3-0000', false, true, null, false],

            // PENDAPATAN
            ['4-0000', 'PENDAPATAN', 'PENDAPATAN', null, false, false, null, false],
            ['4-1000', 'Pendapatan Syahriyah (SPP)', 'PENDAPATAN', '4-0000', false, true, null, false],
            ['4-2000', 'Pendapatan BOS / BOP', 'PENDAPATAN', '4-0000', false, true, null, false],
            ['4-3000', 'Penerimaan Wakaf', 'PENDAPATAN', '4-0000', false, true, null, false],
            ['4-4000', 'Penerimaan Zakat / Infak', 'PENDAPATAN', '4-0000', false, true, null, false],
            ['4-5000', 'Pendapatan Unit Usaha', 'PENDAPATAN', '4-0000', false, true, null, false],
            ['4-6000', 'Laba Pelepasan Aset Tetap', 'PENDAPATAN', '4-0000', false, true, null, false], // G2-13 disposal (gain)

            // BEBAN
            ['5-0000', 'BEBAN', 'BEBAN', null, false, false, null, false],
            ['5-1000', 'Beban Operasional', 'BEBAN', '5-0000', false, true, 'operasional', true],
            ['5-2000', 'Beban Gaji & Honor (Asatidz/PTK)', 'BEBAN', '5-0000', false, true, null, false],
            ['5-3000', 'Beban Pemeliharaan & Sarpras', 'BEBAN', '5-0000', false, true, null, false],
            ['5-4000', 'Beban Pengadaan Barang', 'BEBAN', '5-0000', false, true, null, false],
            ['5-5000', 'Beban Penyaluran Dana Sosial (ZIS)', 'BEBAN', '5-0000', false, true, null, false],
            // Akun sarana ibadah (halal) — penanda 'ibadah' untuk uji BLACKLIST: dana
            // syubhat DILARANG menyentuh akun ini (pembangunan masjid wajib 100% halal).
            ['5-6000', 'Beban Pembangunan / Sarana Ibadah', 'BEBAN', '5-0000', false, true, 'ibadah', false],
            ['5-7000', 'Beban Penyusutan Aset Tetap', 'BEBAN', '5-0000', false, true, null, false],   // G2-13 depresiasi
            ['5-8000', 'Rugi Pelepasan Aset Tetap', 'BEBAN', '5-0000', false, true, null, false],     // G2-13 disposal (loss)
            ['5-9000', 'Beban Selisih / Penyusutan Persediaan', 'BEBAN', '5-0000', false, true, null, false], // G2-14 opname
            ['5-9100', 'Selisih Kas', 'BEBAN', '5-0000', false, true, null, false],                   // G3-21 POS closing (selisih kas)
        ];

        $idByKode = [];
        foreach ($akun as [$kode, $nama, $tipe, $parentKode, $kasBank, $postable, $peruntukan, $bolehKebajikan]) {
            $model = Akun::updateOrCreate(
                ['kode' => $kode],
                [
                    'nama' => $nama,
                    'tipe' => $tipe,
                    'saldo_normal' => Akun::SALDO_NORMAL[$tipe],
                    'parent_id' => $parentKode !== null ? ($idByKode[$parentKode] ?? null) : null,
                    'is_kas_bank' => $kasBank,
                    'peruntukan' => $peruntukan,
                    'boleh_dana_kebajikan' => $bolehKebajikan,
                    'is_postable' => $postable,
                    'is_active' => true,
                ],
            );
            $idByKode[$kode] = $model->id;
        }
    }
}
