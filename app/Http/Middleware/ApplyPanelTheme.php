<?php

namespace App\Http\Middleware;

use App\Support\PanelTheme;
use Closure;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Http\Request;

/**
 * Terapkan tema panel per tenant SAAT RUNTIME (tanpa rebuild assets):
 * - Preset warna → FilamentColor::register (palet tombol/badge/link).
 * - Tema layout  → mutasi panel aktif (mis. navigasi atas).
 * Dipasang SETELAH InitializeTenancyByDomain. Gagal apa pun → tampilan bawaan.
 */
class ApplyPanelTheme
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $primary = PanelTheme::primaryHex();
            if ($primary !== null) {
                FilamentColor::register(['primary' => Color::hex($primary)]);
            }

            if (PanelTheme::topNavigation()) {
                Filament::getCurrentPanel()?->topNavigation();
            }
        } catch (\Throwable) {
            // Setting belum siap / tenant belum termigrasi → biarkan bawaan.
        }

        return $next($request);
    }
}
