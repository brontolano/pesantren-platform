<?php

namespace App\Filament\Pages;

use App\Models\Tenant;
use App\Services\TenantRecoveryService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Cadangan & Pemulihan MANDIRI per pondok (panel TENANT).
 *
 * Memberi super_admin pondok kemampuan backup/restore/RESET DATA-nya SENDIRI,
 * memakai ulang TenantRecoveryService yang sama dengan recovery platform.
 *
 * PENTING — konteks CENTRAL: TenantRecoveryService dirancang untuk panel platform
 * yang berjalan di konteks central (tenancy TIDAK aktif), sehingga storage_path()
 * BELUM ber-sufiks. Panel tenant SELALU meng-inisialisasi tenancy → storage_path()
 * sudah ber-sufiks 'tenant<id>'. Bila service dipanggil langsung dari sini, sufiks
 * GANDA (storage/tenant<id>/tenant<id>) → backup/clear/restore FILE menyasar folder
 * salah. Maka SEMUA panggilan service dibungkus tenancy()->central(...) agar
 * storage_path() tunggal-sufiks (sama persis konteks platform yang sudah teruji).
 *
 * Anti-terkunci pada RESET: reset() men-DROP skema (akun ikut hilang) lalu
 * provision membuat admin baru. Agar pe-reset tak terkunci, setelah reset kita
 * KEMBALIKAN akun pemicu ke email + kata sandi + nama SEMULA (lihat lakukanReset)
 * → login seperti biasa, tanpa kata sandi sekali-tampil yang rawan hilang.
 */
