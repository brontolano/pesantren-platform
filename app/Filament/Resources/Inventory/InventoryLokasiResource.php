<?php

namespace App\Filament\Resources\Inventory;

use App\Filament\Resources\Inventory\InventoryLokasi\Pages\CreateInventoryLokasi;
use App\Filament\Resources\Inventory\InventoryLokasi\Pages\EditInventoryLokasi;
use App\Filament\Resources\Inventory\InventoryLokasi\Pages\ListInventoryLokasi;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Keuangan\Models\InventoryLokasi;

/**
 * G2-14 — Master lokasi penyimpanan inventory (gudang/unit/etalase).
 */
class InventoryLokasiResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = InventoryLokasi::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Lokasi Inventory';

    protected static ?string $modelLabel = 'Lokasi Inventory';

    protected static ?string $pluralModelLabel = 'Lokasi Inventory';

    protected static string|\UnitEnum|null $navigationGroup = 'Kelembagaan & Komunikasi';

    protected static ?int $navigationSort = 22;

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::moduleVisible('keuangan') && (auth()->user()?->can('operate_pos') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Lokasi')->schema([
                TextInput::make('nama')->label('Nama')->required()->maxLength(255),
                TextInput::make('kode')->label('Kode')->required()->maxLength(50)->helperText('Unik per pondok.'),
                Select::make('tipe')->label('Tipe')->native(false)->options(InventoryLokasi::TIPE)->default('gudang'),
                Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->label('Kode')->searchable()->sortable(),
                TextColumn::make('nama')->label('Nama')->searchable(),
                TextColumn::make('tipe')->label('Tipe')->badge()
                    ->formatStateUsing(fn (?string $s): string => InventoryLokasi::TIPE[$s] ?? $s),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('nama')
            ->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryLokasi::route('/'),
            'create' => CreateInventoryLokasi::route('/create'),
            'edit' => EditInventoryLokasi::route('/{record}/edit'),
        ];
    }
}
