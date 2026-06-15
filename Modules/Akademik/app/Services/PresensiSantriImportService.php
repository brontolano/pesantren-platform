<?php

namespace Modules\Akademik\Services;

use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Modules\Akademik\Models\PresensiSantri;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Import presensi kelas harian santri (historis, skenario go-live tengah tahun).
 *
 * Idempoten & TIDAK merusak: bila sudah ada presensi (santri, tanggal) → DILEWATI
 * (skip-if-exists pada kunci unik tenant_id+santri_id+tanggal — bukan updateOrCreate
 * seperti PresensiSantriService::catat yang menimpa, agar re-import tak mengubah
 * status yang sudah dikoreksi). Membuat presensi TIDAK memicu notifikasi/jurnal
 * (hanya audit_log) — terverifikasi audit. kelas opsional: bila kosong, ikut
 * kelas santri saat ini (selaras catat()).
 *
 * Kolom: nis* | tanggal* | status (hadir/izin/sakit/alpa, default hadir) | kelas | keterangan
 */
class PresensiSantriImportService
{
    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'nis' => ['nis', 'nomor_induk_santri', 'no_induk'],
        'tanggal' => ['tanggal', 'tgl', 'date', 'hari'],
        'status' => ['status', 'kehadiran', 'keterangan_hadir'],
        'kelas' => ['kelas', 'nama_kelas', 'rombel'],
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

        $santriCache = [];   // nis(lower) => Santri|null
        $kelasCache = [];    // nama(lower) => id|null
        $pasanganFileIni = []; // (santri_id|tanggal) anti-dobel intra-file

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
                        'nis' => ['required', 'string', 'max:30'],
                        'tanggal' => ['required', 'date'],
                        'status' => ['nullable', 'in:'.implode(',', PresensiSantri::STATUS)],
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

                    $pasanganKey = $santri->id.'|'.$tanggal;
                    if (isset($pasanganFileIni[$pasanganKey])) {
                        $summary['skipped']++;

                        continue;
                    }
                    $pasanganFileIni[$pasanganKey] = true;

                    // Idempoten: presensi (santri, tanggal) sudah ada → lewati (tak ditimpa).
                    $sudahAda = PresensiSantri::query()
                        ->where('tenant_id', $tenant->id)
                        ->where('santri_id', $santri->id)
                        ->whereDate('tanggal', $tanggal)
                        ->exists();
                    if ($sudahAda) {
                        $summary['skipped']++;

                        continue;
                    }

                    // kelas opsional: resolve by nama bila diberi, selain itu kelas santri.
                    $kelasId = $santri->kelas_id;
                    if (isset($payload['kelas']) && trim((string) $payload['kelas']) !== '') {
                        $kName = mb_strtolower(trim((string) $payload['kelas']));
                        if (! array_key_exists($kName, $kelasCache)) {
                            $kelasCache[$kName] = Kelas::withoutGlobalScopes()
                                ->where('tenant_id', $tenant->id)
                                ->whereRaw('LOWER(nama_kelas) = ?', [$kName])->value('id');
                        }
                        $kelasId = $kelasCache[$kName] ?? $santri->kelas_id;
                    }

                    if (! $dryRun) {
                        PresensiSantri::create([
                            'tenant_id' => $tenant->id,
                            'santri_id' => $santri->id,
                            'kelas_id' => $kelasId,
                            'tanggal' => $tanggal,
                            'status' => $payload['status'] ?? 'hadir',
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
