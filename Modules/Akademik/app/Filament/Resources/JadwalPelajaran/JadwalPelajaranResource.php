<?php

namespace Modules\Akademik\Filament\Resources\JadwalPelajaran;

use App\Filament\Concerns\ModuleGate;
use Modules\Akademik\Filament\Resources\JadwalPelajaran\Pages\CreateJadwalPelajaran;
use Modules\Akademik\Filament\Resources\JadwalPelajaran\Pages\EditJadwalPelajaran;
use Modules\Akademik\Filament\Resources\JadwalPelajaran\Pages\ListJadwalPelajaran;
use App\Support\UiMode;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Services\KurikulumService;
use Modules\Kesantrian\Models\Kelas;

class JadwalPelajaranResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = JadwalPelajaran::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Jadwal Pelajaran';

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('akademik') && (auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('akademik') && (auth()->user()?->canAny(['view_akademik', 'manage_akademik']) ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_akademik') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('manage_akademik') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('manage_akademik') ?? false;
    }

    /**
     * WP-06: pesan peringatan LUNAK bila mapel di luar kurikulum aktif
     * tingkat kelas itu; null bila aman / kelas tanpa kurikulum (perilaku
     * lama utuh). TIDAK pernah memblokir simpan.
     */
    public static function kurikulumWarning(mixed $kelasId, mixed $pelajaranId): ?string
    {
        if (! $kelasId || ! $pelajaranId) {
            return null;
        }

        $diLuar = app(KurikulumService::class)
            ->isMapelDiLuarKurikulum((int) $kelasId, (int) $pelajaranId);

        return $diLuar
            ? 'Mapel ini tidak terdaftar di kurikulum aktif untuk tingkat kelas tersebut.'
            : null;
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
                    ->live()
                    ->required(),
                Select::make('pelajaran_id')
                    ->options(fn () => Pelajaran::query()->orderBy('nama')->pluck('nama', 'id'))
                    ->searchable()
                    ->live()
                    // WP-06: validasi LUNAK kurikulum — hanya peringatan,
                    // simpan TIDAK pernah diblokir (tenant tanpa kurikulum
                    // tidak melihat apa-apa).
                    ->hint(fn (Get $get): ?string => self::kurikulumWarning($get('kelas_id'), $get('pelajaran_id')))
                    ->hintColor('warning')
                    ->afterStateUpdated(function (Get $get, $state): void {
                        $warning = self::kurikulumWarning($get('kelas_id'), $state);
                        if ($warning !== null) {
                            Notification::make()
                                ->title('Mapel di luar kurikulum')
                                ->body($warning.' Jadwal tetap bisa disimpan.')
                                ->warning()
                                ->send();
                        }
                    })
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
