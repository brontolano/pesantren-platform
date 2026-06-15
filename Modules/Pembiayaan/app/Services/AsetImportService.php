<?php

namespace Modules\Pembiayaan\Services;

use App\Models\Tenant;
use App\Support\Uang;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Modules\Pembiayaan\Models\Aset;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Import master/register Aset tetap (pembiayaan) — pola importer standar.
 *
 * Idempoten & TIDAK merusak: aset yang sudah ada DILEWATI (skip-if-exists,
 * bukan updateOrCreate) — kunci = nomor_sertifikat bila ada, selain itu
 * nama_aset+tanggal_perolehan (per tenant). Membuat aset TIDAK memicu jurnal
 * Ledger maupun penyusutan — posting hanya lewat DepresiasiService/disposal
 * yang dipanggil eksplisit. Invarian wakaf (metode='none') ditegakkan model.
 *
 * Nilai uang ("nilai", "akumulasi_penyusutan") menerima format Indonesia
 * ("150.000.000"). Kolom:
 * nama_aset* | jenis | kategori | tanggal_perolehan | nilai | lokasi |
 * nomor_sertifikat | is_wakaf | metode_penyusutan | umur_manfaat_bulan | akumulasi_penyusutan
 */
class AsetImportService
{
    private const METODE_VALID = ['none', 'garis_lurus', 'saldo_menurun'];

    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'nama_aset' => ['nama_aset', 'nama', 'aset', 'nama_barang', 'barang'],
        'jenis' => ['jenis', 'tipe'],
        'kategori' => ['kategori', 'kelompok'],
        'tanggal_perolehan' => ['tanggal_perolehan', 'tgl_perolehan', 'tanggal_beli', 'tgl_beli', 'perolehan'],
        'nilai' => ['nilai', 'harga', 'harga_perolehan', 'nilai_perolehan', 'nilai_aset'],
        'lokasi' => ['lokasi', 'tempat', 'penempatan'],
        'nomor_sertifikat' => ['nomor_sertifikat', 'no_sertifikat', 'sertifikat', 'no_bpkb', 'bpkb'],
        'is_wakaf' => ['is_wakaf', 'wakaf', 'status_wakaf'],
        'metode_penyusutan' => ['metode_penyusutan', 'metode', 'penyusutan', 'depresiasi'],
        'umur_manfaat_bulan' => ['umur_manfaat_bulan', 'umur_manfaat', 'umur_bulan', 'masa_manfaat'],
        'akumulasi_penyusutan' => ['akumulasi_penyusutan', 'akumulasi', 'penyusutan_terkumpul'],
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

                    if (isset($payload['metode_penyusutan'])) {
                        $payload['metode_penyusutan'] = str_replace([' ', '-'], '_', mb_strtolower(trim((string) $payload['metode_penyusutan'])));
                    }

                    $validator = Validator::make($payload, [
                        'nama_aset' => ['required', 'string', 'max:255'],
                        'tanggal_perolehan' => ['nullable', 'date'],
                        'umur_manfaat_bulan' => ['nullable', 'integer', 'min:0'],
                        'metode_penyusutan' => ['nullable', 'in:'.implode(',', self::METODE_VALID)],
                    ]);
                    if ($validator->fails()) {
                        $this->gagal($summary, $rowNumber, $payload, $validator->errors()->first());

                        continue;
                    }

                    $nama = trim((string) $payload['nama_aset']);
                    $sertifikat = isset($payload['nomor_sertifikat']) ? trim((string) $payload['nomor_sertifikat']) : '';
                    $tglRaw = isset($payload['tanggal_perolehan']) ? trim((string) $payload['tanggal_perolehan']) : '';
                    $tgl = $tglRaw !== '' ? Carbon::parse($tglRaw)->toDateString() : null;

                    // Kunci dedup/idempotensi: sertifikat bila ada, selain itu nama+tanggal.
                    $kunci = $sertifikat !== ''
                        ? 'srt:'.mb_strtolower($sertifikat)
                        : 'nm:'.mb_strtolower($nama).'|'.($tgl ?? '');
                    if (isset($kunciFileIni[$kunci])) {
                        $summary['skipped']++;

                        continue;
                    }
                    $kunciFileIni[$kunci] = true;

                    $adaQuery = Aset::withoutGlobalScopes()->where('tenant_id', $tenant->id);
                    if ($sertifikat !== '') {
                        $adaQuery->whereRaw('LOWER(nomor_sertifikat) = ?', [mb_strtolower($sertifikat)]);
                    } else {
                        $adaQuery->whereRaw('LOWER(nama_aset) = ?', [mb_strtolower($nama)]);
                        $tgl !== null ? $adaQuery->whereDate('tanggal_perolehan', $tgl) : $adaQuery->whereNull('tanggal_perolehan');
                    }
                    if ($adaQuery->exists()) {
                        $summary['skipped']++;

                        continue;
                    }

                    if (! $dryRun) {
                        $isWakaf = $this->bool($payload['is_wakaf'] ?? null);
                        Aset::create([
                            'tenant_id' => $tenant->id,
                            'nama_aset' => $nama,
                            'jenis' => isset($payload['jenis']) ? trim((string) $payload['jenis']) : null,
                            'kategori' => isset($payload['kategori']) ? trim((string) $payload['kategori']) : null,
                            'tanggal_perolehan' => $tgl,
                            'nilai' => $this->angka($payload['nilai'] ?? null),
                            'lokasi' => isset($payload['lokasi']) ? trim((string) $payload['lokasi']) : null,
                            'nomor_sertifikat' => $sertifikat !== '' ? $sertifikat : null,
                            'is_wakaf' => $isWakaf,
                            // Wakaf dipaksa 'none' oleh model; selain itu pakai input/none.
                            'metode_penyusutan' => $payload['metode_penyusutan'] ?? 'none',
                            'umur_manfaat_bulan' => isset($payload['umur_manfaat_bulan']) ? (int) $payload['umur_manfaat_bulan'] : null,
                            'akumulasi_penyusutan' => $this->angka($payload['akumulasi_penyusutan'] ?? null),
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

    /** Parsing nominal tak ambigu (ribuan/desimal/round-trip) — sumber tunggal. */
    private function angka(mixed $nilai): float
    {
        return Uang::parse($nilai);
    }

    private function bool(mixed $nilai): bool
    {
        $v = mb_strtolower(trim((string) $nilai));

        return in_array($v, ['1', 'true', 'ya', 'wakaf', 'y', 'aktif', 'on'], true);
    }

    /** @param array<string, mixed> $payload */
    private function gagal(array &$summary, int $rowNumber, array $payload, string $pesan): void
    {
        $summary['failed']++;
        $summary['errors'][] = ['row' => $rowNumber, 'ref' => isset($payload['nama_aset']) ? (string) $payload['nama_aset'] : null, 'error' => $pesan];
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
