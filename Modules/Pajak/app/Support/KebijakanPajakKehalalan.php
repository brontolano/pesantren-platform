<?php

namespace Modules\Pajak\Support;

/**
 * G1-04 — Kebijakan Pajak berbasis Kehalalan (diferensiator syariah).
 *
 * Prinsip: hak negara atas pajak hanya bersumber dari transaksi HALAL. Dana
 * syubhat/non-halal (mis. bunga bank) diperlakukan sebagai dana
 * pembersihan/kebajikan — BUKAN pendapatan sah — sehingga tidak menimbulkan
 * kewajiban pajak negara (PPN/PPh dipungut = 0).
 *
 * Titik keputusan tunggal; MesinPajakService memanggil kebijakan ini, tidak
 * menyalin aturannya.
 */
class KebijakanPajakKehalalan
{
    /** Klasifikasi kehalalan yang dibebaskan dari pajak negara. */
    public const BEBAS = ['syubhat', 'non_halal'];

    /** Apakah transaksi dari sumber dana ber-kehalalan ini bebas pajak negara? */
    public function bebasPajakNegara(?string $kehalalan): bool
    {
        return in_array($kehalalan, self::BEBAS, true);
    }
}
