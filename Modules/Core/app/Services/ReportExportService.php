<?php

namespace Modules\Core\Services;

use App\Models\Setting;
use Barryvdh\DomPDF\PDF;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;
use Modules\Core\Exceptions\ReportPdfUnavailableException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * F8 — Report & Export Service (lintas-modul, Modules/Core).
 *
 * Satu tempat untuk: (a) header brand tenant, (b) render laporan dari template
 * blade, (c) ekspor CSV native, (d) ekspor PDF via dompdf BILA terpasang.
 * Tanpa dompdf, layanan tetap berguna (HTML print-to-PDF + CSV); PDF native
 * aktif otomatis begitu `composer require barryvdh/laravel-dompdf` dijalankan
 * (tanpa ubah kode ini).
 *
 * Modul consumer (Laporan Kehalalan/Slip Gaji/Register Aset/Mutu/SPT/Quotation)
 * memanggil service ini, BUKAN membuat generator PDF/CSV sendiri.
 */
class ReportExportService
{
    /** Registry template laporan → view blade. */
    public const TEMPLATES = [
        'sertifikat-kehalalan' => 'reports.sertifikat-kehalalan',
        'laporan-mutu' => 'reports.generic',
        'slip-gaji' => 'reports.generic',
        'register-aset' => 'reports.generic',
        'spt-pph21' => 'reports.generic',
        'quotation-biaya' => 'reports.generic',
        'id-card-santri' => 'reports.id-card',
    ];

    /** Header brand tenant untuk kop laporan. */
    public function brand(): array
    {
        $nama = Setting::getTenantStringValue('branding_name')
            ?: (tenant()?->getAttribute('name') ?? 'Pesantren');
        $logoPath = data_get(Setting::getTenantValue('branding_logo'), 'logo_path');

        return [
            'nama' => $nama,
            'tagline' => Setting::getTenantStringValue('branding_tagline'),
            'logo_url' => $logoPath ? '/_tenant-storage/'.ltrim((string) $logoPath, '/') : null,
            'warna' => data_get(Setting::getTenantValue('branding_primary_color'), 'color', '#0f766e'),
        ];
    }

    /** Apakah library PDF (dompdf) terpasang? */
    public function pdfTersedia(): bool
    {
        return class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)
            || class_exists(PDF::class);
    }

    /** Resolusi key template → nama view blade. */
    public function view(string $template): string
    {
        if (! isset(self::TEMPLATES[$template])) {
            throw new InvalidArgumentException("Template laporan '{$template}' tidak terdaftar.");
        }

        return self::TEMPLATES[$template];
    }

    /** Render laporan jadi HTML lengkap (kop brand + isi). */
    public function html(string $template, array $data = []): string
    {
        $this->ensureTenantContext();

        return View::make($this->view($template), array_merge([
            'brand' => $this->brand(),
            'judul' => $data['judul'] ?? 'Laporan',
        ], $data))->render();
    }

    /**
     * Ekspor PDF. Bila dompdf belum terpasang → ReportPdfUnavailableException
     * (consumer boleh fallback ke html() / print browser).
     */
    public function pdf(string $template, array $data = [], ?string $filename = null): Response
    {
        if (! $this->pdfTersedia()) {
            throw new ReportPdfUnavailableException;
        }

        $html = $this->html($template, $data);
        $nama = $filename ?? ($template.'.pdf');

        /** @var object $pdf */
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4');

        return $pdf->download($nama);
    }

    /**
     * Ekspor CSV native (tanpa library). Mengembalikan StreamedResponse unduhan.
     *
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public function csv(array $headers, iterable $rows, string $filename = 'laporan.csv'): StreamedResponse
    {
        $this->ensureTenantContext();

        return new StreamedResponse(function () use ($headers, $rows) {
            $out = fopen('php://output', 'wb');
            // BOM agar Excel membaca UTF-8 dengan benar.
            fwrite($out, "\xEF\xBB\xBF");
            if ($headers) {
                fputcsv($out, $headers);
            }
            foreach ($rows as $row) {
                fputcsv($out, is_array($row) ? $row : (array) $row);
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk laporan.');
        }
    }
}
