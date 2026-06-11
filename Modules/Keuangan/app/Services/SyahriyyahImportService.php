<?php

namespace Modules\Keuangan\Services;

use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tagihan;
use Modules\Kesantrian\Models\Santri;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use RuntimeException;

/**
 * Importer KHUSUS format "LAPORAN KEUANGAN SYAHRIYYAH" Asy-Syifaa:
 * satu sheet per bulan, baris = transaksi pembayaran dengan kolom
 * No | NIS | NAMA | P/L | PEMBAYARAN | CASH | BSI | BCA | LAINNYA | TANGGAL | CATATAN.
 *
 * - PEMBAYARAN = bulan yang DIBAYAR (mis. "Cicilan Agustus 2025") — bisa
 *   beda dari nama sheet; cicilan didukung (pembayaran parsial menumpuk
 *   sampai tagihan lunas via PembayaranService).
 * - Tagihan Syahriyyah dibuat otomatis per (santri, bulan) nominal standar
 *   (default 700rb) bila belum ada — lewat TagihanService.
 * - Uang: SATU transaksi per baris + pg_advisory_xact_lock per kunci
 *   (anti-race), idempoten re-run (dedup pembayaran by tagihan+nominal+
 *   tanggal+marker import).
 * - Sel rusak (formula error di sheet OKT 25 dst.) → baris gagal,
 *   sisanya jalan terus; sheet gagal total dilaporkan.
 */
class SyahriyyahImportService
{
    private const MARKER = 'Import syahriyyah';

