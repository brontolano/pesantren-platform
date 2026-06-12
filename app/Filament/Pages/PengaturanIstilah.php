<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Support\IstilahRegistry;

/**
 * Pengaturan Istilah Pesantren (WP-31) — per tenant.
 * Tiap pesantren bisa mengganti sebutan (mis. "Musyrif" → "Murabbi").
 * Disimpan ke Setting `label.istilah`; kosongkan kolom = kembali ke bawaan.
 */
class PengaturanIstilah extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-language';

    protected static ?string $navigationLabel = 'Istilah Pesantren';

    protected static ?string $title = 'Istilah Pesantren';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan & Mutu';

    protected static ?int $navigationSort = 16;

    protected static ?string $slug = 'pengaturan/istilah';

    protected string $view = 'filament.pages.pengaturan-istilah';

    public ?array $data = [];

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
        $tersimpan = Setting::getTenantValue(IstilahRegistry::SETTING_KEY);
        if (is_string($tersimpan)) {
            $decoded = json_decode($tersimpan, true);
            $tersimpan = is_array($decoded) ? $decoded : [];
        }
        $tersimpan = is_array($tersimpan) ? $tersimpan : [];

        $labels = [];
        foreach (IstilahRegistry::DEFAULTS as $kunci => $bawaan) {
            $nilai = $tersimpan[$kunci] ?? null;
            $labels[$kunci] = (is_string($nilai) && trim($nilai) !== '' && trim($nilai) !== $bawaan)
                ? trim($nilai)
                : null;
        }

        $this->form->fill(['labels' => $labels]);
    }

    public function form(Schema $schema): Schema
    {
        $fields = [];
        foreach (IstilahRegistry::DEFAULTS as $kunci => $bawaan) {
            $fields[] = TextInput::make("labels.{$kunci}")
                ->label($bawaan)
                ->placeholder($bawaan.' (bawaan)')
                ->helperText('Kunci: '.$kunci.' — kosongkan untuk memakai sebutan bawaan.')
                ->maxLength(100);
        }

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Istilah Pesantren')
                    ->description('Sesuaikan sebutan di panel dengan kebiasaan pesantren Anda (mis. "Musyrif" menjadi "Murabbi"). Kosongkan kolom untuk kembali ke sebutan bawaan.')
                    ->icon('heroicon-o-language')
                    ->columns(2)
                    ->schema($fields),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $labels = is_array($state['labels'] ?? null) ? $state['labels'] : [];

        $simpan = [];
        foreach (IstilahRegistry::DEFAULTS as $kunci => $bawaan) {
            $nilai = $labels[$kunci] ?? null;
            if (is_string($nilai) && trim($nilai) !== '' && trim($nilai) !== $bawaan) {
                $simpan[$kunci] = trim($nilai);
            }
        }

        Setting::updateOrCreate(
            ['key' => IstilahRegistry::SETTING_KEY],
            ['value' => $simpan],
        );

        IstilahRegistry::flushCache();

        Notification::make()
            ->title('Istilah disimpan')
            ->body('Sebutan baru langsung dipakai di seluruh panel pesantren ini.')
            ->success()
            ->send();
    }
}
