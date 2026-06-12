<?php

namespace App\Filament\Resources\CMS\Article;

use App\Filament\Resources\CMS\Article\Pages\CreateArticle;
use App\Filament\Resources\CMS\Article\Pages\EditArticle;
use App\Filament\Resources\CMS\Article\Pages\ListArticles;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Modules\CMS\Models\Article;

class ArticleResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = Article::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationLabel = 'Artikel';

    protected static ?string $modelLabel = 'Artikel';

    protected static ?string $pluralModelLabel = 'Artikel';

    protected static string|\UnitEnum|null $navigationGroup = 'Kelembagaan & Komunikasi';

    protected static ?int $navigationSort = 1;

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
            // Root schema resource Filament default 2 kolom → Grid ini WAJIB
            // columnSpanFull(), kalau tidak ia hanya mengisi 1 dari 2 kolom (separuh
            // kiri) sehingga separuh kanan kosong & sidebar 1/3 jadi gepeng.
            // Responsif: menumpuk 1 kolom di layar sempit, 2:1 di lg+ (≥1024px).
            Grid::make(['default' => 1, 'lg' => 3])
                ->columnSpanFull()
                ->schema([
                // ── KOLOM UTAMA: area menulis (fokus, lebar 2/3) ──
                Group::make([
                    Section::make('Konten Artikel')
                        ->description('Judul, ringkasan, dan isi artikel.')
                        ->icon('heroicon-o-pencil-square')
                        ->schema([
                            TextInput::make('title')
                                ->label('Judul')
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
                                ->unique(Article::class, 'slug', ignoreRecord: true)
                                ->prefix('/artikel/')
                                ->helperText('Otomatis dari judul. Contoh: berita-kegiatan-santri.')
                                ->columnSpanFull(),
                            Textarea::make('excerpt')
                                ->label('Ringkasan')
                                ->maxLength(500)
                                ->rows(2)
                                ->helperText('Tampil di list artikel & meta deskripsi SEO. (maks 500 karakter)')
                                ->columnSpanFull(),
                            RichEditor::make('content')
                                ->label('Isi Artikel')
                                ->columnSpanFull()
                                ->toolbarButtons([
                                    'bold', 'italic', 'underline', 'strike',
                                    'h2', 'h3',
                                    'bulletList', 'orderedList',
                                    'blockquote',
                                    'link',
                                    'redo', 'undo',
                                ]),
                        ]),
                ])->columnSpan(['default' => 1, 'lg' => 2]),

                // ── SIDEBAR: publikasi & meta (lebar 1/3) ──
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
                            Select::make('category')
                                ->label('Kategori')
                                ->options([
                                    'article' => 'Artikel Umum',
                                    'announcement' => 'Pengumuman',
                                    'achievement' => 'Prestasi',
                                ])
                                ->default('article')
                                ->required()
                                ->native(false)
                                ->helperText('Halaman tampil: artikel / pengumuman / prestasi.'),
                        ]),
                    Section::make('Gambar Utama')
                        ->icon('heroicon-o-photo')
                        ->schema([
                            FileUpload::make('featured_image_url')
                                ->label('Gambar Utama')
                                ->hiddenLabel()
                                ->image()
                                ->imageEditor()
                                ->directory('cms/articles')
                                ->maxSize(2048)
                                ->helperText('Maks 2MB. Rasio 16:9 direkomendasikan.'),
                        ]),
                    Section::make('SEO (Opsional)')
                        ->icon('heroicon-o-magnifying-glass')
                        ->collapsed()
                        ->schema([
                            TextInput::make('meta_title')
                                ->label('Meta Title')
                                ->maxLength(70)
                                ->helperText('Kosong = pakai judul artikel. Maks 70.'),
                            Textarea::make('meta_description')
                                ->label('Meta Description')
                                ->maxLength(160)
                                ->rows(2)
                                ->helperText('Kosong = pakai ringkasan. Maks 160.'),
                        ]),
                ])->columnSpan(['default' => 1, 'lg' => 1]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada artikel')
            ->emptyStateDescription('Tulis artikel atau berita pertama untuk ditampilkan di website pondok.')
            ->emptyStateIcon('heroicon-o-newspaper')
            ->columns([
                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'edit' => EditArticle::route('/{record}/edit'),
        ];
    }
}

