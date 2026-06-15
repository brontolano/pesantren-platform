<?php

namespace Modules\Kesantrian\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Validator;
use Modules\Kesantrian\Models\Alumni;
use Modules\Kesantrian\Models\Santri;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Import data Alumni historis dari spreadsheet — pola importer standar.
 * Tautkan ke santri (NIS). Idempoten: 1 alumni per santri (unik tenant+santri),
 * yang sudah ada dilewati.
 *
 * Kolom: nis* | tahun_lulus* | status_lanjutan | sekolah_tujuan | catatan
 */
class AlumniImportService
{
    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'nis' => ['nis', 'nomor_induk_santri'],
        'tahun_lulus' => ['tahun_lulus', 'tahun', 'lulus', 'angkatan_lulus'],
        'status_lanjutan' => ['status_lanjutan', 'status', 'lanjutan'],
        'sekolah_tujuan' => ['sekolah_tujuan', 'tujuan', 'sekolah'],
        'catatan' => ['catatan', 'keterangan'],
    ];

    /**
     * @return array{tenant_id:string, source:string, dry_run:bool, imported:int, skipped:int, failed:int, errors:list<array{row:int, ref:?string, error:string}>}
     */
    public function import(Tenant $tenant, string $path, bool $dryRun = false): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("File import tidak ditemukan: {$path}");
        }

        $reader = ReaderFactory::createFromFile($path);
        $reader->open($path);

        $headers = null;
        $summary = ['tenant_id' => $tenant->id, 'source' => $path, 'dry_run' => $dryRun, 'imported' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
        $nisFileIni = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                    $values = $this->rowValues($row);
                    if ($this->isEmptyRow($values)) {
                        continue;
                    }
                    if ($headers === null) {
                        $headers = $this->mapHeaders($values);

                        continue;
                    }

                    $rowNumber = $rowIndex + 1;
                    $payload = $this->mapRow($headers, $values);
                    if ($payload === []) {
                        $summary['skipped']++;

                        continue;
                    }

                    $validator = Validator::make($payload, [
                        'nis' => ['required', 'string', 'max:20'],
                        'tahun_lulus' => ['required', 'string', 'max:4'],
                    ]);
                    if ($validator->fails()) {
                        $this->gagal($summary, $rowNumber, $payload, $validator->errors()->first());

                        continue;
                    }

                    $nis = trim((string) $payload['nis']);
                    if (isset($nisFileIni[$nis])) {
                        $summary['skipped']++;

                        continue;
                    }
                    $nisFileIni[$nis] = true;

                    $santri = Santri::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)->where('nis', $nis)->first();
                    if (! $santri) {
                        $this->gagal($summary, $rowNumber, $payload, "NIS {$nis} tidak ditemukan — import santri dulu.");

                        continue;
                    }

                    $ada = Alumni::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)->where('santri_id', $santri->getKey())->exists();
                    if ($ada) {
                        $summary['skipped']++;

                        continue;
                    }

                    if (! $dryRun) {
                        Alumni::create([
                            'tenant_id' => $tenant->id,
                            'santri_id' => $santri->getKey(),
                            'tahun_lulus' => trim((string) $payload['tahun_lulus']),
                            'status_lanjutan' => isset($payload['status_lanjutan']) ? (string) $payload['status_lanjutan'] : null,
                            'sekolah_tujuan' => isset($payload['sekolah_tujuan']) ? (string) $payload['sekolah_tujuan'] : null,
                            'catatan' => isset($payload['catatan']) ? (string) $payload['catatan'] : null,
                        ]);
                    }

                    $summary['imported']++;
                }
            }
        } finally {
            $reader->close();
        }

        return $summary;
    }

    /** @param array<string, mixed> $payload */
    private function gagal(array &$summary, int $rowNumber, array $payload, string $pesan): void
    {
        $summary['failed']++;
        $summary['errors'][] = ['row' => $rowNumber, 'ref' => isset($payload['nis']) ? (string) $payload['nis'] : null, 'error' => $pesan];
    }

    /** @return list<mixed> */
    private function rowValues(Row $row): array
    {
        return array_map(fn ($cell) => $cell?->getValue(), $row->getCells());
    }

    /** @param list<mixed> $values */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<mixed>  $values
     * @return array<int, string>
     */
    private function mapHeaders(array $values): array
    {
        $map = [];
        foreach ($values as $index => $value) {
            $header = str_replace([' ', '-'], '_', mb_strtolower(trim((string) $value)));
            foreach (self::HEADER_ALIASES as $key => $aliases) {
                if (in_array($header, array_map(fn ($a) => str_replace(' ', '_', $a), $aliases), true)) {
                    $map[$index] = $key;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  list<mixed>  $values
     * @return array<string, mixed>
     */
    private function mapRow(array $headers, array $values): array
    {
        $payload = [];
        foreach ($headers as $index => $key) {
            $teks = trim((string) ($values[$index] ?? ''));
            if ($teks !== '') {
                $payload[$key] = $teks;
            }
        }

        return $payload;
    }
}
