<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Modules\Keuangan\Services\EmisExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmisExportController extends Controller
{
    /** Unduh ekspor terformat EMIS (CSV). Bukan API real-time. */
    public function csv(EmisExportService $service): StreamedResponse
    {
        abort_unless(Auth::check(), 403);
        abort_unless(Auth::user()?->canAny(['view_keuangan', 'manage_keuangan', 'view_santri']) ?? false, 403);

        $rows = $service->barisSantri();
        $filename = 'ekspor-emis-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8 utk Excel
            fputcsv($handle, EmisExportService::HEADER);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
