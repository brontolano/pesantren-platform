<?php

namespace App\Filament\Resources\PPDB;

use App\Filament\Resources\PPDB\CalonSantri\Pages\CreateCalonSantri;
use App\Filament\Resources\PPDB\CalonSantri\Pages\EditCalonSantri;
use App\Filament\Resources\PPDB\CalonSantri\Pages\ListCalonSantri;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Services\PpdbApplicantAccountService;
use Modules\PPDB\Services\PpdbWhatsappNotifier;

class CalonSantriResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = CalonSantri::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Calon Santri';

    protected static ?string $modelLabel = 'Calon Santri';

    protected static ?string $pluralModelLabel = 'Calon Santri';

    protected static string|\UnitEnum|null $navigationGroup = 'PPDB';

    protected static ?string $slug = 'ppdb/calon-santri';

    protected static ?int $navigationSort = 1;

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
        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return Auth::user()?->can('manage_santri') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
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
                    ->options([
                        'Reguler' => 'Reguler',
                        'Tahfidz' => 'Tahfidz',
                        'Muadalah' => 'Muadalah',
                    ])
                    ->required(),
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
                    ->options([
                        'pending' => 'Pending',
                        'verifikasi_berkas' => 'Verifikasi Berkas',
                        'lulus' => 'Lulus',
                        'cadangan' => 'Cadangan',
                        'ditolak' => 'Ditolak',
                        'registrasi_ulang' => 'Registrasi Ulang',
                    ])
                    ->default('pending')
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
        return $table
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
                    ->sortable(),
                TextColumn::make('jenis_kelamin')
                    ->label('JK')
                    ->sortable(),
                TextColumn::make('program_diminati')
                    ->label('Program')
                    ->sortable(),
                TextColumn::make('no_hp_wali')
                    ->label('No. HP Wali'),
                TextColumn::make('hasil_ujian_count')
                    ->label('Hasil Ujian')
                    ->counts('hasilUjian'),
            ])
            ->defaultSort('nomor_pendaftaran')
            ->filters([
                SelectFilter::make('status_pendaftaran')
                    ->options([
                        'pending' => 'Pending',
                        'verifikasi_berkas' => 'Verifikasi Berkas',
                        'lulus' => 'Lulus',
                        'cadangan' => 'Cadangan',
                        'ditolak' => 'Ditolak',
                        'registrasi_ulang' => 'Registrasi Ulang',
                    ]),
                SelectFilter::make('program_diminati')
                    ->options([
                        'Reguler' => 'Reguler',
                        'Tahfidz' => 'Tahfidz',
                        'Muadalah' => 'Muadalah',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                Action::make('kirimKredensial')
                    ->label('Kirim Kredensial / Reset Password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->visible(fn (): bool => Auth::user()?->can('manage_santri') ?? false)
                    ->modalHeading('Kirim Kredensial Portal via WhatsApp')
                    ->modalDescription('Akun login peserta dibuat/di-reset & password baru dikirim via WhatsApp. Password lama (bila ada) langsung tidak berlaku.')
                    ->modalSubmitActionLabel('Terbitkan & Kirim')
                    ->form([
                        TextInput::make('no_hp')
                            ->label('Nomor WhatsApp Tujuan')
                            ->tel()
                            ->required()
                            ->default(fn (CalonSantri $record): ?string => $record->no_hp_wali)
                            ->helperText('Perbaiki nomor bila peserta salah input. Nomor ini juga menjadi username login portal.'),
                    ])
                    ->action(function (CalonSantri $record, array $data): void {
                        $result = app(PpdbApplicantAccountService::class)
                            ->issueOrResetCredentials($record, $data['no_hp']);

                        $sent = app(PpdbWhatsappNotifier::class)->sendCredentials(
                            $result['username'],
                            $record->nama,
                            $record->nomor_pendaftaran,
                            $result['username'],
                            $result['password'],
                            $result['login_url'],
                        );

                        $notification = Notification::make()
                            ->title($sent
                                ? 'Kredensial terkirim via WhatsApp'
                                : 'WA gagal terkirim — bacakan kredensial ke peserta')
                            ->body("Username: {$result['username']}\nPassword: {$result['password']}")
                            ->persistent();

                        ($sent ? $notification->success() : $notification->warning())->send();
                    }),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('hasilUjian');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCalonSantri::route('/'),
            'create' => CreateCalonSantri::route('/create'),
            'edit' => EditCalonSantri::route('/{record}/edit'),
        ];
    }
}

