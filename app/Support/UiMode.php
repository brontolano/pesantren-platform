<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Mode tampilan panel: 'simple' (menu tugas harian saja) vs 'advanced'
 * (semua menu modul). HANYA mengatur visibilitas navigasi — RBAC tetap
 * pagar keras dan semua fungsi/route tetap jalan di kedua mode.
 *
 * Resolusi: preferensi user (users.ui_mode) → default 'advanced'
 * (perilaku lama dipertahankan; simple = opt-in per user).
 */
class UiMode
{
    public static function current(): string
    {
        $mode = Auth::user()->ui_mode ?? null;

        return $mode === 'simple' ? 'simple' : 'advanced';
    }

    public static function simple(): bool
    {
        return self::current() === 'simple';
    }

    public static function advanced(): bool
    {
        return ! self::simple();
    }
}
