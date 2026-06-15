<?php

namespace Modules\Keuangan\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Validator;
use Modules\Keuangan\Models\TabunganTransaksi;
use Modules\Kesantrian\Models\Santri;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Import SALDO AWAL tabungan santri (skenario go-live tengah tahun).
 *
 * MEREUSE TabunganService::setor (sumber tunggal logika saldo: lock baris,
 * hitung saldo_sebelum/sesudah, auto-jurnal Ledger) — TIDAK menulis transaksi
 * langsung agar tak menduplikasi/mengakali logika uang.
 *
 * Idempoten: tiap santri ditandai satu transaksi pembuka (keterangan diawali
 * MARKER). Bila sudah ada → dilewati, jadi aman dijalankan ulang tanpa dobel.
 *
 * Kolom: nis* | saldo_awal* | keterangan
 */
class TabunganSaldoImportService
{
    private const MARKER = 'Saldo awal (impor migrasi)';

    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'nis' => ['nis', 'nomor_induk_santri'],
        'saldo_awal' => ['saldo_awal', 'saldo', 'saldo_tabungan', 'nominal'],
        'keterangan' => ['keterangan', 'catatan'],
    ];

    public function __construct(
        private readonly TabunganService $tabunganService = new TabunganService(),
    ) {
    }

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

        $nisFileIni = [];

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

                    // saldo_awal TIDAK divalidasi 'numeric' karena boleh berformat
                    // Indonesia ("1.500.000"); parsing & nilai aman ditangani angka().
                    $validator = Validator::make($payload, [
                        'nis' => ['required', 'string', 'max:20'],
                        'saldo_awal' => ['required'],
                    ]);

                    if ($validator->fails()) {
                        $this->gagal($summary, $rowNumber, $payload, $validator->errors()->first());

                        continue;
                    }

                    $nis = trim((string) $payload['nis']);
                    $nominal = $this->angka($payload['saldo_awal'] ?? null);

                    if (isset($nisFileIni[$nis])) {
                        $summary['skipped']++;

                        continue;
                    }
                    $nisFileIni[$nis] = true;

                    if ($nominal <= 0) {
                        // Saldo 0 tak perlu transaksi pembuka.
                        $summary['skipped']++;

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

                    // Idempoten: lewati bila santri sudah punya transaksi pembuka.
                    $tabungan = $this->tabunganService->getOrCreateTabungan($santri);
                    $sudah = TabunganTransaksi::query()
                        ->where('tabungan_id', $tabungan->id)
                        ->where('keterangan', 'like', self::MARKER.'%')
                        ->exists();

                    if ($sudah) {
                        $summary['skipped']++;

                        continue;
                    }

                    if (! $dryRun) {
                        $catatan = isset($payload['keterangan']) && trim((string) $payload['keterangan']) !== ''
                            ? self::MARKER.' — '.trim((string) $payload['keterangan'])
                            : self::MARKER;
                        $this->tabunganService->setor($santri, $nominal, ['keterangan' => $catatan]);
                    }

                    $summary['imported']++;
                }
            }
        } finally {
            $reader->close();
        }

        return $summary;
    }

    /** Parsing nominal tahan format Indonesia ("1.000.000,50") + bulatkan 2 desimal. */
    private function angka(mixed $nilai): float
    {
        $teks = trim((string) $nilai);
        if ($teks === '') {
            return 0.0;
        }
        // Buang pemisah ribuan titik & spasi, jadikan koma desimal → titik.
        $teks = str_replace(['.', ' '], '', $teks);
        $teks = str_replace(',', '.', $teks);

        return is_numeric($teks) ? round((float) $teks, 2) : 0.0;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function gagal(array &$summary, int $rowNumber, array $payload, string $pesan): void
    {
        $summary['failed']++;
        $summary['errors'][] = [
            'row' => $rowNumber,
            'ref' => isset($payload['nis']) ? (string) $payload['nis'] : null,
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
            $teks = trim((string) ($values[$index] ?? ''));
            if ($teks !== '') {
                $payload[$key] = $teks;
            }
        }

        return $payload;
    }
}
