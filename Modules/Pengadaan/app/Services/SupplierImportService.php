<?php

namespace Modules\Pengadaan\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Validator;
use Modules\Pengadaan\Models\Supplier;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Import master Supplier/Pemasok (pengadaan) — pola importer standar.
 *
 * Idempoten & TIDAK merusak: supplier dengan nama yang sudah ada (per tenant,
 * case-insensitive) DILEWATI — re-import tak menimpa data yang mungkin sudah
 * diedit TU (skip-if-exists, bukan updateOrCreate). Membuat supplier tidak
 * memicu jurnal/notifikasi (jurnal hanya di alur Belanja terpisah).
 *
 * Kolom: nama* | telepon | alamat | npwp | bank_nama | bank_rekening | kehalalan
 */
class SupplierImportService
{
    private const KEHALALAN_VALID = ['halal', 'syubhat', 'non_halal'];

    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'nama' => ['nama', 'nama_supplier', 'nama_pemasok', 'supplier', 'pemasok', 'vendor'],
        'telepon' => ['telepon', 'telp', 'hp', 'no_hp', 'phone', 'kontak'],
        'alamat' => ['alamat', 'address'],
        'npwp' => ['npwp', 'no_npwp'],
        'bank_nama' => ['bank_nama', 'bank', 'nama_bank'],
        'bank_rekening' => ['bank_rekening', 'rekening', 'no_rekening', 'norek'],
        'kehalalan' => ['kehalalan', 'status_halal', 'halal'],
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
        $namaFileIni = [];

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

                    if (isset($payload['kehalalan'])) {
                        $payload['kehalalan'] = str_replace([' ', '-'], '_', mb_strtolower(trim((string) $payload['kehalalan'])));
                    }

                    $validator = Validator::make($payload, [
                        'nama' => ['required', 'string', 'max:255'],
                        'npwp' => ['nullable', 'string', 'max:30'],
                        'telepon' => ['nullable', 'string', 'max:30'],
                        'bank_nama' => ['nullable', 'string', 'max:60'],
                        'bank_rekening' => ['nullable', 'string', 'max:40'],
                        'kehalalan' => ['nullable', 'in:'.implode(',', self::KEHALALAN_VALID)],
                    ]);
                    if ($validator->fails()) {
                        $this->gagal($summary, $rowNumber, $payload, $validator->errors()->first());

                        continue;
                    }

                    $nama = trim((string) $payload['nama']);
                    $kunci = mb_strtolower($nama);
                    if (isset($namaFileIni[$kunci])) {
                        $summary['skipped']++;

                        continue;
                    }
                    $namaFileIni[$kunci] = true;

                    // Idempoten: supplier dgn nama sama (per tenant) dilewati.
                    if (Supplier::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereRaw('LOWER(nama) = ?', [$kunci])->exists()) {
                        $summary['skipped']++;

                        continue;
                    }

                    if (! $dryRun) {
                        $npwp = isset($payload['npwp']) ? trim((string) $payload['npwp']) : null;
                        Supplier::create([
                            'tenant_id' => $tenant->id,
                            'nama' => $nama,
                            'npwp' => $npwp ?: null,
                            'is_pkp' => ! empty($npwp), // ber-NPWP ⇒ pengusaha kena pajak (sesuai skema)
                            'telepon' => isset($payload['telepon']) ? trim((string) $payload['telepon']) : null,
                            'alamat' => isset($payload['alamat']) ? trim((string) $payload['alamat']) : null,
                            'bank_nama' => isset($payload['bank_nama']) ? trim((string) $payload['bank_nama']) : null,
                            'bank_rekening' => isset($payload['bank_rekening']) ? trim((string) $payload['bank_rekening']) : null,
                            'kehalalan' => $payload['kehalalan'] ?? 'halal',
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
