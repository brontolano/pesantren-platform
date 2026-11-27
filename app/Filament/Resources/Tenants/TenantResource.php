<?php

namespace App\Filament\Resources\Tenants;

use App\Filament\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Resources\Tenants\Pages\EditTenant;
use App\Filament\Resources\Tenants\Pages\ListTenants;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DnsOtomasiService;
use App\Services\TenantRecoveryService;
use App\Services\TenantHealthCheck;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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

    protected static ?string $navigationLabel = 'Daftar Pesantren';

    protected static ?string $modelLabel = 'Tenant';

    protected static ?string $pluralModelLabel = 'Tenants';

    protected static string|\UnitEnum|null $navigationGroup = 'Pesantren';

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

    /**
     * Custom domain tenant = domain bertitik (bukan subdomain platform polos).
     *
     * @return array<int,string>
     */
    public static function customDomains(Tenant $record): array
    {
        return $record->domains
            ->pluck('domain')
            ->filter(fn ($d): bool => is_string($d) && str_contains($d, '.'))
            ->values()
            ->all();
    }

    /** Jalankan aksi DNS (cek/otomasi) untuk semua custom domain lalu tampilkan notifikasi ramah. */
    protected static function jalankanAksiDns(Tenant $record, \Closure $aksi): void
    {
        $svc = app(DnsOtomasiService::class);
        $domains = static::customDomains($record);

        if ($domains === []) {
            Notification::make()->title('Tidak ada custom domain pada tenant ini.')->warning()->send();

            return;
        }

        foreach ($domains as $domain) {
            $hasil = $aksi($svc, $domain);

            Notification::make()
                ->title($hasil['ok'] ? "DNS {$domain}: OK" : "DNS {$domain}: perlu tindakan")
                ->body($hasil['pesan'])
                ->{$hasil['ok'] ? 'success' : 'warning'}()
                ->persistent()
                ->send();
        }
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
                TextInput::make('contact_person')
                    ->label('Kontak Penanggung Jawab (internal platform)')
                    ->maxLength(150),
                TextInput::make('contact_email')
                    ->label('Email Utama (internal platform)')
                    ->email()
                    ->maxLength(150),
                TextInput::make('contact_phone')
                    ->label('Telepon / WhatsApp (internal platform)')
                    ->tel()
                    ->maxLength(40),
                Textarea::make('address')
                    ->label('Alamat Korespondensi (internal platform)')
                    ->rows(3)
                    ->maxLength(500)
                    ->helperText('Terpisah dari alamat yang tampil di website publik pesantren.')
                    ->columnSpanFull(),
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
                    ->helperText(fn (): string => 'Misal: asy-syifaa.sch.id — kosongkan jika pakai subdomain. '
                        .'Opsi MANUAL: arahkan A record ke '.config('dns.server_ip')
                        .' lalu pakai tombol "Cek DNS" di daftar tenant. Opsi OTOMATIS tersedia bila domain dikelola di Hostinger.')
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('sistem_domain')
                    ->label('Domain Sistem (panel admin)')
                    ->helperText(fn (): string => 'Otomatis: sistem.{website_domain}. Isi manual jika berbeda. '
                        .'A record juga harus mengarah ke '.config('dns.server_ip').'.')
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
                Action::make('technical_health')
                    ->label('Cek kesehatan teknis')
                    ->icon('heroicon-o-heart')
                    ->color('gray')
                    ->tooltip('Periksa koneksi database dan kesiapan dasar tenant sekarang.')
                    ->action(function (Tenant $record): void {
                        try {
                            $checks = collect(app(TenantHealthCheck::class)->run($record, withRender: false));
                            $failed = $checks->where('ok', false)->pluck('label')->take(6)->values();

                            $notification = Notification::make()
                                ->title($failed->isEmpty() ? 'Pemeriksaan teknis selesai' : 'Tenant perlu ditinjau')
                                ->body($failed->isEmpty()
                                    ? 'Semua pemeriksaan kesiapan dasar yang tersedia berhasil. Diperiksa '.now()->format('d M Y, H:i').'.'
                                    : 'Perlu ditinjau: '.$failed->implode(', ').'. Diperiksa '.now()->format('d M Y, H:i').'.')
                                ->duration(12000);

                            ($failed->isEmpty() ? $notification->success() : $notification->warning())->send();
                        } catch (\Throwable) {
                            Notification::make()
                                ->title('Pemeriksaan teknis gagal dijalankan')
                                ->body('Coba lagi nanti atau periksa log teknis dengan akses yang sesuai.')
                                ->danger()
                                ->send();
                        }
                    }),
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

                // === DNS custom domain: 2 opsi (manual + otomasi) — Refs #350 ===
                ActionGroup::make([
                    Action::make('dns_cek')
                        ->label('Cek DNS (manual)')
                        ->icon('heroicon-o-magnifying-glass')
                        ->color('gray')
                        ->action(function (Tenant $record): void {
                            static::jalankanAksiDns($record, fn (DnsOtomasiService $svc, string $d) => $svc->cekDns($d));
                        }),
                    Action::make('dns_otomatis')
                        ->label('Otomatis (domain di Hostinger)')
                        ->icon('heroicon-o-bolt')
                        ->color('success')
                        ->disabled(fn (): bool => ! app(DnsOtomasiService::class)->otomasiAktif())
                        ->tooltip(fn (): ?string => app(DnsOtomasiService::class)->otomasiAktif()
                            ? null
                            : 'Nonaktif: isi HOSTINGER_API_TOKEN di ENV untuk mengaktifkan otomasi DNS.')
                        ->requiresConfirmation()
                        ->modalHeading('Buat A record otomatis via Hostinger')
                        ->modalDescription(fn (Tenant $record): string => 'A record untuk '
                            .(implode(', ', static::customDomains($record)) ?: '(tidak ada custom domain)')
                            .' akan dibuat otomatis mengarah ke '.app(DnsOtomasiService::class)->serverIp()
                            .'. Hanya berhasil bila zona domain dikelola di akun Hostinger owner; jika tidak, akan ditampilkan instruksi manual.')
                        ->action(function (Tenant $record): void {
                            static::jalankanAksiDns($record, fn (DnsOtomasiService $svc, string $d) => $svc->buatARecord($d));
                        }),
                ])
                    ->label('DNS Domain')
                    ->icon('heroicon-o-globe-alt')
                    ->button()
                    ->visible(fn (Tenant $record): bool => static::customDomains($record) !== []),

                // === Domain kanonis: OPT-IN MURNI. Tanpa aksi ini dijalankan,
                // TIDAK ADA redirect sama sekali — keberadaan custom domain
                // saja tidak pernah cukup (insiden 2026-07-04: versi otomatis
                // lama meredirect albakids ke host tak valid). Owner memilih
                // domain utama secara sadar per tenant, kapan pun diperlukan. ===
                Action::make('domain_kanonis')
                    ->label('Domain Utama')
                    ->icon('heroicon-o-arrow-uturn-right')
                    ->color('gray')
                    ->visible(fn (Tenant $record): bool => $record->domains->count() > 1)
                    ->form(fn (Tenant $record): array => [
                        Select::make('canonical_domain')
                            ->label('Jadikan Domain Utama (kanonis)')
                            ->helperText('Pengaturan eksplisit — TANPA ini, tidak ada redirect apa pun (default untuk semua tenant). Pilih satu domain agar domain lain ber-type sama dialihkan 301 ke domain itu; pilih "(tanpa domain utama)" untuk menonaktifkan kembali.')
                            ->options(
                                $record->domains
                                    ->mapWithKeys(fn ($d) => [$d->domain => "{$d->domain} ({$d->type})"])
                                    ->prepend('(tanpa domain utama — tidak ada redirect)', '')
                                    ->all()
                            )
                            ->default((string) $record->domains->firstWhere('is_canonical', true)?->domain),
                    ])
                    ->action(function (Tenant $record, array $data): void {
                        abort_unless(static::userCanManageTenants(), 403);
                        $pilihan = (string) ($data['canonical_domain'] ?? '');

                        $record->domains()->update(['is_canonical' => false]);
                        if ($pilihan !== '') {
                            $record->domains()->where('domain', $pilihan)->update(['is_canonical' => true]);
                        }

                        Notification::make()
                            ->title($pilihan !== ''
                                ? "Domain utama tenant {$record->id}: {$pilihan}"
                                : "Domain utama tenant {$record->id} dinonaktifkan — tidak ada redirect.")
                            ->body($pilihan !== ''
                                ? 'Domain lain ber-type sama akan dialihkan 301 ke domain ini (hanya GET; POST/API/livewire/signed-URL tidak disentuh).'
                                : null)
                            ->success()
                            ->send();
                    }),

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
