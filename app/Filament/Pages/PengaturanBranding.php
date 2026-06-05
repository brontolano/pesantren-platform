<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Halaman pengaturan branding tenant dalam bentuk SATU form biasa
 * (menggantikan resource CRUD key-value lama). Tiap field di-mapping ke
 * satu record Setting (key => value JSON) milik tenant aktif.
 */
class PengaturanBranding extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Settings & Branding';

    protected static ?string $title = 'Settings & Branding';

    protected static ?int $navigationSort = 4;

    /** Pertahankan URL lama (admin/settings) agar tidak mengubah muscle memory. */
    protected static ?string $slug = 'settings';

    protected string $view = 'filament.pages.pengaturan-branding';

    public ?array $data = [];

    /** Pilihan font branding (key => label). */
    public const FONTS = [
        'default' => 'Default Sans',
        'serif' => 'Serif Elegan',
        'modern' => 'Modern Sans',
        'mono' => 'Mono Editorial',
    ];

    /** Warna default mengikuti fallback di TenantWebsiteService. */
    private const DEFAULT_PRIMARY = '#14532d';

    private const DEFAULT_SECONDARY = '#c2410c';

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
        $settings = Setting::query()->get()->keyBy('key');

        $this->form->fill([
            'branding_name' => data_get($settings->get('branding_name')?->value, 'text'),
            'branding_tagline' => data_get($settings->get('branding_tagline')?->value, 'text'),
            'branding_primary_color' => data_get($settings->get('branding_primary_color')?->value, 'color', self::DEFAULT_PRIMARY),
            'branding_secondary_color' => data_get($settings->get('branding_secondary_color')?->value, 'color', self::DEFAULT_SECONDARY),
            'branding_logo' => data_get($settings->get('branding_logo')?->value, 'logo_path'),
            'branding_favicon' => data_get($settings->get('branding_favicon')?->value, 'text'),
            'branding_font' => data_get($settings->get('branding_font')?->value, 'text', 'default'),
            'ppdb_form_fields' => data_get($settings->get('ppdb_form_fields')?->value, 'fields', []),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Identitas Branding')
                    ->description('Nama dan tagline yang tampil di website serta dokumen pesantren.')
                    ->schema([
                        TextInput::make('branding_name')
                            ->label('Nama Branding')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('branding_tagline')
                            ->label('Tagline')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Warna & Tipografi')
                    ->description('Skema warna dan font untuk tampilan website tenant.')
                    ->schema([
                        ColorPicker::make('branding_primary_color')
                            ->label('Warna Primer'),
                        ColorPicker::make('branding_secondary_color')
                            ->label('Warna Sekunder'),
                        Select::make('branding_font')
                            ->label('Font Branding')
                            ->options(self::FONTS)
                            ->default('default')
                            ->required(),
                    ])
                    ->columns(3),

                Section::make('Logo & Favicon')
                    ->description('Unggah logo pesantren dan tentukan favicon untuk tab browser.')
                    ->schema([
                        FileUpload::make('branding_logo')
                            ->label('Logo')
                            ->disk('public')
                            ->directory('branding')
                            ->image()
                            ->imageEditor()
                            ->maxSize(2048)
                            ->helperText('Format gambar, maksimal 2 MB.'),
                        TextInput::make('branding_favicon')
                            ->label('Favicon URL / Path')
                            ->helperText('Boleh URL absolut atau path file publik.')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Form PPDB')
                    ->description('Atur mode tiap field formulir PPDB. Isi nilai dengan: required, optional, atau hidden.')
                    ->schema([
                        KeyValue::make('ppdb_form_fields')
                            ->label('Konfigurasi Field PPDB')
                            ->keyLabel('Field')
                            ->valueLabel('Mode')
                            ->addActionLabel('Tambah field'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $payloads = [
            'branding_name' => ['text' => $data['branding_name'] ?? null],
            'branding_tagline' => ['text' => $data['branding_tagline'] ?? null],
            'branding_primary_color' => ['color' => $data['branding_primary_color'] ?? null],
            'branding_secondary_color' => ['color' => $data['branding_secondary_color'] ?? null],
            'branding_logo' => ['logo_path' => $data['branding_logo'] ?? null],
            'branding_favicon' => ['text' => $data['branding_favicon'] ?? null],
            'branding_font' => ['text' => $data['branding_font'] ?? 'default'],
            'ppdb_form_fields' => ['fields' => $data['ppdb_form_fields'] ?? []],
        ];

        foreach ($payloads as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Notification::make()
            ->title('Pengaturan branding disimpan')
            ->success()
            ->send();
    }
}
