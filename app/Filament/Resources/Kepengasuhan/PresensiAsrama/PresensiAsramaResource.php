<?php

namespace App\Filament\Resources\Kepengasuhan\PresensiAsrama;

use App\Filament\Resources\Kepengasuhan\PresensiAsrama\Pages\IsiPresensi;
use App\Filament\Resources\Kepengasuhan\PresensiAsrama\Pages\ListPresensiAsrama;
use App\Filament\Resources\Kepengasuhan\PresensiAsrama\Pages\RekapPresensi;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Kesantrian\Models\PresensiAsrama;
use Modules\Kesantrian\Services\PresensiAsramaService;
use UnitEnum;

class PresensiAsramaResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = PresensiAsrama::class;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('asrama') && (auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('asrama') && (auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false);
    }

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static UnitEnum|string|null $navigationGroup = 'Kesantrian';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Presensi Asrama';

    protected static ?string $modelLabel = 'Presensi Asrama';

    protected static ?string $pluralModelLabel = 'Presensi Asrama';

    public static function table(Table $table): Table
    {
        $svc = app(PresensiAsramaService::class);
        $slotOptions = collect($svc->slotAktif())->pluck('label', 'kode')->all();

        return $table
            ->columns([
                TextColumn::make('tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('slot')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $slotOptions[$state] ?? $state),
                TextColumn::make('santri.nama')
                    ->label('Santri')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'hadir' => 'success',
                        'alpha' => 'danger',
                        'izin' => 'warning',
                        'sakit' => 'info',
                        'terlambat' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('kamar.nomor_kamar')
                    ->label('Kamar')
                    ->placeholder('-'),
                TextColumn::make('halaqah.nama')
                    ->label('Halaqah')
                    ->placeholder('-'),
                TextColumn::make('pencatat.name')
                    ->label('Pencatat'),
            ])
            ->filters([
                Filter::make('tanggal')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('tanggal')
                            ->default(now()),
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['tanggal'],
                        fn (Builder $q, $date) => $q->whereDate('tanggal', $date),
                    )),
                SelectFilter::make('slot')
                    ->options($slotOptions),
                SelectFilter::make('status')
                    ->options([
                        'hadir' => 'Hadir',
                        'alpha' => 'Alpha',
                        'izin' => 'Izin',
                        'sakit' => 'Sakit',
                        'terlambat' => 'Terlambat',
                    ]),
            ])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPresensiAsrama::route('/'),
            'isi' => IsiPresensi::route('/isi'),
            'rekap' => RekapPresensi::route('/rekap'),
        ];
    }
}
