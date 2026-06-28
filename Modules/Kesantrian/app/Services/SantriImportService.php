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
        // — Data pribadi —
        'nis' => ['nis', 'nomor_induk_santri', 'nomor induk santri'],
        'nama' => ['nama', 'nama_santri', 'nama santri', 'nama_lengkap', 'nama lengkap'],
        'nama_panggilan' => ['nama_panggilan', 'nama panggilan', 'panggilan', 'nama_alias'],
        'tanggal_lahir' => ['tanggal_lahir', 'tanggal lahir', 'tgl_lahir', 'tgl lahir', 'birth_date'],
        'tempat_lahir' => ['tempat_lahir', 'tempat lahir'],
        'jenis_kelamin' => ['jenis_kelamin', 'jenis kelamin', 'jk', 'gender', 'kelamin'],
        'agama' => ['agama'],
        'nik' => ['nik', 'nomer_induk_kependudukan_(nik)', 'nomer_induk_kependudukan', 'nomor_induk_kependudukan'],
        'nisn' => ['nisn', 'nomer_induk_siswa_nasional', 'nomor_induk_siswa_nasional'],
        'cita_cita' => ['cita_cita', 'cita cita', 'cita2'],
        'anak_ke' => ['anak_ke', 'anak ke', 'anak_keberapa'],
        'jumlah_saudara' => ['jumlah_saudara', 'jumlah saudara', 'jml_saudara', 'jumlah_saudara_kandung'],
        // — Asal sekolah —
        'asal_sekolah' => ['asal_sekolah', 'asal sekolah', 'sekolah_asal', 'sekolah asal'],
        'jenjang_asal' => ['jenjang_asal', 'jenjang asal', 'jenjang_sekolah_asal'],
        'npsn' => ['npsn', 'npsn_asal', 'npsn_sekolah_asal'],
        'tahun_lulus' => ['tahun_lulus', 'tahun lulus', 'tahun_lulus_asal'],
        // — Alamat santri —
        'alamat' => ['alamat', 'alamat_lengkap', 'alamat lengkap', 'alamat_santri'],
        'rt_rw' => ['rt_rw', 'rt_rw_santri'],
        'desa_kelurahan' => ['desa_kelurahan', 'desa_kelurahan_santri'],
        'kecamatan' => ['kecamatan', 'kecamatan_santri'],
        'kabupaten_kota' => ['kabupaten_kota', 'kabupaten_kota_santri'],
        'provinsi' => ['provinsi', 'provinsi_santri'],
        'kode_pos' => ['kode_pos', 'kode_pos_santri'],
        // — Data ayah —
        'nama_ayah' => ['nama_ayah', 'nama ayah', 'ayah', 'nama_ayah_kandung'],
        'nik_ayah' => ['nik_ayah', 'nik ayah'],
        'pendidikan_ayah' => ['pendidikan_ayah', 'pendidikan ayah'],
        'pekerjaan_ayah' => ['pekerjaan_ayah', 'pekerjaan ayah'],
        'penghasilan_ayah' => ['penghasilan_ayah', 'penghasilan ayah'],
        'no_hp_ayah' => ['no_hp_ayah', 'no hp ayah', 'hp_ayah', 'wa_ayah', 'nomor_telepon_ayah', 'nomer_telepon_ayah', 'telepon_ayah'],
        'alamat_ayah' => ['alamat_ayah', 'alamat ayah'],
        'rt_rw_ayah' => ['rt_rw_ayah'],
        'desa_kelurahan_ayah' => ['desa_kelurahan_ayah'],
        'kecamatan_ayah' => ['kecamatan_ayah'],
        'kabupaten_kota_ayah' => ['kabupaten_kota_ayah'],
        'provinsi_ayah' => ['provinsi_ayah'],
        'kode_pos_ayah' => ['kode_pos_ayah'],
        // — Data ibu —
        'nama_ibu' => ['nama_ibu', 'nama ibu', 'ibu', 'nama_ibu_kandung'],
        'nik_ibu' => ['nik_ibu', 'nik ibu'],
        'pendidikan_ibu' => ['pendidikan_ibu', 'pendidikan ibu'],
        'pekerjaan_ibu' => ['pekerjaan_ibu', 'pekerjaan ibu'],
        'penghasilan_ibu' => ['penghasilan_ibu', 'penghasilan ibu'],
        'no_hp_ibu' => ['no_hp_ibu', 'no hp ibu', 'hp_ibu', 'wa_ibu', 'nomor_telepon_ibu', 'nomer_telepon_ibu', 'telepon_ibu'],
        'alamat_ibu' => ['alamat_ibu', 'alamat ibu'],
        'rt_rw_ibu' => ['rt_rw_ibu'],
        'desa_kelurahan_ibu' => ['desa_kelurahan_ibu'],
        'kecamatan_ibu' => ['kecamatan_ibu'],
        'kabupaten_kota_ibu' => ['kabupaten_kota_ibu'],
        'provinsi_ibu' => ['provinsi_ibu'],
        'kode_pos_ibu' => ['kode_pos_ibu'],
        // — Data wali (penanggung jawab + akun portal) —
        'nama_wali' => ['nama_wali', 'nama wali'],
        'hubungan_wali' => ['hubungan_wali', 'hubungan wali', 'status_wali'],
        'pekerjaan_wali' => ['pekerjaan_wali', 'pekerjaan wali'],
        'penghasilan_wali' => ['penghasilan_wali', 'penghasilan wali'],
        'pembiaya' => ['pembiaya', 'penanggung_biaya', 'penanggung biaya'],
        'no_hp_wali' => ['no_hp_wali', 'no hp wali', 'nomor_wali', 'nomor wali', 'hp_wali', 'wa_wali', 'nomor_telepon_wali'],
        'email_wali' => ['email_wali', 'email wali'],
        'alamat_wali' => ['alamat_wali', 'alamat wali'],
        'rt_rw_wali' => ['rt_rw_wali'],
        'desa_kelurahan_wali' => ['desa_kelurahan_wali'],
        'kecamatan_wali' => ['kecamatan_wali'],
        'kabupaten_kota_wali' => ['kabupaten_kota_wali'],
        'provinsi_wali' => ['provinsi_wali'],
        'kode_pos_wali' => ['kode_pos_wali'],
        // — Kesehatan —
        'golongan_darah' => ['golongan_darah', 'golongan darah', 'gol_darah', 'gol darah'],
        'tinggi_badan' => ['tinggi_badan', 'tinggi badan', 'tinggi'],
        'berat_badan' => ['berat_badan', 'berat badan', 'berat'],
        'riwayat_penyakit' => ['riwayat_penyakit', 'riwayat penyakit', 'penyakit'],
        // — Akademik & status —
        'status' => ['status', 'status_santri', 'status santri'],
        'is_mukim' => ['is_mukim', 'mukim', 'is mukim', 'tinggal_di_asrama', 'mondok'],
        'tahun_angkatan' => ['tahun_angkatan', 'tahun angkatan', 'angkatan', 'tahun_masuk', 'tahun masuk'],
        'kelas' => ['kelas', 'nama_kelas', 'nama kelas', 'kelas_awal', 'kelas awal', 'kelas_detail', 'kelas detail'],
        'tingkat' => ['tingkat', 'level'],
        'jurusan' => ['jurusan'],
    ];

    /** @var array<string, int>|null Cache lebar kolom varchar tabel santri. */
    private ?array $columnLimits = null;

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
                    ], [
                        // Pesan RAMAH (sebut kolom + saran) — translasi locale tak
                        // selalu ke-load di konteks import; default 'validation.required'
                        // membingungkan staf. Tiap pesan menyebut KOLOM yang harus diisi.
                        'nis.required' => 'Kolom "nis" wajib diisi.',
                        'nama.required' => 'Kolom "nama" wajib diisi.',
                        'tanggal_lahir.required' => 'Kolom "tanggal_lahir" wajib diisi (format: YYYY-MM-DD).',
                        'tanggal_lahir.date' => 'Kolom "tanggal_lahir" bukan tanggal yang valid (format: YYYY-MM-DD).',
                        'jenis_kelamin.required' => 'Kolom "jenis_kelamin" wajib diisi (L atau P).',
                        'jenis_kelamin.in' => 'Kolom "jenis_kelamin" harus L atau P.',
                        'status.in' => 'Kolom "status" tidak dikenal (aktif/cuti/lulus/keluar/dikeluarkan).',
                        'email_wali.email' => 'Kolom "email_wali" bukan email yang valid.',
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

                    // Jaring pengaman terakhir: potong tiap field ke lebar kolom DB
                    // agar data migrasi "kotor" tak pernah gagal karena overflow.
                    $payload = $this->clampToColumns($payload);

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

        if (isset($payload['is_mukim']) && $payload['is_mukim'] !== '') {
            $payload['is_mukim'] = $this->normalizeBoolean($payload['is_mukim']);
        } else {
            unset($payload['is_mukim']);
        }

        // Nomor HP/telepon → muat varchar(20) (ambil nomor pertama, digit-only).
        foreach (['no_hp_ayah', 'no_hp_ibu', 'no_hp_wali'] as $phoneField) {
            if (isset($payload[$phoneField]) && $payload[$phoneField] !== '') {
                $payload[$phoneField] = $this->normalizePhone($payload[$phoneField]);
            }
        }

        // Nomor identitas → bersihkan artefak float ('....0'), nomor ganda, non-digit.
        foreach (['nik', 'nisn', 'nik_ayah', 'nik_ibu'] as $idField) {
            if (isset($payload[$idField]) && $payload[$idField] !== '') {
                $payload[$idField] = $this->normalizeIdNumber($payload[$idField]);
            }
        }

        // Kode pos → digit saja (buang teks "Tidak tau", range, artefak '.0').
        foreach (['kode_pos', 'kode_pos_ayah', 'kode_pos_ibu', 'kode_pos_wali'] as $kpField) {
            if (isset($payload[$kpField]) && $payload[$kpField] !== '') {
                $payload[$kpField] = $this->normalizeKodePos($payload[$kpField]);
            }
        }

        // RT/RW → format ringkas "rt/rw" dari aneka tulisan ("RT 10 RW 07").
        foreach (['rt_rw', 'rt_rw_ayah', 'rt_rw_ibu', 'rt_rw_wali'] as $rtField) {
            if (isset($payload[$rtField]) && $payload[$rtField] !== '') {
                $payload[$rtField] = $this->normalizeRtRw($payload[$rtField]);
            }
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

    private function normalizeBoolean(string $value): string
    {
        $normalized = Str::lower(trim($value));

        return in_array($normalized, ['1', 'ya', 'y', 'true', 'mukim', 'mondok', 'asrama', 'iya'], true) ? '1' : '0';
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
            static fn ($cell): string => self::cellToString($cell->getValue()),
            $row->getCells(),
        );
    }

    /**
     * Konversi nilai cell Spout ke string yang AMAN, menangani tipe non-string:
     * - DateTimeInterface (cell tanggal Excel) → 'Y-m-d' (cegah fatal cast).
     * - float yang sebenarnya bilangan bulat (NIK/NISN dibaca numerik) → string
     *   integer tanpa suffix '.0' atau notasi ilmiah (mis. 3204360206120003,
     *   bukan 3204360206120003.0 atau 3.2E+15).
     * - selainnya → cast string biasa.
     */
    private static function cellToString(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            // Bilangan bulat besar (NIK 16 digit) → tampilkan utuh tanpa '.0'/E-notasi.
            if (floor($value) === $value && abs($value) < 1.0e+18) {
                return number_format($value, 0, '', '');
            }

            return trim((string) $value);
        }

        return trim((string) $value);
    }

    /**
     * Normalisasi nomor telepon/HP agar muat di kolom varchar(20):
     * - Bila berisi banyak nomor (dipisah '/', ',', ';', spasi, 'atau') → ambil
     *   nomor PERTAMA saja (sisanya tak muat & jarang dipakai untuk WA blast).
     * - Buang semua karakter selain digit dan '+' awalan.
     * - Potong maksimal 20 karakter (lebar kolom).
     */
    private function normalizePhone(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        // Ambil token pertama sebelum pemisah nomor ganda.
        $first = preg_split('/\s*(?:\/|,|;|\bdan\b|\batau\b|\|)\s*/i', $value)[0] ?? $value;
        $first = trim($first);

        // Pertahankan '+' awalan (kode negara), buang sisanya selain digit.
        $plus = str_starts_with($first, '+') ? '+' : '';
        $digits = preg_replace('/\D+/', '', $first) ?? '';

        return mb_substr($plus.$digits, 0, 20);
    }

    /**
     * Normalisasi nomor identitas (NIK/NISN): buang non-digit & sisa '.0'.
     * Tidak memotong panjang (kolom nik/nisn cukup lebar) tapi membersihkan
     * artefak float ('3204360206120003.0' → '3204360206120003').
     */
    private function normalizeIdNumber(string $value): string
    {
        $value = trim($value);

        // Nomor ganda ('0134430640/2019190925') → ambil yang PERTAMA saja.
        $value = preg_split('/\s*(?:\/|,|;|\bdan\b|\batau\b|\|)\s*/i', $value)[0] ?? $value;

        // Buang bagian desimal artefak float ('3204...003.0' → '3204...003')
        // SEBELUM strip non-digit, agar '.0' tak ikut jadi digit '0' ekstra.
        if (str_contains($value, '.')) {
            $value = explode('.', $value)[0];
        }

        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /**
     * Normalisasi kode pos → digit saja (buang teks "Tidak tau", artefak '.0',
     * range "40287-40288"). Hasil non-digit → kosong (lebih baik kosong daripada
     * teks sampah). Pemotongan ke lebar kolom ditangani jaring pengaman clamp.
     */
    private function normalizeKodePos(string $value): string
    {
        if (str_contains($value, '.')) {
            $value = explode('.', $value)[0];
        }

        return preg_replace('/\D+/', '', trim($value)) ?? '';
    }

    /**
     * Normalisasi RT/RW ke format ringkas "rt/rw" dari aneka tulisan:
     * "RT 10 RW 07" → "10/07", "RT 05/RW 09" → "05/09", "005/002" → "005/002".
     * Bila hanya satu angka → angka itu; bila tak ada angka → string asli di-trim
     * (jaring pengaman clamp yang membatasi panjang).
     */
    private function normalizeRtRw(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        preg_match_all('/\d+/', $value, $matches);
        $nums = $matches[0] ?? [];

        if (count($nums) >= 2) {
            return $nums[0].'/'.$nums[1];
        }

        if (count($nums) === 1) {
            return $nums[0];
        }

        return $value;
    }

    /**
     * Lebar maksimum kolom varchar tabel santri (introspeksi sekali, di-cache).
     * Dipakai sebagai jaring pengaman: setiap nilai dipotong ke lebar kolomnya
     * sehingga import data migrasi yang "kotor" tak pernah gagal karena overflow
     * (SQLSTATE 22001). Cocok dengan skema tenant aktif (bukan tebakan hardcode).
     *
     * @return array<string, int>
     */
    private function columnLimits(): array
    {
        if ($this->columnLimits !== null) {
            return $this->columnLimits;
        }

        $limits = [];

        try {
            $rows = DB::select(
                "SELECT column_name, character_maximum_length
                 FROM information_schema.columns
                 WHERE table_name = 'santri'
                   AND data_type = 'character varying'
                   AND character_maximum_length IS NOT NULL"
            );

            foreach ($rows as $row) {
                $limits[$row->column_name] = (int) $row->character_maximum_length;
            }
        } catch (\Throwable) {
            // Gagal introspeksi → map kosong (clamp dilewati, perilaku lama).
        }

        return $this->columnLimits = $limits;
    }

    /**
     * Jaring pengaman: potong tiap field string payload ke lebar kolom DB-nya
     * agar tak pernah memicu SQLSTATE 22001 ("value too long").
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function clampToColumns(array $payload): array
    {
        $limits = $this->columnLimits();

        if ($limits === []) {
            return $payload;
        }

        foreach ($payload as $field => $value) {
            if (is_string($value) && isset($limits[$field]) && mb_strlen($value) > $limits[$field]) {
                $payload[$field] = mb_substr($value, 0, $limits[$field]);
            }
        }

        return $payload;
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
