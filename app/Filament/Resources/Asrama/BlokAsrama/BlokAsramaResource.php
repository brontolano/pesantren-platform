<?php

namespace App\Filament\Resources\Asrama\BlokAsrama;

use App\Filament\Resources\Asrama\BlokAsrama\Pages\CreateBlokAsrama;
use App\Filament\Resources\Asrama\BlokAsrama\Pages\EditBlokAsrama;
use App\Filament\Resources\Asrama\BlokAsrama\Pages\ListBlokAsrama;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Asrama\Models\BlokAsrama;

class BlokAsramaResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;
    protected static ?string $model = BlokAsrama::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Blok Asrama';

    protected static string|\UnitEnum|null $navigationGroup = 'Asrama';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('asrama') && (auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('asrama') && (auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_santri') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('manage_santri') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama_blok')->required()->maxLength(255),
            TextInput::make('lokasi')->maxLength(255),
            TextInput::make('jumlah_kamar')->numeric()->required()->minValue(0),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama_blok')->searchable()->sortable(),
                TextColumn::make('lokasi')->placeholder('-'),
                TextColumn::make('jumlah_kamar')->sortable(),
                TextColumn::make('kamar_count')->counts('kamar')->label('Kamar Terdaftar'),
                IconColumn::make('is_active')->boolean()->label('Aktif'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlokAsrama::route('/'),
            'create' => CreateBlokAsrama::route('/create'),
            'edit' => EditBlokAsrama::route('/{record}/edit'),
        ];
    }
}

