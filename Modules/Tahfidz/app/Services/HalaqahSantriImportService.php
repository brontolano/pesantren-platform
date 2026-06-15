<?php

namespace Modules\Tahfidz\Services;

use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Modules\Kesantrian\Models\Santri;
use Modules\Tahfidz\Models\Halaqah;
use Modules\Tahfidz\Models\HalaqahSantri;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Import KEANGGOTAAN halaqah (santri ↔ halaqah) — melengkapi import master
 * Halaqah (HalaqahImportService) yang hanya membuat halaqah-nya, bukan isinya.
 *
 * Idempoten & TIDAK merusak: bila santri sudah jadi anggota AKTIF (left_at
 * NULL) halaqah yang sama → dilewati. TIDAK menutup keanggotaan lain (pindah
 * halaqah adalah keputusan manual, bukan efek samping import). Unique parsial
 * DB (halaqah_id, santri_id WHERE left_at IS NULL) jadi jaring pengaman akhir.
 *
 * Kolom: nis* | halaqah* (nama halaqah) | tanggal_gabung (opsional, default hari ini)
 */
class HalaqahSantriImportService
{
    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'nis' => ['nis', 'nomor_induk_santri', 'no_induk'],
        'halaqah' => ['halaqah', 'nama_halaqah', 'kelompok', 'kelas_halaqah'],
        'tanggal_gabung' => ['tanggal_gabung', 'joined_at', 'tgl_gabung', 'tanggal_masuk', 'mulai'],
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

        // Cache resolusi nama halaqah → id (banyak baris berbagi halaqah sama).
        $halaqahCache = [];
        // Pasangan (nis|halaqah_id) yang sudah diproses dalam file ini (anti dobel intra-file).
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

                    $validator = Validator::make($payload, [
                        'nis' => ['required', 'string', 'max:30'],
                        'halaqah' => ['required', 'string', 'max:150'],
                        'tanggal_gabung' => ['nullable', 'date'],
                    ]);
                    if ($validator->fails()) {
                        $this->gagal($summary, $rowNumber, $payload, $validator->errors()->first());

                        continue;
                    }

                    $nis = trim((string) $payload['nis']);
                    $namaHalaqah = trim((string) $payload['halaqah']);
                    $kunciHalaqah = mb_strtolower($namaHalaqah);

                    // Resolusi halaqah (cache).
                    if (! array_key_exists($kunciHalaqah, $halaqahCache)) {
                        $halaqahCache[$kunciHalaqah] = Halaqah::withoutGlobalScopes()
                            ->where('tenant_id', $tenant->id)
                            ->whereRaw('LOWER(nama) = ?', [$kunciHalaqah])
                            ->value('id');
                    }
                    $halaqahId = $halaqahCache[$kunciHalaqah];
                    if ($halaqahId === null) {
                        $this->gagal($summary, $rowNumber, $payload, "Halaqah '{$namaHalaqah}' tidak ditemukan — import master halaqah dulu.");

                        continue;
                    }

                    $santri = Santri::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('nis', $nis)
                        ->first();
                    if (! $santri) {
                        $this->gagal($summary, $rowNumber, $payload, "NIS {$nis} tidak ditemukan — import santri dulu.");

                        continue;
                    }

                    $pasanganKey = $nis.'|'.$halaqahId;
                    if (isset($pasanganFileIni[$pasanganKey])) {
                        $summary['skipped']++;

                        continue;
                    }
                    $pasanganFileIni[$pasanganKey] = true;

                    // Idempoten: sudah anggota AKTIF halaqah ini → lewati.
                    $sudahAktif = HalaqahSantri::query()
                        ->where('tenant_id', $tenant->id)
                        ->where('halaqah_id', $halaqahId)
                        ->where('santri_id', $santri->id)
                        ->whereNull('left_at')
                        ->exists();
                    if ($sudahAktif) {
                        $summary['skipped']++;

                        continue;
                    }

                    $joinedAt = ! empty($payload['tanggal_gabung'])
                        ? Carbon::parse((string) $payload['tanggal_gabung'])->toDateString()
                        : Carbon::now()->toDateString();

                    if (! $dryRun) {
                        try {
                            HalaqahSantri::create([
                                'tenant_id' => $tenant->id,
                                'halaqah_id' => $halaqahId,
                                'santri_id' => $santri->id,
                                'joined_at' => $joinedAt,
                            ]);
                        } catch (QueryException $e) {
                            // 23505: balapan dengan unique parsial → anggap sudah ada.
                            if ($e->getCode() === '23505') {
                                $summary['skipped']++;

                                continue;
                            }
                            throw $e;
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
            $teks = trim((string) ($values[$index] ?? ''));
            if ($teks !== '') {
                $payload[$key] = $teks;
            }
        }

        return $payload;
    }
}
