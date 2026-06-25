<?php

namespace Modules\Kesantrian\Services;

use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

class SantriImportService
{
    /**
     * @var array<string, list<string>>
     */
    private const HEADER_ALIASES = [
        'nis' => ['nis', 'nomor_induk_santri', 'nomor induk santri'],
        'nama' => ['nama', 'nama_santri', 'nama santri', 'nama_lengkap', 'nama lengkap'],
        'tanggal_lahir' => ['tanggal_lahir', 'tanggal lahir', 'tgl_lahir', 'tgl lahir', 'birth_date'],
        'tempat_lahir' => ['tempat_lahir', 'tempat lahir'],
        'jenis_kelamin' => ['jenis_kelamin', 'jenis kelamin', 'jk', 'gender', 'kelamin'],
        'agama' => ['agama'],
        'nik' => ['nik', 'nomer_induk_kependudukan_(nik)', 'nomer_induk_kependudukan', 'nomor_induk_kependudukan'],
        'nisn' => ['nisn', 'nomer_induk_siswa_nasional', 'nomor_induk_siswa_nasional'],
        'asal_sekolah' => ['asal_sekolah', 'asal sekolah', 'sekolah_asal', 'sekolah asal'],
        'alamat' => ['alamat', 'alamat_lengkap', 'alamat lengkap', 'alamat_santri'],
        'rt_rw' => ['rt_rw', 'rt_rw_santri'],
        'desa_kelurahan' => ['desa_kelurahan', 'desa_kelurahan_santri'],
        'kecamatan' => ['kecamatan', 'kecamatan_santri'],
        'kabupaten_kota' => ['kabupaten_kota', 'kabupaten_kota_santri'],
        'provinsi' => ['provinsi', 'provinsi_santri'],
        'kode_pos' => ['kode_pos', 'kode_pos_santri'],
        'no_hp_wali' => ['no_hp_wali', 'no hp wali', 'nomor_wali', 'nomor wali', 'hp_wali', 'wa_wali', 'nomor_telepon_wali', 'nomer_telepon_ayah', 'nomor_telepon_ayah', 'nomor_telepon_ibu'],
        'rt_rw_wali' => ['rt_rw_wali'],
        'desa_kelurahan_wali' => ['desa_kelurahan_wali'],
        'kecamatan_wali' => ['kecamatan_wali'],
        'kabupaten_kota_wali' => ['kabupaten_kota_wali'],
        'provinsi_wali' => ['provinsi_wali'],
        'kode_pos_wali' => ['kode_pos_wali'],
        'email_wali' => ['email_wali', 'email wali'],
        'nama_ayah' => ['nama_ayah', 'nama ayah', 'ayah', 'nama_ayah_kandung', 'nama_ayah_kandung'],
        'nama_ibu' => ['nama_ibu', 'nama ibu', 'ibu', 'nama_ibu_kandung'],
        'pekerjaan_ayah' => ['pekerjaan_ayah', 'pekerjaan ayah'],
        'pekerjaan_ibu' => ['pekerjaan_ibu', 'pekerjaan ibu'],
        'status' => ['status', 'status_santri', 'status santri'],
        'tahun_angkatan' => ['tahun_angkatan', 'tahun angkatan', 'angkatan', 'tahun_masuk', 'tahun masuk'],
        'kelas' => ['kelas', 'nama_kelas', 'nama kelas', 'kelas_awal', 'kelas awal', 'kelas_detail', 'kelas detail'],
        'tingkat' => ['tingkat', 'level'],
        'jurusan' => ['jurusan'],
    ];

    public function __construct(
        private readonly SantriService $santriService = new SantriService,
    ) {}

