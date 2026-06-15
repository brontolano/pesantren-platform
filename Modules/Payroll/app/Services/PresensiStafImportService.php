<?php

namespace Modules\Payroll\Services;

use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\PresensiStaf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Import presensi staf/pegawai harian (DATA HISTORIS, opt-in kebijakan).
 *
 * CATATAN KEBIJAKAN: presensi staf menjadi sumber agregasi Khidmah → Bisyaroh
 * (payroll). Importer hanya MENULIS baris presensi (tanpa observer/juruh/jurnal
 * saat create); dampak ke bisyaroh muncul bila agregator dijalankan atas periode
 * itu — karena itu fitur ini default mati & dijaga saklar kebijakan.
 *
 * Idempoten & TIDAK merusak: skip-if-exists pada kunci unik (tenant_id,
 * employee_id, tanggal). Pegawai diresolusi via NIP.
 *
 * Kolom: nip* | tanggal* | status (hadir/izin/sakit/alpa/cuti, default hadir) | jam_masuk | jam_keluar | keterangan
 */
class PresensiStafImportService
{
    private const STATUS_VALID = ['hadir', 'izin', 'sakit', 'alpa', 'cuti'];

    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'nip' => ['nip', 'no_induk_pegawai', 'nomor_induk_pegawai'],
        'tanggal' => ['tanggal', 'tgl', 'date'],
        'status' => ['status', 'kehadiran'],
        'jam_masuk' => ['jam_masuk', 'masuk', 'jam_datang'],
        'jam_keluar' => ['jam_keluar', 'keluar', 'jam_pulang'],
        'keterangan' => ['keterangan', 'catatan', 'alasan'],
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

        $empCache = [];
        $pasanganFileIni = [];

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

                    if (isset($payload['status'])) {
                        $payload['status'] = mb_strtolower(trim((string) $payload['status']));
                    }

                    $validator = Validator::make($payload, [
                        'nip' => ['required', 'string', 'max:40'],
                        'tanggal' => ['required', 'date'],
                        'status' => ['nullable', 'in:'.implode(',', self::STATUS_VALID)],
                    ]);
                    if ($validator->fails()) {
                        $this->gagal($summary, $rowNumber, $payload, $validator->errors()->first());

                        continue;
                    }

                    $nip = trim((string) $payload['nip']);
                    $nipKey = mb_strtolower($nip);
                    if (! array_key_exists($nipKey, $empCache)) {
                        $empCache[$nipKey] = Employee::withoutGlobalScopes()
                            ->where('tenant_id', $tenant->id)->where('nip', $nip)->value('id');
                    }
                    $empId = $empCache[$nipKey];
                    if ($empId === null) {
                        $this->gagal($summary, $rowNumber, $payload, "NIP {$nip} tidak ditemukan — import pegawai dulu.");

                        continue;
                    }

                    $tanggal = Carbon::parse((string) $payload['tanggal'])->toDateString();

                    $pasanganKey = $empId.'|'.$tanggal;
                    if (isset($pasanganFileIni[$pasanganKey])) {
                        $summary['skipped']++;

                        continue;
                    }
                    $pasanganFileIni[$pasanganKey] = true;

                    $sudahAda = PresensiStaf::query()
                        ->where('tenant_id', $tenant->id)
                        ->where('employee_id', $empId)
                        ->whereDate('tanggal', $tanggal)
                        ->exists();
                    if ($sudahAda) {
                        $summary['skipped']++;

                        continue;
                    }

                    if (! $dryRun) {
                        PresensiStaf::create([
                            'tenant_id' => $tenant->id,
                            'employee_id' => $empId,
                            'tanggal' => $tanggal,
                            'status' => $payload['status'] ?? 'hadir',
                            'jam_masuk' => $payload['jam_masuk'] ?? null,
                            'jam_keluar' => $payload['jam_keluar'] ?? null,
                            'keterangan' => isset($payload['keterangan']) ? trim((string) $payload['keterangan']) : null,
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
            $teks = trim((string) ($values[$index] ?? ''));
            if ($teks !== '') {
                $payload[$key] = $teks;
            }
        }

        return $payload;
    }
}
