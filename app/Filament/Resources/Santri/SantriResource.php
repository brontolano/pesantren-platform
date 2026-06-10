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
use Filament\Schemas\Components\Actions as FormActions;
use Filament\Actions\Action as FormAction;
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

    /** Pencarian global (Cmd/Ctrl+K): cari santri cepat dgn nama atau NIS. */
    public static function getGloballySearchableAttributes(): array
    {
        return ['nama', 'nis'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return (string) $record->nama;
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'NIS' => (string) ($record->nis ?? '—'),
            'Kelas' => (string) ($record->kelas?->nama_kelas ?? '—'),
        ];
    }

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

            Section::make('Data Ayah')->schema([
                TextInput::make('nama_ayah')->label('Nama Ayah')->maxLength(255),
                TextInput::make('pekerjaan_ayah')->label('Pekerjaan Ayah')->maxLength(255),
                TextInput::make('no_hp_ayah')->label('No. HP Ayah')->tel()->maxLength(20),
                Section::make('Alamat Ayah')->schema(
                    static::alamatPihakFields('ayah')
                )->columns(2)->columnSpanFull()->collapsible(),
            ])->columns(2),

            Section::make('Data Ibu')->schema([
                FormActions::make([
                    FormAction::make('salinAlamatAyah')
                        ->label('Salin alamat dari Ayah')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->action(function (Get $get, Set $set): void {
                            foreach (['alamat', 'rt_rw', 'kode_pos', 'provinsi', 'kabupaten_kota', 'kecamatan', 'desa_kelurahan'] as $f) {
                                $set("{$f}_ibu", $get("{$f}_ayah"));
                            }
                        }),
                ])->columnSpanFull(),
                TextInput::make('nama_ibu')->label('Nama Ibu')->maxLength(255),
                TextInput::make('pekerjaan_ibu')->label('Pekerjaan Ibu')->maxLength(255),
                TextInput::make('no_hp_ibu')->label('No. HP Ibu')->tel()->maxLength(20),
                Section::make('Alamat Ibu')->schema(
                    static::alamatPihakFields('ibu')
                )->columns(2)->columnSpanFull()->collapsible(),
            ])->columns(2),

            Section::make('Akun Wali (untuk Portal Wali & login)')->schema([
                TextInput::make('no_hp_wali')->label('No. HP Wali Utama')->tel()->maxLength(20)
                    ->helperText('Nomor ini jadi kunci akun Portal Wali — boleh nomor ayah, ibu, atau wali lain. Anak lain dengan nomor sama akan tergabung ke satu akun.'),
                TextInput::make('email_wali')->label('Email Wali')->email()->maxLength(255),

                Section::make('Alamat Wali / Domisili Keluarga')->schema([
                    TextInput::make('rt_rw_wali')->label('RT/RW')->maxLength(255)->placeholder('001/002'),
                    TextInput::make('kode_pos_wali')->label('Kode POS')->maxLength(255)->placeholder('45312'),
                    Select::make('provinsi_wali')->label('Provinsi')
                        ->options(fn (Get $get) => static::provinsiOptions($get('provinsi_wali')))
                        ->searchable()->live()
                        ->afterStateUpdated(function (Set $set) {
                            $set('kabupaten_kota_wali', null);
                            $set('kecamatan_wali', null);
                            $set('desa_kelurahan_wali', null);
                        }),
                    Select::make('kabupaten_kota_wali')->label('Kabupaten / Kota')
                        ->options(fn (Get $get) => static::kotaOptions($get('provinsi_wali'), $get('kabupaten_kota_wali')))
                        ->searchable()->live()
                        ->afterStateUpdated(function (Set $set) {
                            $set('kecamatan_wali', null);
                            $set('desa_kelurahan_wali', null);
                        }),
                    Select::make('kecamatan_wali')->label('Kecamatan')
                        ->options(fn (Get $get) => static::kecamatanOptions($get('kabupaten_kota_wali'), $get('kecamatan_wali')))
                        ->searchable()->live()
                        ->afterStateUpdated(fn (Set $set) => $set('desa_kelurahan_wali', null)),
                    Select::make('desa_kelurahan_wali')->label('Desa / Kelurahan')
                        ->options(fn (Get $get) => static::desaOptions($get('kecamatan_wali'), $get('desa_kelurahan_wali')))
                        ->searchable(),
                ])->columns(2)->columnSpanFull()->collapsible()->collapsed(),
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
    /**
     * Field alamat terurai (cascading wilayah) untuk satu pihak: 'ayah' | 'ibu'.
     * Memakai helper wilayah yang sama dengan alamat santri/wali.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    protected static function alamatPihakFields(string $pihak): array
    {
        return [
            Textarea::make("alamat_{$pihak}")->label('Alamat (jalan/dusun)')->rows(2)->columnSpanFull(),
            TextInput::make("rt_rw_{$pihak}")->label('RT/RW')->maxLength(10)->placeholder('001/002'),
            TextInput::make("kode_pos_{$pihak}")->label('Kode POS')->maxLength(5)->placeholder('45312'),
            Select::make("provinsi_{$pihak}")->label('Provinsi')
                ->options(fn (Get $get) => static::provinsiOptions($get("provinsi_{$pihak}")))
                ->searchable()->live()
                ->afterStateUpdated(function (Set $set) use ($pihak) {
                    $set("kabupaten_kota_{$pihak}", null);
                    $set("kecamatan_{$pihak}", null);
                    $set("desa_kelurahan_{$pihak}", null);
                }),
            Select::make("kabupaten_kota_{$pihak}")->label('Kabupaten / Kota')
                ->options(fn (Get $get) => static::kotaOptions($get("provinsi_{$pihak}"), $get("kabupaten_kota_{$pihak}")))
                ->searchable()->live()
                ->afterStateUpdated(function (Set $set) use ($pihak) {
                    $set("kecamatan_{$pihak}", null);
                    $set("desa_kelurahan_{$pihak}", null);
                }),
            Select::make("kecamatan_{$pihak}")->label('Kecamatan')
                ->options(fn (Get $get) => static::kecamatanOptions($get("kabupaten_kota_{$pihak}"), $get("kecamatan_{$pihak}")))
                ->searchable()->live()
                ->afterStateUpdated(fn (Set $set) => $set("desa_kelurahan_{$pihak}", null)),
            Select::make("desa_kelurahan_{$pihak}")->label('Desa / Kelurahan')
                ->options(fn (Get $get) => static::desaOptions($get("kecamatan_{$pihak}"), $get("desa_kelurahan_{$pihak}")))
                ->searchable(),
        ];
    }

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
            ->emptyStateHeading('Belum ada data santri')
            ->emptyStateDescription('Mulai dengan mengimpor data dari Excel, atau tambah santri satu per satu.')
            ->emptyStateIcon('heroicon-o-user-group')
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
                \Filament\Actions\Action::make('id_card')
                    ->label('ID Card')
                    ->icon('heroicon-o-identification')
                    ->action(fn ($record) => static::unduhIdCard(collect([$record]))),
                \Filament\Actions\Action::make('kirim_kredensial')
                    ->label('Kirim Kredensial')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->visible(fn (): bool => auth()->user()?->can('manage_santri') ?? false)
                    ->modalHeading('Kirim Info Login App Santri')
                    ->modalDescription('Akun santri dibuat (bila belum), PIN baru 6 digit di-generate, lalu dikirim via WhatsApp ke nomor wali di bawah. Santri login pakai NIS + PIN. Periksa nomornya baik-baik.')
                    ->modalSubmitActionLabel('Buat PIN & Kirim')
                    ->form([
                        TextInput::make('no_hp')
                            ->label('Nomor WhatsApp Wali Tujuan')
                            ->tel()
                            ->required()
                            ->default(fn (Santri $record): ?string => $record->no_hp_wali ?: ($record->no_hp_ayah ?: $record->no_hp_ibu))
                            ->helperText('Default = No. HP Wali Utama. Bisa diubah ke nomor ayah/ibu.'),
                    ])
                    ->action(function (Santri $record, array $data): void {
                        $terkirim = static::kirimKredensialSantri($record, (string) $data['no_hp']);
                        \Filament\Notifications\Notification::make()
                            ->title($terkirim ? 'Kredensial terkirim' : 'PIN dibuat, tapi WA gagal/template nonaktif')
                            ->body($terkirim
                                ? "Info login (NIS {$record->nis} + PIN baru) dikirim ke {$data['no_hp']}."
                                : 'PIN baru sudah dibuat. Aktifkan template KREDENSIAL_SANTRI / cek nomor WA.')
                            ->{$terkirim ? 'success' : 'warning'}()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkAction::make('id_card_massal')
                    ->label('Cetak ID Card')
                    ->icon('heroicon-o-identification')
                    ->action(fn ($records) => static::unduhIdCard(collect($records)))
                    ->deselectRecordsAfterCompletion(),
                \Filament\Actions\BulkAction::make('kirim_kredensial_massal')
                    ->label('Kirim Kredensial')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->visible(fn (): bool => auth()->user()?->can('manage_santri') ?? false)
                    ->requiresConfirmation()
                    ->modalDescription('Buat PIN baru & kirim info login ke nomor WALI UTAMA masing-masing santri terpilih. Santri tanpa nomor wali dilewati.')
                    ->action(function ($records): void {
                        $ok = 0;
                        $lewat = 0;
                        foreach ($records as $record) {
                            $phone = $record->no_hp_wali ?: ($record->no_hp_ayah ?: $record->no_hp_ibu);
                            if (! $phone) {
                                $lewat++;

                                continue;
                            }
                            if (static::kirimKredensialSantri($record, (string) $phone)) {
                                $ok++;
                            }
                        }
                        \Filament\Notifications\Notification::make()
                            ->title("Kredensial terkirim: {$ok}")
                            ->body($lewat > 0 ? "{$lewat} santri dilewati (tanpa nomor wali)." : 'Selesai.')
                            ->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    /**
     * Buat akun santri (idempoten) + PIN baru, kirim ke WA wali via template
     * KREDENSIAL_SANTRI. Return true bila WA terkirim. Tak melempar.
     */
    protected static function kirimKredensialSantri(Santri $record, string $phone): bool
    {
        try {
            /** @var \Modules\Kesantrian\Services\SantriAccountService $svc */
            $svc = app(\Modules\Kesantrian\Services\SantriAccountService::class);
            [, $pin] = $svc->resetPin($record);
            $svc->tautkanWali($record->fresh());

            $hasil = app(\Modules\Notifikasi\Services\NotifDispatcher::class)->kirim('kredensial_santri', [
                'wa' => [$phone],
                'data' => [
                    'nama_santri' => (string) $record->nama,
                    'nis' => (string) $record->nis,
                    'username' => (string) $record->nis, // login App Santri = NIS
                    'password' => $pin,
                    'nama_pesantren' => \App\Models\Setting::getTenantStringValue('branding_name') ?? (tenant('name') ?? 'Pesantren'),
                ],
            ]);

            return (int) ($hasil['terkirim'] ?? 0) > 0;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Unduh ID Card (pola F8): PDF bila dompdf terpasang; fallback HTML
     * siap-print-browser. QR NIS inline — lihat IdCardService.
     */
    public static function unduhIdCard(\Illuminate\Support\Collection $santri)
    {
        $svc = app(\Modules\Kesantrian\Services\IdCardService::class);

        try {
            return app(\Modules\Core\Services\ReportExportService::class)->pdf(
                'id-card-santri',
                ['judul' => 'ID Card Santri', 'kartu' => $svc->kartu($santri)],
                'id-card-santri.pdf',
            );
        } catch (\Modules\Core\Exceptions\ReportPdfUnavailableException) {
            $html = $svc->html($santri);

            return response()->streamDownload(
                fn () => print($html),
                'id-card-santri.html',
                ['Content-Type' => 'text/html; charset=UTF-8'],
            );
        }
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