    /**
     * @return array{
     *   tenant_id:string,
     *   source:string,
     *   imported:int,
     *   skipped:int,
     *   failed:int,
     *   errors:list<array{row:int,nis:?string,nama:?string,error:string}>,
     *   created_kelas:int
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
            'imported' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
            'created_kelas' => 0,
        ];

        $knownKelas = [];

        try {
            foreach ($reader->getSheetIterator() as $sheetIndex => $sheet) {
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

                    if ($this->isEmptyPayload($payload)) {
                        $summary['skipped']++;

                        continue;
                    }

                    $validator = Validator::make($payload, [
                        'nis' => ['required', 'string', 'max:20'],
                        'nama' => ['required', 'string', 'max:255'],
                        'tanggal_lahir' => ['required', 'date'],
                        'jenis_kelamin' => ['required', 'in:L,P'],
                        'status' => ['nullable', 'in:aktif,cuti,lulus,keluar,dikeluarkan'],
                        'tahun_angkatan' => ['nullable', 'string', 'max:9'],
                        'email_wali' => ['nullable', 'email', 'max:255'],
                    ]);

                    if ($validator->fails()) {
                        $summary['failed']++;
                        $summary['errors'][] = [
                            'row' => $rowNumber,
                            'nis' => $payload['nis'] ?? null,
                            'nama' => $payload['nama'] ?? null,
                            'error' => $validator->errors()->first(),
                        ];

                        continue;
                    }

                    if (Santri::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('nis', $payload['nis'])
                        ->exists()) {
                        $summary['skipped']++;
                        $summary['errors'][] = [
                            'row' => $rowNumber,
                            'nis' => $payload['nis'],
                            'nama' => $payload['nama'],
                            'error' => 'NIS sudah ada pada tenant ini.',
                        ];

                        continue;
                    }

                    $kelasId = $this->resolveKelasId($tenant, $payload, $knownKelas, $summary, $dryRun);
                    unset($payload['kelas'], $payload['tingkat'], $payload['jurusan']);

                    $payload['tenant_id'] = $tenant->id;
                    $payload['kelas_id'] = $kelasId;
                    $payload['status'] = $payload['status'] ?? 'aktif';
                    $payload['agama'] = $payload['agama'] ?? 'Islam';

                    if (! $dryRun) {
                        DB::transaction(function () use ($payload): void {
                            $this->santriService->create($payload);
                        });
                    }

                    $summary['imported']++;
                }

                if ($sheetIndex === 0) {
                    break;
                }
            }
        } finally {
            $reader->close();
        }

        return $summary;
    }

    /**
     * @param  list<string>  $values
     * @return array<int, string|null>
     */
    private function mapHeaders(array $values): array
    {
        $mapped = [];

        foreach ($values as $value) {
            $normalized = $this->normalizeHeader($value);
            $resolved = null;

            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (in_array($normalized, $aliases, true)) {
                    $resolved = $field;
                    break;
                }
            }

            $mapped[] = $resolved;
        }

