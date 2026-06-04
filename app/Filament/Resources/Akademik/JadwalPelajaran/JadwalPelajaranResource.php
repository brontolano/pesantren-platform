<?php

namespace App\Filament\Resources\Akademik\JadwalPelajaran;

use App\Filament\Resources\Akademik\JadwalPelajaran\Pages\CreateJadwalPelajaran;
use App\Filament\Resources\Akademik\JadwalPelajaran\Pages\EditJadwalPelajaran;
use App\Filament\Resources\Akademik\JadwalPelajaran\Pages\ListJadwalPelajaran;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Services\JadwalPelajaranService;
use Modules\Kesantrian\Models\Kelas;

class JadwalPelajaranResource extends Resource
{
    protected static ?string $model = JadwalPelajaran::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Jadwal Pelajaran';

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_akademik') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('manage_akademik') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('manage_akademik') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Jadwal')->schema([
                Select::make('periode_akademik_id')
                    ->label('Periode Akademik')
                    ->options(fn () => PeriodeAkademik::query()->orderByDesc('tanggal_mulai')->pluck('nama', 'id'))
                    ->searchable()
                    ->nullable(),
                Select::make('kelas_id')
                    ->options(fn () => Kelas::query()->orderBy('nama_kelas')->pluck('nama_kelas', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('pelajaran_id')
                    ->options(fn () => Pelajaran::query()->orderBy('nama')->pluck('nama', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('hari')
                    ->options([
                        'senin' => 'Senin',
                        'selasa' => 'Selasa',
                        'rabu' => 'Rabu',
                        'kamis' => 'Kamis',
                        'jumat' => 'Jumat',
                        'sabtu' => 'Sabtu',
                        'ahad' => 'Ahad',
                    ])
                    ->required(),
                TextInput::make('jam_mulai')->type('time')->required(),
                TextInput::make('jam_selesai')->type('time')->required(),
                TextInput::make('ruangan')->maxLength(255),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kelas.nama_kelas')->label('Kelas')->sortable(),
                TextColumn::make('pelajaran.nama')->label('Pelajaran')->searchable(),
                TextColumn::make('pelajaran.guruPengampu.name')->label('Guru'),
                TextColumn::make('hari')->badge(),
                TextColumn::make('jam_mulai'),
                TextColumn::make('jam_selesai'),
                TextColumn::make('ruangan'),
            ])
            ->defaultSort('hari')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJadwalPelajaran::route('/'),
            'create' => CreateJadwalPelajaran::route('/create'),
            'edit' => EditJadwalPelajaran::route('/{record}/edit'),
        ];
    }
}
