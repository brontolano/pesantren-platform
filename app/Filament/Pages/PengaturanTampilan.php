<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\PanelTheme;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Pengaturan Tampilan panel admin — per PESANTREN (Setting per tenant).
 * Tema (layout) dan Preset Warna sengaja dipisah: tema mengubah susunan
 * panel, preset warna hanya mengubah palet.
 */
class PengaturanTampilan extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationLabel = 'Tampilan & Tema';

    protected static ?string $title = 'Tampilan & Tema';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan & Mutu';

    protected static ?int $navigationSort = 15;

    protected static ?string $slug = 'pengaturan/tampilan';

    protected string $view = 'filament.pages.pengaturan-tampilan';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && (Auth::user()?->can('manage_tenant_settings') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Tampil di KEDUA mode UI — pilihan tampilan adalah keputusan
        // operasional pesantren, jangan tersembunyi di mode Lengkap saja.
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill([
            'tema' => PanelTheme::theme(),
            'warna' => PanelTheme::color(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $temaOpsi = [];
        $temaDesk = [];
        foreach (PanelTheme::THEMES as $key => $t) {
            $temaOpsi[$key] = $t['label'];
            $temaDesk[$key] = $t['desc'];
        }

        $warnaOpsi = [];
        foreach (PanelTheme::COLORS as $key => $w) {
            $warnaOpsi[$key] = $w['label'];
        }

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Tema Panel')
                    ->description('Susunan/layout panel admin. Berlaku untuk semua pengguna pesantren ini.')
                    ->icon('heroicon-o-rectangle-group')
                    ->schema([
                        Radio::make('tema')
                            ->label('')
                            ->options($temaOpsi)
                            ->descriptions($temaDesk)
                            ->default('bawaan')
                            ->required(),
                    ]),

                Section::make('Preset Warna')
                    ->description('Palet warna panel (header, menu aktif, tombol). Catatan: tema yang membawa palet sendiri (mis. Baitul Maal) selalu memakai palet temanya — pilihan di sini diabaikan.')
                    ->icon('heroicon-o-swatch')
                    ->schema([
                        Radio::make('warna')
                            ->label('')
                            ->options($warnaOpsi)
                            ->descriptions($this->warnaSwatchDescriptions())
                            ->default('bawaan')
                            ->required(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $tema = array_key_exists($state['tema'] ?? '', PanelTheme::THEMES) ? $state['tema'] : 'bawaan';
        $warna = array_key_exists($state['warna'] ?? '', PanelTheme::COLORS) ? $state['warna'] : 'bawaan';

        Setting::updateOrCreate(['key' => PanelTheme::SETTING_THEME], ['value' => ['text' => $tema]]);
        Setting::updateOrCreate(['key' => PanelTheme::SETTING_COLOR], ['value' => ['text' => $warna]]);

        Notification::make()
            ->title('Tampilan disimpan')
            ->body('Muat ulang halaman untuk melihat tema baru diterapkan penuh.')
            ->success()
            ->send();

        $this->redirect(static::getUrl());
    }

    /** Deskripsi opsi warna: tampilkan hex utama agar ada gambaran tanpa swatch. */
    private function warnaSwatchDescriptions(): array
    {
        $desk = [];
        foreach (PanelTheme::COLORS as $key => $w) {
            $desk[$key] = $w['primary'] === null
                ? 'Palet hijau standar — tampilan saat ini.'
                : 'Warna utama '.$w['primary'];
        }

        return $desk;
    }
}
