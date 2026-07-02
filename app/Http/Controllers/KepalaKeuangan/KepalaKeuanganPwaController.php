<?php

declare(strict_types=1);

namespace App\Http\Controllers\KepalaKeuangan;

use App\Http\Controllers\Controller;
use App\Services\KepalaKeuangan\KepalaKeuanganBrandingService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PWA Kepala Keuangan — 13 layar Inertia (Vue).
 * Semua data berat di-lazy-load via KepalaKeuanganApiController.
 */
class KepalaKeuanganPwaController extends Controller
{
    public function __construct(
        private readonly KepalaKeuanganBrandingService $brandingService,
    ) {}

    private function can(): array
    {
        $u = Auth::user();

        return [
            'manage_keuangan' => $u?->can('manage_keuangan') ?? false,
            'view_ledger' => $u?->can('view_ledger') ?? false,
            'manage_ledger' => $u?->can('manage_ledger') ?? false,
            'close_period' => $u?->can('close_period') ?? false,
            'approve_pencairan' => $u?->can('approve_pencairan') ?? false,
            'run_payroll' => $u?->can('run_payroll') ?? false,
            'manage_pajak' => $u?->can('manage_pajak') ?? false,
            'manage_dana_kebajikan' => $u?->can('manage_dana_kebajikan') ?? false,
            'view_laporan_pimpinan' => $u?->can('view_laporan_pimpinan') ?? false,
            'operate_pos' => $u?->can('operate_pos') ?? false,
            'setor_tabungan' => $u?->can('setor_tabungan') ?? false,
        ];
    }

    private function baseProps(): array
    {
        return [
            'can' => $this->can(),
            'userName' => Auth::user()?->name,
            'branding' => $this->brandingService->resolve(),
        ];
    }

    // ── 01 Beranda ──────────────────────────────────────────────

    public function beranda(): Response
    {
        return Inertia::render('KepalaKeuangan/Beranda', $this->baseProps());
    }

    // ── 02 Penerimaan ───────────────────────────────────────────

    public function tagihan(): Response
    {
        return Inertia::render('KepalaKeuangan/Penerimaan/Tagihan', $this->baseProps());
    }

    public function verifikasi(): Response
    {
        return Inertia::render('KepalaKeuangan/Penerimaan/Verifikasi', $this->baseProps());
    }

    public function loket(): Response
    {
        return Inertia::render('KepalaKeuangan/Penerimaan/Loket', $this->baseProps());
    }

    // ── 03 Kas Harian ───────────────────────────────────────────

    public function tabungan(): Response
    {
        return Inertia::render('KepalaKeuangan/KasHarian/Tabungan', $this->baseProps());
    }

    public function pos(): Response
    {
        return Inertia::render('KepalaKeuangan/KasHarian/Pos', $this->baseProps());
    }

    // ── 04 Pengeluaran ──────────────────────────────────────────

    public function approval(): Response
    {
        return Inertia::render('KepalaKeuangan/Pengeluaran/Approval', $this->baseProps());
    }

    public function payroll(): Response
    {
        return Inertia::render('KepalaKeuangan/Pengeluaran/Payroll', $this->baseProps());
    }

    // ── 05 Ledger ───────────────────────────────────────────────

    public function jurnal(): Response
    {
        return Inertia::render('KepalaKeuangan/Ledger/Jurnal', $this->baseProps());
    }

    public function anggaran(): Response
    {
        return Inertia::render('KepalaKeuangan/Ledger/Anggaran', $this->baseProps());
    }

    // ── 06 Pusat Laporan ────────────────────────────────────────

    public function laporan(): Response
    {
        return Inertia::render('KepalaKeuangan/Laporan/Index', $this->baseProps());
    }

    public function neracaLr(): Response
    {
        return Inertia::render('KepalaKeuangan/Laporan/NeracaLR', $this->baseProps());
    }

    public function arusKas(): Response
    {
        return Inertia::render('KepalaKeuangan/Laporan/ArusKas', $this->baseProps());
    }

    public function laporanOperasional(): Response
    {
        return Inertia::render('KepalaKeuangan/Laporan/Operasional', $this->baseProps());
    }

    public function laporanDana(): Response
    {
        return Inertia::render('KepalaKeuangan/Laporan/Dana', $this->baseProps());
    }

    public function laporanNeracaSaldo(): Response
    {
        return Inertia::render('KepalaKeuangan/Laporan/NeracaSaldo', $this->baseProps());
    }

    public function pajak(): Response
    {
        return Inertia::render('KepalaKeuangan/Pajak/Index', $this->baseProps());
    }

    public function laporanPsak109(): Response
    {
        return Inertia::render('KepalaKeuangan/Laporan/Psak109', $this->baseProps());
    }

    public function laporanIsak35(): Response
    {
        return Inertia::render('KepalaKeuangan/Laporan/Isak35', $this->baseProps());
    }

    public function rekapTahunan(): Response
    {
        return Inertia::render('KepalaKeuangan/Laporan/RekapTahunan', $this->baseProps());
    }
}
