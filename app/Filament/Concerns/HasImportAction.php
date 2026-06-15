<?php

namespace App\Filament\Concerns;

use App\Models\Setting;
use App\Models\Tenant;
use App\Support\ImportColumnMapper;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

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
 *   enabledSetting?: string,
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
            $this->buildExportAction($config),
            $this->buildTemplateAction($config),
            $this->buildImportAction($config),
        ]));
    }

    /**
     * Aksi "Export" round-trip — CSV data unit dalam FORMAT yang sama dengan
     * import (header = kolom 'template'), sehingga bisa diunduh → koreksi di
     * Excel → impor ulang. Baris diambil dari closure 'export' (pemetaan
     * per-unit; trait hanya menstream). Muncul hanya bila config punya keduanya.
     *
     * @param  ImportConfig  $config
     */
    protected function buildExportAction(array $config): ?Action
    {
        $columns = $config['template'] ?? null;
        $rowsResolver = $config['export'] ?? null;
        if (empty($columns) || ! is_array($columns) || ! is_callable($rowsResolver)) {
            return null;
        }

        $entitas = $config['entitas'];
        $ability = $config['ability'] ?? null;
        $enabledSetting = $config['enabledSetting'] ?? null;
        $filename = 'export-'.str()->slug($entitas).'.csv';

        return Action::make(($config['name'] ?? 'import'.str()->studly($entitas)).'Export')
            ->label('Export')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(fn (): bool => tenancy()->initialized
                && ($ability === null || (auth()->user()?->can($ability) ?? false))
                && ($enabledSetting === null || static::importSettingAktif($enabledSetting)))
            ->action(function () use ($columns, $rowsResolver, $filename) {
                /** @var Tenant|null $tenant */
                $tenant = Tenant::query()->find(tenant('id'));
                if ($tenant === null) {
                    Notification::make()->title('Tenant aktif tidak ditemukan.')->danger()->send();

                    return null;
                }

                return response()->streamDownload(function () use ($columns, $rowsResolver, $tenant): void {
                    $out = fopen('php://output', 'w');
                    fputcsv($out, $columns);
                    foreach ($rowsResolver($tenant) as $row) {
                        $line = [];
                        foreach ($columns as $col) {
                            $line[] = $row[$col] ?? '';
                        }
                        fputcsv($out, $line);
                    }
                    fclose($out);
                }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
            });
    }

    /**
     * Aksi "Unduh Template" — file .xlsx berisi header kolom (+ baris contoh)
     * sesuai yang diterima importer, agar admin tahu format yang benar. Memakai
     * format Excel asli (lebih akrab untuk TU); importer juga menerima .csv/.ods.
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
        $enabledSetting = $config['enabledSetting'] ?? null;
        $contoh = $config['template_contoh'] ?? [];
        $filename = 'template-import-'.str()->slug($entitas).'.xlsx';

        return Action::make(($config['name'] ?? 'import'.str()->studly($entitas)).'Template')
            ->label('Unduh Template')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->visible(fn (): bool => tenancy()->initialized
                && ($ability === null || (auth()->user()?->can($ability) ?? false))
                && ($enabledSetting === null || static::importSettingAktif($enabledSetting)))
            ->action(function () use ($columns, $contoh, $filename) {
                $tmp = tempnam(sys_get_temp_dir(), 'tmpl').'.xlsx';
                $writer = new Writer;
                $writer->openToFile($tmp);
                $writer->addRow(Row::fromValues($columns));
                if ($contoh !== []) {
                    $writer->addRow(Row::fromValues($contoh));
                }
                $writer->close();

                return response()->download($tmp, $filename)->deleteFileAfterSend();
            });
    }

    /**
     * Bangun Action import siap pakai untuk header halaman List.
     *
     * @param  ImportConfig  $config
     */
    /**
     * Saklar kebijakan per tenant (KebijakanRegistry) untuk fitur impor opsional.
     * Baca TOLERAN (bool / "1"/"true"/"ya" / bungkus ['value'=>..]); default mati.
     */
    protected static function importSettingAktif(string $key): bool
    {
        $raw = Setting::getTenantValue($key, false);

        if (is_array($raw)) {
            $raw = $raw['value'] ?? $raw['text'] ?? (count($raw) === 1 ? reset($raw) : null);
        }
        if (is_bool($raw)) {
            return $raw;
        }
        if (is_int($raw) || is_float($raw)) {
            return (int) $raw !== 0;
        }
        if (is_string($raw)) {
            return in_array(strtolower(trim($raw)), ['true', '1', 'on', 'ya', 'aktif'], true);
        }

        return false;
    }

    protected function buildImportAction(array $config): Action
    {
        $entitas = $config['entitas'];
        $serviceClass = $config['service'];
        $directory = $config['directory'];
        $ability = $config['ability'] ?? null;
        $enabledSetting = $config['enabledSetting'] ?? null;
        $name = $config['name'] ?? 'import'.str()->studly($entitas);
        $label = $config['label'] ?? 'Import '.ucfirst($entitas);
        $petunjuk = $config['petunjuk']
            ?? 'Unggah file .xlsx / .csv / .ods. Hanya sheet pertama yang dibaca. Baris yang sudah ada akan dilewati (aman diulang).';
        $extraSchema = $config['schema'] ?? [];
        $columns = $config['template'] ?? [];
        $aliases = $config['aliases'] ?? [];

        /** @var callable $invoke */
        $invoke = $config['invoke'] ?? function (object $service, Tenant $tenant, string $path, bool $dryRun, array $data): array {
            return $service->import($tenant, $path, $dryRun);
        };

        /** @var callable|null $extraSummary */
        $extraSummary = $config['extraSummary'] ?? null;

        $fileUpload = FileUpload::make('file')
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
            ->required();

        $schema = [
            Placeholder::make('petunjuk')->label('Petunjuk')->content($petunjuk),
            $fileUpload,
        ];

        // Pemetaan kolom fleksibel (Level 2): muncul bila importer punya kolom
        // kanonik. Saat file diunggah, tebak pemetaan (profil tersimpan / auto),
        // lalu admin bisa sesuaikan. Biarkan kosong = pakai header apa adanya.
        if ($columns !== []) {
            $fileUpload->live()->afterStateUpdated(function (?string $state, callable $set) use ($columns, $aliases, $entitas): void {
                if (blank($state)) {
                    return;
                }
                try {
                    $mapper = app(ImportColumnMapper::class);
                    $headers = $mapper->headers(Storage::disk('local')->path($state));
                    $profil = $mapper->loadProfile($entitas);
                    $auto = $mapper->autoMatch($headers, $columns, $aliases);
                    foreach ($columns as $col) {
                        $pilih = $profil[$col] ?? ($auto[$col] !== '' ? $auto[$col] : null);
                        $set("map.{$col}", $pilih);
                    }
                } catch (\Throwable) {
                    // gagal baca header → biarkan, import jatuh ke alias bawaan
                }
            });

            $mapFields = array_map(
                fn (string $col) => Select::make("map.{$col}")
                    ->label($col)
                    ->options(function (callable $get): array {
                        $f = $get('file');
                        if (blank($f)) {
                            return [];
                        }
                        try {
                            $h = app(ImportColumnMapper::class)->headers(Storage::disk('local')->path($f));

                            return array_combine($h, $h) ?: [];
                        } catch (\Throwable) {
                            return [];
                        }
                    })
                    ->searchable()
                    ->native(false),
                $columns,
            );

            $schema[] = Section::make('Pemetaan kolom (opsional)')
                ->description('Cocokkan kolom file Anda ke field sistem bila namanya berbeda. Biarkan apa adanya jika header sudah sesuai template.')
                ->collapsed()
                ->visible(fn (callable $get): bool => filled($get('file')))
                ->schema([
                    ...$mapFields,
                    Checkbox::make('simpan_profil')
                        ->label('Simpan pemetaan ini untuk impor berikutnya (pesantren ini)'),
                ]);
        }

        $schema = [...$schema, ...$extraSchema, Checkbox::make('dry_run')
            ->label('Validasi dulu (dry-run) — tidak menyimpan ke database')
            ->default(true)];

        return Action::make($name)
            ->label($label)
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->visible(fn (): bool => tenancy()->initialized
                && ($ability === null || (auth()->user()?->can($ability) ?? false))
                && ($enabledSetting === null || static::importSettingAktif($enabledSetting)))
            ->schema($schema)
            ->action(function (array $data) use ($serviceClass, $entitas, $invoke, $extraSummary, $columns, $ability, $enabledSetting): void {
                // Defense-in-depth: tegakkan izin + saklar kebijakan di sisi server (bukan hanya visible()).
                abort_unless($ability === null || (auth()->user()?->can($ability) ?? false), 403);
                abort_unless($enabledSetting === null || static::importSettingAktif($enabledSetting), 403, 'Fitur impor ini dinonaktifkan oleh kebijakan pesantren.');

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

                // Pemetaan kolom: bila admin memilih mapping, normalisasi file
                // dulu agar service importer (tak diubah) menerima header kanonik.
                $tmpNormalized = null;
                $mapping = array_filter($data['map'] ?? [], fn ($v): bool => filled($v));
                if ($columns !== [] && $mapping !== []) {
                    $mapper = app(ImportColumnMapper::class);
                    if (! empty($data['simpan_profil'])) {
                        $mapper->saveProfile($entitas, $data['map']);
                    }
                    $tmpNormalized = $mapper->normalizeToCsv($absolutePath, $columns, $mapping);
                }

                try {
                    $service = app($serviceClass);
                    $summary = $invoke($service, $tenant, $tmpNormalized ?? $absolutePath, $dryRun, $data);
                } finally {
                    Storage::disk('local')->delete($relativePath);
                    if ($tmpNormalized !== null && is_file($tmpNormalized)) {
                        @unlink($tmpNormalized);
                    }
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

                // Laporan kegagalan LENGKAP: untuk file besar (notifikasi hanya
                // memuat 5), tulis semua baris gagal ke CSV agar bisa diunduh &
                // diperbaiki TU. Disimpan via Storage tenancy-aware.
                $semuaError = $summary['errors'] ?? [];
                if (count($semuaError) > 5) {
                    try {
                        $buf = fopen('php://temp', 'r+');
                        fputcsv($buf, ['baris', 'referensi', 'masalah']);
                        foreach ($semuaError as $e) {
                            fputcsv($buf, [$e['row'] ?? '', $e['nis'] ?? $e['ref'] ?? '', $e['error'] ?? '']);
                        }
                        rewind($buf);
                        $isi = (string) stream_get_contents($buf);
                        fclose($buf);
                        $errFile = 'import-errors/'.str()->slug($entitas).'-'.now()->format('Ymd-His').'.csv';
                        Storage::disk('local')->put($errFile, $isi);
                        $body .= "\n\nLaporan lengkap ".count($semuaError).' kegagalan tersimpan: '.basename($errFile);
                    } catch (\Throwable) {
                        // gagal menulis laporan tak boleh menggagalkan import
                    }
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
