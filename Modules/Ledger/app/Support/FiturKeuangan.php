<?php

namespace Modules\Ledger\Support;

use App\Models\Setting;

/**
 * Pembaca feature-flag keuangan per tenant (Configuration-over-Customization §14).
 */
class FiturKeuangan
{
    public const KEY_PEMISAHAN_KEHALALAN = 'keuangan.pemisahan_kehalalan_aktif';

    public const KEY_JENIS_LEMBAGA = 'keuangan.jenis_lembaga';

    /** Jenis lembaga tenant → menentukan rezim BOS Madrasah vs BOP Pesantren (Koreksi #2). */
    public static function jenisLembaga(): string
    {
        return Setting::getTenantStringValue(self::KEY_JENIS_LEMBAGA, 'pesantren') ?: 'pesantren';
    }

    /** Rezim sumber dana negara default sesuai jenis lembaga. */
    public static function rezimDanaNegara(): string
    {
        return self::jenisLembaga() === 'madrasah' ? 'bos_madrasah' : 'bop_pesantren';
    }

    /** Tarif honor per poin (configurable; default Rp10.000). */
    public static function tarifPoin(): float
    {
        $v = Setting::getTenantValue('keuangan.payroll.tarif_poin', null);
        if (is_array($v)) {
            $v = $v['value'] ?? null;
        }

        return $v !== null ? (float) $v : 10000.0;
    }

    /** Ambang DSCR lembaga (configurable; default 1,25× — Koreksi #1, BUKAN 2,5×). */
    public static function ambangDscr(): float
    {
        $v = Setting::getTenantValue('keuangan.dscr.ambang', null);
        if (is_array($v)) {
            $v = $v['value'] ?? null;
        }

        return $v !== null ? (float) $v : 1.25;
    }

    /** Persentase PPh 21 sederhana (configurable; default 0% — banyak honor di bawah PTKP). */
    public static function pph21Persen(): float
    {
        $v = Setting::getTenantValue('keuangan.payroll.pph21_persen', null);
        if (is_array($v)) {
            $v = $v['value'] ?? null;
        }

        return $v !== null ? (float) $v : 0.0;
    }

    /** Apakah pemisahan dana halal/syubhat diaktifkan untuk tenant ini? Default OFF (opt-in). */
    public static function pemisahanKehalalanAktif(): bool
    {
        $value = Setting::getTenantValue(self::KEY_PEMISAHAN_KEHALALAN, false);

        if (is_array($value)) {
            $value = $value['enabled'] ?? $value['value'] ?? false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
