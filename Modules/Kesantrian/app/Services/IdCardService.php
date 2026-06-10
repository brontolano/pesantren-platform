<?php

namespace Modules\Kesantrian\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Collection;
use Modules\Core\Services\ReportExportService;
use Modules\Kesantrian\Models\Santri;
use RuntimeException;

/**
 * Auto-generate ID Card santri (PDF + visual) — task papan PPDB Admin & Ujian.
 *
 * Kartu dirender via F8 ReportExportService (template 'id-card-santri', kop
 * branding tenant otomatis). QR berisi NIS (inline data-URI SVG via chillerlan,
 * tanpa dependensi JS) — bisa dipindai untuk presensi/identifikasi.
 * PDF bila dompdf terpasang; fallback html() untuk print browser (pola F8).
 */
class IdCardService
{
    public function __construct(private readonly ReportExportService $exporter)
    {
    }

    /** Render kartu untuk kumpulan santri sebagai HTML siap-cetak. */
    public function html(Collection $santri): string
    {
        $this->ensureTenantContext();

        if ($santri->isEmpty()) {
            throw new RuntimeException('Tidak ada santri untuk dicetak.');
        }

        return $this->exporter->html('id-card-santri', [
            'judul' => 'ID Card Santri',
            'kartu' => $this->kartu($santri),
        ]);
    }

    /** @return array<int, array{nama:string,nis:string,kelas:?string,tahun:?string,qr:string}> */
    public function kartu(Collection $santri): array
    {
        $qr = new QRCode(new QROptions([
            'outputBase64' => true, // data-URI → langsung dipakai <img src>
            'scale' => 4,
        ]));

        return $santri->map(fn (Santri $s): array => [
            'nama' => (string) $s->nama,
            'nis' => (string) $s->nis,
            'kelas' => $s->kelas?->nama ?? $s->kelas?->nama_kelas ?? null,
            'tahun' => $s->tahun_angkatan ?? null,
            'qr' => $qr->render((string) $s->nis),
        ])->values()->all();
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
