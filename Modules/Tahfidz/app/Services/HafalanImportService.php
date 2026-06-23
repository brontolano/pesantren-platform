<?php

namespace Modules\Tahfidz\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Validator;
use Modules\Tahfidz\Models\HafalanTahfidz;
use Modules\Tahfidz\Models\Halaqah;
use Modules\Kesantrian\Models\Santri;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Import progres Hafalan dari spreadsheet — pola importer standar.
 *
 * Santri (NIS) wajib & harus ada. Halaqah (nama) opsional → ditautkan bila ada.
 * Idempoten: baris dengan (santri, surah, ayat_awal, ayat_akhir) yang sama
 * dilewati — aman diulang.
 *
 * Kolom: nis* | halaqah | surah | ayat_awal | ayat_akhir | status | tanggal_target | tanggal_selesai | catatan
 */
class HafalanImportService
{
    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'nis' => ['nis', 'nomor_induk_santri'],
        'halaqah' => ['halaqah', 'nama_halaqah', 'kelompok'],
        'surah' => ['surah', 'surat'],
        'ayat_awal' => ['ayat_awal', 'ayat_mulai', 'dari_ayat'],
        'ayat_akhir' => ['ayat_akhir', 'sampai_ayat', 'hingga_ayat'],
        'status' => ['status'],
        'tanggal_target' => ['tanggal_target', 'target'],
        'tanggal_selesai' => ['tanggal_selesai', 'selesai'],
        'catatan' => ['catatan', 'keterangan'],
    ];

    private const STATUS_VALID = ['direncanakan', 'berjalan', 'selesai'];

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
        $kunciFileIni = [];

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
                        'nis' => ['required', 'string', 'max:20'],
                        'ayat_awal' => ['nullable', 'integer', 'min:1'],
                        'ayat_akhir' => ['nullable', 'integer', 'min:1'],
                        'status' => ['nullable', 'in:'.implode(',', self::STATUS_VALID)],
                        'tanggal_target' => ['nullable', 'date'],
                        'tanggal_selesai' => ['nullable', 'date'],
                    ]);
                    if ($validator->fails()) {
                        $this->gagal($summary, $rowNumber, $payload, $validator->errors()->first());

                        continue;
                    }

                    $nis = trim((string) $payload['nis']);
                    $santri = Santri::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('nis', $nis)->first();
                    if (! $santri) {
                        $this->gagal($summary, $rowNumber, $payload, "NIS {$nis} tidak ditemukan — import santri dulu.");

                        continue;
                    }

                    $surah = isset($payload['surah']) ? trim((string) $payload['surah']) : null;
                    $awal = isset($payload['ayat_awal']) ? (int) $payload['ayat_awal'] : null;
                    $akhir = isset($payload['ayat_akhir']) ? (int) $payload['ayat_akhir'] : null;
                    $kunci = $santri->getKey().'|'.mb_strtolower((string) $surah).'|'.$awal.'|'.$akhir;

                    if (isset($kunciFileIni[$kunci])) {
                        $summary['skipped']++;

                        continue;
                    }

                    $ada = HafalanTahfidz::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('santri_id', $santri->getKey())
                        ->where('surah', $surah)
                        ->where('ayat_awal', $awal)
                        ->where('ayat_akhir', $akhir)
                        ->exists();
                    if ($ada) {
                        $kunciFileIni[$kunci] = true;
                        $summary['skipped']++;

                        continue;
                    }

                    if (! $dryRun) {
                        HafalanTahfidz::create([
                            'tenant_id' => $tenant->id,
                            'santri_id' => $santri->getKey(),
                            'halaqah_id' => $this->resolveHalaqah($tenant, $payload['halaqah'] ?? null),
                            'surah' => $surah,
                            'ayat_awal' => $awal,
                            'ayat_akhir' => $akhir,
                            'status' => $payload['status'] ?? 'direncanakan',
                            'tanggal_target' => $payload['tanggal_target'] ?? null,
                            'tanggal_selesai' => $payload['tanggal_selesai'] ?? null,
                            'catatan' => isset($payload['catatan']) ? (string) $payload['catatan'] : null,
                        ]);
                    }

                    $kunciFileIni[$kunci] = true;
                    $summary['imported']++;
                }
            }
        } finally {
            $reader->close();
        }

        return $summary;
    }

    private function resolveHalaqah(Tenant $tenant, ?string $nama): ?int
    {
        $teks = trim((string) $nama);

        return $teks === '' ? null : Halaqah::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereRaw('LOWER(nama) = ?', [mb_strtolower($teks)])
            ->value('id');
    }

    /** @param array<string, mixed> $payload */
    private function gagal(array &$summary, int $rowNumber, array $payload, string $pesan): void
    {
        $summary['failed']++;
        $summary['errors'][] = ['row' => $rowNumber, 'ref' => isset($payload['nis']) ? (string) $payload['nis'] : null, 'error' => $pesan];
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
