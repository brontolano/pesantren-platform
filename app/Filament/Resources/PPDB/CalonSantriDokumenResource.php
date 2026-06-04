<?php

namespace App\Filament\Resources\PPDB;

use App\Filament\Resources\PPDB\CalonSantriDokumen\Pages\CreateCalonSantriDokumen;
use App\Filament\Resources\PPDB\CalonSantriDokumen\Pages\EditCalonSantriDokumen;
use App\Filament\Resources\PPDB\CalonSantriDokumen\Pages\ListCalonSantriDokumen;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\CalonSantriDokumen;

class CalonSantriDokumenResource extends Resource
{
    protected static ?string $model = CalonSantriDokumen::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder-open';

    protected static ?string $navigationLabel = 'Dokumen PPDB';

    protected static ?string $modelLabel = 'Dokumen PPDB';

    protected static ?string $pluralModelLabel = 'Dokumen PPDB';

    protected static string|\UnitEnum|null $navigationGroup = 'PPDB';

    protected static ?string $slug = 'ppdb/dokumen';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->canAny(['view_santri', 'manage_santri']) ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dokumen Calon Santri')->schema([
                Select::make('calon_santri_id')
                    ->label('Calon Santri')
                    ->options(fn () => CalonSantri::orderBy('nama')->pluck('nama', 'id'))
                    ->searchable()
                    ->required(),
                TextInput::make('jenis_dokumen')
                    ->label('Jenis Dokumen')
                    ->required()
                    ->maxLength(100),
                TextInput::make('file_url')
                    ->label('File URL')
                    ->required()
                    ->maxLength(255)
                    ->url(),
            ])->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('calonSantri.nama')
                    ->label('Calon Santri')
                    ->searchable(),
                TextColumn::make('jenis_dokumen')
                    ->label('Jenis Dokumen')
                    ->sortable(),
                TextColumn::make('file_url')
                    ->label('File')
                    ->limit(40),
            ])
            ->defaultSort('id')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('calonSantri');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCalonSantriDokumen::route('/'),
            'create' => CreateCalonSantriDokumen::route('/create'),
            'edit' => EditCalonSantriDokumen::route('/{record}/edit'),
        ];
    }
}

