<?php

namespace App\Filament\Resources\Santri\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Filament\Resources\Santri\SantriResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
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
            $this->unduhKredensialWaliAction(),
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
                // Template PERSIS field form "Buat Santri" panel admin, urut per-section:
                // Data Pribadi → Alamat Santri → Data Ayah (+alamat) → Data Ibu (+alamat) →
                // Akun Wali (+alamat) → Akademik. Foto dilewati (upload, bukan teks Excel).
                // Hanya nis, nama, jenis_kelamin, tanggal_lahir yang WAJIB.
                'template' => [
                    // Data Pribadi
                    'nis', 'nama', 'tanggal_lahir', 'tempat_lahir', 'jenis_kelamin', 'agama', 'nik', 'nisn', 'asal_sekolah',
                    // Alamat Santri
                    'alamat', 'rt_rw', 'kode_pos', 'provinsi', 'kabupaten_kota', 'kecamatan', 'desa_kelurahan',
                    // Data Ayah (+ Alamat Ayah)
                    'nama_ayah', 'pekerjaan_ayah', 'no_hp_ayah', 'alamat_ayah', 'rt_rw_ayah', 'kode_pos_ayah', 'provinsi_ayah', 'kabupaten_kota_ayah', 'kecamatan_ayah', 'desa_kelurahan_ayah',
                    // Data Ibu (+ Alamat Ibu)
                    'nama_ibu', 'pekerjaan_ibu', 'no_hp_ibu', 'alamat_ibu', 'rt_rw_ibu', 'kode_pos_ibu', 'provinsi_ibu', 'kabupaten_kota_ibu', 'kecamatan_ibu', 'desa_kelurahan_ibu',
                    // Akun Wali (+ Alamat Wali / Domisili Keluarga)
                    'no_hp_wali', 'email_wali', 'alamat_wali', 'rt_rw_wali', 'kode_pos_wali', 'provinsi_wali', 'kabupaten_kota_wali', 'kecamatan_wali', 'desa_kelurahan_wali',
                    // Akademik
                    'kelas', 'tahun_angkatan', 'status',
                ],
                'template_contoh' => [
                    // Data Pribadi
                    '2024001', 'Ahmad Fauzi', '2010-05-17', 'Bandung', 'L', 'Islam', '3273010101100001', '0098765432', 'SDN Merdeka 1',
                    // Alamat Santri
                    'Jl. Merdeka No. 1', '003/008', '40132', 'Jawa Barat', 'Kota Bandung', 'Coblong', 'Sukamaju',
                    // Data Ayah (+ Alamat Ayah)
                    'Bapak Fulan', 'Wiraswasta', '081234567891', 'Jl. Merdeka No. 1', '003/008', '40132', 'Jawa Barat', 'Kota Bandung', 'Coblong', 'Sukamaju',
                    // Data Ibu (+ Alamat Ibu)
                    'Ibu Fulanah', 'Ibu Rumah Tangga', '081234567892', 'Jl. Merdeka No. 1', '003/008', '40132', 'Jawa Barat', 'Kota Bandung', 'Coblong', 'Sukamaju',
                    // Akun Wali (+ Alamat Wali)
                    '081234567890', 'wali@contoh.com', 'Jl. Merdeka No. 1', '003/008', '40132', 'Jawa Barat', 'Kota Bandung', 'Coblong', 'Sukamaju',
                    // Akademik
                    '7 A', '2024', 'aktif',
                ],
                'extraSummary' => fn (array $s): string => ($s['created_kelas'] ?? 0) > 0 ? 'Kelas baru dibuat: '.$s['created_kelas'] : '',
                'petunjuk' => 'Impor data santri dari file Excel/CSV. Kolom WAJIB: nis, nama, jenis_kelamin (isi L atau P), tanggal_lahir. Kolom "kelas" akan dibuat otomatis bila belum ada. Baris dengan NIS yang sudah terdaftar akan DILEWATI (aman diulang). Centang "Validasi dulu (dry-run)" untuk mengecek tanpa menyimpan.',
            ]),
            // Export SINKRON (streamDownload) — langsung unduh CSV, TANPA antrian/
            // worker/notifikasi (Filament ExportAction queued sempat 500: tabel
            // 'exports' + notifikasi 'layouts' di worker). 47 kolom = round-trip
            // dgn template impor. Tergerbang sama dgn akses halaman (view_santri).
            Action::make('exportSantri')
                ->label('Export Santri')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $kolom = [
                        'nis', 'nama', 'tanggal_lahir', 'tempat_lahir', 'jenis_kelamin', 'agama', 'nik', 'nisn', 'asal_sekolah',
                        'alamat', 'rt_rw', 'kode_pos', 'provinsi', 'kabupaten_kota', 'kecamatan', 'desa_kelurahan',
                        'nama_ayah', 'pekerjaan_ayah', 'no_hp_ayah', 'alamat_ayah', 'rt_rw_ayah', 'kode_pos_ayah', 'provinsi_ayah', 'kabupaten_kota_ayah', 'kecamatan_ayah', 'desa_kelurahan_ayah',
                        'nama_ibu', 'pekerjaan_ibu', 'no_hp_ibu', 'alamat_ibu', 'rt_rw_ibu', 'kode_pos_ibu', 'provinsi_ibu', 'kabupaten_kota_ibu', 'kecamatan_ibu', 'desa_kelurahan_ibu',
                        'no_hp_wali', 'email_wali', 'alamat_wali', 'rt_rw_wali', 'kode_pos_wali', 'provinsi_wali', 'kabupaten_kota_wali', 'kecamatan_wali', 'desa_kelurahan_wali',
                        'kelas', 'tahun_angkatan', 'status',
                    ];

                    return response()->streamDownload(function () use ($kolom): void {
                        $out = fopen('php://output', 'w');
                        fputcsv($out, $kolom);
                        Santri::query()->with('kelas')->orderBy('nama')->lazy()
                            ->each(function ($s) use ($out, $kolom): void {
                                $baris = [];
                                foreach ($kolom as $k) {
                                    $baris[] = match ($k) {
                                        'kelas' => (string) ($s->kelas?->nama_kelas ?? ''),
                                        'tanggal_lahir' => $s->tanggal_lahir?->format('Y-m-d') ?? '',
                                        default => (string) ($s->{$k} ?? ''),
                                    };
                                }
                                fputcsv($out, $baris);
                            });
                        fclose($out);
                    }, 'santri-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
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
                    ->content('Membuat akun Portal Wali untuk santri AKTIF yang punya NOMOR HP wali (login wali pakai no. HP). Santri yang walinya sudah berakun cukup ditautkan (tidak dobel). Setelah dibuat, unduh kredensial sementara lewat tombol "Unduh Kredensial Wali" di halaman ini.'),
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
                    $body .= "\n\nKredensial sementara siap: klik tombol \"Unduh Kredensial Wali\" (file ".basename((string) $result['csv_path']).').';
                }

                $notification = Notification::make()
                    ->title($dryRun ? 'Hasil validasi akun wali' : 'Akun wali selesai dibuat')
                    ->body($body)
                    ->persistent();

                $result['failed'] > 0 ? $notification->warning() : $notification->success();
                $notification->send();
            });
    }

    /**
     * Unduh CSV kredensial wali TERAKHIR lewat panel (menutup alur CLI/akses
     * storage/ manual). Terproteksi: hanya pemegang manage_santri; file berisi
     * password sementara, jadi tak pernah diekspos lewat URL publik.
     */
    protected function unduhKredensialWaliAction(): Action
    {
        return Action::make('unduhKredensialWali')
            ->label('Unduh Kredensial Wali')
            ->icon('heroicon-o-key')
            ->color('gray')
            ->visible(fn (): bool => tenancy()->initialized
                && (auth()->user()?->can('manage_santri') ?? false)
                && WaliImportService::lastCsvFile() !== null)
            ->action(function () {
                $file = WaliImportService::lastCsvFile();
                if ($file === null) {
                    Notification::make()->title('Belum ada file kredensial wali.')->warning()->send();

                    return null;
                }

                return response()->streamDownload(function () use ($file): void {
                    echo Storage::disk('local')->get($file);
                }, basename($file), ['Content-Type' => 'text/csv; charset=UTF-8']);
            });
    }
}
