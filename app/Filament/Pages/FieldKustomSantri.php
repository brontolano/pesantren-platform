<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\CustomFieldRegistry;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Custom Field per-tenant — halaman kelola definisi field kustom Santri (pilot).
 *
 * Admin tenant menyusun daftar field sendiri (golongan darah, hobi, dsb) TANPA
 * migrasi/ngoding. Definisi disimpan sebagai Setting `custom_fields.santri`
 * (bentuk value ['fields' => [...]]), meniru pola `ppdb_form_fields`. Reader:
 * App\Support\CustomFieldRegistry. Nilai per-santri disimpan di kolom JSON
 * santri.custom_fields (dirender dinamis di SantriResource seksi "Data Tambahan").
 *
 * Gate: manage_tenant_settings, panel tenant saja. Auto-discover app/Filament/Pages.
 */
class FieldKustomSantri extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = 'Field Kustom Santri';

    // Grup nav TANPA ikon-grup (jebakan Filament v5: grup berikon + item
    // berikon → 500). "Santri" adalah grup existing.
    protected static string|\UnitEnum|null $navigationGroup = 'Santri';

    protected static ?string $title = 'Field Kustom Santri';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'field-kustom-santri';

    protected string $view = 'filament.pages.field-kustom-santri';

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
        // Baca definisi mentah dari Setting agar tampil apa adanya (termasuk
        // options sbg teks multi-baris) di form editor.
        $raw = Setting::getTenantValue('custom_fields.'.CustomFieldRegistry::ENTITY_SANTRI, []);
        $fields = is_array($raw) ? ($raw['fields'] ?? $raw) : [];

        $rows = [];
        foreach (is_array($fields) ? $fields : [] as $f) {
            if (! is_array($f)) {
                continue;
            }
            $options = $f['options'] ?? '';
            if (is_array($options)) {
                $options = implode("\n", $options);
            }
            $rows[] = [
                'key' => (string) ($f['key'] ?? ''),
                'label' => (string) ($f['label'] ?? ''),
                'type' => (string) ($f['type'] ?? 'text'),
                'options' => (string) $options,
                'required' => (bool) ($f['required'] ?? false),
            ];
        }

        $this->form->fill(['fields' => $rows]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Daftar Field Kustom Santri')
                    ->description('Definisikan field tambahan khusus pesantren ini. Field muncul otomatis di form Santri (seksi "Data Tambahan"). Kosongkan daftar bila tak perlu — tak ada beban untuk data yang sudah ada.')
                    ->schema([
                        Repeater::make('fields')
                            ->label('')
                            ->addActionLabel('Tambah field')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->schema([
                                TextInput::make('label')
                                    ->label('Nama Field')
                                    ->placeholder('Golongan Darah')
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('key')
                                    ->label('Kunci (slug)')
                                    ->helperText('Huruf kecil tanpa spasi, mis. golongan_darah. Jangan diubah setelah ada data.')
                                    ->placeholder('golongan_darah')
                                    ->required()
                                    ->maxLength(60),
                                Select::make('type')
                                    ->label('Tipe')
                                    ->options(CustomFieldRegistry::TYPES)
                                    ->default('text')
                                    ->live()
                                    ->required(),
                                Textarea::make('options')
                                    ->label('Opsi Pilihan')
                                    ->helperText('Satu opsi per baris. Hanya untuk tipe "Pilihan".')
                                    ->rows(3)
                                    ->visible(fn (Get $get): bool => $get('type') === 'select'),
                                Toggle::make('required')
                                    ->label('Wajib diisi')
                                    ->inline(false)
                                    ->default(false),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $rows = $data['fields'] ?? [];

        // Normalisasi: slug key, buang baris tak valid / duplikat. options
        // disimpan sbg array baris. Bentuk value ['fields'=>...] konsisten dgn
        // reader CustomFieldRegistry.
        $clean = [];
        $seen = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $key = strtolower(trim((string) ($row['key'] ?? '')));
            $key = trim((string) preg_replace('/[^a-z0-9]+/', '_', $key), '_');
            $type = (string) ($row['type'] ?? 'text');

            if ($label === '' || $key === '' || ! isset(CustomFieldRegistry::TYPES[$type]) || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $options = $row['options'] ?? '';
            $optionList = is_array($options)
                ? $options
                : (preg_split('/\r\n|\r|\n/', (string) $options) ?: []);
            $optionList = array_values(array_filter(array_map(
                static fn ($o) => trim((string) $o),
                $optionList,
            ), static fn ($o) => $o !== ''));

            $clean[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'options' => $optionList,
                'required' => (bool) ($row['required'] ?? false),
            ];
        }

        Setting::updateOrCreate(
            ['key' => 'custom_fields.'.CustomFieldRegistry::ENTITY_SANTRI],
            ['value' => ['fields' => $clean]],
        );

        Notification::make()
            ->title('Field kustom Santri disimpan')
            ->success()
            ->send();
    }
}
