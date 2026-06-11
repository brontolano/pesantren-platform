<?php

namespace Modules\Keuangan\Services;

use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Keuangan\Models\Tagihan;
use Modules\Kesantrian\Models\Santri;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Import tagihan historis (+ pelunasan) dari spreadsheet — migrasi data
 * Asy-Syifaa dkk. SEMUA penulisan uang LEWAT service resmi:
 * TagihanService::buatTagihanUntukSantri, PembayaranService::inputPembayaran
 * + verifikasiPembayaran (lock + event auto-jurnal idempoten by ref).
 *
 * Kolom: nis* | jenis_tagihan* | nominal* | jatuh_tempo* | diskon |
 *        keterangan | periode_akademik | bulan (YYYY-MM) |
 *        status_bayar (lunas|belum, default belum) | tanggal_pembayaran |
 *        metode | nomor_bukti
 *
 * Idempoten: baris dengan santri+jenis+bulan yang SUDAH punya tagihan
 * dilewati (aman dijalankan ulang). Baris 'lunas' membuat pembayaran
 * senilai nominal_akhir lalu diverifikasi atas nama verifikator.
 */
class KeuanganImportService
{
    /** @var array<string, list<string>> */
    private const HEADER_ALIASES = [
        'nis' => ['nis', 'nomor_induk_santri'],
        'jenis_tagihan' => ['jenis_tagihan', 'jenis tagihan', 'jenis', 'item', 'nama_tagihan'],
        'nominal' => ['nominal', 'jumlah', 'nilai', 'tagihan'],
        'diskon' => ['diskon', 'potongan', 'beasiswa'],
        'jatuh_tempo' => ['jatuh_tempo', 'jatuh tempo', 'tempo', 'due_date'],
        'keterangan' => ['keterangan', 'catatan', 'deskripsi'],
        'periode_akademik' => ['periode_akademik', 'periode akademik', 'tahun_ajaran', 'tahun ajaran'],
        'bulan' => ['bulan', 'bulan_masehi', 'periode', 'bulan_tagihan'],
        'status_bayar' => ['status_bayar', 'status bayar', 'status', 'lunas'],
        'tanggal_pembayaran' => ['tanggal_pembayaran', 'tanggal bayar', 'tgl_bayar', 'tanggal_bayar'],
        'metode' => ['metode', 'metode_pembayaran', 'cara_bayar'],
        'nomor_bukti' => ['nomor_bukti', 'no_bukti', 'no_kwitansi', 'kwitansi'],
    ];

    public function __construct(
        private readonly TagihanService $tagihanService = new TagihanService(),
        private readonly PembayaranService $pembayaranService = new PembayaranService(),
    ) {
    }

