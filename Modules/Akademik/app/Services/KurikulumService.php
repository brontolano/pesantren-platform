<?php

namespace Modules\Akademik\Services;

use Dompdf\Dompdf;
use Illuminate\Support\Collection;
use Modules\Akademik\Models\Kurikulum;
use Modules\Akademik\Models\KurikulumMapel;
use Modules\Akademik\Models\Lembaga;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Kesantrian\Models\Kelas;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

/**
 * WP-06 — Kurikulum per Tingkat.
 *
 * - bukaPeriode()           : generate baris Nilai kosong santri×mapel-wajib,
 *                             IDEMPOTEN (firstOrCreate pada unique
 *                             tenant+santri+pelajaran+periode — klik ulang
 *                             tidak menggandakan baris).
 * - isMapelDiLuarKurikulum(): validasi LUNAK jadwal — true hanya bila kelas
 *                             punya kurikulum aktif yang MENGATUR tingkat itu
 *                             dan mapel tidak terdaftar. Tanpa kurikulum →
 *                             false (perilaku lama utuh, tidak pernah blokir).
 * - strukturPdf()           : cetak struktur kurikulum per tingkat (pola
 *                             dompdf/mpdf SuratIzinSantriService).
 */
class KurikulumService
{
    /**
     * Buka periode: baris Nilai kosong per santri aktif × mapel WAJIB
     * sesuai tingkat kelasnya, untuk periode akademik aktif lembaga.
     *
     * @return array{periode_id:int, dibuat:int, dilewati:int}
     */
    public function bukaPeriode(Kurikulum $kurikulum, ?PeriodeAkademik $periode = null): array
    {
        $this->ensureTenantContext();

        $periode ??= $this->periodeAktifUntukLembaga($kurikulum->lembaga_id);

        if ($periode === null) {
            throw new RuntimeException('Tidak ada periode akademik aktif untuk lembaga ini. Aktifkan periode dulu.');
        }

        // Mapel wajib per tingkat: ['7' => [pelajaran_id, ...], ...]
        $wajibPerTingkat = $kurikulum->mapel()
            ->where('is_wajib', true)
            ->get()
            ->groupBy('tingkat')
            ->map(fn (Collection $rows) => $rows->pluck('pelajaran_id')->unique()->values());

        if ($wajibPerTingkat->isEmpty()) {
            return ['periode_id' => $periode->id, 'dibuat' => 0, 'dilewati' => 0];
        }

        $kelasQuery = Kelas::query()
            ->where('is_active', true)
            ->whereIn('tingkat', $wajibPerTingkat->keys()->all());

        // Kelas lembaga_id NULL = lembaga default tenant (kontrak WP-01):
        // ikut digarap hanya bila kurikulum menempel pada lembaga default.
        $isDefaultLembaga = $kurikulum->lembaga_id !== null
            && $kurikulum->lembaga_id === $this->defaultLembagaId();
        $kelasQuery->where(function ($q) use ($kurikulum, $isDefaultLembaga): void {
            $q->where('lembaga_id', $kurikulum->lembaga_id);
            if ($isDefaultLembaga) {
                $q->orWhereNull('lembaga_id');
            }
        });

        // Bulk insertOrIgnore (chunked): aman dari race double-click via unique
        // tenant+santri+pelajaran+periode, dan bebas N×M query firstOrCreate.
        $now = now();
        $rows = [];

        foreach ($kelasQuery->with(['santri' => fn ($q) => $q->where('status', 'aktif')])->get() as $kelas) {
            $pelajaranIds = $wajibPerTingkat->get($kelas->tingkat, collect());

            foreach ($kelas->santri as $santri) {
                foreach ($pelajaranIds as $pelajaranId) {
                    $rows[] = [
                        'tenant_id' => tenant('id'),
                        'santri_id' => $santri->id,
                        'pelajaran_id' => $pelajaranId,
                        'periode_akademik_id' => $periode->id,
                        'kelas_id' => $kelas->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        $dibuat = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            $dibuat += Nilai::query()->insertOrIgnore($chunk);
        }

        return ['periode_id' => $periode->id, 'dibuat' => $dibuat, 'dilewati' => count($rows) - $dibuat];
    }

    /**
     * Periode akademik aktif milik lembaga; fallback ke periode aktif
     * tanpa lembaga (data lama, kontrak WP-01 NULL = default).
     */
    public function periodeAktifUntukLembaga(?int $lembagaId): ?PeriodeAkademik
    {
        return PeriodeAkademik::query()
            ->where('is_active', true)
            ->where('lembaga_id', $lembagaId)
            ->orderByDesc('id')
            ->first()
            ?? PeriodeAkademik::query()
                ->where('is_active', true)
                ->whereNull('lembaga_id')
                ->orderByDesc('id')
                ->first();
    }

    /**
     * Lembaga default tenant (kontrak WP-01: kelas lembaga_id NULL = default).
     * Prioritas jenis 'pondok' (seeder LembagaSeeder), fallback lembaga tertua.
     */
    public function defaultLembagaId(): ?int
    {
        return Lembaga::query()
            ->orderByRaw("CASE WHEN jenis = 'pondok' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->value('id');
    }

    /**
     * Validasi LUNAK jadwal: true bila mapel TIDAK terdaftar pada kurikulum
     * aktif lembaga-kelas untuk tingkat kelas itu. Tanpa kurikulum aktif /
     * tingkat tidak diatur → false (jangan ganggu tenant lama).
     */
    public function isMapelDiLuarKurikulum(int $kelasId, int $pelajaranId): bool
    {
        $kelas = Kelas::query()->find($kelasId);

        if ($kelas === null) {
            return false;
        }

        // Kelas lembaga_id NULL = lembaga default (kontrak WP-01) — cek hanya
        // kurikulum lembaga itu, bukan semua (hindari warning palsu lintas-lembaga).
        $lembagaId = $kelas->lembaga_id ?? $this->defaultLembagaId();

        $entriTingkat = KurikulumMapel::query()
            ->where('tingkat', $kelas->tingkat)
            ->whereHas('kurikulum', function ($q) use ($lembagaId): void {
                $q->where('aktif', true)->where('lembaga_id', $lembagaId);
            })
            ->get(['pelajaran_id']);

        if ($entriTingkat->isEmpty()) {
            return false; // tingkat tidak diatur kurikulum manapun → perilaku lama
        }

        return ! $entriTingkat->pluck('pelajaran_id')->contains($pelajaranId);
    }

    /**
     * Cetak struktur kurikulum sebagai PDF (string biner).
     */
    public function strukturPdf(Kurikulum $kurikulum): string
    {
        $this->ensureTenantContext();

        return $this->generatePdf($this->renderTemplate($kurikulum));
    }

    private function renderTemplate(Kurikulum $kurikulum): string
    {
        $kurikulum->loadMissing(['lembaga', 'mapel.pelajaran', 'mapel.kitab']);

        $nama = e($kurikulum->nama);
        $lembaga = e($kurikulum->lembaga?->nama ?? '-');
        $tanggal = now()->format('d-m-Y');
        $tenantNama = e(tenant('name') ?? tenant('id'));

        $body = '';
        foreach ($kurikulum->mapel->groupBy('tingkat') as $tingkat => $rows) {
            $tingkatLabel = e((string) $tingkat);
            $body .= "<h3>Tingkat {$tingkatLabel}</h3>";
            $body .= '<table><thead><tr><th>No</th><th>Mata Pelajaran</th><th>Kitab</th><th>Jam/Minggu</th><th>Sifat</th></tr></thead><tbody>';
            $no = 1;
            $totalJam = 0;
            foreach ($rows->sortBy('urutan')->values() as $row) {
                $pelajaran = e($row->pelajaran?->nama ?? '-');
                $kitab = e($row->kitab?->nama ?? '-');
                $sifat = $row->is_wajib ? 'Wajib' : 'Pilihan';
                $body .= "<tr><td>{$no}</td><td>{$pelajaran}</td><td>{$kitab}</td><td class=\"num\">{$row->jam_per_minggu}</td><td>{$sifat}</td></tr>";
                $totalJam += $row->jam_per_minggu;
                $no++;
            }
            $body .= "<tr class=\"total\"><td colspan=\"3\">Total</td><td class=\"num\">{$totalJam}</td><td></td></tr>";
            $body .= '</tbody></table>';
        }

        if ($body === '') {
            $body = '<p><em>Belum ada mapel terdaftar pada kurikulum ini.</em></p>';
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; line-height: 1.4; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 6mm; margin-bottom: 6mm; }
        .header h1 { font-size: 14pt; margin: 0 0 2mm; }
        .header p { font-size: 9pt; color: #555; margin: 0; }
        h3 { margin: 6mm 0 2mm; font-size: 11pt; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
        th, td { border: 1px solid #888; padding: 2mm 3mm; text-align: left; }
        th { background: #eee; }
        .num { text-align: center; }
        .total td { font-weight: bold; background: #f7f7f7; }
        .footer { margin-top: 8mm; font-size: 9pt; color: #555; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Struktur Kurikulum — {$nama}</h1>
        <p>{$tenantNama} · Lembaga: {$lembaga} · Dicetak: {$tanggal}</p>
    </div>
    {$body}
    <div class="footer">Dokumen dihasilkan otomatis oleh sistem.</div>
</body>
</html>
HTML;
    }

    private function generatePdf(string $html): string
    {
        if (class_exists('Dompdf\Dompdf')) {
            $dompdf = new Dompdf;
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4');
            $dompdf->render();

            return $dompdf->output();
        }

        if (class_exists('Mpdf\Mpdf')) {
            $mpdf = new Mpdf;
            $mpdf->WriteHTML($html);

            return $mpdf->Output('', Destination::STRING_RETURN);
        }

        throw new RuntimeException('PDF library tidak ditemukan. Pastikan "dompdf/dompdf" atau "mpdf/mpdf" ter-install.');
    }

    private function ensureTenantContext(): void
    {
        if (! tenant()) {
            throw new RuntimeException('KurikulumService harus dijalankan dalam konteks tenant.');
        }
    }
}
