<?php

declare(strict_types=1);

namespace App\Http\Controllers\PortalGuru;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalGuru\Concerns\ResolvesPortalGuruContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Payroll\Models\SlipGaji;

/**
 * F3-PWA — "Slip Saya": daftar slip gaji MILIK user login SAJA
 * (slip_gaji.users_id = auth id; draft tampil ber-badge, final bisa cetak).
 * Cetak reuse route existing tenant.payroll.slip.print yang sudah ber-gate
 * pemilik (users_id + view_slip_sendiri) ATAU run_payroll — TANPA menyentuh
 * PayrollService/mesin slip.
 */
class PortalGuruSlipController extends Controller
{
    use ResolvesPortalGuruContext;

    public function index(Request $request): Response
    {
        $guru = $this->currentGuru($request);

        // Angka gaji = data sensitif: tanpa view_slip_sendiri jangan tampilkan
        // apa pun (bukan cuma sembunyikan tombol cetak).
        abort_unless($guru->can('view_slip_sendiri'), 403);

        $slip = SlipGaji::query()
            ->where('users_id', $guru->id)
            ->whereIn('status', ['draft', 'final'])
            ->orderByDesc('periode')
            ->get()
            ->map(fn (SlipGaji $s): array => [
                'id' => $s->id,
                'periode' => $s->periode,
                'tipe' => $s->tipe,
                'status' => $s->status,
                'bruto' => (float) $s->bruto,
                'pph21' => (float) $s->pph21_dipotong,
                'bpjs' => (float) $s->bpjs_dipotong,
                'potongan_cuti' => (float) $s->potongan_cuti,
                'neto' => (float) $s->neto,
                // Cetak hanya slip final; route print ber-gate pemilik.
                'print_url' => $s->status === 'final' ? "/admin/payroll/slip/{$s->id}/print" : null,
            ])->values();

        return Inertia::render('PortalGuru/SlipSaya', [
            ...$this->sharedProps($request),
            'slip' => $slip,
            'bisaCetak' => $guru->can('view_slip_sendiri'),
        ]);
    }
}
