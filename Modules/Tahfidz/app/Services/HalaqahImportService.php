<?php

namespace Modules\Tahfidz\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Modules\Tahfidz\Models\Halaqah;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Import master Halaqah dari spreadsheet — pola importer standar.
 *
 * Musyrif WAJIB (kolom musyrif_id not-null): di-resolve dari User via
 * email/nama; tak ketemu → baris ditolak (buat akun musyrif dulu).
 * Idempoten: halaqah dengan nama yang sudah ada dilewati.
 *
 * Kolom: nama* | musyrif* (email/nama User) | jenis (tahfidz/kitab/diniyah)
 */
class HalaqahImportService
{
    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'nama' => ['nama', 'nama_halaqah', 'halaqah', 'kelompok'],
        'musyrif' => ['musyrif', 'pembimbing', 'ustadz', 'email_musyrif', 'guru'],
        'jenis' => ['jenis', 'tipe', 'kategori'],
    ];

    private const JENIS_VALID = ['tahfidz', 'kitab', 'diniyah'];

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

                    if (isset($payload['jenis'])) {
                        $payload['jenis'] = mb_strtolower(trim((string) $payload['jenis']));
                    }

                    $validator = Validator::make($payload, [
                        'nama' => ['required', 'string', 'max:150'],
                        'musyrif' => ['required', 'string'],
                        'jenis' => ['nullable', 'in:'.implode(',', self::JENIS_VALID)],
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

                    if (Halaqah::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereRaw('LOWER(nama) = ?', [$kunci])->exists()) {
                        $namaFileIni[$kunci] = true;
                        $summary['skipped']++;

                        continue;
                    }

                    $musyrifId = $this->resolveUser($tenant, (string) $payload['musyrif']);
                    if ($musyrifId === null) {
                        $this->gagal($summary, $rowNumber, $payload, "Musyrif '{$payload['musyrif']}' tidak ditemukan — buat akun musyrif dulu.");

                        continue;
                    }

                    if (! $dryRun) {
                        Halaqah::create([
                            'tenant_id' => $tenant->id,
                            'nama' => $nama,
                            'musyrif_id' => $musyrifId,
                            'jenis' => $payload['jenis'] ?? 'tahfidz',
                        ]);
                    }

                    $namaFileIni[$kunci] = true;
                    $summary['imported']++;
                }
            }
        } finally {
            $reader->close();
        }

        return $summary;
    }

    private function resolveUser(Tenant $tenant, string $nilai): ?int
    {
        $teks = mb_strtolower(trim($nilai));

        return $teks === '' ? null : User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where(fn ($q) => $q->whereRaw('LOWER(email) = ?', [$teks])->orWhereRaw('LOWER(name) = ?', [$teks]))
            ->value('id');
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
