<?php

namespace Modules\Akademik\Services;

use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Modules\Akademik\Models\KalenderAkademik;
use Modules\Akademik\Models\Lembaga;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Import entri kalender akademik massal (opt-in kebijakan) — libur pondok,
 * ujian, pasaran, kegiatan khusus. Libur nasional sudah di-seed otomatis.
 *
 * Idempoten & TIDAK merusak: skip-if-exists pada (tenant, tanggal_mulai,
 * keterangan) — selaras kunci idempoten KalenderLiburNasionalSeeder. Tanpa efek
 * samping (tanpa observer/notifikasi/jurnal). lembaga opsional (kosong = semua).
 *
 * Kolom: tanggal_mulai* | tanggal_selesai | jenis | keterangan* | lembaga
 */
class KalenderImportService
{
    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'tanggal_mulai' => ['tanggal_mulai', 'mulai', 'tgl_mulai', 'tanggal', 'dari'],
        'tanggal_selesai' => ['tanggal_selesai', 'selesai', 'tgl_selesai', 'sampai', 'hingga'],
        'jenis' => ['jenis', 'tipe', 'kategori'],
        'keterangan' => ['keterangan', 'nama', 'acara', 'deskripsi', 'catatan'],
        'lembaga' => ['lembaga', 'nama_lembaga', 'unit'],
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

        $lembagaCache = [];
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

                    if (isset($payload['jenis'])) {
                        $payload['jenis'] = str_replace([' ', '-'], '_', mb_strtolower(trim((string) $payload['jenis'])));
                    }

                    $validator = Validator::make($payload, [
                        'tanggal_mulai' => ['required', 'date'],
                        'tanggal_selesai' => ['nullable', 'date'],
                        'keterangan' => ['required', 'string', 'max:255'],
                        'jenis' => ['nullable', 'in:'.implode(',', KalenderAkademik::JENIS)],
                    ]);
                    if ($validator->fails()) {
                        $this->gagal($summary, $rowNumber, $payload, $validator->errors()->first());

                        continue;
                    }

                    $mulai = Carbon::parse((string) $payload['tanggal_mulai'])->toDateString();
                    $selesai = ! empty($payload['tanggal_selesai'])
                        ? Carbon::parse((string) $payload['tanggal_selesai'])->toDateString()
                        : $mulai;
                    $keterangan = trim((string) $payload['keterangan']);

                    $kunci = $mulai.'|'.mb_strtolower($keterangan);
                    if (isset($kunciFileIni[$kunci])) {
                        $summary['skipped']++;

                        continue;
                    }
                    $kunciFileIni[$kunci] = true;

                    $sudahAda = KalenderAkademik::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->whereDate('tanggal_mulai', $mulai)
                        ->whereRaw('LOWER(keterangan) = ?', [mb_strtolower($keterangan)])
                        ->exists();
                    if ($sudahAda) {
                        $summary['skipped']++;

                        continue;
                    }

                    // lembaga opsional: resolve by nama bila diberi (kosong = semua lembaga).
                    $lembagaId = null;
                    if (isset($payload['lembaga']) && trim((string) $payload['lembaga']) !== '') {
                        $lName = mb_strtolower(trim((string) $payload['lembaga']));
                        if (! array_key_exists($lName, $lembagaCache)) {
                            $lembagaCache[$lName] = Lembaga::withoutGlobalScopes()
                                ->where('tenant_id', $tenant->id)
                                ->whereRaw('LOWER(nama) = ?', [$lName])->value('id');
                        }
                        $lembagaId = $lembagaCache[$lName];
                    }

                    if (! $dryRun) {
                        KalenderAkademik::create([
                            'tenant_id' => $tenant->id,
                            'lembaga_id' => $lembagaId,
                            'tanggal_mulai' => $mulai,
                            'tanggal_selesai' => $selesai,
                            'jenis' => $payload['jenis'] ?? 'libur_pondok',
                            'keterangan' => $keterangan,
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
        $summary['errors'][] = ['row' => $rowNumber, 'ref' => isset($payload['keterangan']) ? (string) $payload['keterangan'] : null, 'error' => $pesan];
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
