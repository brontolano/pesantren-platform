<?php

namespace App\Filament\Resources\Akademik\Nilai;

use App\Filament\Resources\Akademik\Nilai\Pages\CreateNilai;
use App\Filament\Resources\Akademik\Nilai\Pages\EditNilai;
use App\Filament\Resources\Akademik\Nilai\Pages\ListNilai;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;

class NilaiResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;
    protected static ?string $model = Nilai::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Input Nilai';

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik & Tahfidz';

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('akademik') && (auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('akademik') && (auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false);
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
            Section::make('Input Nilai')->schema([
                Select::make('periode_akademik_id')
                    ->label('Periode Akademik')
                    ->options(fn () => PeriodeAkademik::query()->orderByDesc('tanggal_mulai')->pluck('nama', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('kelas_id')
                    ->label('Kelas')
                    ->options(fn () => static::kelasOptions())
                    ->searchable()
                    ->required(),
                Select::make('pelajaran_id')
                    ->label('Pelajaran')
                    ->options(fn () => static::pelajaranOptions())
                    ->searchable()
                    ->required(),
                Select::make('santri_id')
                    ->label('Santri')
                    ->options(fn () => Santri::query()->orderBy('nama')->pluck('nama', 'id'))
                    ->searchable()
                    ->required(),
                TextInput::make('nilai_harian')->numeric()->minValue(0)->maxValue(100),
                TextInput::make('nilai_uts')->numeric()->minValue(0)->maxValue(100),
                TextInput::make('nilai_uas')->numeric()->minValue(0)->maxValue(100),
                TextInput::make('nilai_akhir')->numeric()->minValue(0)->maxValue(100)->helperText('Kosongkan bila ingin dihitung otomatis dari bobot tenant.'),
                Textarea::make('catatan')->rows(3),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('santri.nama')->label('Santri')->searchable(),
                TextColumn::make('kelas.nama_kelas')->label('Kelas')->sortable(),
                TextColumn::make('pelajaran.nama')->label('Pelajaran')->searchable(),
                TextColumn::make('periodeAkademik.nama')->label('Periode'),
                TextColumn::make('nilai_harian')->sortable(),
                TextColumn::make('nilai_uts')->sortable(),
                TextColumn::make('nilai_uas')->sortable(),
                TextColumn::make('nilai_akhir')->sortable()->badge(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['santri', 'kelas', 'pelajaran', 'periodeAkademik']);

        $user = auth()->user();

        if (! $user instanceof User) {
            return $query;
        }

        if ($user->hasAnyRole(['guru', 'guru_tahfidz']) && ! $user->hasAnyRole(['super_admin', 'admin_pesantren', 'tu', 'kepala'])) {
            $kelasIds = JadwalPelajaran::query()
                ->whereHas('pelajaran', fn (Builder $pelajaranQuery) => $pelajaranQuery->where('guru_pengampu_id', $user->getKey()))
                ->pluck('kelas_id')
                ->unique()
                ->all();

            return $query
                ->whereHas('pelajaran', fn (Builder $pelajaranQuery) => $pelajaranQuery->where('guru_pengampu_id', $user->getKey()))
                ->whereIn('kelas_id', $kelasIds);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNilai::route('/'),
            'create' => CreateNilai::route('/create'),
            'edit' => EditNilai::route('/{record}/edit'),
        ];
    }

    private static function pelajaranOptions(): array
    {
        $query = Pelajaran::query()->orderBy('nama');
        $user = auth()->user();

        if ($user instanceof User && $user->hasAnyRole(['guru', 'guru_tahfidz']) && ! $user->hasAnyRole(['super_admin', 'admin_pesantren', 'tu', 'kepala'])) {
            $query->where('guru_pengampu_id', $user->getKey());
        }

        return $query->pluck('nama', 'id')->all();
    }

    private static function kelasOptions(): array
    {
        $query = Kelas::query()->orderBy('nama_kelas');
        $user = auth()->user();

        if ($user instanceof User && $user->hasAnyRole(['guru', 'guru_tahfidz']) && ! $user->hasAnyRole(['super_admin', 'admin_pesantren', 'tu', 'kepala'])) {
            $kelasIds = JadwalPelajaran::query()
                ->whereHas('pelajaran', fn (Builder $pelajaranQuery) => $pelajaranQuery->where('guru_pengampu_id', $user->getKey()))
                ->pluck('kelas_id')
                ->unique()
                ->all();

            $query->whereIn('id', $kelasIds);
        }

        return $query->pluck('nama_kelas', 'id')->all();
    }
}

