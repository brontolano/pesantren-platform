<?php

namespace App\Filament\Resources\Santri\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Filament\Exports\SantriExporter;
use App\Filament\Resources\Santri\SantriResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Services\SantriImportService;
use Modules\Kesantrian\Services\WaliImportService;

class ListSantri extends ListRecords
{
    use HasImportAction;

    protected static string $resource = SantriResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->buatAkunWaliAction(),
            // Import data santri dari Excel/CSV — memenuhi janji empty-state
            // ("Mulai dengan mengimpor data dari Excel"). Mesin tetap di
            // SantriImportService (idempoten: NIS yang sudah ada dilewati,
            // kelas dibuat otomatis). Hanya Unduh Template + Import yang dipasang;
            // Export round-trip SENGAJA tak diaktifkan karena halaman ini sudah
            // punya "Export Santri" + ekspor/cetak per-kelas (hindari dobel).
            ...$this->importActions([
                'entitas' => 'santri',
                'label' => 'Import Santri',
                'service' => SantriImportService::class,
                'directory' => 'imports/kesantrian/santri/uploads',
                'ability' => 'manage_santri',
                'template' => ['nis', 'nama', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'nik', 'nisn', 'kelas', 'tahun_angkatan', 'status', 'nama_ayah', 'nama_ibu', 'no_hp_wali', 'email_wali', 'alamat'],
                'template_contoh' => ['2024001', 'Ahmad Fauzi', 'L', 'Bandung', '2010-05-17', '3273010101100001', '0098765432', '7 A', '2024', 'aktif', 'Bapak Fulan', 'Ibu Fulanah', '081234567890', 'wali@contoh.com', 'Jl. Merdeka No. 1'],
                'extraSummary' => fn (array $s): string => ($s['created_kelas'] ?? 0) > 0 ? 'Kelas baru dibuat: '.$s['created_kelas'] : '',
                'petunjuk' => 'Impor data santri dari file Excel/CSV. Kolom WAJIB: nis, nama, jenis_kelamin (isi L atau P), tanggal_lahir. Kolom "kelas" akan dibuat otomatis bila belum ada. Baris dengan NIS yang sudah terdaftar akan DILEWATI (aman diulang). Centang "Validasi dulu (dry-run)" untuk mengecek tanpa menyimpan.',
            ]),
            ExportAction::make()
                ->exporter(SantriExporter::class)
                ->label('Export Santri')
                ->icon('heroicon-o-arrow-down-tray'),
            Action::make('exportCsvPerKelas')
                ->label('Export Excel per Kelas')
                ->icon('heroicon-o-table-cells')
                ->form([
                    Select::make('kelas_id')
                        ->label('Kelas')
                        ->options(fn () => Kelas::query()->orderBy('nama_kelas')->pluck('nama_kelas', 'id'))
                        ->required()
                        ->searchable(),
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            '' => 'Semua status',
                            'aktif' => 'Aktif',
                            'cuti' => 'Cuti',
                            'lulus' => 'Lulus',
                            'keluar' => 'Keluar',
                            'dikeluarkan' => 'Dikeluarkan',
                        ])
                        ->default(''),
                ])
                ->action(fn (array $data) => redirect()->route('tenant.kesantrian.santri.export.csv', $data))
                ->visible(fn () => tenancy()->initialized),
            Action::make('printPdfPerKelas')
                ->label('Cetak / Simpan PDF')
                ->icon('heroicon-o-printer')
                ->form([
                    Select::make('kelas_id')
                        ->label('Kelas')
                        ->options(fn () => Kelas::query()->orderBy('nama_kelas')->pluck('nama_kelas', 'id'))
                        ->required()
                        ->searchable(),
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            '' => 'Semua status',
                            'aktif' => 'Aktif',
                            'cuti' => 'Cuti',
                            'lulus' => 'Lulus',
                            'keluar' => 'Keluar',
                            'dikeluarkan' => 'Dikeluarkan',
                        ])
                        ->default(''),
                ])
                ->action(fn (array $data) => redirect()->route('tenant.kesantrian.santri.export.print', $data))
                ->openUrlInNewTab(false)
                ->visible(fn () => tenancy()->initialized),
            CreateAction::make(),
        ];
    }

    /**
     * Buat akun Portal Wali massal dari santri aktif yang punya email_wali.
     * Bukan import file — generate dari data yang sudah ada. Idempoten.
     */
    protected function buatAkunWaliAction(): Action
    {
        return Action::make('buatAkunWali')
            ->label('Buat Akun Wali')
            ->icon('heroicon-o-user-plus')
            ->color('gray')
            ->visible(fn (): bool => tenancy()->initialized && (auth()->user()?->can('manage_santri') ?? false))
            ->schema([
                Placeholder::make('petunjuk')
                    ->label('Petunjuk')
                    ->content('Membuat akun Portal Wali untuk santri AKTIF yang punya NOMOR HP wali (login wali pakai no. HP). Santri yang walinya sudah berakun cukup ditautkan (tidak dobel). Setelah dibuat, kredensial sementara tersimpan sebagai CSV di server untuk dibagikan.'),
                Checkbox::make('dry_run')
                    ->label('Validasi dulu (dry-run) — tidak membuat akun')
                    ->default(true),
            ])
            ->action(function (array $data): void {
                if (! tenancy()->initialized) {
                    Notification::make()->title('Tenant aktif tidak ditemukan.')->danger()->send();

                    return;
                }

                $dryRun = (bool) ($data['dry_run'] ?? false);
                $result = app(WaliImportService::class)->generate((string) tenant('id'), $dryRun);

                $body = sprintf(
                    "%s\nDiproses: %d  •  Akun baru: %d  •  Sudah ada: %d  •  Tanpa no. HP: %d  •  Gagal: %d",
                    $dryRun ? 'Validasi (dry-run) selesai.' : 'Pembuatan akun wali selesai.',
                    $result['total'],
                    $result['created'],
                    $result['existing'],
                    $result['no_hp'],
                    $result['failed'],
                );

                if (! $dryRun && $result['csv_path'] !== null) {
                    $body .= "\n\nKredensial sementara tersimpan di server: ".basename((string) $result['csv_path']);
                }

                $notification = Notification::make()
                    ->title($dryRun ? 'Hasil validasi akun wali' : 'Akun wali selesai dibuat')
                    ->body($body)
                    ->persistent();

                $result['failed'] > 0 ? $notification->warning() : $notification->success();
                $notification->send();
            });
    }
}
