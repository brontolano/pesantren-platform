<?php

namespace Modules\Akademik\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Validator;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Kesantrian\Models\Kelas;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Import Jadwal Pelajaran dari spreadsheet (xlsx/csv/ods) — pola importer
 * standar (alias header, validasi per baris, dry-run, ringkasan + laporan).
 *
 * Menautkan ke master yang SUDAH ada: kelas (nama_kelas), pelajaran (kode/nama),
 * periode akademik (nama; default ke periode aktif bila kosong). Master tak
 * ketemu → baris ditolak (import pelajaran/kelas/periode dulu).
 *
 * Idempoten: slot identik (kelas+periode+hari+jam_mulai+pelajaran) dilewati.
 * Bentrok: slot sama (kelas+periode+hari+jam_mulai) untuk pelajaran berbeda
 * ditolak agar tidak tabrakan jadwal.
 *
 * Kolom: kelas* | pelajaran* | periode | hari* | jam_mulai* | jam_selesai* | ruangan
 */
class JadwalImportService
{
    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'kelas' => ['kelas', 'nama_kelas', 'rombel'],
        'pelajaran' => ['pelajaran', 'mata_pelajaran', 'mapel', 'kode_pelajaran', 'kode'],
        'periode' => ['periode', 'periode_akademik', 'tahun_ajaran', 'semester'],
        'hari' => ['hari', 'day'],
        'jam_mulai' => ['jam_mulai', 'jam mulai', 'mulai', 'jam_awal', 'start'],
        'jam_selesai' => ['jam_selesai', 'jam selesai', 'selesai', 'jam_akhir', 'end'],
        'ruangan' => ['ruangan', 'ruang', 'kelas_ruang', 'lokasi'],
    ];

    private const HARI_VALID = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'ahad', 'minggu'];

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

        $slotFileIni = []; // "kelasId|periodeId|hari|jam_mulai" => pelajaranId (deteksi bentrok dlm file)

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

                    // Normalisasi hari + jam.
                    if (isset($payload['hari'])) {
                        $payload['hari'] = mb_strtolower(trim((string) $payload['hari']));
                    }
                    foreach (['jam_mulai', 'jam_selesai'] as $k) {
                        if (isset($payload[$k])) {
                            $payload[$k] = $this->normalisasiJam((string) $payload[$k]);
                        }
                    }

                    $validator = Validator::make($payload, [
                        'kelas' => ['required', 'string', 'max:100'],
                        'pelajaran' => ['required', 'string', 'max:150'],
                        'hari' => ['required', 'in:'.implode(',', self::HARI_VALID)],
                        'jam_mulai' => ['required', 'date_format:H:i'],
                        'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
                        'ruangan' => ['nullable', 'string', 'max:100'],
                    ]);

                    if ($validator->fails()) {
                        $this->gagal($summary, $rowNumber, $payload, $validator->errors()->first());

                        continue;
                    }

                    $kelas = Kelas::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->whereRaw('LOWER(nama_kelas) = ?', [mb_strtolower(trim((string) $payload['kelas']))])
                        ->first();

                    if (! $kelas) {
                        $this->gagal($summary, $rowNumber, $payload, "Kelas '{$payload['kelas']}' tidak ditemukan — buat/import kelas dulu.");

                        continue;
                    }

                    $pelajaran = $this->resolvePelajaran($tenant, (string) $payload['pelajaran']);

                    if (! $pelajaran) {
                        $this->gagal($summary, $rowNumber, $payload, "Pelajaran '{$payload['pelajaran']}' tidak ditemukan — import pelajaran dulu.");

                        continue;
                    }

                    $periodeId = $this->resolvePeriode($tenant, $payload['periode'] ?? null);

                    $slotKunci = $kelas->getKey().'|'.($periodeId ?? '0').'|'.$payload['hari'].'|'.$payload['jam_mulai'];

                    // Bentrok dalam file ini.
                    if (isset($slotFileIni[$slotKunci]) && $slotFileIni[$slotKunci] !== $pelajaran->getKey()) {
                        $this->gagal($summary, $rowNumber, $payload, 'Bentrok slot di file (kelas, hari & jam sama untuk pelajaran berbeda).');

                        continue;
                    }

                    $existing = JadwalPelajaran::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('kelas_id', $kelas->getKey())
                        ->where('hari', $payload['hari'])
                        ->where('jam_mulai', $payload['jam_mulai'])
                        ->when($periodeId !== null, fn ($q) => $q->where('periode_akademik_id', $periodeId))
                        ->when($periodeId === null, fn ($q) => $q->whereNull('periode_akademik_id'))
                        ->first();

                    if ($existing) {
                        // Slot sama: idempoten bila pelajaran sama, bentrok bila beda.
                        if ((int) $existing->pelajaran_id === (int) $pelajaran->getKey()) {
                            $slotFileIni[$slotKunci] = $pelajaran->getKey();
                            $summary['skipped']++;

                            continue;
                        }

                        $this->gagal($summary, $rowNumber, $payload, 'Bentrok slot di database (kelas, hari & jam sudah terisi pelajaran lain).');

                        continue;
                    }

                    if (! $dryRun) {
                        JadwalPelajaran::create([
                            'tenant_id' => $tenant->id,
                            'kelas_id' => $kelas->getKey(),
                            'pelajaran_id' => $pelajaran->getKey(),
                            'periode_akademik_id' => $periodeId,
                            'hari' => $payload['hari'],
                            'jam_mulai' => $payload['jam_mulai'],
                            'jam_selesai' => $payload['jam_selesai'],
                            'ruangan' => $payload['ruangan'] ?? null,
                        ]);
                    }

                    $slotFileIni[$slotKunci] = $pelajaran->getKey();
                    $summary['imported']++;
                }
            }
        } finally {
            $reader->close();
        }

        return $summary;
    }

    private function resolvePelajaran(Tenant $tenant, string $nilai): ?Pelajaran
    {
        $teks = mb_strtolower(trim($nilai));

        return Pelajaran::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where(function ($q) use ($teks): void {
                $q->whereRaw('LOWER(kode) = ?', [$teks])
                    ->orWhereRaw('LOWER(nama) = ?', [$teks]);
            })
            ->first();
    }

    private function resolvePeriode(Tenant $tenant, ?string $nilai): ?int
    {
        $teks = trim((string) $nilai);

        if ($teks !== '') {
            $id = PeriodeAkademik::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where(function ($q) use ($teks): void {
                    $q->whereRaw('LOWER(nama) = ?', [mb_strtolower($teks)])
                        ->orWhereRaw('LOWER(tahun_akademik) = ?', [mb_strtolower($teks)]);
                })
                ->value('id');

            if ($id !== null) {
                return (int) $id;
            }
        }

        // Default: periode aktif bila ada.
        $aktif = PeriodeAkademik::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->value('id');

        return $aktif !== null ? (int) $aktif : null;
    }

    private function normalisasiJam(string $nilai): string
    {
        $teks = str_replace('.', ':', trim($nilai));
        // "7:0" / "07:00:00" → H:i
        if (preg_match('/^(\d{1,2}):(\d{1,2})/', $teks, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return $teks;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function gagal(array &$summary, int $rowNumber, array $payload, string $pesan): void
    {
        $summary['failed']++;
        $summary['errors'][] = [
            'row' => $rowNumber,
            'ref' => isset($payload['kelas']) ? (string) $payload['kelas'] : null,
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
                $payload[$key] = $value->format('H:i');

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
