<?php

namespace Modules\Kesantrian\Services;

use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Modules\Kesantrian\Models\PresensiAsrama;
use Modules\Kesantrian\Models\Santri;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Import presensi asrama (kepengasuhan) per slot harian — historis go-live.
 *
 * Idempoten & TIDAK merusak: bila sudah ada (santri, tanggal, slot) → DILEWATI
 * (skip-if-exists pada kunci unik tenant_id+santri_id+tanggal+slot, bukan
 * updateOrCreate). Membuat presensi TIDAK memicu notifikasi/jurnal (model tanpa
 * observer/dispatch). pencatat_id WAJIB (FK users) → diisi user pengimpor.
 *
 * Kolom: nis* | tanggal* | slot* | status (hadir/alpha/izin/sakit/terlambat,
 * default hadir; 'alpa' diterima sbg alias 'alpha') | keterangan
 */
class PresensiAsramaImportService
{
    private const STATUS_VALID = ['hadir', 'alpha', 'izin', 'sakit', 'terlambat'];

    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'nis' => ['nis', 'nomor_induk_santri', 'no_induk'],
        'tanggal' => ['tanggal', 'tgl', 'date'],
        'slot' => ['slot', 'waktu', 'sesi', 'jam'],
        'status' => ['status', 'kehadiran'],
        'keterangan' => ['keterangan', 'catatan', 'alasan'],
    ];

    /**
     * @return array{tenant_id:string, source:string, dry_run:bool, imported:int, skipped:int, failed:int, errors:list<array{row:int, ref:?string, error:string}>}
     */
    public function import(Tenant $tenant, string $path, bool $dryRun = false, ?int $pencatatId = null): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("File import tidak ditemukan: {$path}");
        }
        if (! $dryRun && $pencatatId === null) {
            throw new RuntimeException('Pencatat (pengguna) wajib untuk impor presensi asrama.');
        }

        $reader = ReaderFactory::createFromFile($path);
        $reader->open($path);

        $headers = null;
        $summary = ['tenant_id' => $tenant->id, 'source' => $path, 'dry_run' => $dryRun, 'imported' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];

        $santriCache = [];
        $tripletFileIni = []; // (santri_id|tanggal|slot)

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
                        $s = mb_strtolower(trim((string) $payload['status']));
                        $payload['status'] = $s === 'alpa' ? 'alpha' : $s; // selaraskan dgn domain asrama
                    }

                    $validator = Validator::make($payload, [
                        'nis' => ['required', 'string', 'max:30'],
                        'tanggal' => ['required', 'date'],
                        'slot' => ['required', 'string', 'max:30'],
                        'status' => ['nullable', 'in:'.implode(',', self::STATUS_VALID)],
                    ]);
                    if ($validator->fails()) {
                        $this->gagal($summary, $rowNumber, $payload, $validator->errors()->first());

                        continue;
                    }

                    $nis = trim((string) $payload['nis']);
                    $nisKey = mb_strtolower($nis);
                    if (! array_key_exists($nisKey, $santriCache)) {
                        $santriCache[$nisKey] = Santri::withoutGlobalScopes()
                            ->where('tenant_id', $tenant->id)->where('nis', $nis)->first();
                    }
                    $santri = $santriCache[$nisKey];
                    if (! $santri) {
                        $this->gagal($summary, $rowNumber, $payload, "NIS {$nis} tidak ditemukan — import santri dulu.");

                        continue;
                    }

                    $tanggal = Carbon::parse((string) $payload['tanggal'])->toDateString();
                    $slot = trim((string) $payload['slot']);

                    $triplet = $santri->id.'|'.$tanggal.'|'.mb_strtolower($slot);
                    if (isset($tripletFileIni[$triplet])) {
                        $summary['skipped']++;

                        continue;
                    }
                    $tripletFileIni[$triplet] = true;

                    $sudahAda = PresensiAsrama::query()
                        ->where('tenant_id', $tenant->id)
                        ->where('santri_id', $santri->id)
                        ->whereDate('tanggal', $tanggal)
                        ->where('slot', $slot)
                        ->exists();
                    if ($sudahAda) {
                        $summary['skipped']++;

                        continue;
                    }

                    if (! $dryRun) {
                        PresensiAsrama::create([
                            'tenant_id' => $tenant->id,
                            'santri_id' => $santri->id,
                            'tanggal' => $tanggal,
                            'slot' => $slot,
                            'status' => $payload['status'] ?? 'hadir',
                            'keterangan' => isset($payload['keterangan']) ? trim((string) $payload['keterangan']) : null,
                            'pencatat_id' => $pencatatId,
                            'kamar_id' => $santri->kamar_id ?? null,
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
