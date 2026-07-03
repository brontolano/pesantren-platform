<?php

namespace App\Http\Middleware;

use App\Support\PanelTheme;
use Closure;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        } catch (\Throwable $e) {
            // Fail-soft: setting belum siap / tenant belum termigrasi → tema
            // default dipakai, TIDAK melempar. Tapi jangan telan diam-diam:
            // catat agar bisa didiagnosis (mis. kolom setting hilang).
            Log::warning('ApplyPanelTheme gagal', [
                'tenant' => tenant()?->id,
                'error' => $e->getMessage(),
            ]);

            // Teruskan ke Sentry hanya bila memang sudah terpasang (opsional).
            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        }

        return $next($request);
    }
}
