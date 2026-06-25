<?php

namespace Modules\Tahfidz\Filament\Resources\Munaqasyah;

use App\Filament\Concerns\ModuleGate;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Kesantrian\Models\Santri;
use Modules\Tahfidz\Filament\Resources\Munaqasyah\Pages\CreateMunaqasyah;
use Modules\Tahfidz\Filament\Resources\Munaqasyah\Pages\EditMunaqasyah;
use Modules\Tahfidz\Filament\Resources\Munaqasyah\Pages\ListMunaqasyah;
use Modules\Tahfidz\Models\Munaqasyah;
use UnitEnum;

/**
 * WP-11 — Munaqasyah (ujian kelulusan juz) + penandaan peserta wisuda +
 * aksi cetak daftar wisuda per periode (hanya lulus + ditandai).
 */
class MunaqasyahResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = Munaqasyah::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Ujian Hafalan (Munaqasyah)';

    protected static UnitEnum|string|null $navigationGroup = 'Tahfidz';

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('tahfidz');
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('tahfidz');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make()->schema([
                Select::make('santri_id')
                    ->label('Santri')
                    ->options(Santri::query()->orderBy('nama')->pluck('nama', 'id'))
                    ->searchable()
                    ->required(),
                TextInput::make('juz_diuji')
                    ->label('Juz Diuji')
                    ->placeholder('cth. Juz 1-10')
                    ->required()
                    ->maxLength(255),
                Select::make('penguji_id')
                    ->label('Penguji')
                    ->options(User::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('hasil')
                    ->label('Hasil')
                    ->options(['lulus' => 'Lulus', 'mengulang' => 'Mengulang'])
                    ->required(),
                TextInput::make('nilai')
                    ->label('Nilai')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100),
                DatePicker::make('tanggal')
                    ->label('Tanggal')
                    ->required()
                    ->default(now()),
                Select::make('periode_akademik_id')
                    ->label('Periode Akademik')
                    ->options(PeriodeAkademik::query()->orderByDesc('tanggal_mulai')->pluck('nama', 'id'))
                    ->searchable(),
                Toggle::make('is_peserta_wisuda')
                    ->label('Peserta Wisuda')
                    ->helperText('Hanya munaqasyah berhasil LULUS yang masuk daftar cetak wisuda.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada munaqasyah')
            ->emptyStateDescription('Hasil ujian munaqasyah santri akan tercatat di sini.')
            ->emptyStateIcon('heroicon-o-academic-cap')
            ->columns([
                TextColumn::make('santri.nama')->label('Santri')->sortable()->searchable(),
                TextColumn::make('juz_diuji')->label('Juz Diuji'),
                TextColumn::make('hasil')->label('Hasil')->badge()
                    ->color(fn (string $state): string => $state === 'lulus' ? 'success' : 'warning'),
                TextColumn::make('nilai')->label('Nilai'),
                TextColumn::make('tanggal')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('periodeAkademik.nama')->label('Periode'),
                IconColumn::make('is_peserta_wisuda')->label('Wisuda')->boolean(),
                TextColumn::make('penguji.name')->label('Penguji'),
            ])
            ->filters([
                SelectFilter::make('hasil')->options(['lulus' => 'Lulus', 'mengulang' => 'Mengulang']),
                SelectFilter::make('periode_akademik_id')
                    ->label('Periode')
                    ->options(fn () => PeriodeAkademik::query()->orderByDesc('tanggal_mulai')->pluck('nama', 'id')),
            ])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMunaqasyah::route('/'),
            'create' => CreateMunaqasyah::route('/create'),
            'edit' => EditMunaqasyah::route('/{record}/edit'),
        ];
    }
}
