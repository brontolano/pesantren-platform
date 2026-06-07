<?php

namespace App\Filament\Resources\CMS\Gallery;

use App\Filament\Resources\CMS\Gallery\Pages\CreateGallery;
use App\Filament\Resources\CMS\Gallery\Pages\EditGallery;
use App\Filament\Resources\CMS\Gallery\Pages\ListGalleries;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use App\Services\StorageQuotaService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Illuminate\Support\HtmlString;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Modules\CMS\Models\Gallery;

class GalleryResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = Gallery::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Galeri';

    protected static ?string $modelLabel = 'Galeri';

    protected static ?string $pluralModelLabel = 'Galeri';

    protected static string|\UnitEnum|null $navigationGroup = 'Website & CMS';

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

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->canAny(['manage_santri', 'manage_tenant_settings']) ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
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
                            ->description('Unggah foto galeri. Seret ↕ untuk mengubah urutan. Setiap foto otomatis masuk ke File Media.')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Repeater::make('images')
                                    ->hiddenLabel()
                                    ->relationship('images')
                                    ->schema([
                                        Placeholder::make('image_preview')
                                            ->hiddenLabel()
                                            ->content(fn ($get): ?HtmlString => self::thumbnail($get('image_url')))
                                            ->visible(fn ($get): bool => filled($get('image_url')))
                                            ->columnSpanFull(),
                                        FileUpload::make('image_url')
                                            ->label('Foto')
                                            ->image()
                                            ->imageEditor()
                                            ->disk(StorageQuotaService::diskName())
                                            ->visibility('public')
                                            ->directory('cms/galleries/images')
                                            ->maxSize(2048)
                                            ->required()
                                            ->columnSpanFull(),
                                        TextInput::make('caption')
                                            ->label('Keterangan Foto')
                                            ->maxLength(255),
                                        TextInput::make('sort_order')
                                            ->label('Urutan')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0),
                                        Toggle::make('is_featured')
                                            ->label('Foto Utama')
                                            ->default(false),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(0)
                                    ->addActionLabel('Tambah Foto')
                                    ->reorderable('sort_order')
                                    ->collapsible()
                                    ->collapsed()
                                    ->itemLabel(fn (array $state): ?string => $state['caption'] ?? 'Foto'),
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
                                    ->content(fn ($get): ?HtmlString => self::thumbnail($get('cover_image_url')))
                                    ->visible(fn ($get): bool => filled($get('cover_image_url'))),
                                FileUpload::make('cover_image_url')
                                    ->label('Gambar Cover')
                                    ->hiddenLabel()
                                    ->image()
                                    ->imageEditor()
                                    ->disk(StorageQuotaService::diskName())
                                    ->visibility('public')
                                    ->directory('cms/galleries/covers')
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

    /** Render thumbnail <img> dari path/URL media via Gallery::resolveMediaUrl. */
    private static function thumbnail(?string $value): ?HtmlString
    {
        $url = Gallery::resolveMediaUrl($value);

        if (! $url) {
            return null;
        }

        return new HtmlString(
            '<img src="' . e($url) . '" alt="preview" '
            . 'class="rounded-lg max-h-40 w-auto object-cover ring-1 ring-gray-200 dark:ring-white/10" '
            . 'onerror="this.style.display=\'none\'">'
        );
    }

    public static function table(Table $table): Table
    {
        return $table
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

