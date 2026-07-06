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

    /**
     * Item bottom-nav (kontrak {key,label,href,active}) — di-gate per izin
     * `can.*` & ditandai aktif via nama rute. Dipakai KeuanganLapanganLayout.
     */
    private function nav(array $can): array
    {
        $base = '/admin/keuangan/pwa';
        $items = [
            ['key' => 'beranda', 'label' => 'Beranda', 'href' => '/staf', 'route' => 'beranda', 'show' => true],
            ['key' => 'catat', 'label' => 'Belanja', 'href' => "{$base}/catat-belanja", 'route' => 'belanja', 'show' => $can['input_belanja']],
            ['key' => 'setor', 'label' => 'Setor', 'href' => "{$base}/setor-keliling", 'route' => 'setor-keliling', 'show' => $can['setor_tabungan']],
            ['key' => 'dasbor', 'label' => 'Dasbor', 'href' => "{$base}/dasbor", 'route' => 'dasbor', 'show' => $can['view_laporan_pimpinan']],
            ['key' => 'slip', 'label' => 'Slip', 'href' => "{$base}/slip-saya", 'route' => 'slip', 'show' => $can['view_slip_sendiri']],
        ];

        return collect($items)
            ->filter(fn (array $i): bool => $i['show'])
            ->map(fn (array $i): array => [
                'key' => $i['key'],
                'label' => $i['label'],
                'href' => $i['href'],
                'active' => request()->routeIs("tenant.keuangan.pwa.{$i['route']}"),
            ])
            ->values()
            ->all();
    }

    public function beranda()
    {
        $can = $this->can();

        abort_unless(in_array(true, $can, true), 403);

        return Inertia::render('KeuanganPwa/Beranda', [
            'can' => $can,
            'navigation' => $this->nav($can),
            'userName' => Auth::user()?->name,
        ]);
    }

    public function catatBelanja()
    {
        abort_unless(Auth::user()?->can('input_belanja') ?? false, 403);

        return Inertia::render('KeuanganPwa/CatatBelanja', [
            'navigation' => $this->nav($this->can()),
            'sumberDana' => SumberDana::orderBy('kode_dana')->get(['id', 'kode_dana', 'nama_sumber']),
            'divisi' => DivisiUnit::orderBy('nama_divisi')->get(['id', 'nama_divisi']),
            'akunBeban' => Akun::whereIn('tipe', ['BEBAN', 'ASET'])->where('is_postable', true)->orderBy('kode')->get(['id', 'kode', 'nama']),
            'akunKas' => Akun::where('is_kas_bank', true)->where('is_postable', true)->orderBy('kode')->get(['id', 'kode', 'nama']),
        ]);
    }

    public function catatPemasukan()
    {
        abort_unless(Auth::user()?->can('input_belanja') ?? false, 403);

        return Inertia::render('KeuanganPwa/CatatPemasukan', [
            'navigation' => $this->nav($this->can()),
        ]);
    }

    public function dasbor()
    {
        abort_unless(Auth::user()?->can('view_laporan_pimpinan') ?? false, 403);

        return Inertia::render('KeuanganPwa/DasborPimpinan', [
            'navigation' => $this->nav($this->can()),
        ]);
    }

    public function slipSaya()
    {
        abort_unless(Auth::user()?->can('view_slip_sendiri') ?? false, 403);

        return Inertia::render('KeuanganPwa/SlipSaya', [
            'navigation' => $this->nav($this->can()),
        ]);
    }

    /** WP-32: Setor Keliling — pilih kamar → santri → setoran draf (offline-ready). */
    public function setorKeliling()
    {
        abort_unless(Auth::user()?->can('setor_tabungan') ?? false, 403);

        return Inertia::render('KeuanganPwa/SetorKeliling', [
            'navigation' => $this->nav($this->can()),
        ]);
    }
}
