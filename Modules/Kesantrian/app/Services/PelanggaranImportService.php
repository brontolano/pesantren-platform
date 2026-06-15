<?php

namespace Modules\Kesantrian\Services;

use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Modules\Kesantrian\Models\JenisPelanggaran;
use Modules\Kesantrian\Models\PelanggaranSantri;
use Modules\Kesantrian\Models\Santri;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Import riwayat pelanggaran/prestasi santri (DATA HISTORIS, opt-in kebijakan).
 *
 * PENTING — efek samping ditekan: membuat baris LANGSUNG (bukan lewat
 * DisiplinService::catat) sehingga TIDAK mengirim notifikasi wali & TIDAK
 * menerbitkan SP/eskalasi otomatis. Cocok untuk data lama; SP berjalan tetap
 * dikelola lewat alur normal. Idempoten & TIDAK merusak: skip-if-exists pada
 * (santri, jenis, waktu_kejadian).
 *
 * pencatat_id WAJIB (FK users) → user pengimpor. Status default 'selesai'
 * (kasus historis tertutup; poin terhitung sesuai masa berlaku kebijakan).
 *
 * Kolom: nis* | jenis* (nama jenis pelanggaran) | waktu_kejadian* | kronologi | lokasi | status
 */
class PelanggaranImportService
{
    /** Status yang otomatis dianggap terverifikasi (isi verified_at/verifikator). */
    private const STATUS_TERVERIFIKASI = ['diverifikasi', 'sanksi_dijalankan', 'selesai'];

    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'nis' => ['nis', 'nomor_induk_santri', 'no_induk'],
        'jenis' => ['jenis', 'jenis_pelanggaran', 'pelanggaran', 'nama_pelanggaran'],
        'waktu_kejadian' => ['waktu_kejadian', 'tanggal', 'tgl', 'waktu', 'tanggal_kejadian'],
        'kronologi' => ['kronologi', 'keterangan', 'catatan', 'uraian'],
        'lokasi' => ['lokasi', 'tempat'],
        'status' => ['status'],
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
            throw new RuntimeException('Pencatat (pengguna) wajib untuk impor pelanggaran.');
        }

        $reader = ReaderFactory::createFromFile($path);
        $reader->open($path);

        $headers = null;
        $summary = ['tenant_id' => $tenant->id, 'source' => $path, 'dry_run' => $dryRun, 'imported' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];

        $jenisCache = [];
        $tripletFileIni = [];

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
                        'jenis' => ['required', 'string', 'max:255'],
                        'waktu_kejadian' => ['required', 'date'],
                        'status' => ['nullable', 'in:'.implode(',', PelanggaranSantri::STATUS)],
                    ]);
                    if ($validator->fails()) {
                        $this->gagal($summary, $rowNumber, $payload, $validator->errors()->first());

                        continue;
                    }

                    $nis = trim((string) $payload['nis']);
                    $santri = Santri::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)->where('nis', $nis)->first();
                    if (! $santri) {
                        $this->gagal($summary, $rowNumber, $payload, "NIS {$nis} tidak ditemukan — import santri dulu.");

                        continue;
                    }

                    $namaJenis = trim((string) $payload['jenis']);
                    $jKey = mb_strtolower($namaJenis);
                    if (! array_key_exists($jKey, $jenisCache)) {
                        $jenisCache[$jKey] = JenisPelanggaran::withoutGlobalScopes()
                            ->where('tenant_id', $tenant->id)
                            ->whereRaw('LOWER(nama) = ?', [$jKey])->value('id');
                    }
                    $jenisId = $jenisCache[$jKey];
                    if ($jenisId === null) {
                        $this->gagal($summary, $rowNumber, $payload, "Jenis pelanggaran '{$namaJenis}' tidak ditemukan — buat master jenis dulu.");

                        continue;
                    }

                    $waktu = Carbon::parse((string) $payload['waktu_kejadian']);
                    $waktuStr = $waktu->toDateTimeString();

                    $triplet = $santri->id.'|'.$jenisId.'|'.$waktuStr;
                    if (isset($tripletFileIni[$triplet])) {
                        $summary['skipped']++;

                        continue;
                    }
                    $tripletFileIni[$triplet] = true;

                    $sudahAda = PelanggaranSantri::query()
                        ->where('tenant_id', $tenant->id)
                        ->where('santri_id', $santri->id)
                        ->where('jenis_pelanggaran_id', $jenisId)
                        ->where('waktu_kejadian', $waktuStr)
                        ->exists();
                    if ($sudahAda) {
                        $summary['skipped']++;

                        continue;
                    }

                    $status = $payload['status'] ?? 'selesai';
                    $terverifikasi = in_array($status, self::STATUS_TERVERIFIKASI, true);

                    if (! $dryRun) {
                        PelanggaranSantri::create([
                            'tenant_id' => $tenant->id,
                            'santri_id' => $santri->id,
                            'jenis_pelanggaran_id' => $jenisId,
                            'waktu_kejadian' => $waktuStr,
                            'lokasi' => isset($payload['lokasi']) ? trim((string) $payload['lokasi']) : null,
                            'kronologi' => isset($payload['kronologi']) && trim((string) $payload['kronologi']) !== ''
                                ? trim((string) $payload['kronologi'])
                                : '(impor data historis)',
                            'pencatat_id' => $pencatatId,
                            'status' => $status,
                            'verifikator_id' => $terverifikasi ? $pencatatId : null,
                            'verified_at' => $terverifikasi ? $waktuStr : null,
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
