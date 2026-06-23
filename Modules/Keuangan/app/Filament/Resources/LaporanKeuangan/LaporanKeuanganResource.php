<?php

namespace Modules\Keuangan\Filament\Resources\LaporanKeuangan;

use Modules\Keuangan\Filament\Resources\LaporanKeuangan\Pages\ListLaporanKeuangan;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Keuangan\Models\Tagihan;

class LaporanKeuanganResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = Tagihan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Laporan Keuangan';

    protected static ?string $modelLabel = 'Laporan';

    protected static ?string $pluralModelLabel = 'Laporan Keuangan';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'laporan-keuangan';

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
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('santri.nama')
                    ->label('Santri')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jenis_tagihan')
                    ->label('Jenis')
                    ->badge(),
                TextColumn::make('nominal_akhir')
                    ->label('Tagihan')
                    ->money('IDR'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'belum_bayar' => 'danger',
                        'sebagian' => 'warning',
                        'lunas' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('bulan_masehi')
                    ->label('Periode')
                    ->sortable(),
                TextColumn::make('jatuh_tempo')
                    ->label('Jatuh Tempo')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('jatuh_tempo', 'desc')
            ->query(fn () => Tagihan::query()->with('santri')->whereNull('deleted_at'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLaporanKeuangan::route('/'),
        ];
    }
}