    /**
     * @return array{
     *   tenant_id:string, source:string, dry_run:bool,
     *   tagihan_dibuat:int, pembayaran_diverifikasi:int,
     *   skipped:int, failed:int,
     *   total_tagihan:float, total_terbayar:float,
     *   errors:list<array{row:int,nis:?string,error:string}>
     * }
     */
    public function import(Tenant $tenant, string $path, ?int $verifikatorId, bool $dryRun = false): array
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
            'tagihan_dibuat' => 0,
            'pembayaran_diverifikasi' => 0,
            'skipped' => 0,
            'failed' => 0,
            'total_tagihan' => 0.0,
            'total_terbayar' => 0.0,
            'errors' => [],
        ];

        $dibuatFileIni = []; // "santriId|jenis|bulan" → row (dedup dlm file, juga utk dry-run)

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

                    $payload['status_bayar'] = $this->normalisasiStatusBayar($payload['status_bayar'] ?? null);

                    $validator = Validator::make($payload, [
                        'nis' => ['required', 'string', 'max:20'],
                        'jenis_tagihan' => ['required', 'string', 'max:100'],
                        'nominal' => ['required', 'numeric', 'gt:0'],
                        'diskon' => ['nullable', 'numeric', 'gte:0', 'lte:'.(float) ($payload['nominal'] ?? 0)],
                        'jatuh_tempo' => ['required', 'date'],
                        'bulan' => ['nullable', 'date_format:Y-m'],
                        'status_bayar' => ['required', 'in:lunas,belum'],
                        'tanggal_pembayaran' => ['nullable', 'date'],
                    ]);

                    if ($validator->fails()) {
                        $this->gagal($summary, $rowNumber, $payload, $validator->errors()->first());
                        continue;
                    }

                    // Gerbang verifikator berlaku JUGA di dry-run agar pratinjau
                    // akurat (temuan adversarial: dry-run hijau, run nyata gagal).
                    if ($payload['status_bayar'] === 'lunas' && $verifikatorId === null) {
                        $this->gagal($summary, $rowNumber, $payload, 'Baris lunas butuh verifikator (--verifikator=email).');
                        continue;
                    }

                    $santri = Santri::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('nis', $payload['nis'])
                        ->first();

                    if (! $santri) {
                        $this->gagal($summary, $rowNumber, $payload, 'NIS tidak ditemukan pada tenant ini — import santri dulu.');
                        continue;
                    }

                    // Normalisasi jenis: rapikan whitespace internal agar dedup
                    // tidak bocor ('SPP  Bulanan' vs 'SPP Bulanan') — nilai yang
                    // disimpan pun yang ternormalisasi.
                    $payload['jenis_tagihan'] = preg_replace('/\s+/u', ' ', trim($payload['jenis_tagihan']));

                    $bulan = $payload['bulan'] ?? Carbon::parse($payload['jatuh_tempo'])->format('Y-m');
                    $kunci = $santri->getKey().'|'.mb_strtolower($payload['jenis_tagihan']).'|'.$bulan;

                    // Idempoten: sudah ada di file ini ATAU di DB → lewati.
                    if (isset($dibuatFileIni[$kunci])) {
                        $summary['skipped']++;
                        $summary['errors'][] = [
                            'row' => $rowNumber,
                            'nis' => $payload['nis'],
                            'error' => sprintf('Duplikat di file (baris %d) — dilewati.', $dibuatFileIni[$kunci]),
                        ];
                        continue;
                    }

                    $nominal = round((float) $payload['nominal'], 2);
                    $diskon = round((float) ($payload['diskon'] ?? 0), 2);
                    $nominalAkhir = round(max($nominal - $diskon, 0), 2);

                    if ($dryRun) {
                        // Pratinjau: cek exists tanpa lock (cukup utk dry-run).
                        $sudahAda = $this->tagihanAda($tenant->id, $santri->getKey(), $payload['jenis_tagihan'], $bulan, lock: false) !== null;
                        if ($sudahAda) {
                            $summary['skipped']++;
                            $summary['errors'][] = [
                                'row' => $rowNumber,
                                'nis' => $payload['nis'],
                                'error' => "Tagihan {$payload['jenis_tagihan']} {$bulan} sudah ada — dilewati (idempoten).",
                            ];
                            continue;
                        }

                        if ($payload['status_bayar'] === 'lunas' && $nominalAkhir > 0) {
                            $summary['pembayaran_diverifikasi']++;
                            $summary['total_terbayar'] += $nominalAkhir;
                        }
                        $dibuatFileIni[$kunci] = $rowNumber;
                        $summary['tagihan_dibuat']++;
                        $summary['total_tagihan'] += $nominalAkhir;
                        continue;
                    }

                    // === RUN NYATA: SATU transaksi per baris ===
                    // (temuan adversarial) Advisory lock per kunci menutup race
                    // import paralel (exists-then-create TOCTOU); transaksi
                    // membungkus create+bayar+verifikasi sehingga gagal di
                    // tengah = rollback bersih, TIDAK ada tagihan yatim.
                    try {
                        $hasil = DB::transaction(function () use ($tenant, $santri, $payload, $bulan, $kunci, $nominal, $diskon, $nominalAkhir, $verifikatorId): string {
                            // Serialisasi antar-proses utk kunci yang sama
                            // (lepas otomatis saat commit/rollback).
                            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', [$tenant->id.'|'.$kunci]);

                            $tagihan = $this->tagihanAda($tenant->id, $santri->getKey(), $payload['jenis_tagihan'], $bulan, lock: true);

                            if ($tagihan !== null) {
                                // Idempoten + SELF-HEAL: bila run sebelumnya sempat
                                // membuat tagihan tapi pelunasannya gagal, lengkapi
                                // pembayarannya sekarang — jangan dilewati diam-diam.
                                if ($payload['status_bayar'] === 'lunas' && $tagihan->status !== 'lunas' && $nominalAkhir > 0) {
                                    $this->lunasiTagihan($tagihan, $payload, $nominalAkhir, (int) $verifikatorId);

                                    return 'dilengkapi';
                                }

                                return 'sudah-ada';
                            }

                            $tagihan = $this->tagihanService->buatTagihanUntukSantri($santri, [
                                'jenis_tagihan' => $payload['jenis_tagihan'],
                                'nominal' => $nominal,
                                'diskon' => $diskon,
                                'jatuh_tempo' => $payload['jatuh_tempo'],
                                'bulan_masehi' => $bulan,
                                'periode_akademik' => $payload['periode_akademik'] ?? null,
                                'keterangan' => $payload['keterangan'] ?? 'Import migrasi',
                            ]);

                            if ($payload['status_bayar'] === 'lunas' && $nominalAkhir > 0) {
                                $this->lunasiTagihan($tagihan, $payload, $nominalAkhir, (int) $verifikatorId);
                            }

                            return 'dibuat';
                        });
                    } catch (\Throwable $e) {
                        $this->gagal($summary, $rowNumber, $payload, $e->getMessage());
                        continue;
                    }

                    $dibuatFileIni[$kunci] = $rowNumber;

                    if ($hasil === 'sudah-ada') {
                        $summary['skipped']++;
                        $summary['errors'][] = [
                            'row' => $rowNumber,
                            'nis' => $payload['nis'],
                            'error' => "Tagihan {$payload['jenis_tagihan']} {$bulan} sudah ada — dilewati (idempoten).",
                        ];
                        continue;
                    }

                    if ($hasil === 'dilengkapi') {
                        $summary['skipped']++;
                        $summary['pembayaran_diverifikasi']++;
                        $summary['total_terbayar'] += $nominalAkhir;
                        $summary['errors'][] = [
                            'row' => $rowNumber,
                            'nis' => $payload['nis'],
                            'error' => "Tagihan {$payload['jenis_tagihan']} {$bulan} sudah ada — pelunasan DILENGKAPI (self-heal).",
                        ];
                        continue;
                    }

                    if ($payload['status_bayar'] === 'lunas' && $nominalAkhir > 0) {
                        $summary['pembayaran_diverifikasi']++;
                        $summary['total_terbayar'] += $nominalAkhir;
                    }
                    $summary['tagihan_dibuat']++;
                    $summary['total_tagihan'] += $nominalAkhir;
                }

                break; // hanya sheet pertama
            }
        } finally {
            $reader->close();
        }

        if ($headers === null) {
            throw new RuntimeException('File tidak memiliki baris header yang dikenali.');
        }

        $summary['total_tagihan'] = round($summary['total_tagihan'], 2);
        $summary['total_terbayar'] = round($summary['total_terbayar'], 2);

        return $summary;
    }

    /** Cari tagihan kunci (santri+jenis+bulan); lock=true utk run nyata. */
    private function tagihanAda(string $tenantId, int $santriId, string $jenis, string $bulan, bool $lock): ?Tagihan
    {
        $query = Tagihan::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('santri_id', $santriId)
            ->whereRaw('LOWER(jenis_tagihan) = ?', [mb_strtolower($jenis)])
            ->where('bulan_masehi', $bulan);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /** Pelunasan historis via service resmi (input + verifikasi terkunci). */
    private function lunasiTagihan(Tagihan $tagihan, array $payload, float $nominalAkhir, int $verifikatorId): void
    {
        $pembayaran = $this->pembayaranService->inputPembayaran($tagihan, [
            'nominal' => $nominalAkhir,
            'metode' => $payload['metode'] ?? 'tunai',
            'tanggal_pembayaran' => $payload['tanggal_pembayaran'] ?? $payload['jatuh_tempo'],
            'nomor_bukti' => $payload['nomor_bukti'] ?? null,
            'catatan' => 'Import migrasi (historis)',
        ]);

        $this->pembayaranService->verifikasiPembayaran($pembayaran, $verifikatorId);
    }

    /** @param array<string, mixed> $payload */
    private function gagal(array &$summary, int $row, array $payload, string $pesan): void
    {
        $summary['failed']++;
        $summary['errors'][] = [
            'row' => $row,
            'nis' => isset($payload['nis']) ? (string) $payload['nis'] : null,
            'error' => $pesan,
        ];
    }

    private function normalisasiStatusBayar(mixed $nilai): string
    {
        return match (mb_strtolower(trim((string) $nilai))) {
            'lunas', 'paid', 'ya', 'y', '1', 'sudah', 'sudah_bayar' => 'lunas',
            default => 'belum',
        };
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
                $payload[$key] = Carbon::instance($value)->toDateString();
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
