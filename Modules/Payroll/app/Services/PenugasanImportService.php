<?php

namespace Modules\Payroll\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Validator;
use Modules\Akademik\Models\Lembaga;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\Jabatan;
use Modules\Payroll\Models\PenugasanPegawai;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Import Penugasan Pegawai (SK jabatan) dari spreadsheet — pola importer standar.
 * Tautkan ke pegawai (NIP), jabatan (nama, wajib), lembaga (nama, opsional).
 * Idempoten: penugasan (pegawai+jabatan+lembaga) yang sudah ada dilewati.
 *
 * Penulisan dibungkus try/catch PER BARIS: ada partial-unique "satu jabatan utama
 * aktif per pegawai" + CHECK tanggal — pelanggaran jadi kegagalan baris (dicatat),
 * bukan meng-crash/menyisakan import setengah jadi.
 *
 * Kolom: nip* | jabatan* | lembaga | tanggal_mulai* | nomor_sk | is_primary | keterangan
 */
class PenugasanImportService
{
    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'nip' => ['nip', 'nomor_induk_pegawai', 'no_pegawai'],
        'jabatan' => ['jabatan', 'posisi'],
        'lembaga' => ['lembaga', 'unit', 'nama_lembaga'],
        'tanggal_mulai' => ['tanggal_mulai', 'tmt', 'mulai', 'tgl_mulai'],
        'nomor_sk' => ['nomor_sk', 'no_sk', 'sk'],
        'is_primary' => ['is_primary', 'utama', 'jabatan_utama', 'primary'],
        'keterangan' => ['keterangan', 'catatan'],
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
                        'nip' => ['required', 'string', 'max:40'],
                        'jabatan' => ['required', 'string', 'max:150'],
                        'tanggal_mulai' => ['required', 'date'],
                    ]);
                    if ($validator->fails()) {
                        $this->gagal($summary, $rowNumber, $payload, $validator->errors()->first());

                        continue;
                    }

                    $nip = trim((string) $payload['nip']);
                    $employee = Employee::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('nip', $nip)->first();
                    if (! $employee) {
                        $this->gagal($summary, $rowNumber, $payload, "NIP {$nip} tidak ditemukan — import pegawai dulu.");

                        continue;
                    }

                    $jabatan = Jabatan::withoutGlobalScopes()->where('tenant_id', $tenant->id)
                        ->whereRaw('LOWER(nama) = ?', [mb_strtolower(trim((string) $payload['jabatan']))])->first();
                    if (! $jabatan) {
                        $this->gagal($summary, $rowNumber, $payload, "Jabatan '{$payload['jabatan']}' tidak ditemukan.");

                        continue;
                    }

                    $lembagaId = null;
                    if (isset($payload['lembaga']) && trim((string) $payload['lembaga']) !== '') {
                        $lembagaId = Lembaga::withoutGlobalScopes()->where('tenant_id', $tenant->id)
                            ->whereRaw('LOWER(nama) = ?', [mb_strtolower(trim((string) $payload['lembaga']))])->value('id');
                    }

                    // Idempoten: penugasan sama (pegawai+jabatan+lembaga) sudah ada.
                    $ada = PenugasanPegawai::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('employee_id', $employee->getKey())
                        ->where('jabatan_id', $jabatan->getKey())
                        ->when($lembagaId !== null, fn ($q) => $q->where('lembaga_id', $lembagaId))
                        ->when($lembagaId === null, fn ($q) => $q->whereNull('lembaga_id'))
                        ->exists();
                    if ($ada) {
                        $summary['skipped']++;

                        continue;
                    }

                    if (! $dryRun) {
                        try {
                            PenugasanPegawai::create([
                                'tenant_id' => $tenant->id,
                                'employee_id' => $employee->getKey(),
                                'jabatan_id' => $jabatan->getKey(),
                                'lembaga_id' => $lembagaId,
                                'tanggal_mulai' => $payload['tanggal_mulai'],
                                'nomor_sk' => isset($payload['nomor_sk']) ? (string) $payload['nomor_sk'] : null,
                                'is_primary' => $this->boolean($payload['is_primary'] ?? null),
                                'keterangan' => isset($payload['keterangan']) ? (string) $payload['keterangan'] : null,
                            ]);
                        } catch (\Throwable $e) {
                            // mis. partial-unique "satu jabatan utama aktif per pegawai".
                            $this->gagal($summary, $rowNumber, $payload, 'Ditolak: '.$e->getMessage());

                            continue;
                        }
                    }

                    $summary['imported']++;
                }
            }
        } finally {
            $reader->close();
        }

        return $summary;
    }

    private function boolean(mixed $v): bool
    {
        return in_array(mb_strtolower(trim((string) $v)), ['1', 'true', 'ya', 'yes', 'y', 'utama'], true);
    }

    /** @param array<string, mixed> $payload */
    private function gagal(array &$summary, int $rowNumber, array $payload, string $pesan): void
    {
        $summary['failed']++;
        $summary['errors'][] = ['row' => $rowNumber, 'ref' => isset($payload['nip']) ? (string) $payload['nip'] : null, 'error' => $pesan];
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
            $value = $values[$index] ?? null;
            if ($value instanceof \DateTimeInterface) {
                $payload[$key] = \Carbon\Carbon::instance($value)->toDateString();

                continue;
            }
            $teks = trim((string) $value);
            if ($teks !== '') {
                $payload[$key] = $teks;
            }
        }

        return $payload;
    }
}
