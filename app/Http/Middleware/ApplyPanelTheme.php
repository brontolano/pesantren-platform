<?php

namespace App\Http\Middleware;

use App\Support\PanelTheme;
use Closure;
use Filament\Enums\ThemeMode;
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
            // Palet warna: SELALU register set lengkap yang deterministik agar
            // tidak bocor antar request/tenant di worker long-running.
            // Baseline = palet provider (Emerald/Slate) + default Filament,
            // lalu DITIMPA palet vendor tema aktif (replika ->colors() plugin
            // vendor — mis. Nord: gray polar-night + primer frost) atau
            // primer preset warna utk tema tanpa palet.
            FilamentColor::register($this->resolveColors());

            $panel = Filament::getCurrentPanel();

            if ($panel !== null) {
                // Arsitektur "multi-build CSS + pilih runtime": entry CSS Vite
                // tema aktif dipilih per-request (getTheme() Filament baru
                // dievaluasi saat render — setelah middleware ini). SELALU
                // di-set (juga saat bawaan) agar tidak bocor antar request di
                // worker long-running (FrankenPHP). Font idem (Brisk = Kumbh
                // Sans via provider Bunny bawaan; null = font platform).
                $panel->viteTheme(PanelTheme::cssEntry());
                $panel->font(PanelTheme::font() ?? 'Plus Jakarta Sans');

                // Tema gelap-default (Moonlight: seluruh CSS di-scope .dark)
                // → default theme mode DARK; user tetap bisa toggle. SELALU
                // di-set (System bila bukan) agar tak bocor antar request.
                $panel->defaultThemeMode(
                    PanelTheme::defaultDark() ? ThemeMode::Dark : ThemeMode::System,
                );

                if (PanelTheme::topNavigation()) {
                    $panel->topNavigation();
                }
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

    /**
     * Set warna final utk FilamentColor::register — deterministik per-request.
     *
     * @return array<string, mixed>
     */
    private function resolveColors(): array
    {
        // Baseline identitas platform (samakan dgn ->colors() panel provider)
        // + default Filament utk key lain yang mungkin ditimpa tema.
        $colors = [
            'primary' => Color::Emerald,
            'gray' => Color::Slate,
            'danger' => Color::Red,
            'info' => Color::Blue,
            'success' => Color::Green,
            'warning' => Color::Amber,
        ];

        // Palet vendor tema eksternal MENGIKAT (replika plugin vendor).
        $palette = PanelTheme::palette();

        if ($palette !== []) {
            foreach ($palette as $key => $value) {
                $colors[$key] = $this->toColor($value);
            }

            return $colors;
        }

        // Tema tanpa palet vendor: hormati preset warna tenant (primer saja).
        $primary = PanelTheme::primaryHex();

        if ($primary !== null) {
            $colors['primary'] = Color::hex($primary);
        }

        return $colors;
    }

    /** Konversi nilai palet: hex string / nama palet Filament / array shade. */
    private function toColor(string|array $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (str_starts_with($value, '#')) {
            return Color::hex($value);
        }

        return constant(Color::class.'::'.ucfirst($value));
    }
}
