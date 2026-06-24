<?php

namespace Modules\CMS\Filament\Resources\Profile;

use App\Filament\Concerns\BuiltInWebsiteGate;
use App\Filament\Concerns\ModuleGate;
use Modules\CMS\Filament\Resources\Profile\Pages\CreateProfile;
use Modules\CMS\Filament\Resources\Profile\Pages\EditProfile;
use Modules\CMS\Filament\Resources\Profile\Pages\ListProfiles;
use App\Support\UiMode;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\CMS\Models\WebsiteSection;

class ProfileResource extends Resource
{
    use BuiltInWebsiteGate;
    use ModuleGate;

    protected static ?string $model = WebsiteSection::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'Profil Website';

    protected static ?string $modelLabel = 'Profil Website';

    protected static ?string $pluralModelLabel = 'Profil Website';

    protected static string|\UnitEnum|null $navigationGroup = 'Kelembagaan & Pengaturan';

    protected static ?int $navigationSort = 9;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('cms') && (static::canAccess());
    }

    public static function canAccess(): bool
    {
        return static::builtInWebsiteEnabled()
            && static::moduleVisible('cms')
            && (auth()->user()?->can('manage_tenant_settings') ?? false);
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return static::canAccess() && ! static::getEloquentQuery()->exists();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess() && $record->tenant_id === tenant('id');
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profil Pesantren')->schema([
                TextInput::make('section_type')
                    ->default('profile')
                    ->dehydrated()
                    ->hidden(),
                TextInput::make('config.title')
                    ->label('Judul Section')
                    ->default('Profil Pesantren')
                    ->maxLength(100),
                Textarea::make('config.about')
                    ->label('Tentang Pesantren')
                    ->rows(5)
                    ->maxLength(2000)
                    ->columnSpanFull(),
                Textarea::make('config.vision')
                    ->label('Visi')
                    ->rows(3)
                    ->maxLength(1000)
                    ->columnSpanFull(),
                Repeater::make('config.mission')
                    ->label('Misi')
                    ->simple(
                        TextInput::make('value')
                            ->label('Poin Misi')
                            ->required()
                            ->maxLength(255)
                    )
                    ->defaultItems(0)
                    ->addActionLabel('Tambah Misi')
                    ->columnSpanFull(),
                Repeater::make('config.highlights')
                    ->label('Highlight')
                    ->simple(
                        TextInput::make('value')
                            ->label('Highlight')
                            ->required()
                            ->maxLength(255)
                    )
                    ->defaultItems(0)
                    ->addActionLabel('Tambah Highlight')
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('config.title')
                    ->label('Judul')
                    ->default('Profil Pesantren')
                    ->searchable(),
                TextColumn::make('config.about')
                    ->label('Tentang')
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()->hidden(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('section_type', 'profile');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProfiles::route('/'),
            'create' => CreateProfile::route('/create'),
            'edit' => EditProfile::route('/{record}/edit'),
        ];
    }
}
