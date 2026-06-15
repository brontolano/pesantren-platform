<?php

namespace App\Filament\Resources\PenempatanKelas;

use App\Filament\Resources\PenempatanKelas\Pages\CreatePenempatanKelas;
use App\Filament\Resources\PenempatanKelas\Pages\ListPenempatanKelas;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\PenempatanKelas;
use Modules\Kesantrian\Models\Santri;

class PenempatanKelasResource extends Resource
{
    protected static ?string $model = PenempatanKelas::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Penempatan Kelas';

    protected static ?string $modelLabel = 'Penempatan Kelas';

    protected static ?string $pluralModelLabel = 'Penempatan Kelas';

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('manage_santri') ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_santri') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('santri_id')
                ->label('Santri')
                ->options(fn () => Santri::where('status', 'aktif')->pluck('nama', 'id'))
                ->searchable()
                ->required(),
            Select::make('kelas_id')
                ->label('Kelas Tujuan')
                ->options(fn () => Kelas::where('is_active', true)->pluck('nama_kelas', 'id'))
                ->searchable()
                ->required(),
            TextInput::make('tahun_akademik')
                ->label('Tahun Akademik')
                ->required()
                ->placeholder('2026/2027'),
            Select::make('semester')
                ->label('Semester')
                ->options(['Ganjil' => 'Ganjil', 'Genap' => 'Genap'])
                ->nullable(),
            DatePicker::make('tanggal_penempatan')
                ->label('Tanggal Penempatan')
                ->default(now())
                ->required(),
            TextInput::make('alasan_mutasi')
                ->label('Alasan Mutasi')
                ->placeholder('Kosongkan jika penempatan awal')
                ->maxLength(255),
            Toggle::make('is_current')
                ->label('Penempatan Aktif')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('santri.nama')
                    ->label('Santri')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('santri.nis')
                    ->label('NIS')
                    ->searchable(),
                TextColumn::make('kelas.nama_kelas')
                    ->label('Kelas')
                    ->sortable(),
                TextColumn::make('tahun_akademik')
                    ->label('Tahun Akademik')
                    ->sortable(),
                TextColumn::make('semester')
                    ->label('Semester'),
                TextColumn::make('tanggal_penempatan')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('alasan_mutasi')
                    ->label('Alasan')
                    ->placeholder('Penempatan awal')
                    ->limit(30),
                IconColumn::make('is_current')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->defaultSort('tanggal_penempatan', 'desc')
            ->filters([
                SelectFilter::make('kelas_id')
                    ->label('Kelas')
                    ->options(fn () => Kelas::pluck('nama_kelas', 'id')),
                SelectFilter::make('is_current')
                    ->label('Status')
                    ->options([1 => 'Aktif', 0 => 'Riwayat']),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['santri', 'kelas']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPenempatanKelas::route('/'),
            'create' => CreatePenempatanKelas::route('/create'),
        ];
    }
}
