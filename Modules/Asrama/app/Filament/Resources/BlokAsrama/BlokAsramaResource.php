<?php

namespace Modules\Asrama\Filament\Resources\BlokAsrama;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Asrama\Filament\Resources\BlokAsrama\Pages\CreateBlokAsrama;
use Modules\Asrama\Filament\Resources\BlokAsrama\Pages\EditBlokAsrama;
use Modules\Asrama\Filament\Resources\BlokAsrama\Pages\ListBlokAsrama;
use Modules\Asrama\Models\BlokAsrama;

class BlokAsramaResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = BlokAsrama::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Blok Asrama';

    protected static string|\UnitEnum|null $navigationGroup = 'Asrama & Pengasuhan';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('asrama') && (auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('asrama') && (auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_santri') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('manage_santri') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama_blok')->required()->maxLength(255),
            Select::make('gender')->label('Peruntukan Gender')->native(false)
                ->options(['putra' => 'Putra', 'putri' => 'Putri', 'campur' => 'Campur'])
                ->helperText('Kosong/Campur = tanpa batasan penempatan otomatis.'),
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
