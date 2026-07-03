<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\PanelTheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * API untuk manajemen tema panel admin per tenant.
 * Endpoint ini hanya tersedia untuk authenticated user dengan manage_tenant_settings.
 */
class ThemeApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * GET /api/tenant/themes
     * Daftar semua tema dengan metadata lengkap + tema aktif saat ini.
     */
    public function listThemes(): JsonResponse
    {
        if (!Auth::user()?->can('manage_tenant_settings')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'current_theme' => PanelTheme::theme(),
            'current_color' => PanelTheme::color(),
            'themes' => PanelTheme::allThemesWithMetadata(),
            'categories' => collect(PanelTheme::categories())
                ->map(fn (string $cat) => [
                    'id' => $cat,
                    'label' => PanelTheme::categoryLabel($cat),
                    'count' => count(PanelTheme::themesByCategory($cat)),
                ])
                ->values()
                ->all(),
            'applicable_colors' => PanelTheme::applicableColors(),
        ], 200);
    }

    /**
     * GET /api/tenant/themes/{themeKey}
     * Detail tema + CSS vars preview untuk live inject.
     */
    public function getTheme(string $themeKey): JsonResponse
    {
        if (!Auth::user()?->can('manage_tenant_settings')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $theme = PanelTheme::themeWithMetadata($themeKey);
        if (!$theme) {
            return response()->json(['error' => 'Theme not found'], 404);
        }

        $colors = PanelTheme::applicableColors();
        $cssVars = $this->generateCssVars($themeKey, PanelTheme::color());

        return response()->json([
            'theme' => $theme,
            'applicable_colors' => $colors,
            'css_vars' => $cssVars,
        ], 200);
    }

    /**
     * PUT /api/tenant/theme-settings
     * Simpan pilihan tema & warna untuk tenant aktif.
     *
     * Payload: {theme: 'slate-pro', color: 'bawaan'}
     */
    public function updateThemeSettings(Request $request): JsonResponse
    {
        if (!Auth::user()?->can('manage_tenant_settings')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'theme' => ['required', 'string', 'in:' . implode(',', array_keys(PanelTheme::THEMES))],
            'color' => ['required', 'string', 'in:' . implode(',', array_keys(PanelTheme::COLORS))],
        ]);

        // Validasi bahwa tema & warna combo valid
        $themeMeta = PanelTheme::THEMES[$validated['theme']] ?? null;
        if (!$themeMeta) {
            return response()->json(['error' => 'Invalid theme'], 400);
        }

        // Jika tema punya palet bawaan (primary exists), warna preset diabaikan
        if (isset($themeMeta['primary'])) {
            $validated['color'] = $validated['theme']; // lock ke palet tema
        }

        try {
            Setting::updateOrCreate(
                ['key' => PanelTheme::SETTING_THEME],
                ['value' => ['text' => $validated['theme']]]
            );
            Setting::updateOrCreate(
                ['key' => PanelTheme::SETTING_COLOR],
                ['value' => ['text' => $validated['color']]]
            );

            return response()->json([
                'success' => true,
                'message' => 'Tema disimpan. Muat ulang halaman untuk melihat perubahan.',
                'current_theme' => $validated['theme'],
                'current_color' => $validated['color'],
                'css_vars' => $this->generateCssVars($validated['theme'], $validated['color']),
            ], 200);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to save theme: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Generates CSS variables untuk tema & warna tertentu (untuk preview inject).
     */
    private function generateCssVars(string $themeKey, string $colorKey): string
    {
        $theme = PanelTheme::THEMES[$themeKey] ?? [];
        $color = PanelTheme::COLORS[$colorKey] ?? [];

        // Tema berpalet penuh → gunakan paletnya, abaikan preset warna
        if (isset($theme['primary'])) {
            $color = $theme;
        }

        $vars = array_merge(
            $color['vars'] ?? [],
            $theme['vars'] ?? [],
        );

        return implode('', array_map(
            fn (string $k, string $v): string => "{$k}:{$v};",
            array_keys($vars),
            $vars,
        ));
    }
}
