<?php

namespace Modules\Pengadaan\Filament\Resources\Supplier;

use Modules\Pengadaan\Filament\Resources\Supplier\Pages\CreateSupplier;
use Modules\Pengadaan\Filament\Resources\Supplier\Pages\EditSupplier;
use Modules\Pengadaan\Filament\Resources\Supplier\Pages\ListSupplier;
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
use Modules\Pengadaan\Models\Supplier;

/**
 * G2-09 — Master Supplier/Vendor.
 */
class SupplierResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = Supplier::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Supplier';

    protected static ?string $modelLabel = 'Supplier';

    protected static ?string $pluralModelLabel = 'Supplier';

    protected static string|\UnitEnum|null $navigationGroup = 'Kelembagaan & Komunikasi';

    protected static ?int $navigationSort = 20;

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::moduleVisible('pengadaan') && (auth()->user()?->canAny(['view_keuangan', 'input_belanja', 'manage_ledger']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('input_belanja') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('input_belanja') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Supplier')->schema([
                TextInput::make('nama')->label('Nama')->required()->maxLength(255),
                TextInput::make('npwp')->label('NPWP')->maxLength(30),
                Toggle::make('is_pkp')->label('PKP (ber-NPWP)')->inline(false),
                Select::make('kehalalan')->label('Kehalalan')->native(false)->default('halal')
                    ->options(['halal' => 'Halal', 'syubhat' => 'Syubhat', 'non_halal' => 'Non-halal']),
                TextInput::make('telepon')->label('Telepon')->maxLength(30),
                TextInput::make('alamat')->label('Alamat')->maxLength(255),
                TextInput::make('bank_nama')->label('Bank')->maxLength(60),
                TextInput::make('bank_rekening')->label('No. Rekening')->maxLength(40),
                Toggle::make('aktif')->label('Aktif')->default(true)->inline(false),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
                TextColumn::make('npwp')->label('NPWP')->placeholder('—'),
                IconColumn::make('is_pkp')->label('PKP')->boolean(),
                TextColumn::make('kehalalan')->label('Kehalalan')->badge()
                    ->color(fn (string $state): string => $state === 'halal' ? 'success' : 'warning'),
                TextColumn::make('telepon')->label('Telepon')->placeholder('—')->toggleable(),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->defaultSort('nama')
            ->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupplier::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
