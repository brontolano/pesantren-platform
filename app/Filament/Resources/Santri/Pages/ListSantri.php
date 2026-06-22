<?php

namespace App\Filament\Resources\Santri\Pages;

use App\Filament\Exports\SantriExporter;
use App\Filament\Resources\Santri\SantriResource;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\SantriImportService;

class ListSantri extends ListRecords
{
    protected static string $resource = SantriResource::class;

    protected function getHeaderActions(): array
    {
        $kelasStatusForm = [
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
        ];

        return [
            ActionGroup::make([
                ExportAction::make('exportSemua')
                    ->exporter(SantriExporter::class)
                    ->label('Export Semua Santri')
                    ->icon('heroicon-o-arrow-down-tray'),
                ExportAction::make('exportAktif')
                    ->exporter(SantriExporter::class)
                    ->label('Export Santri Aktif')
                    ->icon('heroicon-o-check-badge')
                    ->modifyQueryUsing(fn ($query) => $query->where('status', 'aktif')),
                Action::make('exportCsvPerKelas')
                    ->label('Export Excel per Kelas')
                    ->icon('heroicon-o-table-cells')
                    ->form($kelasStatusForm)
                    ->action(fn (array $data) => redirect()->route('tenant.kesantrian.santri.export.csv', $data))
                    ->visible(fn () => tenancy()->initialized),
                Action::make('printPdfPerKelas')
                    ->label('Cetak / Simpan PDF')
                    ->icon('heroicon-o-printer')
                    ->form($kelasStatusForm)
                    ->action(fn (array $data) => redirect()->route('tenant.kesantrian.santri.export.print', $data))
                    ->openUrlInNewTab(false)
                    ->visible(fn () => tenancy()->initialized),
                Action::make('unduhTemplateImport')
                    ->label('Unduh Template Import')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(fn () => redirect()->route('tenant.kesantrian.santri.export.template'))
                    ->visible(fn () => tenancy()->initialized),
            ])
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->button(),

            $this->importAction(),

            CreateAction::make(),
        ];
    }

    protected function importAction(): Action
    {
        return Action::make('importSantri')
            ->label('Import Santri')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->visible(fn () => tenancy()->initialized && auth()->user()?->can('manage_santri'))
            ->form([
                Placeholder::make('petunjuk')
                    ->label('Petunjuk')
                    ->content('Unggah file .xlsx / .csv / .ods. Gunakan tombol "Unduh Template" pada menu Export bila belum punya format. NIS yang sudah ada akan dilewati.'),
                FileUpload::make('file')
                    ->label('File data santri')
                    ->acceptedFileTypes([
                        'text/csv',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.oasis.opendocument.spreadsheet',
                    ])
                    ->storeFiles(true)
                    ->disk('local')
                    ->directory('imports/santri/uploads')
                    ->required(),
                Checkbox::make('dry_run')
                    ->label('Validasi dulu (dry-run) — tidak menyimpan ke database')
                    ->default(true),
            ])
            ->action(function (array $data): void {
                $relativePath = $data['file'];
                $absolutePath = Storage::disk('local')->path($relativePath);
                $dryRun = (bool) ($data['dry_run'] ?? false);

                /** @var Tenant $tenant */
                $tenant = Tenant::query()->find(tenant('id'));

                if ($tenant === null) {
                    Notification::make()
                        ->title('Tenant aktif tidak ditemukan.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $summary = app(SantriImportService::class)->import($tenant, $absolutePath, $dryRun);
                } finally {
                    Storage::disk('local')->delete($relativePath);
                }

                $modeLabel = $dryRun ? 'Validasi (dry-run)' : 'Import';
                $body = sprintf(
                    "%s selesai.\nDiproses: %d  •  Dilewati: %d  •  Gagal: %d  •  Kelas baru: %d",
                    $modeLabel,
                    $summary['imported'],
                    $summary['skipped'],
                    $summary['failed'],
                    $summary['created_kelas'],
                );

                $errors = collect($summary['errors'])
                    ->take(5)
                    ->map(fn (array $e) => "• Baris {$e['row']} ({$e['nis']}): {$e['error']}")
                    ->implode("\n");

                if ($errors !== '') {
                    $body .= "\n\nContoh masalah:\n" . $errors;
                }

                $notification = Notification::make()
                    ->title($dryRun ? 'Hasil validasi import' : 'Import santri selesai')
                    ->body($body)
                    ->persistent();

                $summary['failed'] > 0 ? $notification->warning() : $notification->success();

                $notification->send();
            });
    }
}
