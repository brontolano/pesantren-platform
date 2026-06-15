<?php

namespace Modules\Akademik\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Modules\Akademik\Models\Pelajaran;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Import master Pelajaran dari spreadsheet (xlsx/csv/ods) — pola sama dengan
 * SantriImportService/AsramaImportService: alias header, validasi per baris,
 * dry-run, ringkasan + laporan. Data datar (tanpa FK wajib).
 *
 * Idempoten: pelajaran dengan KODE sama (atau NAMA sama bila kode kosong)
 * dilewati — aman dijalankan ulang. Guru pengampu opsional (dicari via
 * email/nama user; tak ketemu → dibiarkan kosong, baris tetap masuk).
 *
 * Kolom: nama* | kode | sks | guru
 */
class PelajaranImportService
{
    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'nama' => ['nama', 'nama_pelajaran', 'mata_pelajaran', 'mapel', 'pelajaran'],
        'kode' => ['kode', 'kode_pelajaran', 'kode_mapel'],
        'sks' => ['sks', 'jam', 'jam_pelajaran', 'bobot'],
        'guru' => ['guru', 'guru_pengampu', 'pengampu', 'ustadz', 'email_guru'],
    ];

    /**
     * @return array{
     *   tenant_id:string, source:string, dry_run:bool,
     *   imported:int, skipped:int, failed:int,
     *   errors:list<array{row:int, ref:?string, error:string}>
     * }
     */
    public function import(Tenant $tenant, string $path, bool $dryRun = false): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("File import tidak ditemukan: {$path}");
        }

        $reader = ReaderFactory::createFromFile($path);
        $reader->open($path);

        $headers = null;
        $summary = [
            'tenant_id' => $tenant->id,
            'source' => $path,
            'dry_run' => $dryRun,
            'imported' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $dibuatFileIni = []; // key dedup (kode|nama) dalam file ini, juga utk dry-run

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
                        'kode' => ['nullable', 'string', 'max:50'],
                        'sks' => ['nullable', 'integer', 'min:0', 'max:100'],
                    ]);

                    if ($validator->fails()) {
                        $this->gagal($summary, $rowNumber, $payload, $validator->errors()->first());

                        continue;
                    }

                    $kode = isset($payload['kode']) ? trim((string) $payload['kode']) : '';
                    $nama = trim((string) $payload['nama']);
                    $kunci = $kode !== '' ? 'kode:'.mb_strtolower($kode) : 'nama:'.mb_strtolower($nama);

                    if (isset($dibuatFileIni[$kunci])) {
                        $summary['skipped']++;

                        continue;
                    }

                    // Idempoten: sudah ada di DB → lewati.
                    $adaQuery = Pelajaran::withoutGlobalScopes()->where('tenant_id', $tenant->id);
                    $kode !== ''
                        ? $adaQuery->whereRaw('LOWER(kode) = ?', [mb_strtolower($kode)])
                        : $adaQuery->whereRaw('LOWER(nama) = ?', [mb_strtolower($nama)]);

                    if ($adaQuery->exists()) {
                        $dibuatFileIni[$kunci] = true;
                        $summary['skipped']++;

                        continue;
                    }

                    $guruId = $this->resolveGuru($tenant, $payload['guru'] ?? null);

                    if (! $dryRun) {
                        $atribut = [
                            'tenant_id' => $tenant->id,
                            'nama' => $nama,
                            'kode' => $kode !== '' ? $kode : null,
                            'guru_pengampu_id' => $guruId,
                        ];
                        // Hanya set sks bila diisi — biarkan default kolom (1) berlaku.
                        if (isset($payload['sks'])) {
                            $atribut['sks'] = (int) $payload['sks'];
                        }
                        Pelajaran::create($atribut);
                    }

                    $dibuatFileIni[$kunci] = true;
                    $summary['imported']++;
                }
            }
        } finally {
            $reader->close();
        }

        return $summary;
    }

    private function resolveGuru(Tenant $tenant, ?string $nilai): ?int
    {
        $teks = trim((string) $nilai);
        if ($teks === '') {
            return null;
        }

        return User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where(function ($q) use ($teks): void {
                $q->whereRaw('LOWER(email) = ?', [mb_strtolower($teks)])
                    ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($teks)]);
            })
            ->value('id');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function gagal(array &$summary, int $rowNumber, array $payload, string $pesan): void
    {
        $summary['failed']++;
        $summary['errors'][] = [
            'row' => $rowNumber,
            'ref' => isset($payload['nama']) ? (string) $payload['nama'] : null,
            'error' => $pesan,
        ];
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
