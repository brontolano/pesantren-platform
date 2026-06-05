<?php

namespace App\Filament\Resources\CMS\Gallery;

use App\Filament\Resources\CMS\Gallery\Pages\CreateGallery;
use App\Filament\Resources\CMS\Gallery\Pages\EditGallery;
use App\Filament\Resources\CMS\Gallery\Pages\ListGalleries;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
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
            Section::make('Data Galeri')->schema([
                TextInput::make('title')
                    ->label('Judul Galeri')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $context, $state, callable $set) => $context === 'create'
                        ? $set('slug', Str::slug($state))
                        : null
                    ),
                TextInput::make('slug')
                    ->label('Slug URL')
                    ->required()
                    ->maxLength(255)
                    ->unique(Gallery::class, 'slug', ignoreRecord: true),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->rows(3)
                    ->maxLength(500)
                    ->columnSpanFull(),
                FileUpload::make('cover_image_url')
                    ->label('Gambar Cover')
                    ->image()
                    ->imageEditor()
                    ->directory('cms/galleries/covers')
                    ->maxSize(2048)
                    ->helperText('Opsional. Jika kosong, gambar pertama dipakai sebagai cover.'),
                DateTimePicker::make('published_at')
                    ->label('Tanggal Terbit')
                    ->native(false)
                    ->seconds(false),
                Toggle::make('is_published')
                    ->label('Publikasikan')
                    ->default(false)
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $set('published_at', now());
                        }
                    }),
            ])->columns(2),

            Section::make('Foto Galeri')->schema([
                Repeater::make('images')
                    ->label('Daftar Foto')
                    ->relationship('images')
                    ->schema([
                        FileUpload::make('image_url')
                            ->label('Foto')
                            ->image()
                            ->imageEditor()
                            ->directory('cms/galleries/images')
                            ->maxSize(2048)
                            ->required(),
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
                    ->collapsed()
                    ->itemLabel(fn (array $state): ?string => $state['caption'] ?? 'Foto'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('images_count')
                    ->label('Foto')
                    ->counts('images')
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

