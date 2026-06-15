<?php

namespace App\Filament\Pages;

use App\Services\KebijakanService;
use App\Support\KebijakanRegistry;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * WP-34 — Halaman "Kebijakan Pesantren": SEMUA saklar/ambang kebijakan modul
 * dalam satu tempat, berbahasa awam, terkelompok, bisa dicari, bisa
 * dikembalikan ke bawaan, plus ekspor/impor JSON dan preset bawaan.
 * Bukti janji platform: sistem ikut pesantren, bukan sebaliknya.
 */
class KebijakanPesantren extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Kebijakan Pesantren';

    protected static ?string $title = 'Kebijakan Pesantren';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'pengaturan/kebijakan';

    protected string $view = 'filament.pages.kebijakan-pesantren';

    public ?array $data = [];

    public string $cari = '';

    /** Pratinjau impor menunggu konfirmasi (null = tidak ada). */
    public ?array $importPratinjau = null;

    public ?array $importPayload = null;

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
        $this->form->fill($this->stateDariSetting());
    }

    /** State form dari nilai efektif (override ?? default registry). */
    private function stateDariSetting(): array
    {
        $svc = app(KebijakanService::class);
        $state = [];

        foreach (KebijakanRegistry::entries() as $key => $entry) {
            $nilai = $svc->nilai($key);
            $state[self::fieldName($key)] = $entry['tipe'] === 'json'
                ? ($nilai === null ? '' : json_encode($nilai, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                : $nilai;
        }

        return $state;
    }

    public static function fieldName(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];

        foreach (KebijakanRegistry::byKelompok() as $kelompok => $entries) {
            $fields = [];
            foreach ($entries as $key => $entry) {
                $fields[] = $this->bidangUntuk($key, $entry);
            }

            $tabs[] = Tab::make(KebijakanRegistry::KELOMPOK[$kelompok])
                ->schema([
                    Section::make()
                        ->headerActions([
                            Action::make('reset_'.$kelompok)
                                ->label('Kembalikan kelompok ini ke bawaan')
                                ->icon('heroicon-o-arrow-uturn-left')
                                ->color('gray')
                                ->requiresConfirmation()
                                ->modalHeading('Kembalikan seluruh kelompok ke bawaan?')
                                ->modalDescription('Semua kebijakan pada kelompok ini akan kembali ke nilai bawaan sistem.')
                                ->action(fn () => $this->resetKelompok($kelompok)),
                        ])
                        ->schema($fields),
                ]);
        }

        return $schema
            ->statePath('data')
            ->components([Tabs::make('kebijakan')->tabs($tabs)->persistTab()]);
    }

    /** Satu field form sesuai tipe entry registry. */
    private function bidangUntuk(string $key, array $entry): object
    {
        $name = self::fieldName($key);
        $label = $entry['label'];
        $hint = fn () => app(KebijakanService::class)->adaOverride($key) ? 'Diubah dari bawaan' : null;
        $visible = fn (): bool => $this->cocokPencarian($key, $entry);

        $field = match ($entry['tipe']) {
            'bool' => Toggle::make($name)->label($label),
            'number' => TextInput::make($name)->label($label)->numeric()->step('any')
                ->minValue($entry['min'] ?? null)
                ->maxValue($entry['max'] ?? null),
            'select' => Select::make($name)->label($label)->options($entry['options'] ?? [])->native(false),
            'json' => Textarea::make($name)->label($label)->rows(5)
                ->helperText($entry['deskripsi'].' (Format JSON — biarkan kosong untuk ikut bawaan.)'),
            default => TextInput::make($name)->label($label),
        };

        if ($entry['tipe'] !== 'json') {
            $field = $field->helperText($entry['deskripsi']);
        }

        return $field
            ->visible($visible)
            ->hint($hint)
            ->hintColor('warning')
            ->hintAction(
                Action::make('reset_'.$name)
                    ->label('Kembalikan ke bawaan')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn (): bool => app(KebijakanService::class)->adaOverride($key))
                    ->action(fn () => $this->resetKey($key)),
            );
    }

    private function cocokPencarian(string $key, array $entry): bool
    {
        $q = trim(mb_strtolower($this->cari));
        if ($q === '') {
            return true;
        }

        return str_contains(mb_strtolower($entry['label']), $q)
            || str_contains(mb_strtolower($entry['deskripsi']), $q)
            || str_contains(mb_strtolower($key), $q);
    }

    public function save(): void
    {
        $svc = app(KebijakanService::class);
        $state = $this->form->getState();
        $berubah = 0;

        foreach (KebijakanRegistry::entries() as $key => $entry) {
            $name = self::fieldName($key);
            if (! array_key_exists($name, $state)) {
                continue; // field tersembunyi oleh pencarian — jangan sentuh
            }

            $mentah = $state[$name];

            if ($entry['tipe'] === 'json') {
                if ($mentah === null || trim((string) $mentah) === '') {
                    continue; // kosong = biarkan apa adanya (reset lewat tombol khusus)
                }
                $nilai = json_decode((string) $mentah, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Notification::make()
                        ->title('JSON tidak valid pada "'.$entry['label'].'"')
                        ->body('Periksa kembali formatnya — perubahan kebijakan lain belum disimpan.')
                        ->danger()->send();

                    return;
                }
            } else {
                $nilai = match ($entry['tipe']) {
                    'bool' => (bool) $mentah,
                    'number' => $mentah === null || $mentah === '' ? $entry['default'] : (is_numeric($mentah) ? $mentah + 0 : $entry['default']),
                    default => (string) $mentah,
                };
            }

            if ($svc->nilai($key) !== $nilai) {
                try {
                    $svc->set($key, $nilai, Auth::user());
                } catch (InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Nilai tidak valid pada "'.$entry['label'].'"')
                        ->body($e->getMessage())
                        ->danger()->send();

                    return;
                }
                $berubah++;
            }
        }

        $this->form->fill($this->stateDariSetting());

        Notification::make()
            ->title($berubah > 0 ? "Kebijakan disimpan ({$berubah} perubahan)" : 'Tidak ada perubahan')
            ->success()->send();
    }

    public function resetKey(string $key): void
    {
        app(KebijakanService::class)->reset($key, Auth::user());
        $this->form->fill($this->stateDariSetting());

        Notification::make()->title('Dikembalikan ke bawaan')->success()->send();
    }

    public function resetKelompok(string $kelompok): void
    {
        app(KebijakanService::class)->resetKelompok($kelompok, Auth::user());
        $this->form->fill($this->stateDariSetting());

        Notification::make()->title('Kelompok dikembalikan ke bawaan')->success()->send();
    }

    public function terapkanImpor(): void
    {
        if ($this->importPayload === null) {
            return;
        }

        $jumlah = app(KebijakanService::class)->terapkanImpor($this->importPayload, Auth::user());
        $this->importPratinjau = null;
        $this->importPayload = null;
        $this->form->fill($this->stateDariSetting());

        Notification::make()->title("Impor diterapkan ({$jumlah} kebijakan berubah)")->success()->send();
    }

    public function batalImpor(): void
    {
        $this->importPratinjau = null;
        $this->importPayload = null;
    }

    protected function getHeaderActions(): array
    {
        $presetOptions = collect(KebijakanRegistry::presets())
            ->mapWithKeys(fn (array $p, string $k) => [$k => $p['label'].' — '.$p['deskripsi']])
            ->all();

        return [
            Action::make('ekspor')
                ->label('Ekspor JSON')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $payload = app(KebijakanService::class)->ekspor();

                    return response()->streamDownload(
                        fn () => print json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'kebijakan-'.tenant('id').'-'.now()->format('Ymd-His').'.json',
                        ['Content-Type' => 'application/json'],
                    );
                }),

            Action::make('impor')
                ->label('Impor JSON')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->schema([
                    Textarea::make('json')
                        ->label('Isi berkas JSON kebijakan')
                        ->helperText('Buka berkas hasil Ekspor JSON dengan Notepad, salin seluruh isinya, lalu tempel di sini. Anda akan melihat pratinjau perubahan dulu sebelum diterapkan.')
                        ->rows(10)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $payload = json_decode((string) $data['json'], true);
                    if (! is_array($payload)) {
                        Notification::make()->title('JSON tidak valid')->danger()->send();

                        return;
                    }

                    try {
                        $this->importPratinjau = app(KebijakanService::class)->pratinjauImpor($payload);
                        $this->importPayload = $payload;
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('preset')
                ->label('Terapkan Preset')
                ->icon('heroicon-o-sparkles')
                ->schema([
                    Select::make('preset')
                        ->label('Pilih preset')
                        ->options($presetOptions)
                        ->required()
                        ->native(false),
                ])
                ->requiresConfirmation()
                ->modalHeading('Terapkan preset kebijakan?')
                ->modalDescription('Nilai-nilai pada preset akan menimpa kebijakan saat ini. Perubahan tercatat di audit dan tetap bisa diubah lagi satu per satu.')
                ->action(function (array $data) {
                    $jumlah = app(KebijakanService::class)->terapkanPreset($data['preset'], Auth::user());
                    $this->form->fill($this->stateDariSetting());

                    Notification::make()->title("Preset diterapkan ({$jumlah} kebijakan berubah)")->success()->send();
                }),
        ];
    }
}
