<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\PanelTheme;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

/**
 * Pengaturan Tampilan panel admin — per PESANTREN (card-based theme selector).
 * Redesign 2026-06-29: dari form radio generic → card grid informatif dengan
 * kategori tema, metadata (fitur, recommended roles), + live preview iFrame.
 *
 * State: $selectedTheme, $selectedColor, $previewTheme, $previewColor (preview ≠ saved).
 * Real-time preview inject CSS var ke iFrame; Save via DB Setting table.
 */
class PengaturanTampilan extends Page
{

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationLabel = 'Tampilan & Tema';

    protected static ?string $title = 'Tampilan & Tema';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan Sistem';

    protected static ?int $navigationSort = 15;

    protected static ?string $slug = 'pengaturan/tampilan';

    protected string $view = 'filament.pages.pengaturan-tampilan';

    // State: pilihan user (preview vs saved)
    public ?string $selectedTheme = null;
    public ?string $selectedColor = null;
    public ?string $previewTheme = null;
    public ?string $previewColor = null;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && (Auth::user()?->can('manage_tenant_settings') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->selectedTheme = PanelTheme::theme();
        $this->selectedColor = PanelTheme::color();
        $this->previewTheme = $this->selectedTheme;
        $this->previewColor = $this->selectedColor;
    }

    // Computed properties untuk UI
    #[Computed]
    public function themes()
    {
        return PanelTheme::allThemesWithMetadata();
    }

    #[Computed]
    public function themesByCategory()
    {
        return collect(PanelTheme::categories())
            ->mapWithKeys(fn (string $cat) => [
                $cat => PanelTheme::themesByCategory($cat)
            ])
            ->all();
    }

    #[Computed]
    public function applicableColors()
    {
        $themeMeta = PanelTheme::THEMES[$this->previewTheme ?? PanelTheme::theme()] ?? [];

        // Tema berpalet penuh → hanya gunakan palet tema itu
        if (isset($themeMeta['primary'])) {
            return [
                'locked' => true,
                'reason' => "Tema '{$themeMeta['label']}' memiliki palet sendiri.",
                'colors' => [$this->previewTheme => PanelTheme::themeWithMetadata($this->previewTheme)],
            ];
        }

        // Tema tanpa palet → semua preset warna applicable
        return [
            'locked' => false,
            'colors' => collect(PanelTheme::COLORS)
                ->map(fn (array $c, string $k) => array_merge(['key' => $k], $c))
                ->all(),
        ];
    }

    #[Computed]
    public function previewCssVars(): string
    {
        return $this->generateCssVars($this->previewTheme ?? PanelTheme::theme(), $this->previewColor ?? PanelTheme::color());
    }

    // Methods untuk interaksi user
    public function selectTheme(string $themeKey): void
    {
        if (array_key_exists($themeKey, PanelTheme::THEMES)) {
            $this->previewTheme = $themeKey;

            // Jika tema punya palet bawaan, lock warna ke tema itu
            $themeMeta = PanelTheme::THEMES[$themeKey];
            if (isset($themeMeta['primary'])) {
                $this->previewColor = $themeKey;
            }
        }
    }

    public function selectColor(string $colorKey): void
    {
        // Hanya allow jika tidak locked
        if (!$this->applicableColors()['locked'] && array_key_exists($colorKey, PanelTheme::COLORS)) {
            $this->previewColor = $colorKey;
        }
    }

    public function resetToSaved(): void
    {
        $this->previewTheme = $this->selectedTheme;
        $this->previewColor = $this->selectedColor;
    }

    public function saveThemeSettings(): void
    {
        try {
            Setting::updateOrCreate(
                ['key' => PanelTheme::SETTING_THEME],
                ['value' => ['text' => $this->previewTheme]]
            );
            Setting::updateOrCreate(
                ['key' => PanelTheme::SETTING_COLOR],
                ['value' => ['text' => $this->previewColor]]
            );

            $this->selectedTheme = $this->previewTheme;
            $this->selectedColor = $this->previewColor;

            Notification::make()
                ->title('✓ Tampilan disimpan')
                ->body('Muat ulang halaman untuk melihat tema baru diterapkan penuh.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error menyimpan tampilan')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function generateCssVars(string $themeKey, string $colorKey): string
    {
        $theme = PanelTheme::THEMES[$themeKey] ?? [];
        $color = PanelTheme::COLORS[$colorKey] ?? [];

        // Tema berpalet penuh → gunakan paletnya
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

    /**
     * Generate preview HTML yang embedded di iFrame.
     * CSS vars akan di-inject secara real-time via JS.
     */
    public function generatePreviewHtml(): string
    {
        $cssVars = htmlspecialchars($this->previewCssVars());
        $themeName = htmlspecialchars(PanelTheme::THEMES[$this->previewTheme]['label'] ?? '');

        return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preview Tema</title>
    <style data-theme-css>
        :root { {$cssVars} }
    </style>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f3f4f6;
            color: #1f2937;
        }
        .container { display: flex; min-height: 100vh; }
        .sidebar {
            width: 200px;
            background: var(--tema-sidebar-header, #ffffff);
            border-right: 1px solid var(--tema-sidebar-header-border, #e5e7eb);
            padding: 16px;
            box-shadow: inset -1px 0 0 rgba(0,0,0,0.05);
        }
        .sidebar-logo {
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 24px;
            color: #1f2937;
        }
        .sidebar-item {
            padding: 10px 12px;
            margin: 4px 0;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            color: #6b7280;
            transition: all 200ms;
        }
        .sidebar-item.active {
            background: var(--tema-active-bg, #ecfdf5);
            color: var(--tema-active-fg, #047857);
            font-weight: 600;
        }
        .sidebar-item:hover:not(.active) {
            background: rgba(0,0,0,0.04);
        }
        .content {
            flex: 1;
            padding: 32px;
            background: #fafafa;
        }
        .header {
            margin-bottom: 24px;
        }
        .header h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #1f2937;
        }
        .header p {
            color: #6b7280;
            font-size: 14px;
        }
        .card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin: 12px 0;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .card h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1f2937;
        }
        .card p {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 12px;
            line-height: 1.5;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            background: var(--tema-sidebar-header, #059669);
            color: white;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
            transition: all 200ms;
        }
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        .theme-name {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-logo">Pesantren</div>
            <div class="sidebar-item active">📊 Dashboard</div>
            <div class="sidebar-item">👥 Santri</div>
            <div class="sidebar-item">💰 Keuangan</div>
            <div class="sidebar-item">📚 Akademik</div>
            <div class="sidebar-item">⚙️ Pengaturan</div>
        </div>
        <div class="content">
            <div class="header">
                <h1>Pratinjau Tema</h1>
                <p>Perubahan diterapkan real-time saat Anda memilih tema & warna.</p>
            </div>
            <div class="card">
                <h3>Contoh Kartu Panel</h3>
                <p>Kartu ini menunjukkan styling dengan palet warna pilihan. Perhatikan warna sidebar & elemen interaktif.</p>
                <button class="btn">Aksi Contoh</button>
                <div class="theme-name">Tema: {$themeName}</div>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

}
