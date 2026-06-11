<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Payroll\Models\PenugasanPegawai;
use Modules\Payroll\Models\SlipGaji;
use Modules\Payroll\Services\SkPenugasanService;

class PayrollExportController extends Controller
{
    /** Cetak slip gaji (print-friendly). PTK boleh slip sendiri; payroll admin semua. */
    public function slipPrint(Request $request, SlipGaji $slip)
    {
        abort_unless(Auth::check(), 403);
        $user = Auth::user();
        $boleh = ($user?->can('run_payroll') ?? false) || ($slip->users_id === $user?->id && ($user?->can('view_slip_sendiri') ?? false));
        abort_unless($boleh, 403);

        return response()->view('exports.slip-gaji-print', [
            'tenantName' => tenant('name') ?? config('app.name'),
            'slip' => $slip->load('ptk'),
        ]);
    }

    /** WP-03 — Cetak SK penugasan (print-friendly) dari template ber-placeholder. */
    public function skPenugasan(Request $request, PenugasanPegawai $penugasan, SkPenugasanService $service)
    {
        abort_unless(Auth::user()?->can('run_payroll') ?? false, 403);

        return response()->view('exports.sk-penugasan-print', [
            'tenantName' => tenant('name') ?? config('app.name'),
            'penugasan' => $penugasan->load(['employee', 'jabatan', 'lembaga']),
            'isi' => $service->render($penugasan),
        ]);
    }
}
