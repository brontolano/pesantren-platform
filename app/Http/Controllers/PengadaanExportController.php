<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Pengadaan\Models\Belanja;

class PengadaanExportController extends Controller
{
    /**
     * Cetak Slip BKK (Bukti Kas Keluar) + rincian e-faktur sederhana untuk satu belanja.
     * Print-friendly HTML (browser → Print/Save as PDF). DomPDF belum dipasang.
     */
    public function bkkPrint(Request $request, Belanja $belanja)
    {
        abort_unless(Auth::check(), 403);
        abort_unless(Auth::user()?->canAny(['view_keuangan', 'input_belanja', 'manage_ledger']) ?? false, 403);

        return response()->view('exports.bkk-print', [
            'tenantName' => tenant('name') ?? config('app.name'),
            'belanja' => $belanja,
        ]);
    }
}
