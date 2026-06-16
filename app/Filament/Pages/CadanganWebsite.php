<?php

namespace App\Filament\Pages;

use App\Models\Tenant;
use App\Services\WebsiteRecoveryService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Cadangan & Pemulihan WEBSITE & CMS (sisi EKSTERNAL) — TERPISAH dari "Cadangan
 * & Pemulihan Data" (sisi INTERNAL: santri/keuangan/admin). Halaman ini hanya
 * menyentuh konten website: tabel CMS + media-nya. Lihat WebsiteRecoveryService.
 *
 * Reset website TIDAK menghapus akun/auth (beda dgn reset data), jadi TIDAK perlu
 * logout/redirect — pengguna tetap masuk, tinggal membangun ulang website-nya.
 */
class CadanganWebsite extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Cadangan Website';

    protected static string|\UnitEnum|null $navigationGroup = 'Kelembagaan';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Cadangan & Pemulihan Website';

    protected string $view = 'filament.pages.cadangan-website';

    /** Hanya super_admin pondok (akun ber-tenant), di panel tenant. */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && $user !== null
            && $user->tenant_id !== null
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

    protected function namaKonfirmasi(): string
    {
        $t = $this->tenant();

        return trim((string) ($t->name ?: $t->getKey()));
    }

    protected function getViewData(): array
    {
        return ['namaPondok' => $this->namaKonfirmasi()];
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
            ->label('Backup Konten Website')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('primary')
            ->action(function () {
                abort_unless(static::canAccess(), 403);
                try {
                    $path = app(WebsiteRecoveryService::class)->backupDb($this->tenant());

                    return response()->download($path)->deleteFileAfterSend();
                } catch (\Throwable $e) {
                    report($e);
                    Notification::make()->title('Backup konten gagal')->body($e->getMessage())->danger()->send();
                }
            });
    }

    private function aksiBackupFiles(): Action
    {
        return Action::make('backup_files')
            ->label('Backup Media Website')
            ->icon('heroicon-o-photo')
            ->color('primary')
            ->action(function () {
                abort_unless(static::canAccess(), 403);
                try {
                    $path = app(WebsiteRecoveryService::class)->backupFiles($this->tenant());
                    if ($path === null) {
                        Notification::make()->title('Belum ada media website')->warning()->send();

                        return null;
                    }

                    return response()->download($path)->deleteFileAfterSend();
                } catch (\Throwable $e) {
                    report($e);
                    Notification::make()->title('Backup media gagal')->body($e->getMessage())->danger()->send();
                }
            });
    }

    private function aksiRestoreDb(): Action
    {
        return Action::make('restore_db')
            ->label('Restore Konten Website')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Restore konten website dari backup')
            ->modalDescription('Menimpa SELURUH konten website (artikel, galeri, profil, pengaturan website) dengan isi file. Data santri/keuangan TIDAK tersentuh. Backup pengaman dibuat otomatis dulu. Tidak bisa dibatalkan.')
            ->form([
                FileUpload::make('file')
                    ->label('File backup konten (.sql.gz)')
                    ->disk('local')
                    ->directory('website-restore-uploads')
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
                    $svc = app(WebsiteRecoveryService::class);
                    $aman = $svc->backupDb($tenant); // backup pengaman dulu
                    $svc->restoreDb($tenant, $path);
                    Log::warning('WEBSITE SELF-RESTORE DB', ['tenant' => $tenant->getKey(), 'by' => auth()->user()?->email]);
                    Notification::make()->title('Restore konten berhasil')
                        ->body('Backup pengaman: '.basename($aman))
                        ->success()->persistent()->send();
                } catch (\Throwable $e) {
                    report($e);
                    Notification::make()->title('Restore konten gagal')->body($e->getMessage())->danger()->send();
                } finally {
                    @unlink($path);
                }
            });
    }

    private function aksiRestoreFiles(): Action
    {
        return Action::make('restore_files')
            ->label('Restore Media Website')
            ->icon('heroicon-o-photo')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Restore media website')
            ->modalDescription('Menimpa media website (gambar artikel & galeri) dengan isi arsip (.tar.gz). Folder lama disisihkan (.bak) dulu.')
            ->form([
                FileUpload::make('file')
                    ->label('Arsip media (.tar.gz)')
                    ->disk('local')
                    ->directory('website-restore-uploads')
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
                    app(WebsiteRecoveryService::class)->restoreFiles($tenant, $path);
                    Log::warning('WEBSITE SELF-RESTORE FILES', ['tenant' => $tenant->getKey(), 'by' => auth()->user()?->email]);
                    Notification::make()->title('Restore media berhasil')->success()->send();
                } catch (\Throwable $e) {
                    report($e);
                    Notification::make()->title('Restore media gagal')->body($e->getMessage())->danger()->send();
                } finally {
                    @unlink($path);
                }
            });
    }

    private function aksiReset(): Action
    {
        return Action::make('reset')
            ->label('RESET Website')
            ->icon('heroicon-o-exclamation-triangle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Kosongkan SELURUH konten website')
            ->modalDescription('Menghapus semua artikel, galeri, profil, dan pengaturan website + media-nya — website kembali kosong. Data santri/keuangan/admin TIDAK tersentuh. Backup pengaman dibuat OTOMATIS lebih dulu. TIDAK BISA DIBATALKAN.')
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
                try {
                    $hasil = app(WebsiteRecoveryService::class)->resetWebsite($tenant);
                } catch (\Throwable $e) {
                    report($e);
                    Notification::make()->title('Reset website GAGAL')
                        ->body($e->getMessage().' — backup pengaman mungkin sudah dibuat.')
                        ->danger()->persistent()->send();

                    return;
                }
                Log::warning('WEBSITE SELF-RESET', ['tenant' => $tenant->getKey(), 'by' => auth()->user()?->email]);
                Notification::make()->title('Website dikosongkan')
                    ->body('Konten website dikosongkan. Data operasional tidak tersentuh. Backup pengaman: '.basename($hasil['db_backup']))
                    ->success()->persistent()->send();
            });
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
