<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\DivisiUnit;
use Modules\Ledger\Models\SumberDana;

/**
 * Shell PWA "Keuangan Lapangan" (Vue/Inertia). Memakai API api/keuangan/* yang
 * sudah teruji. Beranda berbasis peran (operator/kasir vs pimpinan vs asatidz).
 */
class KeuanganPwaController extends Controller
{
    private function can(): array
    {
        $u = Auth::user();

        return [
            'input_belanja' => $u?->can('input_belanja') ?? false,
            'setor_tabungan' => $u?->can('setor_tabungan') ?? false,
            'approve_pencairan' => $u?->can('approve_pencairan') ?? false,
            'view_laporan_pimpinan' => $u?->can('view_laporan_pimpinan') ?? false,
            'view_slip_sendiri' => $u?->can('view_slip_sendiri') ?? false,
        ];
    }

    public function beranda()
    {
        return Inertia::render('KeuanganPwa/Beranda', [
            'can' => $this->can(),
            'userName' => Auth::user()?->name,
        ]);
    }

    public function catatBelanja()
    {
        return Inertia::render('KeuanganPwa/CatatBelanja', [
            'sumberDana' => SumberDana::orderBy('kode_dana')->get(['id', 'kode_dana', 'nama_sumber']),
            'divisi' => DivisiUnit::orderBy('nama_divisi')->get(['id', 'nama_divisi']),
            'akunBeban' => Akun::whereIn('tipe', ['BEBAN', 'ASET'])->where('is_postable', true)->orderBy('kode')->get(['id', 'kode', 'nama']),
            'akunKas' => Akun::where('is_kas_bank', true)->where('is_postable', true)->orderBy('kode')->get(['id', 'kode', 'nama']),
        ]);
    }

    public function catatPemasukan()
    {
        return Inertia::render('KeuanganPwa/CatatPemasukan');
    }

    public function dasbor()
    {
        return Inertia::render('KeuanganPwa/DasborPimpinan');
    }

    public function slipSaya()
    {
        return Inertia::render('KeuanganPwa/SlipSaya');
    }
}
