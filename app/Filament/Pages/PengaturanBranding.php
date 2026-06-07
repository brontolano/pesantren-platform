<?php

namespace App\Filament\Pages;

use App\Models\MediaFile;
use App\Models\Setting;
use App\Services\StorageQuotaService;
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
use Illuminate\Support\Facades\Storage;

// NOTE: FileUpload SENGAJA tidak di-fill dengan logo path yang tersimpan.
// Alasan: Filament v5 FileUpload yang diisi path existing akan memanggil
// Storage::disk('public')->url($path) → APP_URL/storage/{path}.
// Di Docker + stancl/tenancy, file sesungguhnya berada di
// storage/tenantXXX/app/public/{path} (path tenant), tetapi symlink
// public/storage → storage/app/public/ (non-tenant) → URL selalu 404.
// Hasilnya: komponen Alpine.js hang di "awaitingFileSize = true" →
// "Menunggu ukuran berkas" tak terbatas.
// Solusi: logo existing ditampilkan via route /_tenant-storage/{path} di blade.

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

    /**
     * Path logo yang sudah tersimpan di disk 'public' tenant.
     * Diisi di mount(), dibaca blade untuk preview via /_tenant-storage/{path}.
     * TIDAK dimasukkan ke FileUpload (lihat komentar di bagian import).
     */
    public ?string $currentLogoPath = null;

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

        // Simpan path logo tersimpan sebagai Livewire property terpisah.
        // Blade template akan menampilkan preview via route /_tenant-storage/{path}.
        $this->currentLogoPath = data_get($settings->get('branding_logo')?->value, 'logo_path');

        $this->form->fill([
            'branding_name'             => data_get($settings->get('branding_name')?->value, 'text'),
            'branding_tagline'          => data_get($settings->get('branding_tagline')?->value, 'text'),
            'branding_primary_color'    => data_get($settings->get('branding_primary_color')?->value, 'color', self::DEFAULT_PRIMARY),
            'branding_secondary_color'  => data_get($settings->get('branding_secondary_color')?->value, 'color', self::DEFAULT_SECONDARY),
            'branding_logo'             => null,   // SENGAJA null — lihat komentar di atas
            'branding_favicon'          => data_get($settings->get('branding_favicon')?->value, 'text'),
            'branding_font'             => data_get($settings->get('branding_font')?->value, 'text', 'default'),
            'branding_latitude'         => data_get($settings->get('branding_latitude')?->value, 'text'),
            'branding_longitude'        => data_get($settings->get('branding_longitude')?->value, 'text'),
            'ppdb_form_fields'          => data_get($settings->get('ppdb_form_fields')?->value, 'fields', []),
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
                            ->label('Upload Logo Baru')
                            ->disk(StorageQuotaService::diskName())
                            ->directory('branding')
                            ->image()
                            // imageEditor() SENGAJA dibuang — butuh URL preview temp
                            // file via /livewire/preview-file; rawan gagal di Docker.
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])
                            ->maxSize(2048)
                            ->helperText('JPG/PNG/WebP/SVG, maks 2 MB. Logo lama tetap aktif sampai ada yang baru.'),
                        TextInput::make('branding_favicon')
                            ->label('Favicon URL / Path')
                            ->helperText('Boleh URL absolut atau path file publik.')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Lokasi & Jadwal Sholat')
                    ->description('Koordinat pesantren untuk perhitungan jadwal sholat di dashboard (metode Kemenag, zona WIB). Kosongkan untuk memakai default Bandung.')
                    ->schema([
                        TextInput::make('branding_latitude')
                            ->label('Lintang (Latitude)')
                            ->helperText('Contoh: -6.85 (selatan = negatif)')
                            ->numeric()
                            ->minValue(-11)
                            ->maxValue(6),
                        TextInput::make('branding_longitude')
                            ->label('Bujur (Longitude)')
                            ->helperText('Contoh: 107.49')
                            ->numeric()
                            ->minValue(95)
                            ->maxValue(141),
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

        // Jika user upload logo baru → pakai; jika tidak → pertahankan logo lama.
        $newLogoPath = $data['branding_logo'] ?? null;
        $logoPath    = $newLogoPath ?: $this->currentLogoPath;

        if ($newLogoPath) {
            // Daftarkan logo baru ke media library
            $this->registerLogoAsMediaFile($newLogoPath);
            // Update property agar blade langsung menampilkan logo baru
            $this->currentLogoPath = $newLogoPath;
        }

        $payloads = [
            'branding_name'            => ['text' => $data['branding_name'] ?? null],
            'branding_tagline'         => ['text' => $data['branding_tagline'] ?? null],
            'branding_primary_color'   => ['color' => $data['branding_primary_color'] ?? null],
            'branding_secondary_color' => ['color' => $data['branding_secondary_color'] ?? null],
            'branding_logo'            => ['logo_path' => $logoPath],
            'branding_favicon'         => ['text' => $data['branding_favicon'] ?? null],
            'branding_font'            => ['text' => $data['branding_font'] ?? 'default'],
            'branding_latitude'        => ['text' => $data['branding_latitude'] ?? null],
            'branding_longitude'       => ['text' => $data['branding_longitude'] ?? null],
            'ppdb_form_fields'         => ['fields' => $data['ppdb_form_fields'] ?? []],
        ];

        foreach ($payloads as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        // Reset FileUpload ke kosong agar tidak mencoba preview path final
        // (path final di disk tenant tidak bisa di-resolve via URL standar).
        $this->form->fill([
            'branding_name'            => $data['branding_name'] ?? null,
            'branding_tagline'         => $data['branding_tagline'] ?? null,
            'branding_primary_color'   => $data['branding_primary_color'] ?? null,
            'branding_secondary_color' => $data['branding_secondary_color'] ?? null,
            'branding_logo'            => null,   // Selalu kosong pasca-save
            'branding_favicon'         => $data['branding_favicon'] ?? null,
            'branding_font'            => $data['branding_font'] ?? 'default',
            'branding_latitude'        => $data['branding_latitude'] ?? null,
            'branding_longitude'       => $data['branding_longitude'] ?? null,
            'ppdb_form_fields'         => $data['ppdb_form_fields'] ?? [],
        ]);

        Notification::make()
            ->title('Pengaturan branding disimpan')
            ->success()
            ->send();
    }

    /**
     * Daftarkan logo yang diunggah ke tabel media_files agar:
     * 1. Muncul di halaman File Media (media-files).
     * 2. Terhitung dalam kuota penyimpanan tenant.
     * Idempotent: jika path sudah ada di DB, record di-update (tidak duplikat).
     */
    private function registerLogoAsMediaFile(string $path): void
    {
        try {
            $disk = Storage::disk(StorageQuotaService::diskName());

            if (! $disk->exists($path)) {
                return;
            }

            MediaFile::updateOrCreate(
                ['path' => $path],
                [
                    'original_name' => basename($path),
                    'mime'          => $disk->mimeType($path) ?: 'image/png',
                    'size_bytes'    => (int) $disk->size($path),
                    'folder'        => 'branding',
                    'uploaded_by'   => Auth::id(),
                ],
            );
        } catch (\Throwable) {
            // Jangan blokir penyimpanan branding jika registrasi media gagal.
        }
    }
}