    private const BULAN_ID = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
        'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8, 'agus' => 8,
        'september' => 9, 'oktober' => 10, 'november' => 11, 'nopember' => 11,
        'desember' => 12,
    ];

    public function __construct(
        private readonly TagihanService $tagihanService = new TagihanService(),
        private readonly PembayaranService $pembayaranService = new PembayaranService(),
    ) {
    }

    /**
     * @return array{
     *   tenant_id:?string, source:string, mode:string, nominal_standar:float,
     *   sheets:int, baris_dibaca:int, pembayaran_masuk:int, tagihan_dibuat:int,
     *   skipped:int, failed:int, total_terbayar:float,
     *   per_sheet:array<string,int>,
     *   errors:list<array{sheet:string,row:int,nis:?string,error:string}>
     * }
     */
    public function import(
        ?Tenant $tenant,
        string $path,
        ?int $verifikatorId,
        float $nominalStandar = 700000.0,
        bool $dryRun = false,
        bool $parseOnly = false,
    ): array {
        if (! is_file($path)) {
            throw new RuntimeException("File import tidak ditemukan: {$path}");
        }

        if (! $parseOnly && $tenant === null) {
            throw new RuntimeException('Tenant wajib kecuali mode parse-only.');
        }

        if (! $parseOnly && ! $dryRun && $verifikatorId === null) {
            throw new RuntimeException('Run nyata butuh verifikator (--verifikator=email).');
        }

        $reader = ReaderFactory::createFromFile($path);
        $reader->open($path);

        $summary = [
            'tenant_id' => $tenant?->id,
            'source' => $path,
            'mode' => $parseOnly ? 'parse-only' : ($dryRun ? 'dry-run' : 'import'),
            'nominal_standar' => $nominalStandar,
            'sheets' => 0,
            'baris_dibaca' => 0,
            'pembayaran_masuk' => 0,
            'tagihan_dibuat' => 0,
            'skipped' => 0,
            'failed' => 0,
            'total_terbayar' => 0.0,
            'per_sheet' => [],
            'errors' => [],
        ];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $namaSheet = (string) $sheet->getName();
                $summary['sheets']++;
                $summary['per_sheet'][$namaSheet] = 0;

                try {
                    foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                        try {
                            $this->prosesBaris($row, $rowIndex, $namaSheet, $tenant, $verifikatorId, $nominalStandar, $dryRun, $parseOnly, $summary);
                        } catch (\Throwable $e) {
                            // Sel rusak / error tak terduga → baris gagal, lanjut.
                            $summary['failed']++;
                            $summary['errors'][] = [
                                'sheet' => $namaSheet, 'row' => $rowIndex,
                                'nis' => null, 'error' => 'Baris tak terbaca: '.$e->getMessage(),
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    // Sheet rusak total (mis. formula-error parah) → laporkan, lanjut sheet lain.
                    $summary['errors'][] = [
                        'sheet' => $namaSheet, 'row' => 0,
                        'nis' => null, 'error' => 'Sheet gagal dibaca: '.$e->getMessage(),
                    ];
                }
            }
        } finally {
            $reader->close();
        }

        $summary['total_terbayar'] = round($summary['total_terbayar'], 2);

        return $summary;
    }

    private function prosesBaris(
        Row $row,
        int $rowIndex,
        string $namaSheet,
        ?Tenant $tenant,
        ?int $verifikatorId,
        float $nominalStandar,
        bool $dryRun,
        bool $parseOnly,
        array &$summary,
    ): void {
        $v = array_map(fn ($cell) => $cell?->getValue(), $row->getCells());

        // Format kolom tetap: 0=No 1=NIS 2=NAMA 3=P/L 4=PEMBAYARAN
        // 5=CASH 6=BSI 7=BCA 8=LAINNYA 9=TANGGAL 10=CATATAN
        // Sel bisa berisi objek tanggal (baris rekap/rusak) → stringify aman.
        $nis = $this->teks($v[1] ?? null);
        $pembayaranTeks = $this->teks($v[4] ?? null);

        // Lewati header/judul/kosong: NIS harus numerik-ish dan PEMBAYARAN terisi.
        if ($nis === '' || $pembayaranTeks === '' || ! preg_match('/^\d{3,}$/', $nis)) {
            return;
        }

        $summary['baris_dibaca']++;
        $summary['per_sheet'][$namaSheet]++;

        $bulan = $this->parseBulan($pembayaranTeks, $namaSheet);
        if ($bulan === null) {
            $summary['failed']++;
            $summary['errors'][] = [
                'sheet' => $namaSheet, 'row' => $rowIndex, 'nis' => $nis,
                'error' => "Bulan tidak terbaca dari PEMBAYARAN: '{$pembayaranTeks}'",
            ];

            return;
        }

        [$nominal, $metode, $bank] = $this->parseNominalMetode($v);
        if ($nominal <= 0) {
            $summary['failed']++;
            $summary['errors'][] = [
                'sheet' => $namaSheet, 'row' => $rowIndex, 'nis' => $nis,
                'error' => 'Semua kolom nominal (CASH/BSI/BCA/LAINNYA) kosong atau 0.',
            ];

            return;
        }

        $tanggal = $this->parseTanggal($v[9] ?? null, $bulan);
        $catatan = $this->teks($v[10] ?? null);

        if ($parseOnly) {
            $summary['pembayaran_masuk']++;
            $summary['total_terbayar'] += $nominal;

            return;
        }

        $santri = Santri::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('nis', $nis)
            ->first();

        if (! $santri) {
            $summary['failed']++;
            $summary['errors'][] = [
                'sheet' => $namaSheet, 'row' => $rowIndex, 'nis' => $nis,
                'error' => 'NIS tidak ditemukan pada tenant ini.',
            ];

            return;
        }

        if ($dryRun) {
            $summary['pembayaran_masuk']++;
            $summary['total_terbayar'] += $nominal;

            return;
        }

        $kunci = $tenant->id.'|syahriyyah|'.$santri->getKey().'|'.$bulan;
        $catatanImport = self::MARKER.' '.$namaSheet.($catatan !== '' ? ' — '.$catatan : '').($bank !== null ? ' ['.$bank.']' : '');

        $hasil = DB::transaction(function () use ($tenant, $santri, $bulan, $kunci, $nominal, $metode, $tanggal, $catatanImport, $nominalStandar, $verifikatorId): string {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', [$kunci]);

            $tagihan = Tagihan::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('santri_id', $santri->getKey())
                ->whereRaw("LOWER(jenis_tagihan) = 'syahriyyah'")
                ->where('bulan_masehi', $bulan)
                ->lockForUpdate()
                ->first();

            $tagihanBaru = false;
            if (! $tagihan) {
                $tagihan = $this->tagihanService->buatTagihanUntukSantri($santri, [
                    'jenis_tagihan' => 'Syahriyyah',
                    'nominal' => $nominalStandar,
                    'jatuh_tempo' => $bulan.'-10',
                    'bulan_masehi' => $bulan,
                    'keterangan' => self::MARKER,
                ]);
                $tagihanBaru = true;
            }

            // Idempoten re-run: pembayaran sama (tagihan+nominal+tanggal+marker)
            // sudah masuk → lewati tanpa dobel.
            $sudahMasuk = Pembayaran::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('tagihan_id', $tagihan->getKey())
                ->where('nominal', $nominal)
                ->whereDate('tanggal_pembayaran', $tanggal)
                ->where('catatan', $catatanImport)
                ->exists();

            if ($sudahMasuk) {
                return $tagihanBaru ? 'tagihan-saja' : 'sudah-masuk';
            }

            $pembayaran = $this->pembayaranService->inputPembayaran($tagihan, [
                'nominal' => $nominal,
                'metode' => $metode,
                'tanggal_pembayaran' => $tanggal,
                'catatan' => $catatanImport,
            ]);
            $this->pembayaranService->verifikasiPembayaran($pembayaran, (int) $verifikatorId);

            return $tagihanBaru ? 'bayar-tagihan-baru' : 'bayar';
        });

        if ($hasil === 'sudah-masuk' || $hasil === 'tagihan-saja') {
            $summary['skipped']++;
            if ($hasil === 'tagihan-saja') {
                $summary['tagihan_dibuat']++;
            }

            return;
        }

        if ($hasil === 'bayar-tagihan-baru') {
            $summary['tagihan_dibuat']++;
        }
        $summary['pembayaran_masuk']++;
        $summary['total_terbayar'] += $nominal;
    }

    /** "Cicilan Agustus 2025" / "Pelunasan April 2025" / "Agustus 2025" → '2025-08' */
    private function parseBulan(string $teks, string $namaSheet): ?string
    {
        $cari = function (string $sumber): ?string {
            $bersih = mb_strtolower($sumber);
            foreach (self::BULAN_ID as $nama => $angka) {
                if (str_contains($bersih, $nama) && preg_match('/(20\d{2}|\b\d{2}\b)/', $bersih, $m)) {
                    $tahun = strlen($m[1]) === 2 ? '20'.$m[1] : $m[1];

                    return sprintf('%s-%02d', $tahun, $angka);
                }
            }

            return null;
        };

        // Prioritas: teks PEMBAYARAN; fallback nama sheet ("AGS 25" dkk.).
        return $cari($teks) ?? $cari($this->ekspansiSingkatan($namaSheet));
    }

    private function ekspansiSingkatan(string $sheet): string
    {
        $peta = [
            'jan' => 'januari', 'feb' => 'februari', 'mar' => 'maret', 'apr' => 'april',
            'mei' => 'mei', 'jun' => 'juni', 'jul' => 'juli', 'ags' => 'agustus', 'agt' => 'agustus',
            'sep' => 'september', 'okt' => 'oktober', 'nov' => 'november', 'des' => 'desember',
        ];
        $bersih = mb_strtolower($sheet);
        foreach ($peta as $singkat => $penuh) {
            $bersih = str_replace($singkat, $penuh, $bersih);
        }

        return $bersih;
    }

    /** @return array{0: float, 1: string, 2: ?string} [nominal, metode, label bank] */
    private function parseNominalMetode(array $v): array
    {
        $kolom = [
            5 => ['tunai', null],
            6 => ['transfer', 'BSI'],
            7 => ['transfer', 'BCA'],
            8 => ['lainnya', null],
        ];

        $total = 0.0;
        $metode = 'tunai';
        $bank = null;
        $terbesar = 0.0;

        foreach ($kolom as $idx => [$m, $b]) {
            $angka = $this->angka($v[$idx] ?? null);
            if ($angka > 0) {
                $total += $angka;
                if ($angka > $terbesar) {
                    $terbesar = $angka;
                    $metode = $m;
                    $bank = $b;
                }
            }
        }

        return [round($total, 2), $metode, $bank];
    }

    /** Stringify aman: objek tanggal & tipe aneh tidak melempar. */
    private function teks(mixed $nilai): string
    {
        if ($nilai instanceof \DateTimeInterface) {
            return Carbon::instance($nilai)->toDateString();
        }

        if (is_scalar($nilai) || $nilai === null) {
            return trim((string) $nilai);
        }

        return '';
    }

    private function angka(mixed $nilai): float
    {
        if (is_int($nilai) || is_float($nilai)) {
            return (float) $nilai;
        }

        $teks = preg_replace('/[^\d,.\-]/', '', (string) $nilai);
        $teks = str_replace('.', '', $teks);   // 700.000 → 700000
        $teks = str_replace(',', '.', $teks);

        return $teks === '' || $teks === '-' ? 0.0 : (float) $teks;
    }

    private function parseTanggal(mixed $nilai, string $bulanFallback): string
    {
        if ($nilai instanceof \DateTimeInterface) {
            return Carbon::instance($nilai)->toDateString();
        }

        $teks = trim((string) $nilai);
        if ($teks !== '') {
            try {
                return Carbon::parse($teks)->toDateString();
            } catch (\Throwable) {
                // jatuh ke fallback
            }
        }

        return $bulanFallback.'-15';
    }
}
