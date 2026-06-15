<?php

namespace Modules\Ledger\Services;

use App\Models\Tenant;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Jurnal;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Import SALDO AWAL buku besar (skenario go-live tengah tahun).
 *
 * SELURUH FILE = SATU jurnal pembuka berimbang, diposting LEWAT JurnalService
 * (sumber tunggal: validasi seimbang, idempotensi, kehalalan, periode) — TIDAK
 * menulis jurnal_baris langsung. Gagal-aman: bila ada baris invalid ATAU total
 * debit ≠ kredit, TIDAK ada yang diposting (uang tak boleh setengah jadi).
 *
 * Idempoten: idempotency_key 'saldo-awal:{tenant}' → posting kedua dikembalikan
 * jurnal yang sama (tak ganda). Koreksi setelah posting via reversal, bukan ulang.
 *
 * Kolom: kode_akun* | debit | kredit | keterangan
 */
class LedgerSaldoAwalImportService
{
    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'kode_akun' => ['kode_akun', 'kode', 'akun', 'kode_rekening'],
        'debit' => ['debit', 'debet'],
        'kredit' => ['kredit', 'credit'],
        'keterangan' => ['keterangan', 'memo', 'catatan'],
    ];

    public function __construct(
        private readonly JurnalService $jurnalService = new JurnalService,
    ) {
    }

    /**
     * @return array{
     *   tenant_id:string, source:string, dry_run:bool,
     *   imported:int, skipped:int, failed:int, total:float, seimbang:bool,
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
            'tenant_id' => $tenant->id, 'source' => $path, 'dry_run' => $dryRun,
            'imported' => 0, 'skipped' => 0, 'failed' => 0, 'total' => 0.0, 'seimbang' => false, 'errors' => [],
        ];

        $baris = [];
        $totalDebit = 0.0;
        $totalKredit = 0.0;

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
                        continue;
                    }

                    $kode = isset($payload['kode_akun']) ? trim((string) $payload['kode_akun']) : '';
                    $debit = $this->angka($payload['debit'] ?? null);
                    $kredit = $this->angka($payload['kredit'] ?? null);

                    if ($kode === '') {
                        $this->gagal($summary, $rowNumber, $kode, 'kode_akun wajib diisi.');

                        continue;
                    }
                    if ($debit < 0 || $kredit < 0) {
                        $this->gagal($summary, $rowNumber, $kode, 'Debit/kredit tidak boleh negatif.');

                        continue;
                    }
                    if (($debit > 0) === ($kredit > 0)) {
                        $this->gagal($summary, $rowNumber, $kode, 'Isi SALAH SATU: debit atau kredit (tidak boleh keduanya/kosong).');

                        continue;
                    }

                    $akun = Akun::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('kode', $kode)
                        ->first();
                    if (! $akun) {
                        $this->gagal($summary, $rowNumber, $kode, "Akun '{$kode}' tidak ditemukan di bagan akun.");

                        continue;
                    }

                    $baris[] = [
                        'akun_id' => $akun->getKey(),
                        'debit' => $debit,
                        'kredit' => $kredit,
                        'memo' => isset($payload['keterangan']) ? (string) $payload['keterangan'] : 'Saldo awal',
                    ];
                    $totalDebit += $debit;
                    $totalKredit += $kredit;
                }
            }
        } finally {
            $reader->close();
        }

        $summary['total'] = round($totalDebit, 2);
        $summary['seimbang'] = abs($totalDebit - $totalKredit) < 0.01;

        // Gagal-aman: ada baris invalid → JANGAN posting apa pun.
        if ($summary['failed'] > 0) {
            return $summary;
        }

        if (count($baris) < 2) {
            $this->gagal($summary, 0, null, 'Jurnal pembuka minimal 2 baris (satu debit, satu kredit).');

            return $summary;
        }

        if (! $summary['seimbang']) {
            $this->gagal($summary, 0, null, sprintf(
                'Tidak seimbang: total debit %s ≠ total kredit %s.',
                number_format($totalDebit, 2), number_format($totalKredit, 2),
            ));

            return $summary;
        }

        $key = 'saldo-awal:'.$tenant->id;

        // Idempoten: jurnal pembuka sudah ada → lewati (filter tenant eksplisit, defensif).
        if (Jurnal::where('tenant_id', $tenant->id)->where('idempotency_key', $key)->exists()) {
            $summary['skipped'] = count($baris);

            return $summary;
        }

        if (! $dryRun) {
            try {
                $this->jurnalService->postJurnal([
                    'tanggal' => now()->toDateString(),
                    'deskripsi' => 'Saldo awal (impor migrasi)',
                    'tipe_sumber' => 'saldo_awal',
                    'idempotency_key' => $key,
                    'baris' => $baris,
                ]);
            } catch (\Throwable $e) {
                $this->gagal($summary, 0, null, 'Posting ditolak: '.$e->getMessage());

                return $summary;
            }
        }

        $summary['imported'] = count($baris);

        return $summary;
    }

    private function angka(mixed $nilai): float
    {
        $teks = trim((string) $nilai);
        if ($teks === '') {
            return 0.0;
        }
        // Buang pemisah ribuan titik, jadikan koma desimal → titik.
        $teks = str_replace(['.', ' '], '', $teks);
        $teks = str_replace(',', '.', $teks);

        return is_numeric($teks) ? (float) $teks : 0.0;
    }

    private function gagal(array &$summary, int $rowNumber, ?string $ref, string $pesan): void
    {
        $summary['failed']++;
        $summary['errors'][] = ['row' => $rowNumber, 'ref' => $ref, 'error' => $pesan];
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
