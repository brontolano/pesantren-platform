<?php

namespace Modules\PPDB\Filament\Resources;

use App\Filament\Concerns\ModuleGate;
use Modules\PPDB\Filament\Resources\CalonSantri\Pages\CreateCalonSantri;
use Modules\PPDB\Filament\Resources\CalonSantri\Pages\EditCalonSantri;
use Modules\PPDB\Filament\Resources\CalonSantri\Pages\ListCalonSantri;
use App\Support\AlbaKidsTenant;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\UserOtp;
use Modules\PPDB\Services\PesertaAccountService;
use Modules\PPDB\Services\PpdbAdmissionDecisionService;
use Modules\PPDB\Services\PpdbWhatsappNotifier;
use Throwable;

class CalonSantriResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = CalonSantri::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $slug = 'ppdb/calon-santri';

    protected static ?int $navigationSort = 1;

    /** Pencarian global (Cmd/Ctrl+K): cari pendaftar dgn nama atau no. pendaftaran. */
    public static function getGloballySearchableAttributes(): array
    {
        return ['nama', 'nomor_pendaftaran'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return (string) $record->nama;
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'No. Pendaftaran' => (string) ($record->nomor_pendaftaran ?? '—'),
            'Status' => (string) ($record->status_pendaftaran ?? '—'),
        ];
    }

    /** Badge: pendaftar yang masih dalam proses (perlu ditinjau panitia). */
    public static function getNavigationBadge(): ?string
    {
        try {
            $n = CalonSantri::query()
                ->whereNotIn('status_pendaftaran', ['lulus', 'cadangan', 'ditolak', 'registrasi_ulang', 'batal', 'draft'])
                ->count();

            return $n > 0 ? (string) $n : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pendaftar dalam proses';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('ppdb') && (static::canAccess());
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('ppdb') && (Auth::user()?->canAny(['view_santri', 'manage_santri']) ?? false);
    }

    public static function canCreate(): bool
    {
        if (AlbaKidsTenant::isActive()) {
            return false;
        }

        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        $programOptions = AlbaKidsTenant::isActive()
            ? [
                'Preschool' => 'Preschool',
            ]
            : [
                'Reguler' => 'Reguler',
                'Tahfidz' => 'Tahfidz',
                'Muadalah' => 'Muadalah',
            ];

        $statusOptions = [
            'pending' => 'Pending',
            'menunggu_verifikasi' => 'Menunggu Verifikasi',
            'verifikasi_berkas' => 'Verifikasi Berkas',
            'lulus' => 'Lulus',
            'cadangan' => 'Cadangan',
            'ditolak' => 'Ditolak',
            'registrasi_ulang' => 'Registrasi Ulang',
        ];

        return $schema->components([
            Section::make('Data Calon Santri')->schema([
                TextInput::make('nomor_pendaftaran')
                    ->label('Nomor Pendaftaran')
                    ->maxLength(50)
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('nama')
                    ->label('Nama Lengkap')
                    ->required()
                    ->maxLength(255),
                // Field bermode 'staf' di portal SPMB — disembunyikan dari peserta,
                // diisi/diverifikasi operator dari data EMIS di sini.
                TextInput::make('nisn')
                    ->label('NISN (diisi panitia)')
                    ->maxLength(50)
                    ->helperText('Tidak tampil di portal peserta — diverifikasi operator dari data EMIS.'),
                DatePicker::make('tanggal_lahir')
                    ->label('Tanggal Lahir')
                    ->required(),
                TextInput::make('tempat_lahir')
                    ->label('Tempat Lahir')
                    ->maxLength(100),
                Select::make('jenis_kelamin')
                    ->label('Jenis Kelamin')
                    ->options([
                        'L' => 'Laki-laki',
                        'P' => 'Perempuan',
                    ])
                    ->required(),
                TextInput::make('asal_sekolah')
                    ->label('Asal Sekolah')
                    ->maxLength(255),
                Select::make('program_diminati')
                    ->label('Program Diminati')
                    ->options($programOptions)
                    ->required(),
                // WP-08 — mutasi masuk: jalur pindahan + pondok asal.
                Select::make('jalur')
                    ->label('Jalur Pendaftaran')
                    ->options(['reguler' => 'Reguler', 'pindahan' => 'Pindahan (Mutasi Masuk)'])
                    ->default('reguler')
                    ->live()
                    ->nullable(),
                TextInput::make('asal_pondok')
                    ->label('Asal Pondok (jalur pindahan)')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('jalur') === 'pindahan'),
            ])->columns(2),

            Section::make('Data Wali / Orang Tua')->schema([
                TextInput::make('nama_ayah')
                    ->label('Nama Ayah')
                    ->maxLength(255),
                TextInput::make('nama_ibu')
                    ->label('Nama Ibu')
                    ->maxLength(255),
                TextInput::make('no_hp_wali')
                    ->label('No. HP Wali')
                    ->tel()
                    ->required()
                    ->maxLength(20),
                TextInput::make('email_wali')
                    ->label('Email Wali')
                    ->email()
                    ->maxLength(255),
            ])->columns(2),

            Section::make('Keputusan PPDB')->schema([
                Select::make('status_pendaftaran')
                    ->label('Status Pendaftaran')
                    ->options($statusOptions)
                    ->default(AlbaKidsTenant::isActive() ? 'menunggu_verifikasi' : 'pending')
                    ->required(),
                Textarea::make('catatan_keputusan')
                    ->label('Catatan Keputusan')
                    ->rows(3),
                DatePicker::make('tanggal_keputusan')
                    ->label('Tanggal Keputusan'),
                DatePicker::make('tanggal_registrasi_ulang')
                    ->label('Tanggal Registrasi Ulang'),
                TextInput::make('santri_id')
                    ->label('ID Santri')
                    ->numeric()
                    ->disabled(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        $isAlbaKids = AlbaKidsTenant::isActive();
        $programFilterOptions = $isAlbaKids
            ? ['Preschool' => 'Preschool']
            : [
                'Reguler' => 'Reguler',
                'Tahfidz' => 'Tahfidz',
                'Muadalah' => 'Muadalah',
            ];

        return $table
            ->emptyStateHeading('Belum ada pendaftar')
            ->emptyStateDescription('Calon santri akan muncul di sini setelah mengisi formulir pendaftaran.')
            ->emptyStateIcon('heroicon-o-academic-cap')
            ->columns([
                TextColumn::make('nomor_pendaftaran')
                    ->label('No. Pendaftaran')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status_pendaftaran')
                    ->label('Status')
                    ->badge()
                    ->sortable()
                    ->color(fn (?string $state): string => match ($state) {
                        'lulus' => 'success',
                        'verifikasi_berkas', 'registrasi_ulang' => 'info',
                        'pending', 'menunggu_verifikasi', 'cadangan' => 'warning',
                        'ditolak' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('jenis_kelamin')
                    ->label('JK')
                    ->sortable(),
                TextColumn::make('program_diminati')
                    ->label('Program')
                    ->sortable(),
                TextColumn::make('no_hp_wali')
                    ->label('No. HP Wali'),
                TextColumn::make('created_at')
                    ->label('Masuk')
                    ->dateTime('d M Y H:i')
                    ->visible($isAlbaKids),
                TextColumn::make('hasil_ujian_count')
                    ->label('Hasil Ujian')
                    ->counts('hasilUjian')
                    ->visible(! $isAlbaKids),
            ])
            ->defaultSort('nomor_pendaftaran')
            ->filters([
                SelectFilter::make('status_pendaftaran')
                    ->options([
                        'pending' => 'Pending',
                        'menunggu_verifikasi' => 'Menunggu Verifikasi',
                        'verifikasi_berkas' => 'Verifikasi Berkas',
                        'lulus' => 'Lulus',
                        'cadangan' => 'Cadangan',
                        'ditolak' => 'Ditolak',
                        'registrasi_ulang' => 'Registrasi Ulang',
                    ]),
                SelectFilter::make('program_diminati')
                    ->options($programFilterOptions),
            ])
            ->actions([
                EditAction::make(),
                // F1.1.7 — re-issue OTP & koreksi nomor WA (mitigasi R-1, R-2).
                // Menggantikan aksi legacy "Kirim Kredensial": password TIDAK
                // pernah dikirim — peserta membuat password sendiri via OTP.
                Action::make('kirimUlangOtp')
                    ->label('Kirim Ulang OTP / Koreksi No. WA')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->visible(fn (): bool => Auth::user()?->can('manage_santri') ?? false)
                    ->modalHeading('Kirim Ulang Kode OTP Portal SPMB')
                    ->modalDescription('Untuk peserta yang tidak menerima kode atau salah menulis nomor WA. Perbaiki nomor bila perlu — kode OTP baru dikirim ke nomor di bawah dan blokir percobaan sebelumnya dibuka. Password TIDAK ikut terkirim; peserta membuat password sendiri saat memakai kode.')
                    ->modalSubmitActionLabel('Simpan Nomor & Kirim OTP')
                    ->form([
                        TextInput::make('no_hp')
                            ->label('Nomor WhatsApp Wali')
                            ->tel()
                            ->required()
                            ->default(fn (CalonSantri $record): ?string => $record->no_hp_wali)
                            ->helperText('Periksa baik-baik — nomor ini tersimpan ke data pendaftaran dan menjadi salah satu cara login peserta.'),
                    ])
                    ->action(function (CalonSantri $record, array $data): void {
                        $result = app(PesertaAccountService::class)
                            ->reissueOtpByStaff($record, $data['no_hp'], Auth::user());

                        $tujuan = $result['purpose'] === UserOtp::PURPOSE_AKTIVASI
                            ? 'AKTIVASI akun (peserta membuat password baru)'
                            : 'RESET password (akun sudah pernah aktif)';

                        Notification::make()
                            ->title('Kode OTP terkirim')
                            ->body("Kode {$tujuan} dikirim via WhatsApp ke {$result['phone']}. Berlaku ".UserOtp::TTL_MINUTES.' menit.')
                            ->success()
                            ->send();
                    }),
                Action::make('ingatkanDaftarUlang')
                    ->label('Ingatkan Daftar Ulang')
                    ->icon('heroicon-o-bell-alert')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (CalonSantri $record): bool => (string) $record->status_pendaftaran === 'lulus' && (Auth::user()?->can('manage_santri') ?? false))
                    ->action(function (CalonSantri $record): void {
                        if ($record->no_hp_wali) {
                            app(PpdbWhatsappNotifier::class)->sendPengingatDaftarUlang(
                                (string) $record->no_hp_wali,
                                (string) $record->nama,
                                (string) $record->nomor_pendaftaran,
                            );
                        }
                        Notification::make()
                            ->title('Pengingat daftar ulang dikirim via WhatsApp')
                            ->success()
                            ->send();
                    }),
                // Keputusan seleksi cepat — lewat PpdbAdmissionDecisionService (set status +
                // pengumuman WA + pagar "butuh hasil ujian"). Disembunyikan untuk Alba Kids
                // yang memakai alur verifikasi pembayaran tersendiri (tanpa ujian).
                Action::make('luluskan')
                    ->label('Luluskan')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Tetapkan peserta LULUS (butuh minimal satu hasil ujian). Pengumuman dikirim via WhatsApp.')
                    ->visible(fn (CalonSantri $record): bool => ! AlbaKidsTenant::isActive()
                        && in_array((string) $record->status_pendaftaran, ['pending', 'menunggu_verifikasi', 'verifikasi_berkas', 'cadangan'], true)
                        && (Auth::user()?->can('manage_santri') ?? false))
                    ->action(function (CalonSantri $record): void {
                        try {
                            app(PpdbAdmissionDecisionService::class)->decide($record, ['status_pendaftaran' => 'lulus']);
                            Notification::make()->title('Peserta diluluskan & pengumuman dikirim')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Tidak dapat meluluskan')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('jadikanCadangan')
                    ->label('Cadangan')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Tetapkan peserta sebagai CADANGAN (butuh minimal satu hasil ujian).')
                    ->visible(fn (CalonSantri $record): bool => ! AlbaKidsTenant::isActive()
                        && in_array((string) $record->status_pendaftaran, ['pending', 'menunggu_verifikasi', 'verifikasi_berkas'], true)
                        && (Auth::user()?->can('manage_santri') ?? false))
                    ->action(function (CalonSantri $record): void {
                        try {
                            app(PpdbAdmissionDecisionService::class)->decide($record, ['status_pendaftaran' => 'cadangan']);
                            Notification::make()->title('Peserta ditetapkan sebagai cadangan')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Tidak dapat memproses')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('tolakPendaftaran')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->modalDescription('Tolak pendaftaran peserta. Alasan dikirim sebagai catatan keputusan; peserta dikabari via WhatsApp.')
                    ->schema([
                        Textarea::make('catatan_keputusan')
                            ->label('Alasan penolakan')
                            ->required()
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->visible(fn (CalonSantri $record): bool => ! AlbaKidsTenant::isActive()
                        && ! in_array((string) $record->status_pendaftaran, ['ditolak', 'lulus'], true)
                        && (Auth::user()?->can('manage_santri') ?? false))
                    ->action(function (array $data, CalonSantri $record): void {
                        try {
                            app(PpdbAdmissionDecisionService::class)->decide($record, [
                                'status_pendaftaran' => 'ditolak',
                                'catatan_keputusan' => $data['catatan_keputusan'],
                            ]);
                            Notification::make()->title('Pendaftaran ditolak & peserta dikabari')->warning()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Gagal menolak')->body($e->getMessage())->danger()->send();
                        }
                    }),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withCount('hasilUjian');

        if (AlbaKidsTenant::isActive()) {
            $query->where('gelombang', 'Landing Alba Kids');
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCalonSantri::route('/'),
            'create' => CreateCalonSantri::route('/create'),
            'edit' => EditCalonSantri::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return AlbaKidsTenant::isActive() ? 'Daftar Pendaftar' : 'Calon Santri';
    }

    public static function getModelLabel(): string
    {
        return AlbaKidsTenant::isActive() ? 'Pendaftar Alba Kids' : 'Calon Santri';
    }

    public static function getPluralModelLabel(): string
    {
        return AlbaKidsTenant::isActive() ? 'Daftar Pendaftar' : 'Calon Santri';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AlbaKidsTenant::isActive() ? 'PPDB Custom' : 'PPDB';
    }
}
