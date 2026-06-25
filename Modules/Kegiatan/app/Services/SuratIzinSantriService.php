<?php

namespace Modules\Kegiatan\Services;

use chillerlan\QRCode\QRCode;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Cache;
use Modules\Kegiatan\Models\IzinSantri;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

class SuratIzinSantriService
{
    private const CACHE_MINUTES = 1440; // 24 jam

    /**
     * Generate atau retrieve surat PDF izin dengan QR code.
     * Idempoten: kedua kali GET dengan izin_id yang sama → bytewise identik.
     */
    public function suratPdf(IzinSantri $izin): string
    {
        $this->ensureTenantContext();

        if ($izin->status !== 'disetujui') {
            throw new RuntimeException('Hanya izin berstatus "disetujui" yang bisa di-cetak.');
        }

        $cacheKey = "surat_izin_{$izin->id}_v1";
        $cached = Cache::get($cacheKey);

        if ($cached) {
            return $cached;
        }

        $qrContent = json_encode([
            'izin_id' => $izin->id,
            'tenant' => tenant('id'),
            'santri' => $izin->santri_id,
            'valid_until' => $izin->tanggal_selesai,
        ]);

        $qrCode = (new QRCode)->render($qrContent);

        $html = $this->renderTemplate($izin, $qrCode);

        // Gunakan dompdf atau mpdf — anggap DOMPDF_ROOT tersedia via composer
        // atau fallback ke format sederhana jika tidak ada.
        $pdf = $this->generatePdf($html);

        Cache::put($cacheKey, $pdf, self::CACHE_MINUTES);

        return $pdf;
    }

    private function renderTemplate(IzinSantri $izin, string $qrDataUri): string
    {
        $santriNama = $izin->santri?->nama ?? '(Tidak diketahui)';
        $disetujuiOleh = $izin->disetujuiOleh?->name ?? '(Tidak diketahui)';
        $tglMulai = $izin->tanggal_mulai->format('d-m-Y');
        $tglSelesai = $izin->tanggal_selesai->format('d-m-Y');
        $jenisIzin = ucfirst(str_replace('_', ' ', $izin->jenis_izin));
        $tanggalSurat = now()->format('d-m-Y');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; }
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
        }
        .container {
            max-width: 210mm;
            height: 297mm;
            margin: 20mm;
            padding: 20mm;
            border: 2px solid #333;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #333;
            padding-bottom: 10mm;
            margin-bottom: 10mm;
        }
        .header h1 {
            font-size: 18pt;
            margin-bottom: 5mm;
        }
        .header p {
            font-size: 9pt;
            color: #666;
        }
        .content {
            margin: 10mm 0;
        }
        .row {
            display: table;
            width: 100%;
            margin: 5mm 0;
        }
        .label {
            display: table-cell;
            width: 40mm;
            font-weight: bold;
            vertical-align: top;
        }
        .value {
            display: table-cell;
            vertical-align: top;
            padding-left: 10mm;
        }
        .qr-box {
            display: flex;
            justify-content: center;
            margin: 15mm 0;
            padding: 10mm;
            border: 1px solid #ccc;
            background: #f9f9f9;
        }
        .qr-box img {
            max-width: 80mm;
            height: auto;
        }
        .footer {
            margin-top: 20mm;
            text-align: center;
            border-top: 2px solid #999;
            padding-top: 10mm;
            font-size: 9pt;
        }
        .signature {
            display: flex;
            justify-content: flex-end;
            gap: 30mm;
            margin-top: 15mm;
        }
        .sig-block {
            text-align: center;
            width: 50mm;
        }
        .sig-line {
            border-top: 1px solid #000;
            margin-top: 25mm;
            padding-top: 5mm;
            font-size: 9pt;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>SURAT IZIN PULANG</h1>
            <p>Pesantren Digital</p>
        </div>

        <div class="content">
            <div class="row">
                <span class="label">No. Izin:</span>
                <span class="value">{$izin->id}</span>
            </div>
            <div class="row">
                <span class="label">Tanggal Surat:</span>
                <span class="value">{$tanggalSurat}</span>
            </div>

            <div style="margin: 15mm 0; border-top: 1px solid #ccc; border-bottom: 1px solid #ccc; padding: 10mm 0;">
                <h3 style="margin-bottom: 8mm;">Data Santri</h3>

                <div class="row">
                    <span class="label">Nama:</span>
                    <span class="value">{$santriNama}</span>
                </div>
                <div class="row">
                    <span class="label">Jenis Izin:</span>
                    <span class="value">{$jenisIzin}</span>
                </div>
                <div class="row">
                    <span class="label">Tanggal Mulai:</span>
                    <span class="value">{$tglMulai}</span>
                </div>
                <div class="row">
                    <span class="label">Tanggal Selesai:</span>
                    <span class="value">{$tglSelesai}</span>
                </div>
                <div class="row">
                    <span class="label">Alasan:</span>
                    <span class="value">{$izin->alasan}</span>
                </div>
            </div>

            <div class="row">
                <span class="label">Disetujui Oleh:</span>
                <span class="value">{$disetujuiOleh}</span>
            </div>
        </div>

        <div class="qr-box">
            {$qrDataUri}
        </div>

        <div class="footer">
            <p>Surat ini berlaku sebagai bukti izin resmi dari pesantren. Mohon disimpan hingga selesai masa izin.</p>
        </div>

        <div class="signature">
            <div class="sig-block">
                <p style="margin-bottom: 5mm;">Disetujui,</p>
                <div class="sig-line">{$disetujuiOleh}</div>
            </div>
            <div class="sig-block">
                <p style="margin-bottom: 5mm;">Mengetahui,</p>
                <div class="sig-line">Kepala Pesantren</div>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function generatePdf(string $html): string
    {
        // Cek apakah DOMPDF tersedia
        if (class_exists('Dompdf\Dompdf')) {
            $dompdf = new Dompdf;
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4');
            $dompdf->render();

            return $dompdf->output();
        }

        // Fallback ke mpdf jika tersedia
        if (class_exists('Mpdf\Mpdf')) {
            $mpdf = new Mpdf;
            $mpdf->WriteHTML($html);

            return $mpdf->Output('', Destination::STRING_RETURN);
        }

        throw new RuntimeException('PDF library tidak ditemukan. Pastikan "dompdf/dompdf" atau "mpdf/mpdf" sudah di-install via composer.');
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
