<?php

namespace Modules\Payroll\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Modules\Payroll\Models\Employee;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Import master Pegawai/SDM dari spreadsheet (xlsx/csv/ods) — pola importer
 * standar (alias header, validasi per baris, dry-run, ringkasan + laporan).
 *
 * Idempoten: pegawai dengan NIP yang sudah ada dilewati (unik tenant+nip).
 * Akun login PTK opsional: bila kolom `email` cocok User → ditautkan (users_id).
 * Jabatan = string bebas (master Jabatan terpisah sudah di-seed provisioner;
 * importer tak menyentuhnya agar tak tumpang tindih).
 *
 * Kolom: nip* | nama* | jabatan | status_kepegawaian | nik | nuptk | tanggal_masuk | email
 */
class PegawaiImportService
{
    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'nip' => ['nip', 'nomor_induk_pegawai', 'nipy', 'no_pegawai'],
        'nama' => ['nama', 'nama_pegawai', 'nama_lengkap', 'nama_guru'],
        'jabatan' => ['jabatan', 'posisi', 'tugas'],
        'status_kepegawaian' => ['status_kepegawaian', 'status', 'status_pegawai'],
        'nik' => ['nik', 'no_ktp', 'nomor_ktp'],
        'nuptk' => ['nuptk'],
        'tanggal_masuk' => ['tanggal_masuk', 'tgl_masuk', 'tmt', 'mulai_tugas'],
        'email' => ['email', 'email_pegawai', 'email_guru'],
    ];

    private const STATUS_VALID = ['tetap', 'honorer', 'kontrak'];

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

        $nipFileIni = [];

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

                    if (isset($payload['status_kepegawaian'])) {
                        $payload['status_kepegawaian'] = mb_strtolower(trim((string) $payload['status_kepegawaian']));
                    }

                    $validator = Validator::make($payload, [
                        'nip' => ['required', 'string', 'max:40'],
                        'nama' => ['required', 'string', 'max:255'],
                        'status_kepegawaian' => ['nullable', 'in:'.implode(',', self::STATUS_VALID)],
                        'email' => ['nullable', 'email'],
                    ]);

                    if ($validator->fails()) {
                        $this->gagal($summary, $rowNumber, $payload, $validator->errors()->first());

                        continue;
                    }

                    $nip = trim((string) $payload['nip']);
                    $kunci = mb_strtolower($nip);

                    if (isset($nipFileIni[$kunci])) {
                        $summary['skipped']++;

                        continue;
                    }

                    $ada = Employee::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('nip', $nip)
                        ->exists();

                    if ($ada) {
                        $nipFileIni[$kunci] = true;
                        $summary['skipped']++;

                        continue;
                    }

                    if (! $dryRun) {
                        $atribut = [
                            'tenant_id' => $tenant->id,
                            'nip' => $nip,
                            'nama' => trim((string) $payload['nama']),
                            'jabatan' => isset($payload['jabatan']) ? trim((string) $payload['jabatan']) : null,
                            'nik' => isset($payload['nik']) ? trim((string) $payload['nik']) : null,
                            'nuptk' => isset($payload['nuptk']) ? trim((string) $payload['nuptk']) : null,
                            'users_id' => $this->resolveUser($tenant, $payload['email'] ?? null),
                        ];
                        if (isset($payload['status_kepegawaian'])) {
                            $atribut['status_kepegawaian'] = $payload['status_kepegawaian'];
                        }
                        if (isset($payload['tanggal_masuk'])) {
                            $atribut['tanggal_masuk'] = $payload['tanggal_masuk'];
                        }
                        Employee::create($atribut);
                    }

                    $nipFileIni[$kunci] = true;
                    $summary['imported']++;
                }
            }
        } finally {
            $reader->close();
        }

        return $summary;
    }

    private function resolveUser(Tenant $tenant, ?string $email): ?int
    {
        $teks = trim((string) $email);
        if ($teks === '') {
            return null;
        }

        return User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($teks)])
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
            'ref' => isset($payload['nip']) ? (string) $payload['nip'] : null,
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