class CadanganPemulihan extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationLabel = 'Cadangan & Pemulihan';

    protected static string|\UnitEnum|null $navigationGroup = 'Kelembagaan & Pengaturan';

    protected static ?int $navigationSort = 95;

    protected static ?string $title = 'Cadangan & Pemulihan Data';

    protected string $view = 'filament.pages.cadangan-pemulihan';

    /** HANYA super_admin pondok (akun ber-tenant), di panel tenant. */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && $user !== null
            && $user->tenant_id !== null            // tolak akun platform (defense-in-depth, selaras canRecovery)
            && $user->hasRole('super_admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected function tenant(): Tenant
    {
        return tenant();
    }

    /** Nama (di-trim) yang harus diketik untuk konfirmasi aksi destruktif. */
    protected function namaKonfirmasi(): string
    {
        $t = $this->tenant();

        return trim((string) ($t->name ?: $t->getKey()));
    }

    protected function getViewData(): array
    {
        return [
            'namaPondok' => $this->namaKonfirmasi(),
            'ukuranDb' => app(TenantRecoveryService::class)->size($this->tenant()),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->aksiBackupDb(),
            $this->aksiBackupFiles(),
            $this->aksiRestoreDb(),
            $this->aksiRestoreFiles(),
            $this->aksiReset(),
        ];
    }

    private function aksiBackupDb(): Action
    {
        return Action::make('backup_db')
            ->label('Backup Database')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('primary')
            ->action(function () {
                abort_unless(static::canAccess(), 403);
                $tenant = $this->tenant();
                try {
                    $path = tenancy()->central(fn () => app(TenantRecoveryService::class)->backup($tenant));

                    return response()->download($path)->deleteFileAfterSend();
                } catch (\Throwable $e) {
                    report($e);
                    Notification::make()->title('Backup gagal')->body($e->getMessage())->danger()->send();
                }
            });
    }

    private function aksiBackupFiles(): Action
    {
        return Action::make('backup_files')
            ->label('Backup File')
            ->icon('heroicon-o-photo')
            ->color('primary')
            ->action(function () {
                abort_unless(static::canAccess(), 403);
                $tenant = $this->tenant();
                try {
                    $path = tenancy()->central(fn () => app(TenantRecoveryService::class)->backupFiles($tenant));
                    if ($path === null) {
                        Notification::make()->title('Belum ada file unggahan')->warning()->send();

                        return null;
                    }

                    return response()->download($path)->deleteFileAfterSend();
                } catch (\Throwable $e) {
                    report($e);
                    Notification::make()->title('Backup file gagal')->body($e->getMessage())->danger()->send();
                }
            });
    }

    private function aksiRestoreDb(): Action
    {
        return Action::make('restore_db')
            ->label('Restore Database')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Restore database pondok dari backup')
            ->modalDescription('PERINGATAN: menimpa SELURUH data database saat ini dengan isi file. Backup pengaman dibuat otomatis dulu. Tidak bisa dibatalkan.')
            ->form([
                FileUpload::make('file')
                    ->label('File backup (.sql.gz)')
                    ->disk('local')
                    ->directory('tenant-restore-uploads')
                    ->acceptedFileTypes(['application/gzip', 'application/x-gzip', 'application/octet-stream'])
                    ->required(),
                TextInput::make('konfirmasi')
                    ->label('Ketik nama pondok untuk konfirmasi')
                    ->helperText(fn (): string => 'Ketik persis: '.$this->namaKonfirmasi())
                    ->required(),
            ])
            ->action(function (array $data) {
                abort_unless(static::canAccess(), 403);
                if (! $this->konfirmasiCocok($data)) {
                    return;
                }
                $tenant = $this->tenant();
                $path = Storage::disk('local')->path($data['file']); // path absolut (konteks tenant) → konsisten
                try {
                    $aman = tenancy()->central(function () use ($tenant, $path): string {
                        $svc = app(TenantRecoveryService::class);
                        $b = $svc->backup($tenant); // backup pengaman dulu
                        $svc->restore($tenant, $path);

                        return $b;
                    });
                    Log::warning('TENANT SELF-RESTORE DB', ['tenant' => $tenant->getKey(), 'by' => auth()->user()?->email]);
                    Notification::make()->title('Restore berhasil')
                        ->body('Backup pengaman dibuat: '.basename($aman).'. Jika akun Anda berubah, login ulang.')
                        ->success()->persistent()->send();
                } catch (\Throwable $e) {
                    report($e);
                    Notification::make()->title('Restore gagal')->body($e->getMessage())->danger()->send();
                } finally {
                    @unlink($path);
                }
            });
    }

    private function aksiRestoreFiles(): Action
    {
        return Action::make('restore_files')
            ->label('Restore File')
            ->icon('heroicon-o-photo')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Restore file unggahan pondok')
            ->modalDescription('Menimpa file unggahan saat ini dengan isi arsip (.tar.gz). Folder lama disisihkan (.bak) dulu.')
            ->form([
                FileUpload::make('file')
                    ->label('Arsip file (.tar.gz)')
                    ->disk('local')
                    ->directory('tenant-restore-uploads')
                    ->acceptedFileTypes(['application/gzip', 'application/x-gzip', 'application/octet-stream'])
                    ->required(),
                TextInput::make('konfirmasi')
                    ->label('Ketik nama pondok untuk konfirmasi')
                    ->helperText(fn (): string => 'Ketik persis: '.$this->namaKonfirmasi())
                    ->required(),
            ])
            ->action(function (array $data) {
                abort_unless(static::canAccess(), 403);
                if (! $this->konfirmasiCocok($data)) {
                    return;
                }
                $tenant = $this->tenant();
                $path = Storage::disk('local')->path($data['file']);
                try {
                    tenancy()->central(fn () => app(TenantRecoveryService::class)->restoreFiles($tenant, $path));
                    Log::warning('TENANT SELF-RESTORE FILES', ['tenant' => $tenant->getKey(), 'by' => auth()->user()?->email]);
                    Notification::make()->title('Restore file berhasil')->success()->send();
                } catch (\Throwable $e) {
                    report($e);
                    Notification::make()->title('Restore file gagal')->body($e->getMessage())->danger()->send();
                } finally {
                    @unlink($path);
                }
            });
    }

    private function aksiReset(): Action
    {
        return Action::make('reset')
            ->label('RESET Seluruh Data')
            ->icon('heroicon-o-exclamation-triangle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Reset SELURUH data pondok ini')
            ->modalDescription('Menghapus SEMUA data (santri, keuangan, presensi, file unggahan, dll) lalu kembali ke kondisi awal: kosong + fondasi (peran, COA, branding). Backup pengaman (DB + file) dibuat OTOMATIS lebih dulu. Setelah reset Anda akan keluar dan harus LOGIN ULANG dengan email & kata sandi Anda yang BIASA. TIDAK BISA DIBATALKAN.')
            ->form([
                TextInput::make('konfirmasi')
                    ->label('Ketik nama pondok untuk konfirmasi')
                    ->helperText(fn (): string => 'Ketik persis: '.$this->namaKonfirmasi())
                    ->required(),
            ])
            ->action(function (array $data) {
                abort_unless(static::canAccess(), 403);
                if (! $this->konfirmasiCocok($data)) {
                    return;
                }

                $tenant = $this->tenant();
                $email = (string) auth()->user()->email;

                try {
                    $hasil = $this->lakukanReset();
                } catch (\Throwable $e) {
                    report($e);
                    Notification::make()->title('Reset GAGAL')
                        ->body($e->getMessage().' — backup pengaman mungkin sudah dibuat; hubungi admin platform.')
                        ->danger()->persistent()->send();

                    return;
                }

                Log::warning('TENANT SELF-RESET', ['tenant' => $tenant->getKey(), 'by' => $email]);

                Notification::make()->title('Reset selesai')
                    ->body('Data pondok dikosongkan & dikembalikan ke kondisi awal. Silakan LOGIN ULANG dengan email & kata sandi Anda yang biasa. Backup pengaman: '.basename($hasil['db_backup']))
                    ->success()->persistent()->send();

                // (Sudah di-logout di dalam lakukanReset.) $this->redirect() — BUKAN
                // return redirect() — karena Livewire mengabaikan RedirectResponse
                // yang di-return dari closure aksi (XHR).
                $this->redirect(Filament::getCurrentPanel()?->getLoginUrl() ?? '/admin/login');
            });
    }

    /**
     * RESET di konteks central + KEMBALIKAN kredensial admin pemicu (anti-terkunci).
     *
     * @return array{db_backup:string, files_backup:?string, admin_email:?string, admin_password:?string}
     */
    protected function lakukanReset(): array
    {
        $tenant = $this->tenant();
        $u = auth()->user();
        $email = (string) $u->email;
        $hash = (string) $u->password; // hash bcrypt yg sudah ada
        $nama = (string) $u->name;

        // Keluar DULU sebelum wipe: provision (reseed COA dll) memicu observer audit
        // yang mencatat actor_id = id pengguna saat ini. Setelah DROP SCHEMA, id itu
        // hilang → FK violation audit_log.actor_id menggagalkan reset. Tanpa auth,
        // actor_id = null (aman, sama spt jalur platform tanpa actingAs).
        Auth::guard()->logout();

        // Reset di central → storage_path() tunggal-sufiks (file backup/clear benar).
        $hasil = tenancy()->central(fn () => app(TenantRecoveryService::class)->reset($tenant, $email));

        // Pulihkan akun pemicu ke email+sandi+nama SEMULA. Query builder = lewati
        // cast 'hashed' agar hash tak di-hash ulang. provision sudah beri super_admin.
        $tenant->run(function () use ($email, $hash, $nama): void {
            DB::table('users')->where('email', $email)->update([
                'password' => $hash,
                'name' => $nama,
                'updated_at' => now(),
            ]);
        });

        return $hasil;
    }

    /** @param array<string,mixed> $data */
    private function konfirmasiCocok(array $data): bool
    {
        if (trim((string) ($data['konfirmasi'] ?? '')) === $this->namaKonfirmasi()) {
            return true;
        }

        Notification::make()->title('Konfirmasi tidak cocok')
            ->body('Ketik nama pondok yang benar untuk melanjutkan.')->danger()->send();

        return false;
    }
}
