<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\StafNav;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Tabungan;
use Modules\Keuangan\Models\TabunganTransaksi;
use Modules\Keuangan\Services\KantinService;
use Modules\Keuangan\Services\TabunganService;
use Throwable;

/**
 * PWA "POS Kantin" — persona kasir kantin. Menggantikan PosKantinDemoController:
 * data nyata via Modules\Keuangan\KantinService/TabunganService (belanja
 * kantin dipotong dari tabungan santri, PIN App Santri, race-safe — sudah
 * ada & dipakai KasirKantin di panel admin).
 *
 * BUKAN cakupan: katalog produk + stok — tak ada model inventaris kantin di
 * sistem ini (KasirKantin admin pun nominal-only, bukan keranjang per-item).
 * Layar "Stok" demo lama sengaja TIDAK diportal — akan jadi data palsu bila
 * dipaksakan. Layar Kasir di sini memakai alur nyata: scan/ketik NIS →
 * nominal → PIN santri (bukan keranjang produk).
 *
 * Gate: middleware('auth') + can:operate_pos — permission yang SUDAH
 * dimiliki role `kasir` dan dipakai KasirKantin::canAccess() di panel admin.
 */
class PosKantinController extends Controller
{
    public function __construct(
        private KantinService $kantinService,
        private TabunganService $tabunganService,
    ) {}

    private function nav(): array
    {
        // Bottom-nav STANDAR mockup (Beranda/Menu/Aktivitas/Akun) - owner 2026-07-06.
        return StafNav::items();
    }

    /** Transaksi belanja/refund kantin oleh kasir login, hari ini. */
    private function transaksiHariIni()
    {
        return TabunganTransaksi::whereIn('tipe', ['belanja_kantin', 'refund_kantin'])
            ->where('created_by', Auth::id())
            ->whereDate('tanggal', now()->toDateString())
            ->with('tabungan.santri')
            ->latest('id')
            ->limit(50)
            ->get();
    }

    public function beranda(): Response
    {
        $hariIni = $this->transaksiHariIni();
        $belanja = $hariIni->where('tipe', 'belanja_kantin');

        return Inertia::render('PosKantin/Beranda', [
            'userName' => Auth::user()?->name,
            'omzetHariIni' => (float) $belanja->sum('nominal'),
            'stats' => [
                ['key' => 'transaksi', 'icon' => 'receipt', 'tone' => 'blue', 'label' => 'Transaksi Hari Ini', 'value' => (string) $belanja->count(), 'hint' => 'Oleh kasir ini'],
                ['key' => 'refund', 'icon' => 'inbox', 'tone' => 'amber', 'label' => 'Refund Hari Ini', 'value' => (string) $hariIni->where('tipe', 'refund_kantin')->count(), 'hint' => 'Dibatalkan'],
            ],
            'transaksiTerbaru' => $belanja->take(5)->map(fn (TabunganTransaksi $t): array => [
                'santri' => $t->tabungan?->santri?->nama ?? '—',
                'nominal' => (float) $t->nominal,
                'waktu' => $t->created_at->format('H:i'),
            ])->values(),
            'navigation' => $this->nav(),
        ]);
    }

    public function kasir(Request $request): Response
    {
        $santri = null;
        $nis = trim((string) $request->query('nis', ''));
        if ($nis !== '') {
            $found = Santri::where('nis', $nis)->first();
            if ($found !== null) {
                $santri = [
                    'id' => $found->id,
                    'nama' => $found->nama,
                    'nis' => $found->nis,
                    'saldo' => $this->tabunganService->getSaldo($found),
                ];
            }
        }

        return Inertia::render('PosKantin/Kasir', [
            'santri' => $santri,
            'nisDicari' => $nis,
            'navigation' => $this->nav(),
        ]);
    }

    public function cariSantri(Request $request): JsonResponse
    {
        $nis = trim((string) $request->query('nis', ''));
        $santri = $nis !== '' ? Santri::where('nis', $nis)->first() : null;

        if ($santri === null) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'santri' => [
                'id' => $santri->id,
                'nama' => $santri->nama,
                'nis' => $santri->nis,
                'saldo' => $this->tabunganService->getSaldo($santri),
            ],
        ]);
    }

    public function belanja(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'santri_id' => ['required', 'integer', 'exists:santri,id'],
            'nominal' => ['required', 'numeric', 'min:1'],
            'pin' => ['required', 'string'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $santri = Santri::findOrFail($data['santri_id']);

        try {
            $trx = $this->kantinService->belanja($santri, (float) $data['nominal'], $data['pin'], [
                'keterangan' => $data['keterangan'] ?? null,
                'created_by' => Auth::id(),
            ]);
        } catch (Throwable $e) {
            return back()->withErrors(['pin' => $e->getMessage()]);
        }

        return redirect()->route('tenant.pos-kantin.kasir')
            ->with('success', 'Belanja Rp '.number_format((float) $trx->nominal, 0, ',', '.').' tercatat. Sisa saldo Rp '.number_format((float) $trx->saldo_sesudah, 0, ',', '.').'.');
    }

    public function tabungan(): Response
    {
        $mutasi = TabunganTransaksi::whereIn('tipe', ['belanja_kantin', 'refund_kantin', 'setoran', 'setor_keliling'])
            ->with('tabungan.santri')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (TabunganTransaksi $t): array => [
                'id' => $t->id,
                'santri' => $t->tabungan?->santri?->nama ?? '—',
                'jenis' => in_array($t->tipe, ['setoran', 'setor_keliling'], true) ? 'topup' : 'belanja',
                'nominal' => (float) $t->nominal,
                'waktu' => $t->created_at->translatedFormat('d M, H:i'),
            ]);

        return Inertia::render('PosKantin/Tabungan', [
            'saldoTotal' => (float) Tabungan::sum('saldo'),
            'jumlahSantri' => Tabungan::where('saldo', '>', 0)->count(),
            'mutasi' => $mutasi,
            'navigation' => $this->nav(),
        ]);
    }

    public function riwayat(): Response
    {
        $hariIni = $this->transaksiHariIni();
        $belanja = $hariIni->where('tipe', 'belanja_kantin');

        return Inertia::render('PosKantin/Riwayat', [
            'sesi' => [
                'label' => 'Sesi Hari Ini',
                'kasir' => Auth::user()?->name ?? 'Kasir Kantin',
                'omzet' => (float) $belanja->sum('nominal'),
                'transaksi' => $belanja->count(),
            ],
            'transaksi' => $hariIni->map(fn (TabunganTransaksi $t): array => [
                'id' => 'TRX-'.$t->id,
                'item' => $t->keterangan ?: ($t->tipe === 'refund_kantin' ? 'Refund' : 'Belanja kantin'),
                'nominal' => (float) $t->nominal,
                'waktu' => $t->created_at->format('H:i'),
            ])->values(),
            'navigation' => $this->nav(),
        ]);
    }
}
