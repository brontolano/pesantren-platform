<?php

namespace App\Filament\Resources\Pajak;

use App\Filament\Resources\Pajak\Pages\ListTarifPajak;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Pajak\Models\TarifPajak;

/**
 * Edit tarif & ambang pajak dari panel (tanpa ngoding) — kebijakan dapat berubah.
 */
class TarifPajakResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = TarifPajak::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'Tarif Pajak';

    protected static ?string $modelLabel = 'Tarif Pajak';

    protected static ?string $pluralModelLabel = 'Tarif Pajak';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 14;

    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\UiMode::advanced() && static::moduleVisible('pajak') && (auth()->user()?->can('manage_pajak') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        return false; // 5 tarif standar di-seed; cukup diedit, tak perlu buat baru.
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('manage_pajak') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Tarif Pajak (PMK 59/2022 — dapat diubah)')->schema([
                TextInput::make('kode')->label('Kode')->required()->maxLength(30)
                    ->helperText('ppn, pph22, pph23_npwp, pph23_non_npwp, pph_final_42'),
                TextInput::make('nama')->label('Nama')->required()->maxLength(255),
                TextInput::make('tarif_persen')->label('Tarif (%)')->numeric()->required()->suffix('%')->step('0.01'),
                TextInput::make('ambang_min')->label('Ambang Minimum (Rp)')->numeric()->prefix('Rp')
                    ->helperText('Kosongkan bila tanpa batas minimum.'),
                TextInput::make('dasar')->label('Dasar Regulasi')->maxLength(255),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->label('Kode')->sortable()->searchable(),
                TextColumn::make('nama')->label('Nama')->searchable(),
                TextColumn::make('tarif_persen')->label('Tarif')->suffix('%'),
                TextColumn::make('ambang_min')->label('Ambang')->money('IDR')->placeholder('—'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('kode')
            ->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTarifPajak::route('/'),
        ];
    }
}
