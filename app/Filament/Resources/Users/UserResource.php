<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'Users';

    /**
     * Label RAMAH untuk dropdown Role (form User): slug teknis → bahasa staf
     * pesantren. Hanya untuk TAMPILAN — nilai opsi tetap role_id (lihat field
     * 'roles' yang pakai relationship()). Peran eksternal (santri/wali/jemaah/
     * peserta) & alias 'keuangan' sengaja tak masuk daftar (disembunyikan).
     *
     * @var array<string, string>
     */
    private const ROLE_LABELS = [
        'super_admin' => 'Super Admin (akses penuh / pemilik)',
        'admin_pesantren' => 'Admin Pesantren (administrator)',
        'kepala' => 'Kepala / Kyai (pimpinan)',
        'tu' => 'Tata Usaha (administrasi)',
        'guru' => 'Guru (mata pelajaran)',
        'guru_tahfidz' => 'Guru Tahfidz / Musyrif',
        'asatidz' => 'Ustadz / Asatidz (pengajar kitab)',
        'bendahara' => 'Bendahara / Kasir Unit (keuangan)',
        'bendahara_pusat' => 'Bendahara Pusat / Yayasan',
        'kepala_keuangan' => 'Kepala Keuangan',
        'kasir' => 'Kasir (kantin / POS)',
        'operator_divisi' => 'Operator Divisi / Unit',
        'dewan_pembina' => 'Dewan Pembina (pengawas)',
        'konselor' => 'Konselor / Guru BK',
        'pengelola_uks' => 'Petugas UKS / Kesehatan',
        'pengelola_asrama' => 'Pengasuh Asrama / Musyrif',
        'panitia_ppdb' => 'Panitia PPDB / Pendaftaran',
        'pengelola_sarpras' => 'Petugas Sarpras / Aset',
        'IT' => 'IT / Admin Teknis',
    ];

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return static::userCanManageUsers();
    }

    public static function canViewAny(): bool
    {
        return static::userCanManageUsers();
    }

    public static function canCreate(): bool
    {
        return static::userCanManageUsers();
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCanManageUsers() && $record->tenant_id === tenant('id');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nama User')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),
            TextInput::make('password')
                ->label('Password')
                ->password()
                ->revealable()
                ->rule(Password::defaults())
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->maxLength(255),
            Select::make('roles')
                ->label('Role / Peran')
                ->helperText('Peran menentukan menu & akses yang terlihat staf. Untuk PEGAWAI, peran biasanya TER-ISI OTOMATIS dari Jabatan/Penugasan — pilih manual di sini hanya bila perlu.')
                // relationship() menyetir opsi: value = ID role (cocok pivot
                // model_has_roles.role_id). JANGAN override ->options() dgn nama —
                // value jadi nama → relationship resolve "where roles.id='<nama>'"
                // → 500 (bigint vs string). Urutan abjad lewat query relationship.
                // Sembunyikan peran EKSTERNAL/komunitas (santri/wali/jemaah/peserta)
                // — diberikan OTOMATIS oleh sistem (SantriAccountService, dll) saat
                // akun santri/wali/jemaah/calon-santri dibuat — dan alias 'keuangan'
                // (kembar 'bendahara') agar tak membingungkan. Label DITAMPILKAN ramah
                // via getOptionLabelFromRecordUsing (nilai tetap role_id).
                ->relationship(
                    'roles',
                    'name',
                    fn (Builder $query): Builder => $query
                        ->whereNotIn('name', ['santri', 'wali', 'jemaah', 'peserta', 'keuangan'])
                        ->orderBy('name'),
                )
                ->getOptionLabelFromRecordUsing(fn (Model $record): string => self::ROLE_LABELS[$record->name] ?? $record->name)
                ->multiple()
                ->preload()
                ->searchable()
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Role / Peran')
                    ->badge()
                    ->separator(',')
                    // Tampilkan label ramah yang sama dgn dropdown (slug → bahasa staf).
                    ->formatStateUsing(fn (string $state): string => self::ROLE_LABELS[$state] ?? $state),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('name');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('roles');

        // Di domain central (tenancy tidak aktif) panel platform HANYA boleh
        // melihat user platform (tenant_id NULL). Di panel tenant, TenantScope
        // sudah memfilter ke tenant aktif.
        if (! tenancy()->initialized) {
            $query->whereNull('tenant_id');
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    protected static function userCanManageUsers(): bool
    {
        return auth()->user()?->can('manage_users') ?? false;
    }
}
