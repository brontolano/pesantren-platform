<?php

namespace App\Filament\Concerns;

use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Trait pemandu import data lewat panel (self-service migrasi).
 *
 * Membungkus pola yang sudah terbukti di import Santri menjadi satu tempat
 * supaya tiap halaman List cukup mendeklarasikan konfigurasinya — tanpa
 * menyalin logika upload + dry-run + notifikasi berulang kali.
 *
 * Mesin import TETAP di Service masing-masing modul (SantriImportService,
 * AsramaImportService, KeuanganImportService, dst.). Trait ini hanya lapisan UI.
 *
 * Kontrak Service yang diharapkan: mengembalikan array ringkasan minimal berisi
 * 'imported', 'skipped', 'failed', 'errors' (errors = list of
 * ['row'=>int,'error'=>string, ...]). Kunci tambahan (mis. 'created_blok')
 * bisa ditampilkan lewat closure 'extraSummary'.
 *
 * @phpstan-type ImportConfig array{
 *   name?: string,
 *   label?: string,
 *   entitas: string,
 *   service: class-string,
 *   directory: string,
 *   ability?: string,
 *   petunjuk?: string,
 *   schema?: array<int, \Filament\Forms\Components\Component>,
 *   invoke?: callable,
 *   extraSummary?: callable,
 * }
 */
trait HasImportAction
{
    /**
     * Pasangan aksi siap-spread untuk header List: [Unduh Template, Import].
     * Tombol template hanya muncul bila config punya 'template' (daftar kolom).
     *
     * @param  ImportConfig  $config
     * @return list<Action>
     */
    protected function importActions(array $config): array
    {
        return array_values(array_filter([
            $this->buildTemplateAction($config),
            $this->buildImportAction($config),
        ]));
    }

    /**
     * Aksi "Unduh Template" — CSV berisi header kolom (+ baris contoh opsional)
     * sesuai yang diterima importer, agar admin tahu format yang benar.
     *
     * @param  ImportConfig  $config
     */
    protected function buildTemplateAction(array $config): ?Action
    {
        $columns = $config['template'] ?? null;
        if (empty($columns) || ! is_array($columns)) {
            return null;
        }

        $entitas = $config['entitas'];
        $ability = $config['ability'] ?? null;
        $contoh = $config['template_contoh'] ?? [];
        $filename = 'template-import-'.str()->slug($entitas).'.csv';

        return Action::make(($config['name'] ?? 'import'.str()->studly($entitas)).'Template')
            ->label('Unduh Template')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->visible(fn (): bool => tenancy()->initialized
                && ($ability === null || (auth()->user()?->can($ability) ?? false)))
            ->action(function () use ($columns, $contoh, $filename) {
                return response()->streamDownload(function () use ($columns, $contoh): void {
                    $out = fopen('php://output', 'w');
                    fputcsv($out, $columns);
                    if ($contoh !== []) {
                        fputcsv($out, $contoh);
                    }
                    fclose($out);
                }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
            });
    }

    /**
     * Bangun Action import siap pakai untuk header halaman List.
     *
     * @param  ImportConfig  $config
     */
    protected function buildImportAction(array $config): Action
    {
        $entitas = $config['entitas'];
        $serviceClass = $config['service'];
        $directory = $config['directory'];
        $ability = $config['ability'] ?? null;
        $name = $config['name'] ?? 'import'.str()->studly($entitas);
        $label = $config['label'] ?? 'Import '.ucfirst($entitas);
        $petunjuk = $config['petunjuk']
            ?? 'Unggah file .xlsx / .csv / .ods. Hanya sheet pertama yang dibaca. Baris yang sudah ada akan dilewati (aman diulang).';
        $extraSchema = $config['schema'] ?? [];

        /** @var callable $invoke */
        $invoke = $config['invoke'] ?? function (object $service, Tenant $tenant, string $path, bool $dryRun, array $data): array {
            return $service->import($tenant, $path, $dryRun);
        };

        /** @var callable|null $extraSummary */
        $extraSummary = $config['extraSummary'] ?? null;

        return Action::make($name)
            ->label($label)
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->visible(fn (): bool => tenancy()->initialized
                && ($ability === null || (auth()->user()?->can($ability) ?? false)))
            ->schema([
                Placeholder::make('petunjuk')
                    ->label('Petunjuk')
                    ->content($petunjuk),
                FileUpload::make('file')
                    ->label('File data '.$entitas)
                    ->acceptedFileTypes([
                        'text/csv',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.oasis.opendocument.spreadsheet',
                    ])
                    ->storeFiles(true)
                    ->disk('local')
                    ->directory($directory)
                    ->required(),
                ...$extraSchema,
                Checkbox::make('dry_run')
                    ->label('Validasi dulu (dry-run) — tidak menyimpan ke database')
                    ->default(true),
            ])
            ->action(function (array $data) use ($serviceClass, $entitas, $invoke, $extraSummary): void {
                $relativePath = $data['file'];
                $absolutePath = Storage::disk('local')->path($relativePath);
                $dryRun = (bool) ($data['dry_run'] ?? false);

                /** @var Tenant|null $tenant */
                $tenant = Tenant::query()->find(tenant('id'));

                if ($tenant === null) {
                    Notification::make()
                        ->title('Tenant aktif tidak ditemukan.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $service = app($serviceClass);
                    $summary = $invoke($service, $tenant, $absolutePath, $dryRun, $data);
                } finally {
                    Storage::disk('local')->delete($relativePath);
                }

                $modeLabel = $dryRun ? 'Validasi (dry-run)' : 'Import';
                $body = sprintf(
                    "%s %s selesai.\nDiproses: %d  •  Dilewati: %d  •  Gagal: %d",
                    $modeLabel,
                    $entitas,
                    $summary['imported'] ?? 0,
                    $summary['skipped'] ?? 0,
                    $summary['failed'] ?? 0,
                );

                if ($extraSummary !== null) {
                    $tambahan = (string) $extraSummary($summary);
                    if ($tambahan !== '') {
                        $body .= '  •  '.$tambahan;
                    }
                }

                $errors = collect($summary['errors'] ?? [])
                    ->take(5)
                    ->map(function (array $e): string {
                        $ref = $e['nis'] ?? $e['ref'] ?? '';
                        $ref = $ref !== '' ? " ({$ref})" : '';

                        return "• Baris {$e['row']}{$ref}: {$e['error']}";
                    })
                    ->implode("\n");

                if ($errors !== '') {
                    $body .= "\n\nContoh masalah:\n".$errors;
                }

                $notification = Notification::make()
                    ->title($dryRun ? 'Hasil validasi import' : ucfirst($entitas).' selesai diimpor')
                    ->body($body)
                    ->persistent();

                ($summary['failed'] ?? 0) > 0 ? $notification->warning() : $notification->success();

                $notification->send();
            });
    }
}
