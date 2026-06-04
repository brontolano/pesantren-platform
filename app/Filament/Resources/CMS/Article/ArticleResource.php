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
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Modules\CMS\Models\Article;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationLabel = 'Artikel';

    protected static ?string $modelLabel = 'Artikel';

    protected static ?string $pluralModelLabel = 'Artikel';

    protected static string|\UnitEnum|null $navigationGroup = 'Website & CMS';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canAny(['manage_santri', 'manage_tenant_settings']) ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canAny(['manage_santri', 'manage_tenant_settings']) ?? false;
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
            Section::make('Konten Artikel')->schema([
                TextInput::make('title')
                    ->label('Judul')
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
                    ->unique(Article::class, 'slug', ignoreRecord: true)
                    ->helperText('Contoh: berita-kegiatan-santri. Otomatis dari judul.'),
                Textarea::make('excerpt')
                    ->label('Ringkasan')
                    ->maxLength(500)
                    ->rows(3)
                    ->helperText('Tampil di list artikel dan meta deskripsi SEO.'),
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
            ])->columns(2),

            Section::make('Gambar & Publikasi')->schema([
                FileUpload::make('featured_image_url')
                    ->label('Gambar Utama')
                    ->image()
                    ->imageEditor()
                    ->directory('cms/articles')
                    ->maxSize(2048)
                    ->helperText('Maks 2MB. Rasio 16:9 direkomendasikan.'),
                DateTimePicker::make('published_at')
                    ->label('Tanggal Terbit')
                    ->native(false)
                    ->seconds(false),
                Toggle::make('is_published')
                    ->label('Publikasikan')
                    ->helperText('Aktifkan agar artikel tampil di website.')
                    ->default(false)
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $set('published_at', now());
                        }
                    }),
            ])->columns(2),

            Section::make('SEO (Opsional)')->schema([
                TextInput::make('meta_title')
                    ->label('Meta Title')
                    ->maxLength(70)
                    ->helperText('Biarkan kosong untuk pakai judul artikel. Maks 70 karakter.'),
                Textarea::make('meta_description')
                    ->label('Meta Description')
                    ->maxLength(160)
                    ->rows(2)
                    ->helperText('Biarkan kosong untuk pakai ringkasan. Maks 160 karakter.'),
            ])->columns(2)->collapsed(),
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
