<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\SantriLoginMethod;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Pengaturan App Santri — pesantren memilih metode login yang ditawarkan ke
 * santri (NIS+PIN selalu aktif; QR siap pakai; sidik jari/NFC butuh perangkat).
 */
class PengaturanAppSantri extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationLabel = 'Pengaturan App Santri';

    protected static ?string $title = 'Pengaturan App Santri';

    protected static string|\UnitEnum|null $navigationGroup = 'Kesantrian';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'app-santri/pengaturan';

    protected string $view = 'filament.pages.pengaturan-app-santri';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && (Auth::user()?->can('manage_tenant_settings') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill([
            'metode' => SantriLoginMethod::enabled(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $opsi = [];
        $desk = [];
        foreach (SantriLoginMethod::METODE as $key => $m) {
            $tag = $m['wajib'] ? ' (selalu aktif)' : ($m['siap'] ? '' : ' — butuh perangkat');
            $opsi[$key] = $m['label'].$tag;
            $desk[$key] = $m['desc'];
        }

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Metode Login Santri')
                    ->description('Pilih cara santri masuk ke App Santri. Santri di pondok tak pegang HP → login di perangkat pondok. NIS + PIN selalu tersedia.')
                    ->icon('heroicon-o-key')
                    ->schema([
                        CheckboxList::make('metode')
                            ->label('')
                            ->options($opsi)
                            ->descriptions($desk)
                            ->columns(1)
                            ->default(['nis_pin']),
                    ]),
            ]);
    }

    public function save(): void
    {
        $metode = (array) ($this->form->getState()['metode'] ?? []);
        // nis_pin wajib; saring hanya key dikenal.
        $metode = array_values(array_unique(array_merge(['nis_pin'], array_intersect($metode, array_keys(SantriLoginMethod::METODE)))));

        Setting::updateOrCreate(['key' => SantriLoginMethod::SETTING_KEY], ['value' => $metode]);

        Notification::make()->title('Pengaturan App Santri disimpan')->success()->send();
    }
}
