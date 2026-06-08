<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Modules\Ledger\Services\LaporanService;
use Modules\Pembiayaan\Models\PengajuanPembiayaan;

class PembiayaanExportController extends Controller
{
    /** L3 — Paket Kelayakan Kredit Bank: ringkasan DSCR + agunan non-wakaf + L6 ringkas. */
    public function paketKreditPrint(PengajuanPembiayaan $pengajuan, LaporanService $laporan)
    {
        abort_unless(Auth::check(), 403);
        abort_unless(Auth::user()?->can('manage_pembiayaan') ?? false, 403);

        return response()->view('exports.paket-kredit-print', [
            'tenantName' => tenant('name') ?? config('app.name'),
            'pengajuan' => $pengajuan->load('agunan'),
            'neraca' => $laporan->neraca(),
            'labaRugi' => $laporan->labaRugi(),
        ]);
    }
}
