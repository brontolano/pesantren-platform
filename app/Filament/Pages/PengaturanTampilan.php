<?php

namespace App\Filament\Pages;

use App\Support\PanelTheme;
use App\Support\ThemePackage;
use App\Support\ThemeThumbnail;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

/**
 * Pengaturan Tampilan panel admin — per PESANTREN.
 *
 * Redesign 2026-07-03: halaman DISEDERHANAKAN menjadi grid kartu Paket Tema
 * saja (tips 1 baris + kartu: thumbnail, deskripsi, Terapkan, badge Aktif).
 * Seksi "Pratinjau Langsung" (iFrame) dan "Kustom Lanjutan" (pilih tema &
 * warna manual) DIHAPUS — paket = tema eksternal jadi, apa yang dipilih =
 * apa yang didapat, tanpa utak-atik.
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

    /** Tema & warna TERSIMPAN tenant (untuk highlight kartu Aktif). */
    public ?string $selectedTheme = null;

    public ?string $selectedColor = null;

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
    }

    /** Semua paket tema untuk kartu grid. */
    #[Computed]
    public function packages()
    {
        return ThemePackage::all();
    }

    /** Peran user login (untuk badge "Disarankan untuk Anda"). */
    #[Computed]
    public function currentRoles(): array
    {
        $user = Auth::user();

        if ($user === null || ! method_exists($user, 'getRoleNames')) {
            return [];
        }

        try {
            return $user->getRoleNames()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** Key paket yang cocok dgn tema+warna tersimpan (highlight kartu aktif). */
    #[Computed]
    public function activePackageKey(): ?string
    {
        return ThemePackage::activeKey($this->selectedTheme ?? '', $this->selectedColor ?? '');
    }

    /** SVG thumbnail generatif untuk satu paket (dipakai blade). */
    public function packageThumbnail(string $packageKey): string
    {
        return ThemeThumbnail::inlineForPackage($packageKey);
    }

    /** Apakah paket ini disarankan untuk peran user login? */
    public function packageRecommended(string $packageKey): bool
    {
        return ThemePackage::isRecommendedForRoles($packageKey, $this->currentRoles());
    }

    /** Terapkan paket 1-klik: tulis Setting tema + warna tenant, lalu segarkan. */
    public function applyPackage(string $packageKey): void
    {
        $pkg = ThemePackage::get($packageKey);

        if ($pkg === null) {
            Notification::make()
                ->title('Paket tidak dikenal')
                ->danger()
                ->send();

            return;
        }

        try {
            if (! ThemePackage::applyToSetting($packageKey)) {
                throw new \RuntimeException('Paket merujuk tema/warna tidak valid.');
            }

            $this->selectedTheme = $pkg['theme'];
            $this->selectedColor = ThemePackage::effectiveColor($packageKey);

            Notification::make()
                ->title('✓ Paket "'.$pkg['name'].'" diterapkan')
                ->body('Memuat tampilan baru…')
                ->success()
                ->send();

            // Tema eksternal = file CSS di <head> — hanya termuat pada full page
            // load. Muat ulang otomatis (jeda singkat agar toast sempat terlihat)
            // supaya pergantian terasa LANGSUNG seperti versi CSS-vars dulu.
            //
            // Mode gelap: pilihan lama user tersimpan di localStorage('theme')
            // dan MENGALAHKAN defaultThemeMode panel. Saat ganti paket, ikuti
            // mode bawaan tema barunya (Moonlight → dark; lainnya → system)
            // agar tema gelap-default benar-benar terlihat gelap. User tetap
            // bisa toggle sesudahnya.
            $mode = PanelTheme::defaultDarkFor($pkg['theme']) ? 'dark' : 'system';
            $this->js("localStorage.setItem('theme', '{$mode}'); setTimeout(() => window.location.reload(), 500)");
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error menerapkan paket')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
