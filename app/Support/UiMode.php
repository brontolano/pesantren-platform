<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Auth;

/**
 * Mode tampilan panel: 'simple' (menu tugas harian saja) vs 'advanced'
 * (semua menu modul). HANYA mengatur visibilitas navigasi — RBAC tetap
 * pagar keras dan semua fungsi/route tetap jalan di kedua mode.
 *
 * Resolusi: preferensi user (users.ui_mode) → default 'simple'
 * bagi semua peran. Mode Lengkap tetap tersedia lewat toggle topbar.
 */
class UiMode
{
    /**
     * Kunci allowlist "tampilkan walau Mode Sederhana" yang dikenal.
     * Tiap kunci dipetakan ke Setting tenant boolean
     * `ui.sederhana_tampilkan_{key}` (default tak-ada → FALSE → nol regresi).
     *
     * @var array<int, string>
     */
    public const NAV_KEYS = ['ledger', 'akademik_inti', 'kesehatan_lanjut', 'pajak'];

    public static function current(): string
    {
        $user = Auth::user();
        $mode = $user->ui_mode ?? null;

        if ($mode === 'advanced') {
            return 'advanced';
        }
        if ($mode === 'simple') {
            return 'simple';
        }

        // Pengguna baru mulai dengan menu ringkas. Izin tetap dijaga oleh RBAC;
        // pengguna yang membutuhkan semua fitur dapat memilih Mode Lengkap.
        return 'simple';
    }

    public static function simple(): bool
    {
        return self::current() === 'simple';
    }

    public static function advanced(): bool
    {
        return ! self::simple();
    }

    /**
     * Apakah kunci nav termasuk allowlist yang dikenal?
     */
    public static function isNavKey(string $key): bool
    {
        return in_array($key, self::NAV_KEYS, true);
    }

    /**
     * Visibilitas navigasi untuk menu INTI yang boleh "dipromosikan" agar
     * tetap muncul di Mode Sederhana lewat setelan per-pesantren.
     *
     * Default (Setting tak-ada / tenant belum siap) → FALSE pada cabang
     * promosi → hasil PERSIS sama dengan UiMode::advanced() (NOL REGRESI).
     */
    public static function navAdvanced(string $key): bool
    {
        return self::advanced() || self::tampilDiSederhana($key);
    }

    /**
     * Baca Setting tenant boolean `ui.sederhana_tampilkan_{key}`.
     * Dibungkus try/catch: bila tabel/tenant belum siap → default FALSE.
     * Kunci di luar allowlist → selalu FALSE (tak bisa promosi sembarang menu).
     */
    protected static function tampilDiSederhana(string $key): bool
    {
        if (! self::isNavKey($key)) {
            return false;
        }

        try {
            $value = Setting::getTenantValue('ui.sederhana_tampilkan_'.$key, false);

            if (is_array($value)) {
                $value = $value['enabled'] ?? $value['value'] ?? false;
            }

            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        } catch (\Throwable) {
            // Tabel settings / tenant belum siap → perlakukan sebagai OFF.
            return false;
        }
    }
}
