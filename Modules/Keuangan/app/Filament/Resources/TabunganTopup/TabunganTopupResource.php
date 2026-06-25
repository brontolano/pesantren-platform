<?php

namespace Modules\Keuangan\Filament\Resources\TabunganTopup;

use App\Filament\Concerns\ModuleGate;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Keuangan\Filament\Resources\TabunganTopup\Pages\ListTabunganTopup;
use Modules\Keuangan\Models\TabunganTopup;
use Modules\Keuangan\Services\TopupService;

class TabunganTopupResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = TabunganTopup::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Verifikasi Topup';

    protected static ?string $modelLabel = 'Topup Tabungan';

    protected static ?string $pluralModelLabel = 'Topup Tabungan';

    protected static string|\UnitEnum|null $navigationGroup = 'Tabungan Santri';

    protected static ?int $navigationSort = 5;

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
        return false; // Topup diajukan oleh wali via portal, bukan dari ERP
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tabungan.santri.nama')
                    ->label('Santri')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nominal')
                    ->label('Nominal')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('nama_pengirim')
                    ->label('Pengirim')
                    ->searchable(),
                TextColumn::make('tanggal_transfer')
                    ->label('Tgl Transfer')
                    ->date('d M Y')
                    ->sortable(),
                ImageColumn::make('bukti')
                    ->label('Bukti')
                    ->disk('public')
                    ->height(48)
                    ->width(48)
                    ->extraImgAttributes(['class' => 'rounded-lg object-cover']),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'menunggu' => 'warning',
                        'disetujui' => 'success',
                        'ditolak' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('verifier.name')
                    ->label('Diverifikasi oleh')
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'menunggu' => 'Menunggu',
                        'disetujui' => 'Disetujui',
                        'ditolak' => 'Ditolak',
                    ]),
            ])
            ->actions([
                Action::make('setujui')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (TabunganTopup $record) => $record->status === 'menunggu' && auth()->user()?->can('manage_keuangan'))
                    ->action(function (TabunganTopup $record): void {
                        (new TopupService)->setujui($record, auth()->id());
                    }),
                Action::make('tolak')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (TabunganTopup $record) => $record->status === 'menunggu' && auth()->user()?->can('manage_keuangan'))
                    ->form([
                        Textarea::make('alasan')
                            ->label('Alasan Penolakan')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (TabunganTopup $record, array $data): void {
                        (new TopupService)->tolak($record, auth()->id(), $data['alasan']);
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['tabungan.santri', 'verifier']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTabunganTopup::route('/'),
        ];
    }
}
