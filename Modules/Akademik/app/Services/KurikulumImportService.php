<?php

namespace Modules\Akademik\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Validator;
use Modules\Akademik\Models\Kurikulum;
use Modules\Akademik\Models\Lembaga;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Import master Kurikulum dari spreadsheet — pola importer standar.
 * Tautkan ke Lembaga (by nama; wajib karena FK not-null). Idempoten: kurikulum
 * dengan (lembaga, nama) yang sama dilewati (unik tenant+lembaga+nama).
 *
 * Kolom: nama* | lembaga* (nama lembaga) | aktif
 */
class KurikulumImportService
{
    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'nama' => ['nama', 'nama_kurikulum', 'kurikulum'],
        'lembaga' => ['lembaga', 'nama_lembaga', 'unit'],
        'aktif' => ['aktif', 'status', 'is_active'],
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
        $kunciFileIni = [];

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
                        'nama' => ['required', 'string', 'max:150'],
                        'lembaga' => ['required', 'string', 'max:150'],
                    ]);
                    if ($validator->fails()) {
                        $this->gagal($summary, $rowNumber, $payload, $validator->errors()->first());

                        continue;
                    }

                    $nama = trim((string) $payload['nama']);
                    $namaLembaga = trim((string) $payload['lembaga']);

                    $lembaga = Lembaga::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->whereRaw('LOWER(nama) = ?', [mb_strtolower($namaLembaga)])
                        ->first();
                    if (! $lembaga) {
                        $this->gagal($summary, $rowNumber, $payload, "Lembaga '{$namaLembaga}' tidak ditemukan — buat lembaga dulu.");

                        continue;
                    }

                    $kunci = $lembaga->getKey().'|'.mb_strtolower($nama);
                    if (isset($kunciFileIni[$kunci])) {
                        $summary['skipped']++;

                        continue;
                    }

                    $ada = Kurikulum::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('lembaga_id', $lembaga->getKey())
                        ->whereRaw('LOWER(nama) = ?', [mb_strtolower($nama)])
                        ->exists();
                    if ($ada) {
                        $kunciFileIni[$kunci] = true;
                        $summary['skipped']++;

                        continue;
                    }

                    if (! $dryRun) {
                        Kurikulum::create([
                            'tenant_id' => $tenant->id,
                            'lembaga_id' => $lembaga->getKey(),
                            'nama' => $nama,
                        ]);
                    }

                    $kunciFileIni[$kunci] = true;
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
        $summary['errors'][] = ['row' => $rowNumber, 'ref' => isset($payload['nama']) ? (string) $payload['nama'] : null, 'error' => $pesan];
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
