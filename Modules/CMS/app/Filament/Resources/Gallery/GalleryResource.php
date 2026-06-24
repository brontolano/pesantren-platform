<?php

namespace Modules\CMS\Filament\Resources\Gallery;

use App\Filament\Concerns\ModuleGate;
use Modules\CMS\Filament\Resources\Gallery\Pages\CreateGallery;
use Modules\CMS\Filament\Resources\Gallery\Pages\EditGallery;
use Modules\CMS\Filament\Resources\Gallery\Pages\ListGalleries;
use App\Services\StorageQuotaService;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\CMS\Models\Gallery;

class GalleryResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = Gallery::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Galeri';

    protected static ?string $modelLabel = 'Galeri';

    protected static ?string $pluralModelLabel = 'Galeri';

    protected static string|\UnitEnum|null $navigationGroup = 'Kelembagaan & Pengaturan';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('cms') && (auth()->user()?->canAny(['manage_santri', 'manage_tenant_settings']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('cms') && (auth()->user()?->canAny(['manage_santri', 'manage_tenant_settings']) ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->canAny(['manage_santri', 'manage_tenant_settings']) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->canAny(['manage_santri', 'manage_tenant_settings']) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('manage_tenant_settings') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // Root schema resource default 2 kolom → Grid WAJIB columnSpanFull()
            // agar mengisi lebar penuh. Responsif: menumpuk di layar sempit, 2:1 di lg+.
            Grid::make(['default' => 1, 'lg' => 3])
                ->columnSpanFull()
                ->schema([
                    // ── KOLOM UTAMA (2/3): data + daftar foto ──
                    Group::make([
                        Section::make('Data Galeri')
                            ->description('Judul, slug, dan deskripsi galeri.')
                            ->icon('heroicon-o-rectangle-stack')
                            ->schema([
                                TextInput::make('title')
                                    ->label('Judul Galeri')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (string $context, $state, callable $set) => $context === 'create'
                                        ? $set('slug', Str::slug($state))
                                        : null
                                    )
                                    ->columnSpanFull(),
                                TextInput::make('slug')
                                    ->label('Slug URL')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(Gallery::class, 'slug', ignoreRecord: true)
                                    ->prefix('/galeri/')
                                    ->helperText('Otomatis dari judul.')
                                    ->columnSpanFull(),
                                Textarea::make('description')
                                    ->label('Deskripsi')
                                    ->rows(3)
                                    ->maxLength(500)
                                    ->helperText('Tampil di list galeri & meta deskripsi SEO. (maks 500)')
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Foto Galeri')
                            ->description('Unggah beberapa foto sekaligus pada SATU area di atas. Foto yang sudah ada tampil sebagai grid di bawah — atur keterangan, urutan (seret ↕), atau hapus. File disimpan per folder slug & otomatis masuk File Media.')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                // ── SATU dropzone untuk unggah banyak foto sekaligus ──
                                FileUpload::make('new_photos')
                                    ->label('Unggah Foto Baru')
                                    ->multiple()
                                    ->image()
                                    ->panelLayout('grid')
                                    ->reorderable()
                                    ->appendFiles()
                                    ->disk(StorageQuotaService::diskName())
                                    ->visibility('public')
                                    ->directory(fn ($get): string => self::photoDir($get))
                                    ->maxSize(2048)
                                    ->maxFiles(30)
                                    ->helperText('Pilih banyak foto sekaligus. Klik "Simpan" untuk menambahkannya ke galeri di bawah.')
                                    ->columnSpanFull(),

                                // ── Grid foto yang SUDAH tersimpan (thumbnail tampil) ──
                                Repeater::make('images')
                                    ->hiddenLabel()
                                    ->relationship('images')
                                    ->grid(['default' => 1, 'sm' => 2, 'xl' => 3])
                                    ->schema([
                                        // image_url tidak diedit di sini (upload lewat dropzone),
                                        // tapi WAJIB ada agar tersimpan kembali (tidak ter-null) &
                                        // bisa dibaca oleh thumbnail.
                                        Hidden::make('image_url'),
                                        Placeholder::make('image_preview')
                                            ->hiddenLabel()
                                            ->content(fn ($get) => self::thumbnail($get('image_url'))),
                                        TextInput::make('caption')
                                            ->hiddenLabel()
                                            ->placeholder('Keterangan foto (opsional)')
                                            ->maxLength(255),
                                        Toggle::make('is_featured')
                                            ->label('Jadikan foto utama')
                                            ->inline(false)
                                            ->default(false),
                                    ])
                                    ->columns(1)
                                    ->defaultItems(0)
                                    // Tambah foto lewat dropzone di atas, bukan tombol repeater.
                                    ->addable(false)
                                    ->deletable()
                                    ->reorderable('sort_order')
                                    ->itemLabel(fn (array $state): ?string => $state['caption'] ?? null)
                                    ->visible(fn (?Gallery $record): bool => $record !== null && $record->images()->exists()),
                            ]),
                    ])->columnSpan(['default' => 1, 'lg' => 2]),

                    // ── SIDEBAR (1/3): publikasi, cover, SEO ──
                    Group::make([
                        Section::make('Publikasi')
                            ->icon('heroicon-o-paper-airplane')
                            ->schema([
                                Toggle::make('is_published')
                                    ->label('Publikasikan')
                                    ->helperText('Aktifkan agar tampil di website.')
                                    ->default(false)
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $set('published_at', now());
                                        }
                                    }),
                                DateTimePicker::make('published_at')
                                    ->label('Tanggal Terbit')
                                    ->native(false)
                                    ->seconds(false),
                            ]),

                        Section::make('Gambar Cover')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Placeholder::make('cover_preview')
                                    ->hiddenLabel()
                                    ->content(fn ($get) => self::thumbnail($get('cover_image_url')))
                                    ->visible(fn ($get): bool => filled($get('cover_image_url'))),
                                FileUpload::make('cover_image_url')
                                    ->label('Gambar Cover')
                                    ->hiddenLabel()
                                    ->image()
                                    ->imageEditor()
                                    ->disk(StorageQuotaService::diskName())
                                    ->visibility('public')
                                    ->directory(fn ($get): string => self::photoDir($get))
                                    ->maxSize(2048)
                                    ->helperText('Opsional. Jika kosong, foto pertama dipakai sebagai cover.'),
                            ]),

                        Section::make('SEO (Opsional)')
                            ->icon('heroicon-o-magnifying-glass')
                            ->collapsed()
                            ->schema([
                                TextInput::make('meta_title')
                                    ->label('Meta Title')
                                    ->maxLength(70)
                                    ->helperText('Kosong = pakai judul galeri. Maks 70.'),
                                Textarea::make('meta_description')
                                    ->label('Meta Description')
                                    ->maxLength(160)
                                    ->rows(2)
                                    ->helperText('Kosong = pakai deskripsi. Maks 160.'),
                            ]),
                    ])->columnSpan(['default' => 1, 'lg' => 1]),
                ]),
        ]);
    }

    /**
     * Render thumbnail foto via Blade view (robust: ada fallback bila gambar
     * gagal dimuat). URL di-resolve ke /_tenant-storage via resolveMediaUrl.
     */
    private static function thumbnail(?string $value): View
    {
        return view('filament.forms.gallery-thumb', [
            'url' => Gallery::resolveMediaUrl($value),
        ]);
    }

    /**
     * Folder penyimpanan file per galeri = cms/galleries/{slug}.
     * Slug diambil dari field form (fallback judul, lalu "umum") sehingga
     * tiap postingan galeri punya foldernya sendiri sesuai Slug URL.
     */
    private static function photoDir(callable $get): string
    {
        $slug = Str::slug((string) ($get('slug') ?: $get('title') ?: ''));

        return 'cms/galleries/'.($slug !== '' ? $slug : 'umum');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada galeri')
            ->emptyStateDescription('Unggah foto kegiatan untuk ditampilkan di website pondok.')
            ->emptyStateIcon('heroicon-o-photo')
            ->columns([
                ImageColumn::make('cover_url')
                    ->label('Cover')
                    ->height(56)
                    ->width(84)
                    ->extraImgAttributes(['class' => 'rounded-lg object-cover'])
                    ->toggleable(),
                TextColumn::make('title')
                    ->label('Judul')
                    ->weight('semibold')
                    ->description(fn (Gallery $r): ?string => $r->description ? Str::limit($r->description, 60) : null)
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('images_count')
                    ->label('Foto')
                    ->counts('images')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-m-photo')
                    ->sortable(),
                IconColumn::make('is_published')
                    ->label('Publik')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->label('Terbit')
                    ->dateTime('d M Y')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->striped()
            ->defaultPaginationPageOption(25)
            ->defaultSort('published_at', 'desc')
            ->filters([
                TernaryFilter::make('is_published')
                    ->label('Status')
                    ->trueLabel('Publik')
                    ->falseLabel('Draft'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGalleries::route('/'),
            'create' => CreateGallery::route('/create'),
            'edit' => EditGallery::route('/{record}/edit'),
        ];
    }
}
