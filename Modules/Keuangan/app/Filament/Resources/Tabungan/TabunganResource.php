<?php

namespace Modules\Keuangan\Filament\Resources\Tabungan;

use App\Filament\Concerns\ModuleGate;
use Modules\Keuangan\Filament\Resources\Tabungan\Pages\ListTabungan;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Keuangan\Models\Tabungan;
use Modules\Keuangan\Services\TabunganService;

class TabunganResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = Tabungan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wallet';

    protected static ?string $navigationLabel = 'Tabungan Santri';

    protected static ?string $modelLabel = 'Tabungan';

    protected static ?string $pluralModelLabel = 'Tabungan';

    protected static string|\UnitEnum|null $navigationGroup = 'Kas';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('keuangan') && (auth()->user()?->canAny(['view_keuangan', 'manage_keuangan']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('keuangan') && (auth()->user()?->canAny(['view_keuangan', 'manage_keuangan']) ?? false);
    }

    public static function canCreate(): bool
    {
        return false; // Tabungan dibuat otomatis saat setor pertama
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada tabungan')
            ->emptyStateDescription('Saldo tabungan santri akan tampil di sini setelah ada setoran.')
            ->emptyStateIcon('heroicon-o-wallet')
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
                TextColumn::make('limit_harian')
                    ->label('Limit Tarik/Hari')
                    ->money('IDR')
                    ->placeholder('Tanpa limit')
                    ->sortable(),
                IconColumn::make('is_frozen')
                    ->label('Dibekukan')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('danger')
                    ->falseColor('success'),
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
                        (new TabunganService)->setor(
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
                        (new TabunganService)->tarik(
                            $record->santri_id,
                            (float) $data['nominal'],
                            [
                                'keterangan' => $data['keterangan'] ?? null,
                                'created_by' => auth()->id(),
                            ]
                        );
                    }),
                Action::make('pengaturan')
                    ->label('Limit / Bekukan')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('gray')
                    ->visible(fn () => auth()->user()?->can('manage_keuangan'))
                    ->form([
                        TextInput::make('limit_harian')
                            ->label('Limit penarikan per hari')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(0)
                            ->helperText('Kosongkan / 0 = tanpa limit. Berlaku utk tarik tunai & bayar dari saldo.'),
                        Toggle::make('is_frozen')
                            ->label('Bekukan tabungan')
                            ->helperText('Saat dibekukan: penarikan & pembayaran dari saldo ditolak; setoran tetap boleh.'),
                    ])
                    ->fillForm(fn (Tabungan $record): array => [
                        'limit_harian' => $record->limit_harian,
                        'is_frozen' => (bool) $record->is_frozen,
                    ])
                    ->action(function (Tabungan $record, array $data): void {
                        $record->update([
                            'limit_harian' => filled($data['limit_harian'] ?? null) && (float) $data['limit_harian'] > 0
                                ? (float) $data['limit_harian'] : null,
                            'is_frozen' => (bool) ($data['is_frozen'] ?? false),
                        ]);
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
