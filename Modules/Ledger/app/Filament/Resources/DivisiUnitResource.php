<?php

namespace Modules\Ledger\Filament\Resources;

use Modules\Ledger\Filament\Resources\Pages\CreateDivisiUnit;
use Modules\Ledger\Filament\Resources\Pages\EditDivisiUnit;
use Modules\Ledger\Filament\Resources\Pages\ListDivisiUnit;
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
use Modules\Ledger\Models\DivisiUnit;

class DivisiUnitResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = DivisiUnit::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Divisi / Unit';

    protected static ?string $modelLabel = 'Divisi / Unit';

    protected static ?string $pluralModelLabel = 'Divisi / Unit';

    protected static string|\UnitEnum|null $navigationGroup = 'Akuntansi & Pajak';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::moduleVisible('ledger') && (auth()->user()?->canAny(['view_ledger', 'manage_ledger']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('ledger') && (auth()->user()?->canAny(['view_ledger', 'manage_ledger']) ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_ledger') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('manage_ledger') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('manage_ledger') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Unit Pelaksana (Dimensi DI MANA)')->schema([
                TextInput::make('kode')->label('Kode')->maxLength(30),
                TextInput::make('nama_divisi')->label('Nama Divisi')->required()->maxLength(255),
                Select::make('tipe_layanan')->label('Tipe Layanan')->options([
                    'Formal' => 'Formal',
                    'Non-Formal' => 'Non-Formal',
                ]),
                Toggle::make('is_komersial')->label('Unit Komersial (sumber EBITDA)'),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->label('Kode')->sortable()->searchable(),
                TextColumn::make('nama_divisi')->label('Nama')->searchable(),
                TextColumn::make('tipe_layanan')->label('Tipe')->badge(),
                IconColumn::make('is_komersial')->label('Komersial')->boolean(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('kode')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDivisiUnit::route('/'),
            'create' => CreateDivisiUnit::route('/create'),
            'edit' => EditDivisiUnit::route('/{record}/edit'),
        ];
    }
}
