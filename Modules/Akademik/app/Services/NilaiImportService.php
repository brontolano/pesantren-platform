<?php

namespace Modules\Akademik\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Validator;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\Pelajaran;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Import Nilai historis dari spreadsheet (xlsx/csv/ods) — pola importer
 * standar. Menautkan ke santri (NIS), pelajaran (kode/nama), periode (nama;
 * default periode aktif) dan kelas (nama_kelas; default kelas santri).
 *
 * Idempoten via kunci unik (santri, pelajaran, periode): baris yang sudah ada
 * DIPERBARUI nilainya (updateOrCreate), bukan dobel — aman diulang & cocok
 * untuk migrasi bertahap. Nilai yang kosong tidak menimpa nilai lama.
 *
 * Kolom: nis* | pelajaran* | periode | kelas | nilai_harian | nilai_uts | nilai_uas | nilai_akhir | catatan
 */
class NilaiImportService
{
    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'nis' => ['nis', 'nomor_induk_santri'],
        'pelajaran' => ['pelajaran', 'mata_pelajaran', 'mapel', 'kode_pelajaran', 'kode'],
        'periode' => ['periode', 'periode_akademik', 'tahun_ajaran', 'semester'],
        'kelas' => ['kelas', 'nama_kelas', 'rombel'],
        'nilai_harian' => ['nilai_harian', 'harian', 'tugas', 'nh'],
        'nilai_uts' => ['nilai_uts', 'uts', 'pts'],
        'nilai_uas' => ['nilai_uas', 'uas', 'pas'],
        'nilai_akhir' => ['nilai_akhir', 'akhir', 'na', 'nilai'],
        'catatan' => ['catatan', 'keterangan', 'deskripsi'],
    ];

    /**
     * @return array{
     *   tenant_id:string, source:string, dry_run:bool,
     *   imported:int, updated:int, skipped:int, failed:int,
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
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $kunciFileIni = []; // "santri|pelajaran|periode" → cegah dobel di file

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
                        'pelajaran' => ['required', 'string', 'max:150'],
                        'nilai_harian' => ['nullable', 'numeric', 'between:0,100'],
                        'nilai_uts' => ['nullable', 'numeric', 'between:0,100'],
                        'nilai_uas' => ['nullable', 'numeric', 'between:0,100'],
                        'nilai_akhir' => ['nullable', 'numeric', 'between:0,100'],
                    ]);

                    if ($validator->fails()) {
                        $this->gagal($summary, $rowNumber, $payload, $validator->errors()->first());

                        continue;
                    }

                    $santri = Santri::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('nis', $payload['nis'])
                        ->first();

                    if (! $santri) {
                        $this->gagal($summary, $rowNumber, $payload, "NIS {$payload['nis']} tidak ditemukan — import santri dulu.");

                        continue;
                    }

                    $pelajaran = $this->resolvePelajaran($tenant, (string) $payload['pelajaran']);

                    if (! $pelajaran) {
                        $this->gagal($summary, $rowNumber, $payload, "Pelajaran '{$payload['pelajaran']}' tidak ditemukan — import pelajaran dulu.");

                        continue;
                    }

                    $periodeId = $this->resolvePeriode($tenant, $payload['periode'] ?? null);

                    if ($periodeId === null) {
                        $this->gagal($summary, $rowNumber, $payload, 'Periode akademik tidak ditemukan & tidak ada periode aktif — set periode dulu.');

                        continue;
                    }

                    $kelasId = $this->resolveKelas($tenant, $payload['kelas'] ?? null, $santri);

                    if ($kelasId === null) {
                        $this->gagal($summary, $rowNumber, $payload, 'Kelas tidak ditemukan & santri belum punya kelas — isi kolom kelas / tempatkan santri dulu.');

                        continue;
                    }

                    $kunci = $santri->getKey().'|'.$pelajaran->getKey().'|'.$periodeId;

                    if (isset($kunciFileIni[$kunci])) {
                        $summary['skipped']++;

                        continue;
                    }
                    $kunciFileIni[$kunci] = true;

                    // Nilai yang diisi saja (jangan timpa nilai lama dgn null).
                    $nilaiBaru = array_filter([
                        'nilai_harian' => $this->angka($payload['nilai_harian'] ?? null),
                        'nilai_uts' => $this->angka($payload['nilai_uts'] ?? null),
                        'nilai_uas' => $this->angka($payload['nilai_uas'] ?? null),
                        'nilai_akhir' => $this->angka($payload['nilai_akhir'] ?? null),
                        'catatan' => isset($payload['catatan']) ? (string) $payload['catatan'] : null,
                    ], fn ($v) => $v !== null);

                    if (! $dryRun) {
                        $nilai = Nilai::withoutGlobalScopes()->firstOrNew([
                            'tenant_id' => $tenant->id,
                            'santri_id' => $santri->getKey(),
                            'pelajaran_id' => $pelajaran->getKey(),
                            'periode_akademik_id' => $periodeId,
                        ]);
                        $baru = ! $nilai->exists;
                        $nilai->kelas_id = $kelasId;
                        $nilai->fill($nilaiBaru);
                        $nilai->save();

                        $baru ? $summary['imported']++ : $summary['updated']++;
                    } else {
                        $ada = Nilai::withoutGlobalScopes()
                            ->where('tenant_id', $tenant->id)
                            ->where('santri_id', $santri->getKey())
                            ->where('pelajaran_id', $pelajaran->getKey())
                            ->where('periode_akademik_id', $periodeId)
                            ->exists();
                        $ada ? $summary['updated']++ : $summary['imported']++;
                    }
                }
            }
        } finally {
            $reader->close();
        }

        return $summary;
    }

    private function angka(mixed $nilai): ?float
    {
        $teks = trim((string) $nilai);

        return $teks === '' ? null : (float) str_replace(',', '.', $teks);
    }

    private function resolvePelajaran(Tenant $tenant, string $nilai): ?Pelajaran
    {
        $teks = mb_strtolower(trim($nilai));

        return Pelajaran::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where(fn ($q) => $q->whereRaw('LOWER(kode) = ?', [$teks])->orWhereRaw('LOWER(nama) = ?', [$teks]))
            ->first();
    }

    private function resolvePeriode(Tenant $tenant, ?string $nilai): ?int
    {
        $teks = trim((string) $nilai);

        if ($teks !== '') {
            $id = PeriodeAkademik::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where(fn ($q) => $q->whereRaw('LOWER(nama) = ?', [mb_strtolower($teks)])->orWhereRaw('LOWER(tahun_akademik) = ?', [mb_strtolower($teks)]))
                ->value('id');

            if ($id !== null) {
                return (int) $id;
            }
        }

        $aktif = PeriodeAkademik::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->value('id');

        return $aktif !== null ? (int) $aktif : null;
    }

    private function resolveKelas(Tenant $tenant, ?string $nilai, Santri $santri): ?int
    {
        $teks = trim((string) $nilai);

        if ($teks !== '') {
            $id = Kelas::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereRaw('LOWER(nama_kelas) = ?', [mb_strtolower($teks)])
                ->value('id');

            if ($id !== null) {
                return (int) $id;
            }
        }

        return $santri->kelas_id !== null ? (int) $santri->kelas_id : null;
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
