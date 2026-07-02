<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Modules\Kesantrian\Models\Santri;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Patch field alamat terurai + NIK/NISN ke santri yang sudah ada.
 * Dipakai setelah migrasi dari Excel sumber yang punya kolom alamat detail.
 *
 * Usage:
 *   php artisan santri:patch-address {tenant} {file.xlsx}
 *   php artisan santri:patch-address asy-syifaa /app/storage/app/data-lengkap-santri.xlsx --force
 */
class SantriPatchAddressCommand extends Command
{
    protected $signature = 'santri:patch-address
        {tenant : ID tenant (mis. asy-syifaa)}
        {file   : Path ke file Excel (harus punya header di baris pertama)}
        {--force : Timpa nilai yang sudah ada (default: hanya isi yang masih kosong)}
        {--dry-run : Preview saja, tidak menyimpan}';

    protected $description = 'Patch field alamat detail + NIK/NISN santri dari Excel sumber';

    // Mapping: field DB → [alias header yang dinormalisasi]
    private const FIELD_ALIASES = [
        'nik' => ['nik', 'nomer_induk_kependudukan_(nik)', 'nomer_induk_kependudukan'],
        'nisn' => ['nisn', 'nomer_induk_siswa_nasional'],
        'rt_rw' => ['rt_rw_santri', 'rt_rw'],
        'desa_kelurahan' => ['desa_kelurahan_santri', 'desa_kelurahan'],
        'kecamatan' => ['kecamatan_santri', 'kecamatan'],
        'kabupaten_kota' => ['kabupaten_kota_santri', 'kabupaten_kota'],
        'provinsi' => ['provinsi_santri', 'provinsi'],
        'kode_pos' => ['kode_pos_santri', 'kode_pos'],
        'rt_rw_wali' => ['rt_rw_wali'],
        'desa_kelurahan_wali' => ['desa_kelurahan_wali'],
        'kecamatan_wali' => ['kecamatan_wali'],
        'kabupaten_kota_wali' => ['kabupaten_kota_wali'],
        'provinsi_wali' => ['provinsi_wali'],
        'kode_pos_wali' => ['kode_pos_wali'],
    ];

    public function handle(): int
    {
        $tenantId = $this->argument('tenant');
        $filePath = $this->argument('file');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            $this->error("Tenant tidak ditemukan: {$tenantId}");

            return self::FAILURE;
        }

        if (! is_file($filePath)) {
            $this->error("File tidak ditemukan: {$filePath}");

            return self::FAILURE;
        }

        $this->info("Tenant   : {$tenantId}");
        $this->info("File     : {$filePath}");
        $this->info('Force    : '.($force ? 'ya (timpa nilai yang ada)' : 'tidak (hanya isi yang kosong)'));
        $this->info('Dry-run  : '.($dryRun ? 'ya' : 'tidak'));
        $this->newLine();

        // Baca Excel
        $reader = new XlsxReader;
        $reader->open($filePath);

        $headerMap = null; // [dbField => colIndex]
        $nisCol = null;
        $updated = 0;
        $skipped = 0;
        $notFound = 0;
        $rowNum = 0;

        tenancy()->initialize($tenant);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rowNum++;
                    $cells = $row->getCells();
                    $values = array_map(fn ($c) => trim((string) $c->getValue()), $cells);

                    // Baris pertama = header
                    if ($headerMap === null) {
                        $headerMap = $this->buildHeaderMap($values);
                        $nisCol = $this->findNisCol($values);

                        if ($nisCol === null) {
                            $this->error('Kolom NIS tidak ditemukan di header baris 1.');

                            return self::FAILURE;
                        }

                        $detected = array_keys($headerMap);
                        $this->line('Field terdeteksi: '.implode(', ', $detected));
                        $this->newLine();

                        continue;
                    }

                    $nis = trim($values[$nisCol] ?? '');
                    if (! preg_match('/^\d{6,10}$/', $nis)) {
                        continue;
                    }

                    // Ambil nilai baru dari Excel
                    $patch = [];
                    foreach ($headerMap as $field => $colIdx) {
                        $val = trim($values[$colIdx] ?? '');
                        if ($val !== '' && ! str_starts_with($val, '=')) {
                            $patch[$field] = $val;
                        }
                    }

                    if (empty($patch)) {
                        continue;
                    }

                    $santri = Santri::withoutGlobalScopes()
                        ->where('tenant_id', $tenantId)
                        ->where('nis', $nis)
                        ->first();

                    if (! $santri) {
                        $notFound++;

                        continue;
                    }

                    // Hanya update field yang kosong (kecuali --force)
                    $toUpdate = [];
                    foreach ($patch as $field => $val) {
                        if ($force || ($santri->$field === null || $santri->$field === '')) {
                            $toUpdate[$field] = $val;
                        }
                    }

                    if (empty($toUpdate)) {
                        $skipped++;

                        continue;
                    }

                    if (! $dryRun) {
                        $santri->update($toUpdate);
                    }

                    $updated++;
                }

                break; // hanya sheet pertama
            }
        } finally {
            $reader->close();
            tenancy()->end();
        }

        $this->info($dryRun ? '=== DRY-RUN (tidak ada yang disimpan) ===' : '=== SELESAI ===');
        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Diupdate', $updated],
                ['Skip (sudah terisi)', $skipped],
                ['NIS tidak ditemukan', $notFound],
                ['Total baris diproses', $rowNum - 1],
            ]
        );

        return self::SUCCESS;
    }

    /** Bangun map: dbField → kolom index */
    private function buildHeaderMap(array $headerValues): array
    {
        $map = [];

        foreach ($headerValues as $idx => $raw) {
            $normalized = $this->normalizeHeader($raw);

            foreach (self::FIELD_ALIASES as $field => $aliases) {
                if (in_array($normalized, $aliases, true) && ! isset($map[$field])) {
                    $map[$field] = $idx;
                    break;
                }
            }
        }

        return $map;
    }

    private function findNisCol(array $headerValues): ?int
    {
        foreach ($headerValues as $idx => $raw) {
            if ($this->normalizeHeader($raw) === 'nis') {
                return $idx;
            }
        }

        return null;
    }

    private function normalizeHeader(string $value): string
    {
        return str_replace(
            ' ', '_',
            trim(preg_replace('/\s+/', ' ',
                str_replace(['.', '-', '/', '\\'], ' ',
                    strtolower($value)
                )
            ))
        );
    }
}
