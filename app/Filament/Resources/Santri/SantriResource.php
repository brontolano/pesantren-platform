<?php

namespace App\Filament\Resources\Santri;

use App\Filament\Resources\Santri\Pages\CreateSantri;
use App\Filament\Resources\Santri\Pages\EditSantri;
use App\Filament\Resources\Santri\Pages\ListSantri;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\DB;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;

class SantriResource extends Resource
{
    protected static ?string $model = Santri::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Santri';

    protected static ?string $modelLabel = 'Santri';

    protected static ?string $pluralModelLabel = 'Santri';

    protected static string|\UnitEnum|null $navigationGroup = 'Kesantrian';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_santri') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('manage_santri') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('manage_santri') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Pribadi')->schema([
                TextInput::make('nis')
                    ->label('NIS')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),
                TextInput::make('nama')
                    ->label('Nama Lengkap')
                    ->required()
                    ->maxLength(255),
                DatePicker::make('tanggal_lahir')
                    ->label('Tanggal Lahir')
                    ->required(),
                TextInput::make('tempat_lahir')
                    ->label('Tempat Lahir')
                    ->maxLength(100),
                Select::make('jenis_kelamin')
                    ->label('Jenis Kelamin')
                    ->options(['L' => 'Laki-laki', 'P' => 'Perempuan'])
                    ->required(),
                TextInput::make('agama')
                    ->label('Agama')
                    ->default('Islam')
                    ->maxLength(50),
                TextInput::make('nik')
                    ->label('NIK')
                    ->maxLength(30)
                    ->placeholder('16 digit NIK'),
                TextInput::make('nisn')
                    ->label('NISN')
                    ->maxLength(50)
                    ->placeholder('10 digit NISN'),
                TextInput::make('asal_sekolah')
                    ->label('Asal Sekolah')
                    ->maxLength(255),
                FileUpload::make('photo_url')
                    ->label('Foto')
                    ->image()
                    ->directory('santri-photos')
                    ->maxSize(2048),
            ])->columns(2),

            Section::make('Alamat Santri')->schema([
                Textarea::make('alamat')
                    ->label('Alamat Lengkap')
                    ->rows(2)
                    ->columnSpanFull(),
                TextInput::make('rt_rw')
                    ->label('RT/RW')
                    ->maxLength(255)
                    ->placeholder('003/008'),
                TextInput::make('kode_pos')
                    ->label('Kode POS')
                    ->maxLength(255)
                    ->placeholder('45321'),
                Select::make('provinsi')
                    ->label('Provinsi')
                    ->options(fn (Get $get) => static::provinsiOptions($get('provinsi')))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (Set $set) {
                        $set('kabupaten_kota', null);
                        $set('kecamatan', null);
                        $set('desa_kelurahan', null);
                    }),
                Select::make('kabupaten_kota')
                    ->label('Kabupaten / Kota')
                    ->options(fn (Get $get) => static::kotaOptions($get('provinsi'), $get('kabupaten_kota')))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (Set $set) {
                        $set('kecamatan', null);
                        $set('desa_kelurahan', null);
                    }),
                Select::make('kecamatan')
                    ->label('Kecamatan')
                    ->options(fn (Get $get) => static::kecamatanOptions($get('kabupaten_kota'), $get('kecamatan')))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (Set $set) {
                        $set('desa_kelurahan', null);
                    }),
                Select::make('desa_kelurahan')
                    ->label('Desa / Kelurahan')
                    ->options(fn (Get $get) => static::desaOptions($get('kecamatan'), $get('desa_kelurahan')))
                    ->searchable(),
            ])->columns(2),

            Section::make('Data Wali / Orang Tua')->schema([
                TextInput::make('nama_ayah')->label('Nama Ayah')->maxLength(255),
                TextInput::make('nama_ibu')->label('Nama Ibu')->maxLength(255),
                TextInput::make('pekerjaan_ayah')->label('Pekerjaan Ayah')->maxLength(255),
                TextInput::make('pekerjaan_ibu')->label('Pekerjaan Ibu')->maxLength(255),
                TextInput::make('no_hp_wali')->label('No. HP Wali')->tel()->maxLength(20),
                TextInput::make('email_wali')->label('Email Wali')->email()->maxLength(255),

                Section::make('Alamat Wali')->schema([
                    TextInput::make('rt_rw_wali')
                        ->label('RT/RW')
                        ->maxLength(255)
                        ->placeholder('001/002'),
                    TextInput::make('kode_pos_wali')
                        ->label('Kode POS')
                        ->maxLength(255)
                        ->placeholder('45312'),
                    Select::make('provinsi_wali')
                        ->label('Provinsi')
                        ->options(fn (Get $get) => static::provinsiOptions($get('provinsi_wali')))
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set) {
                            $set('kabupaten_kota_wali', null);
                            $set('kecamatan_wali', null);
                            $set('desa_kelurahan_wali', null);
                        }),
                    Select::make('kabupaten_kota_wali')
                        ->label('Kabupaten / Kota')
                        ->options(fn (Get $get) => static::kotaOptions($get('provinsi_wali'), $get('kabupaten_kota_wali')))
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set) {
                            $set('kecamatan_wali', null);
                            $set('desa_kelurahan_wali', null);
                        }),
                    Select::make('kecamatan_wali')
                        ->label('Kecamatan')
                        ->options(fn (Get $get) => static::kecamatanOptions($get('kabupaten_kota_wali'), $get('kecamatan_wali')))
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set) {
                            $set('desa_kelurahan_wali', null);
                        }),
                    Select::make('desa_kelurahan_wali')
                        ->label('Desa / Kelurahan')
                        ->options(fn (Get $get) => static::desaOptions($get('kecamatan_wali'), $get('desa_kelurahan_wali')))
                        ->searchable(),
                ])->columns(2)->columnSpanFull(),
            ])->columns(2),

            Section::make('Akademik')->schema([
                Select::make('kelas_id')
                    ->label('Kelas')
                    ->options(fn () => Kelas::where('is_active', true)->pluck('nama_kelas', 'id'))
                    ->searchable()
                    ->nullable(),
                TextInput::make('tahun_angkatan')
                    ->label('Tahun Angkatan')
                    ->placeholder('2026/2027')
                    ->maxLength(9),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'aktif' => 'Aktif',
                        'cuti' => 'Cuti',
                        'lulus' => 'Lulus',
                        'keluar' => 'Keluar',
                        'dikeluarkan' => 'Dikeluarkan',
                    ])
                    ->default('aktif')
                    ->required(),
            ])->columns(2),
        ]);
    }

    /**
     * Opsi provinsi (nama baku dari tabel wilayah tenant).
     * Selalu menyertakan nilai lama (hasil import) agar tidak terhapus.
     */
    protected static function provinsiOptions(?string $current): array
    {
        return static::wilayahOptions(
            fn () => DB::table('provinces')->orderBy('name')->pluck('name', 'name')->toArray(),
            $current
        );
    }

    protected static function kotaOptions(?string $provinsi, ?string $current): array
    {
        return static::wilayahOptions(function () use ($provinsi) {
            if (! $provinsi) {
                return [];
            }
            $province = DB::table('provinces')->where('name', $provinsi)->first();
            if (! $province) {
                return [];
            }

            return DB::table('cities')
                ->where('province_id', $province->id)
                ->orderBy('name')
                ->pluck('name', 'name')
                ->toArray();
        }, $current);
    }

    protected static function kecamatanOptions(?string $kota, ?string $current): array
    {
        return static::wilayahOptions(function () use ($kota) {
            if (! $kota) {
                return [];
            }
            $city = DB::table('cities')->where('name', $kota)->first();
            if (! $city) {
                return [];
            }

            return DB::table('districts')
                ->where('city_id', $city->id)
                ->orderBy('name')
                ->pluck('name', 'name')
                ->toArray();
        }, $current);
    }

    protected static function desaOptions(?string $kecamatan, ?string $current): array
    {
        return static::wilayahOptions(function () use ($kecamatan) {
            if (! $kecamatan) {
                return [];
            }
            $district = DB::table('districts')->where('name', $kecamatan)->first();
            if (! $district) {
                return [];
            }

            return DB::table('villages')
                ->where('district_id', $district->id)
                ->orderBy('name')
                ->pluck('name', 'name')
                ->toArray();
        }, $current);
    }

    /**
     * Jalankan query opsi dengan aman, lalu pastikan nilai lama tetap
     * jadi opsi valid (ditandai "data lama") agar tidak hilang saat disimpan.
     *
     * @param  \Closure(): array<string, string>  $fetch
     * @return array<string, string>
     */
    protected static function wilayahOptions(\Closure $fetch, ?string $current): array
    {
        try {
            $options = $fetch();
        } catch (\Throwable $e) {
            $options = [];
        }

        if ($current !== null && $current !== '' && ! array_key_exists($current, $options)) {
            $options[$current] = $current . ' (data lama)';
        }

        return $options;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo_url')
                    ->label('Foto')
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?background=random&name=S'),
                TextColumn::make('nis')
                    ->label('NIS')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jenis_kelamin')
                    ->label('L/P')
                    ->sortable(),
                TextColumn::make('kelas.nama_kelas')
                    ->label('Kelas')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'aktif' => 'success',
                        'cuti' => 'warning',
                        'lulus' => 'info',
                        'keluar', 'dikeluarkan' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('tahun_angkatan')
                    ->label('Angkatan')
                    ->sortable(),
            ])
            ->defaultSort('nama')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'aktif' => 'Aktif',
                        'cuti' => 'Cuti',
                        'lulus' => 'Lulus',
                        'keluar' => 'Keluar',
                        'dikeluarkan' => 'Dikeluarkan',
                    ]),
                SelectFilter::make('kelas_id')
                    ->label('Kelas')
                    ->options(fn () => Kelas::pluck('nama_kelas', 'id')),
                SelectFilter::make('jenis_kelamin')
                    ->options(['L' => 'Laki-laki', 'P' => 'Perempuan']),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('kelas');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSantri::route('/'),
            'create' => CreateSantri::route('/create'),
            'edit' => EditSantri::route('/{record}/edit'),
        ];
    }
}

