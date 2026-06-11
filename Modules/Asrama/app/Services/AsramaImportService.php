<?php

namespace Modules\Asrama\Services;

use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Modules\Asrama\Models\BlokAsrama;
use Modules\Asrama\Models\KamarAsrama;
use Modules\Kesantrian\Models\Santri;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Import penempatan asrama dari spreadsheet (xlsx/csv/ods) — pola sama
 * dengan SantriImportService: alias header, validasi per baris, dry-run,
 * ringkasan + laporan. Blok & kamar dibuat otomatis bila belum ada;
 * penempatan LEWAT AsramaService::assignSantriToKamar (gerbang tulis
 * tunggal: cek tenant, kapasitas, tutup penempatan lama).
 *
 * Kolom: nis* | blok* | kamar* | kapasitas | gender_blok | tanggal_masuk | keterangan
 */
class AsramaImportService
{
    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'nis' => ['nis', 'nomor_induk_santri', 'nomor induk santri'],
        'nama' => ['nama', 'nama_santri', 'nama santri'], // opsional, hanya utk laporan
        'blok' => ['blok', 'nama_blok', 'nama blok', 'asrama', 'gedung'],
        'kamar' => ['kamar', 'nomor_kamar', 'nomor kamar', 'no_kamar', 'no kamar'],
        'kapasitas' => ['kapasitas', 'kapasitas_kamar', 'daya_tampung'],
        'gender_blok' => ['gender_blok', 'gender', 'jenis_blok', 'putra_putri'],
        'tanggal_masuk' => ['tanggal_masuk', 'tanggal masuk', 'tgl_masuk', 'mulai'],
        'keterangan' => ['keterangan', 'catatan'],
    ];

    private const KAPASITAS_DEFAULT = 8;

    public function __construct(
        private readonly AsramaService $asramaService = new AsramaService(),
    ) {
    }

    /**
     * @return array{
     *   tenant_id:string, source:string, dry_run:bool,
     *   imported:int, skipped:int, failed:int,
     *   created_blok:int, created_kamar:int,
     *   errors:list<array{row:int,nis:?string,error:string}>
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
            'created_blok' => 0,
            'created_kamar' => 0,
            'errors' => [],
        ];

        // Simulasi dry-run: lacak blok/kamar "akan dibuat" + okupansi berjalan
        // agar kapasitas tetap terjaga walau tanpa menulis ke DB.
        $blokBaru = [];   // namaBlok => true
        $kamarBaru = [];  // "blok|kamar" => kapasitas
        $okupansi = [];   // "blok|kamar" => terisi (termasuk baris file ini)
        $santriDitempatkan = []; // nis => row (cegah dobel di file yang sama)

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
                        'nis' => ['required', 'string', 'max:20'],
                        'blok' => ['required', 'string', 'max:100'],
                        'kamar' => ['required', 'string', 'max:50'],
                        'kapasitas' => ['nullable', 'integer', 'min:1', 'max:200'],
                        'gender_blok' => ['nullable', 'in:putra,putri,campur,L,P'],
                        'tanggal_masuk' => ['nullable', 'date'],
                    ]);

                    if ($validator->fails()) {
                        $this->gagal($summary, $rowNumber, $payload, $validator->errors()->first());
                        continue;
                    }

                    if (isset($santriDitempatkan[$payload['nis']])) {
                        $this->gagal($summary, $rowNumber, $payload, sprintf(
                            'NIS dobel di file (sudah ditempatkan baris %d).',
                            $santriDitempatkan[$payload['nis']],
                        ));
                        continue;
                    }

                    $santri = Santri::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('nis', $payload['nis'])
                        ->first();

                    if (! $santri) {
                        $this->gagal($summary, $rowNumber, $payload, 'NIS tidak ditemukan pada tenant ini — import santri dulu.');
                        continue;
                    }

                    $namaBlok = trim($payload['blok']);
                    $nomorKamar = trim($payload['kamar']);
                    $kunci = mb_strtolower($namaBlok).'|'.mb_strtolower($nomorKamar);
                    $kapasitas = (int) ($payload['kapasitas'] ?? self::KAPASITAS_DEFAULT);

                    // --- Blok: pakai yang ada / buat baru ---
                    $blok = BlokAsrama::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->whereRaw('LOWER(nama_blok) = ?', [mb_strtolower($namaBlok)])
                        ->first();

                    if (! $blok && ! isset($blokBaru[mb_strtolower($namaBlok)])) {
                        $summary['created_blok']++;
                        $blokBaru[mb_strtolower($namaBlok)] = true;
                        if (! $dryRun) {
                            $blok = BlokAsrama::create([
                                'tenant_id' => $tenant->id,
                                'nama_blok' => $namaBlok,
                                'gender' => $this->normalisasiGender($payload['gender_blok'] ?? null),
                                'is_active' => true,
                            ]);
                        }
                    }

                    // --- Kamar: pakai yang ada / buat baru ---
                    $kamar = $blok
                        ? KamarAsrama::withoutGlobalScopes()
                            ->where('tenant_id', $tenant->id)
                            ->where('blok_id', $blok->getKey())
                            ->whereRaw('LOWER(nomor_kamar) = ?', [mb_strtolower($nomorKamar)])
                            ->first()
                        : null;

                    if (! $kamar && ! isset($kamarBaru[$kunci])) {
                        $summary['created_kamar']++;
                        $kamarBaru[$kunci] = $kapasitas;
                        if (! $dryRun && $blok) {
                            $kamar = KamarAsrama::create([
                                'tenant_id' => $tenant->id,
                                'blok_id' => $blok->getKey(),
                                'nomor_kamar' => $nomorKamar,
                                'kapasitas' => $kapasitas,
                                'is_active' => true,
                            ]);
                        }
                    }

                    // --- Guard kapasitas ---
                    // Non-dry-run: DB sudah mencatat penempatan baris sebelumnya,
                    // jadi cukup hitung DB. Dry-run: DB tak berubah → pakai tracker.
                    $kapasitasEfektif = $kamar?->kapasitas ?? $kamarBaru[$kunci] ?? $kapasitas;
                    $terisiDb = $kamar ? $kamar->penempatanAktif()->count() : 0;
                    $terisiFile = $dryRun ? ($okupansi[$kunci] ?? 0) : 0;

                    if ($terisiDb + $terisiFile >= $kapasitasEfektif) {
                        $this->gagal($summary, $rowNumber, $payload, sprintf(
                            'Kamar %s/%s penuh (kapasitas %d, terisi %d).',
                            $namaBlok, $nomorKamar, $kapasitasEfektif, $terisiDb + $terisiFile,
                        ));
                        continue;
                    }

                    // --- Penempatan via gerbang tulis tunggal ---
                    if (! $dryRun) {
                        try {
                            $this->asramaService->assignSantriToKamar($santri, $kamar, [
                                'tanggal_masuk' => $this->tanggal($payload['tanggal_masuk'] ?? null),
                                'keterangan' => $payload['keterangan'] ?? 'Import migrasi',
                            ]);
                        } catch (\Throwable $e) {
                            $this->gagal($summary, $rowNumber, $payload, $e->getMessage());
                            continue;
                        }
                    }

                    $okupansi[$kunci] = $terisiFile + 1;
                    $santriDitempatkan[$payload['nis']] = $rowNumber;
                    $summary['imported']++;
                }

                break; // hanya sheet pertama
            }
        } finally {
            $reader->close();
        }

        if ($headers === null) {
            throw new RuntimeException('File tidak memiliki baris header yang dikenali.');
        }

        return $summary;
    }

    /** @param array<string, mixed> $payload */
    private function gagal(array &$summary, int $row, array $payload, string $pesan): void
    {
        $summary['failed']++;
        $summary['errors'][] = [
            'row' => $row,
            'nis' => isset($payload['nis']) ? (string) $payload['nis'] : null,
            'error' => $pesan,
        ];
    }

    private function normalisasiGender(?string $nilai): ?string
    {
        return match (mb_strtolower(trim((string) $nilai))) {
            'putra', 'l', 'laki-laki' => 'putra',
            'putri', 'p', 'perempuan' => 'putri',
            'campur' => 'campur',
            default => null,
        };
    }

    private function tanggal(mixed $nilai): string
    {
        if ($nilai instanceof \DateTimeInterface) {
            return Carbon::instance($nilai)->toDateString();
        }

        $teks = trim((string) $nilai);

        if ($teks === '') {
            return now()->toDateString();
        }

        try {
            return Carbon::parse($teks)->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    /** @return list<mixed> */
    private function rowValues(Row $row): array
    {
        return array_map(
            fn ($cell) => $cell?->getValue(),
            $row->getCells(),
        );
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
     * @return array<int, string> indeks kolom => key kanonik
     */
    private function mapHeaders(array $values): array
    {
        $map = [];

        foreach ($values as $index => $value) {
            $header = mb_strtolower(trim((string) $value));
            $header = str_replace([' ', '-'], '_', $header);

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
                $payload[$key] = $value;
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