        return $mapped;
    }

    /**
     * @param  array<int, string|null>  $headers
     * @param  list<string>  $values
     * @return array<string, string>
     */
    private function mapRow(array $headers, array $values): array
    {
        $payload = [];

        foreach ($headers as $index => $field) {
            if ($field === null) {
                continue;
            }

            $payload[$field] = trim($values[$index] ?? '');
        }

        if (isset($payload['jenis_kelamin'])) {
            $payload['jenis_kelamin'] = $this->normalizeGender($payload['jenis_kelamin']);
        }

        if (isset($payload['tanggal_lahir'])) {
            $payload['tanggal_lahir'] = $this->normalizeDate($payload['tanggal_lahir']);
        }

        if (isset($payload['status'])) {
            $payload['status'] = $this->normalizeStatus($payload['status']);
        }

        if (isset($payload['tahun_angkatan'])) {
            $payload['tahun_angkatan'] = $this->normalizeAcademicYear($payload['tahun_angkatan']);
        }

        return $payload;
    }

    /**
     * @param  array<string, string>  $payload
     * @param  array<string, int|null>  $knownKelas
     * @param  array{
     *   tenant_id:string,
     *   source:string,
     *   imported:int,
     *   skipped:int,
     *   failed:int,
     *   errors:list<array{row:int,nis:?string,nama:?string,error:string}>,
     *   created_kelas:int
     * }  $summary
     */
    private function resolveKelasId(Tenant $tenant, array $payload, array &$knownKelas, array &$summary, bool $dryRun): ?int
    {
        $kelasName = trim($payload['kelas'] ?? '');

        if ($kelasName === '') {
            return null;
        }

        if (array_key_exists($kelasName, $knownKelas)) {
            return $knownKelas[$kelasName];
        }

        $existing = Kelas::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('nama_kelas', $kelasName)
            ->first();

        if ($existing) {
            return $knownKelas[$kelasName] = $existing->id;
        }

        $tingkat = trim($payload['tingkat'] ?? '');
        $jurusan = trim($payload['jurusan'] ?? '');

        if ($tingkat === '') {
            $tingkat = $this->inferTingkatFromKelas($kelasName);
        }

        if ($dryRun) {
            $summary['created_kelas']++;

            return $knownKelas[$kelasName] = null;
        }

        $kelas = Kelas::create([
            'tenant_id' => $tenant->id,
            'nama_kelas' => $kelasName,
            'tingkat' => $tingkat !== '' ? $tingkat : 'Unknown',
            'jurusan' => $jurusan !== '' ? $jurusan : null,
            'capacity' => 40,
            'is_active' => true,
        ]);

        $summary['created_kelas']++;

        return $knownKelas[$kelasName] = $kelas->id;
    }

    private function inferTingkatFromKelas(string $kelasName): string
    {
        if (preg_match('/\b([0-9]{1,2})\b/', $kelasName, $matches) === 1) {
            return $matches[1];
        }

        $romanMap = [
            'XII' => '12',
            'XI' => '11',
            'X' => '10',
            'IX' => '9',
            'VIII' => '8',
            'VII' => '7',
            'VI' => '6',
            'V' => '5',
            'IV' => '4',
            'III' => '3',
            'II' => '2',
            'I' => '1',
        ];

        foreach ($romanMap as $roman => $arabic) {
            if (preg_match('/\b'.preg_quote($roman, '/').'\b/i', $kelasName) === 1) {
                return $arabic;
            }
        }

        return '';
    }

    private function normalizeGender(string $value): string
    {
        $normalized = Str::upper(trim($value));

        return match ($normalized) {
            'L', 'LK', 'LAKI-LAKI', 'LAKILAKI', 'PUTRA', 'MALE', 'M' => 'L',
            'P', 'PR', 'PEREMPUAN', 'PUTRI', 'FEMALE', 'F' => 'P',
            default => $normalized,
        };
    }

    private function normalizeDate(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return '';
        }

        if (is_numeric($normalized)) {
            $numeric = (float) $normalized;

            if ($numeric > 20000 && $numeric < 60000) {
                return Carbon::create(1899, 12, 30)->addDays((int) floor($numeric))->format('Y-m-d');
            }
        }

        $normalized = str_replace(['.', '\\'], '/', $normalized);

        foreach (['d/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'Y-m-d', 'Y/m/d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $normalized)->format('Y-m-d');
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($normalized)->format('Y-m-d');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function normalizeStatus(string $value): string
    {
        $normalized = Str::lower(trim($value));

        return match ($normalized) {
            'aktif' => 'aktif',
            'cuti' => 'cuti',
            'lulus', 'alumni', 'pengabdian' => 'lulus',
            'keluar', 'dikeluarkan', 'waqof', 'tendik' => 'keluar',
            default => $normalized,
        };
    }

    private function normalizeAcademicYear(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return '';
        }

        if (preg_match('/^\d{4}$/', $normalized) === 1) {
            return $normalized;
        }

        return $normalized;
    }

    private function normalizeHeader(string $value): string
    {
        $normalized = Str::of($value)
            ->lower()
            ->replaceMatches('/^\x{feff}/u', '')
            ->replace(['.', '-', '/', '\\'], ' ')
            ->squish()
            ->replace(' ', '_')
            ->value();

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function rowValues(Row $row): array
    {
        return array_map(
            static fn ($cell): string => trim((string) $cell->getValue()),
            $row->getCells(),
        );
    }

    /**
     * @param  list<string>  $values
     */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function isEmptyPayload(array $payload): bool
    {
        foreach ($payload as $value) {
            if ($value !== '') {
                return false;
            }
        }

        return true;
    }
}
