<?php

namespace App\Filament\Resources\Tenants;

use App\Filament\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Resources\Tenants\Pages\EditTenant;
use App\Filament\Resources\Tenants\Pages\ListTenants;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRecoveryService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Tenants';

    protected static ?string $modelLabel = 'Tenant';

    protected static ?string $pluralModelLabel = 'Tenants';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return static::userCanManageTenants();
    }

    public static function canViewAny(): bool
    {
        return static::userCanManageTenants();
    }

    public static function canCreate(): bool
    {
        return static::userCanManageTenants();
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCanManageTenants();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas Tenant')->schema([
                TextInput::make('id')
                    ->label('Tenant ID')
                    ->required()
                    ->alphaDash()
                    ->maxLength(255)
                    ->disabledOn('edit'),
                TextInput::make('name')
                    ->label('Nama Pesantren')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true)
                    ->inline(false),
            ]),
            Section::make('Domain Mapping')->schema([
                TextInput::make('primary_domain')
                    ->label('Subdomain (fallback)')
                    ->helperText('Misal: asy-syifaa → asy-syifaa.pesantren-platform.com')
                    ->required()
                    ->maxLength(255),
                TextInput::make('website_domain')
                    ->label('Domain Website Publik (custom)')
                    ->helperText('Misal: asy-syifaa.sch.id — kosongkan jika pakai subdomain')
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('sistem_domain')
                    ->label('Domain Sistem (panel admin)')
                    ->helperText('Otomatis: sistem.{website_domain}. Isi manual jika berbeda.')
                    ->nullable()
                    ->maxLength(255),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Tenant ID')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama Pesantren')->searchable()->sortable(),
                TextColumn::make('domains.domain')->label('Domain')->badge()->separator(','),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                EditAction::make(),
                Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-pause-circle')
                    ->color('danger')
                    ->action(fn (Tenant $record) => $record->update(['is_active' => false]))
                    ->requiresConfirmation()
                    ->visible(fn (Tenant $record): bool => $record->is_active),
                Action::make('activate')
                    ->label('Aktifkan')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->action(fn (Tenant $record) => $record->update(['is_active' => true]))
                    ->visible(fn (Tenant $record): bool => ! $record->is_active),

                // === Recovery database per tenant ===
                ActionGroup::make([
                    Action::make('db_info')
                        ->label('Info Database')
                        ->icon('heroicon-o-circle-stack')
                        ->color('gray')
                        ->action(function (Tenant $record) {
                            abort_unless(static::canRecovery(auth()->user()), 403);
                            $size = app(TenantRecoveryService::class)->size($record);
                            Notification::make()
                                ->title('Database tenant')
                                ->body('DB: '.$record->database()->getName().' — Ukuran: '.($size ?? 'tidak diketahui'))
                                ->info()
                                ->send();
                        }),
                    Action::make('backup_db')
                        ->label('Backup / Export DB')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription('Buat & unduh backup database tenant ini (.sql.gz).')
                        ->action(function (Tenant $record) {
                            abort_unless(static::canRecovery(auth()->user()), 403);
                            $path = app(TenantRecoveryService::class)->backup($record);

                            return response()->download($path)->deleteFileAfterSend();
                        }),
                    Action::make('backup_files')
                        ->label('Backup File')
                        ->icon('heroicon-o-photo')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription('Buat & unduh backup file unggahan tenant ini (.tar.gz): foto santri, dokumen, dll.')
                        ->action(function (Tenant $record) {
                            abort_unless(static::canRecovery(auth()->user()), 403);
                            $path = app(TenantRecoveryService::class)->backupFiles($record);
                            if ($path === null) {
                                Notification::make()->title('Tidak ada file')->body('Tenant ini belum punya file unggahan.')->info()->send();

                                return null;
                            }

                            return response()->download($path)->deleteFileAfterSend();
                        }),
                    Action::make('restore_db')
                        ->label('Restore / Import DB')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('PERINGATAN: menimpa data DB tenant ini dgn isi file. Backup pengaman dibuat otomatis dulu. Tidak bisa dibatalkan.')
                        ->form([
                            FileUpload::make('file')
                                ->label('File backup (.sql.gz)')
                                ->disk('local')
                                ->directory('tenant-restore-uploads')
                                ->acceptedFileTypes(['application/gzip', 'application/x-gzip', 'application/octet-stream'])
                                ->required(),
                            TextInput::make('konfirmasi')
                                ->label('Ketik ID pesantren untuk konfirmasi')
                                ->helperText(fn (Tenant $record): string => 'Menimpa SELURUH data DB. Ketik persis: '.$record->getKey())
                                ->required(),
                        ])
                        ->action(function (array $data, Tenant $record) {
                            abort_unless(static::canRecovery(auth()->user()), 403);
                            if (trim((string) ($data['konfirmasi'] ?? '')) !== (string) $record->getKey()) {
                                Notification::make()->title('Konfirmasi tidak cocok')->body('Ketik ID pesantren yang benar.')->danger()->send();

                                return;
                            }
                            $svc = app(TenantRecoveryService::class);
                            $path = Storage::disk('local')->path($data['file']);
                            try {
                                $aman = $svc->backup($record); // backup pengaman dulu
                                $svc->restore($record, $path);
                                Notification::make()->title('Restore berhasil')
                                    ->body('Backup pengaman dibuat: '.basename($aman))
                                    ->success()->persistent()->send();
                            } catch (\Throwable $e) {
                                report($e);
                                Notification::make()->title('Restore gagal')->body($e->getMessage())->danger()->send();
                            } finally {
                                @unlink($path);
                            }
                        }),
                    Action::make('restore_files')
                        ->label('Restore File')
                        ->icon('heroicon-o-photo')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('Menimpa file unggahan tenant dgn isi arsip (.tar.gz). Folder lama disisihkan (.bak) dulu.')
                        ->form([
                            FileUpload::make('file')
                                ->label('Arsip file (.tar.gz)')
                                ->disk('local')
                                ->directory('tenant-restore-uploads')
                                ->acceptedFileTypes(['application/gzip', 'application/x-gzip', 'application/octet-stream'])
                                ->required(),
                            TextInput::make('konfirmasi')
                                ->label('Ketik ID pesantren untuk konfirmasi')
                                ->helperText(fn (Tenant $record): string => 'Menimpa file unggahan. Ketik persis: '.$record->getKey())
                                ->required(),
                        ])
                        ->action(function (array $data, Tenant $record) {
                            abort_unless(static::canRecovery(auth()->user()), 403);
                            if (trim((string) ($data['konfirmasi'] ?? '')) !== (string) $record->getKey()) {
                                Notification::make()->title('Konfirmasi tidak cocok')->body('Ketik ID pesantren yang benar.')->danger()->send();

                                return;
                            }
                            $path = Storage::disk('local')->path($data['file']);
                            try {
                                app(TenantRecoveryService::class)->restoreFiles($record, $path);
                                Notification::make()->title('Restore file berhasil')->success()->send();
                            } catch (\Throwable $e) {
                                report($e);
                                Notification::make()->title('Restore file gagal')->body($e->getMessage())->danger()->send();
                            } finally {
                                @unlink($path);
                            }
                        }),
                    Action::make('reset_db')
                        ->label('RESET Data Pesantren')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Reset SELURUH data pesantren ini')
                        ->modalDescription('Menghapus SEMUA data (santri, keuangan, presensi, file unggahan, dll) lalu kembalikan ke kondisi awal: kosong + fondasi (peran, COA, branding) + admin BARU. Backup pengaman (DB + file) dibuat otomatis lebih dulu. TIDAK BISA DIBATALKAN.')
                        ->form([
                            TextInput::make('konfirmasi')
                                ->label('Ketik ID pesantren untuk konfirmasi')
                                ->helperText(fn (Tenant $record): string => 'Ketik persis: '.$record->getKey())
                                ->required(),
                        ])
                        ->action(function (array $data, Tenant $record) {
                            abort_unless(static::canRecovery(auth()->user()), 403);
                            if (trim((string) ($data['konfirmasi'] ?? '')) !== (string) $record->getKey()) {
                                Notification::make()->title('Konfirmasi tidak cocok')
                                    ->body('Ketik ID pesantren yang benar untuk melanjutkan.')->danger()->send();

                                return;
                            }
                            try {
                                $hasil = app(TenantRecoveryService::class)->reset($record);
                                $body = 'Backup pengaman DB: '.basename($hasil['db_backup']);
                                if (! empty($hasil['files_backup'])) {
                                    $body .= "\nBackup file: ".basename($hasil['files_backup']);
                                }
                                if (! empty($hasil['admin_password'])) {
                                    $body .= "\n\nAdmin baru: ".$hasil['admin_email']
                                        ."\nPassword (CATAT SEKARANG, hanya tampil sekali): ".$hasil['admin_password'];
                                }
                                Notification::make()->title('Reset selesai')->body($body)->success()->persistent()->send();
                            } catch (\Throwable $e) {
                                report($e);
                                Notification::make()->title('Reset GAGAL')
                                    ->body($e->getMessage().' — backup pengaman mungkin sudah dibuat; cek storage/app/tenant-backups.')
                                    ->danger()->persistent()->send();
                            }
                        }),
                ])
                    ->label('Recovery')
                    ->icon('heroicon-o-server-stack')
                    ->button()
                    ->visible(fn (): bool => static::canRecovery(auth()->user())),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('domains');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
            'create' => CreateTenant::route('/create'),
            'edit' => EditTenant::route('/{record}/edit'),
        ];
    }

    protected static function userCanManageTenants(): bool
    {
        return static::canRecovery(auth()->user());
    }

    /**
     * Gerbang aksi platform (kelola tenant + backup/restore/RESET): HANYA
     * super_admin pemilik platform (tenant_id null). Cek tenant_id LEBIH DULU
     * agar staf tenant berperan super_admin lokal tetap ditolak (defense-in-depth,
     * selaras User::canAccessPanel). Pure function dari User → mudah diuji.
     */
    public static function canRecovery(?User $user): bool
    {
        return $user !== null
            && $user->tenant_id === null
            && $user->hasRole('super_admin');
    }
}
