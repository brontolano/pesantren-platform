<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Keuangan\Services\EmisExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmisExportController extends Controller
{
    /**
     * Unduh ekspor terformat EMIS (CSV). Bukan API real-time.
     *
     * WP-22: ?kategori=santri|pegawai|lembaga (default santri — kompatibel
     * dengan tautan lama). Data kurang TIDAK menggagalkan ekspor: kolom
     * dibiarkan kosong + daftar PERINGATAN dilampirkan di bawah data
     * (CSV satu-file; pemisah = baris kosong + header PERINGATAN).
     */
    public function csv(Request $request, EmisExportService $service): StreamedResponse
    {
        abort_unless(Auth::check(), 403);
        abort_unless(Auth::user()?->canAny(['view_keuangan', 'manage_keuangan', 'view_santri']) ?? false, 403);

        $kategori = (string) $request->query('kategori', 'santri');
        abort_unless(in_array($kategori, EmisExportService::KATEGORI, true), 404);

        // PII pegawai (NIK/NUPTK) lebih ketat: hak payroll, bukan view_santri.
        if ($kategori === 'pegawai') {
            abort_unless(Auth::user()?->canAny(['run_payroll', 'manage_keuangan']) ?? false, 403);
        }

        $header = EmisExportService::headerFor($kategori);
        $rows = $service->barisFor($kategori);
        $peringatan = $service->peringatan($kategori);
        $filename = sprintf('ekspor-emis-%s-%s.csv', $kategori, now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($header, $rows, $peringatan): void {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8 utk Excel
            // Netralisasi formula CSV: sel berawalan =,+,-,@ dieksekusi Excel
            // (CSV injection via nama/alamat) — prefix apostrof.
            $aman = static fn (array $row): array => array_map(
                static fn ($sel) => is_string($sel) && $sel !== '' && in_array($sel[0], ['=', '+', '-', '@'], true)
                    ? "'".$sel
                    : $sel,
                $row,
            );

            fputcsv($handle, $header);
            foreach ($rows as $row) {
                fputcsv($handle, $aman($row));
            }

            if ($peringatan !== []) {
                fputcsv($handle, []); // baris kosong pemisah
                fputcsv($handle, ['PERINGATAN KELENGKAPAN DATA (lengkapi sebelum unggah ke portal EMIS)']);
                foreach ($peringatan as $p) {
                    fputcsv($handle, [$p]);
                }
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
