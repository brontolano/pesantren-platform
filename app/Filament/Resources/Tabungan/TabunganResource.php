<?php

namespace App\Filament\Resources\Tabungan;

use App\Filament\Resources\Tabungan\Pages\ListTabungan;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Keuangan\Models\Tabungan;
use Modules\Keuangan\Services\TabunganService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

class TabunganResource extends Resource
{
    protected static ?string $model = Tabungan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wallet';

    protected static ?string $navigationLabel = 'Tabungan Santri';

    protected static ?string $modelLabel = 'Tabungan';

    protected static ?string $pluralModelLabel = 'Tabungan';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canAny(['view_keuangan', 'manage_keuangan']) ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canAny(['view_keuangan', 'manage_keuangan']) ?? false;
    }

    public static function canCreate(): bool
    {
        return false; // Tabungan dibuat otomatis saat setor pertama
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('santri.nis')
                    ->label('NIS')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('santri.nama')
                    ->label('Santri')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('saldo')
                    ->label('Saldo')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Terakhir Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('santri.nama')
            ->actions([
                Action::make('setor')
                    ->label('Setor')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->can('manage_keuangan'))
                    ->form([
                        TextInput::make('nominal')
                            ->label('Nominal Setor')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->minValue(1),
                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(2),
                    ])
                    ->action(function (Tabungan $record, array $data): void {
                        (new TabunganService())->setor(
                            $record->santri_id,
                            (float) $data['nominal'],
                            [
                                'keterangan' => $data['keterangan'] ?? null,
                                'created_by' => auth()->id(),
                            ]
                        );
                    }),
                Action::make('tarik')
                    ->label('Tarik')
                    ->icon('heroicon-o-minus-circle')
                    ->color('danger')
                    ->visible(fn () => auth()->user()?->can('manage_keuangan'))
                    ->form([
                        TextInput::make('nominal')
                            ->label('Nominal Tarik')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->minValue(1),
                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(2),
                    ])
                    ->action(function (Tabungan $record, array $data): void {
                        (new TabunganService())->tarik(
                            $record->santri_id,
                            (float) $data['nominal'],
                            [
                                'keterangan' => $data['keterangan'] ?? null,
                                'created_by' => auth()->id(),
                            ]
                        );
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('santri');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTabungan::route('/'),
        ];
    }
}

