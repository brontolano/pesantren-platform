<?php

namespace App\Filament\Resources\Payroll\Employee;

use App\Filament\Concerns\ModuleGate;
use App\Filament\Resources\Payroll\Employee\Pages\CreateEmployee;
use App\Filament\Resources\Payroll\Employee\Pages\EditEmployee;
use App\Filament\Resources\Payroll\Employee\Pages\ListEmployee;
use App\Models\User;
use App\Services\StorageQuotaService;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\PenilaianUstadz;
use Modules\Payroll\Models\SertifikasiPegawai;
use Modules\Payroll\Services\RaporUstadzService;

/**
 * G2-10 — Master Pegawai + Struktur Gaji.
 * WP-18 — Section Arsip Sertifikasi (TPG/Inpassing/GBPNS/PPG): upload arsip
 * acceptedFileTypes WAJIB pdf/jpg/png (pelajaran stored-XSS WP-08); file
 * diserve via /_tenant-storage, direktori 'sertifikasi' masuk daftar sensitif.
 */
class EmployeeResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = Employee::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Pegawai (HR)';

    protected static ?string $modelLabel = 'Pegawai';

    protected static ?string $pluralModelLabel = 'Pegawai';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 24;

    /** WP-18 — Tipe file arsip sertifikasi yang diizinkan. JANGAN dilonggarkan (WP-08). */
    public const SERTIFIKASI_FILE_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('payroll') && (auth()->user()?->can('run_payroll') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('run_payroll') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('run_payroll') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Pegawai')->schema([
                TextInput::make('nama')->label('Nama')->required()->maxLength(255),
                Select::make('users_id')->label('Akun Pengguna (untuk slip)')->searchable()
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                    ->helperText('Wajib untuk generate slip gaji.'),
                TextInput::make('nip')->label('NIP')->maxLength(40),
                // WP-03: kolom string deprecated — sumber kebenaran kini Penugasan Pegawai.
                TextInput::make('jabatan')->label('Jabatan (lama — gunakan Penugasan)')
                    ->disabled()->dehydrated(false)
                    ->helperText('Kolom lama (read-only). Kelola jabatan via menu Penugasan Pegawai.'),
                // WP-04: opsi dari SATU sumber kebenaran — konstanta model.
                // Nilai legacy tak dikenal (mis. 'magang') IKUT jadi opsi di
                // record itu — tanpa ini edit form kosong + validasi `in`
                // memblokir simpan (data lama rusak/macet diam-diam).
                Select::make('status_kepegawaian')->label('Status Kepegawaian')->native(false)
                    ->default('gtt_honorer')
                    ->options(function (?Employee $record): array {
                        $opts = Employee::STATUS_KEPEGAWAIAN;
                        $lama = $record?->status_kepegawaian;
                        if ($lama !== null && $lama !== '' && ! isset($opts[$lama])) {
                            $opts[$lama] = $lama.' (nilai lama — mohon perbarui)';
                        }

                        return $opts;
                    })
                    ->in(fn (Select $component): array => array_keys($component->getOptions())),
                TextInput::make('npwp')->label('NPWP')->maxLength(30),
                Select::make('ptkp_status')->label('Status PTKP (PPh21)')->native(false)->default('TK0')
                    ->options(['TK0' => 'TK/0', 'TK1' => 'TK/1', 'TK2' => 'TK/2', 'TK3' => 'TK/3',
                        'K0' => 'K/0', 'K1' => 'K/1', 'K2' => 'K/2', 'K3' => 'K/3']),
                DatePicker::make('tanggal_masuk')->label('Tanggal Masuk'),
                DatePicker::make('tanggal_selesai')->label('Tanggal Selesai (resign/purna)')
                    ->helperText('Mengisi tanggal ini otomatis menonaktifkan pegawai.'),
                Toggle::make('aktif')->label('Aktif')->default(true)->inline(false),
            ])->columns(2),
            // WP-04 — Identitas EMIS (S-0.3).
            Section::make('Identitas (EMIS)')->schema([
                TextInput::make('nik')->label('NIK (KTP)')->maxLength(20)
                    ->unique(ignoreRecord: true)
                    ->validationMessages(['unique' => 'NIK ini sudah dipakai pegawai lain.']),
                TextInput::make('nuptk')->label('NUPTK')->maxLength(20),
                TextInput::make('nrg')->label('NRG (sertifikasi)')->maxLength(20),
                TextInput::make('pendidikan_terakhir')->label('Pendidikan Terakhir')
                    ->placeholder('mis. S1 PAI, MA, Pesantren')->maxLength(255),
                Toggle::make('is_mukim')->label('Mukim (tinggal di pondok)')->inline(false),
                Toggle::make('asal_alumni')->label('Alumni pesantren ini')->inline(false),
            ])->columns(2)->collapsible(),
            // WP-18 — Arsip Sertifikasi: CRUD via Repeater relationship (pola
            // komponenGaji). Data queryable utk rekap kesiapan EMIS (WP-12).
            Section::make('Arsip Sertifikasi (EMIS)')->schema([
                Repeater::make('sertifikasi')->label('Sertifikasi')->relationship('sertifikasi')
                    ->schema([
                        Select::make('jenis')->label('Jenis')->native(false)->required()
                            ->options(SertifikasiPegawai::JENIS),
                        TextInput::make('nrg')->label('NRG')->maxLength(255),
                        TextInput::make('nomor_sk')->label('Nomor SK')->required()->maxLength(255),
                        TextInput::make('nominal')->label('Nominal')->numeric()->prefix('Rp'),
                        DatePicker::make('berlaku_mulai')->label('Berlaku Mulai'),
                        DatePicker::make('berlaku_selesai')->label('Berlaku Selesai')
                            ->afterOrEqual('berlaku_mulai'),
                        FileUpload::make('file')
                            ->label('Arsip (PDF/JPG/PNG)')
                            ->disk(StorageQuotaService::diskName())
                            ->directory('sertifikasi')
                            ->acceptedFileTypes(self::SERTIFIKASI_FILE_TYPES) // WAJIB — WP-08
                            ->maxSize(5120)
                            ->columnSpanFull(),
                    ])->columns(3)->defaultItems(0)->addActionLabel('Tambah Sertifikasi'),
            ])->collapsible()->collapsed(),
            Section::make('Struktur Gaji')->schema([
                Repeater::make('komponenGaji')->label('Komponen Gaji')->relationship('komponenGaji')
                    ->schema([
                        TextInput::make('nama')->label('Komponen')->required()->columnSpan(2),
                        Select::make('tipe')->label('Tipe')->native(false)->required()->default('tetap')
                            ->options(['tetap' => 'Tetap (pokok)', 'tunjangan' => 'Tunjangan', 'potongan' => 'Potongan']),
                        TextInput::make('nominal')->label('Nominal')->numeric()->required()->prefix('Rp'),
                        Toggle::make('aktif')->label('Aktif')->default(true),
                    ])->columns(5)->defaultItems(1)->addActionLabel('Tambah Komponen'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
                TextColumn::make('nip')->label('NIP')->placeholder('—'),
                TextColumn::make('jabatan')->label('Jabatan')->placeholder('—'),
                TextColumn::make('status_kepegawaian')->label('Status')->badge()
                    ->formatStateUsing(fn (?string $state) => Employee::labelStatusKepegawaian($state)),
                TextColumn::make('ptkp_status')->label('PTKP')->toggleable(),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->defaultSort('nama')
            // WP-12 Kesiapan EMIS — daftar pegawai yang datanya kurang.
            ->filters([
                Filter::make('tanpa_nik')
                    ->label('Tanpa NIK')
                    ->query(fn (Builder $query): Builder => $query->where(
                        fn (Builder $q) => $q->whereNull('nik')->orWhere('nik', ''),
                    )),
                Filter::make('tanpa_nuptk')
                    ->label('Tanpa NUPTK')
                    ->query(fn (Builder $query): Builder => $query->where(
                        fn (Builder $q) => $q->whereNull('nuptk')->orWhere('nuptk', ''),
                    )),
                Filter::make('tanpa_sertifikasi')
                    ->label('Tanpa Sertifikasi Terdata')
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('sertifikasi')),
            ])
            ->actions([
                EditAction::make(),
                // WP-20 — Penilaian kualitatif mudir (penilaian_ustadz, per tahun).
                // Gate ganda: visible + service simpanPenilaian (403 di service).
                Action::make('nilai_ustadz')->label('Nilai Ustadz')
                    ->icon('heroicon-o-star')->color('warning')
                    ->visible(fn (): bool => auth()->user()?->canAny(['manage_payroll', 'run_payroll']) ?? false)
                    ->schema(function (Employee $r): array {
                        $tahun = (int) now()->year;
                        $ada = PenilaianUstadz::query()
                            ->where('employee_id', $r->id)->where('tahun', $tahun)->first();

                        return [
                            TextInput::make('tahun')->label('Tahun')->numeric()->required()
                                ->default($tahun)->minValue(2000)->maxValue(2100),
                            KeyValue::make('skor')->label('Skor per Kriteria (0–100)')
                                ->keyLabel('Kriteria')->valueLabel('Skor')
                                ->default($ada?->skor ?? ['Keteladanan' => '', 'Kedisiplinan' => '', 'Penguasaan Materi' => ''])
                                ->helperText('Kriteria bebas sesuai kebijakan pesantren (policy-first).'),
                            Textarea::make('catatan')->label('Catatan Mudir')->rows(3)
                                ->default($ada?->catatan),
                        ];
                    })
                    ->action(function (Employee $r, array $data): void {
                        try {
                            $skor = collect($data['skor'] ?? [])
                                ->filter(fn ($v) => $v !== null && $v !== '')
                                ->map(fn ($v) => max(0, min(100, (float) $v)))
                                ->all();
                            app(RaporUstadzService::class)->simpanPenilaian(
                                $r, (int) $data['tahun'], $skor, $data['catatan'] ?? null, auth()->user(),
                            );
                            Notification::make()->title('Penilaian tersimpan')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal menyimpan penilaian')->body($e->getMessage())->danger()->send();
                        }
                    }),
                // WP-20 — Cetak rapor tahunan (print blade). Pegawai ybs juga
                // boleh membuka URL yang sama — gate di route tenant.php.
                Action::make('rapor_ustadz')->label('Rapor Ustadz')
                    ->icon('heroicon-o-document-chart-bar')->color('info')
                    ->url(fn (Employee $r): string => url('/payroll/rapor-ustadz/'.$r->id.'/'.now()->year), true),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployee::route('/'),
            'create' => CreateEmployee::route('/create'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }
}
