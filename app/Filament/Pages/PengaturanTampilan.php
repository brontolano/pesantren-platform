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

}
