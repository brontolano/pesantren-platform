<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Services\BukuBesarService;
use Modules\Ledger\Services\JurnalService;

/**
 * Portal PWA "Akuntan" — role `akuntan` sebelumnya TIDAK PUNYA PWA sama
 * sekali (0%), harus buka Filament admin untuk segala hal. Data nyata dari
 * Modules\Ledger yang SUDAH dipakai panel admin (JurnalResource/AkunResource)
 * — tak ada tabel/logika baru.
 *
 * PENTING (ditemukan saat riset): Jurnal DILARANG dibuat manual — bahkan
 * JurnalResource::canCreate() selalu false ("Pagar #2": semua jurnal WAJIB
 * lahir dari service domain — payroll/belanja/tagihan/dll — bukan diketik
 * bebas). Jadi portal ini SENGAJA tidak punya form "catat jurnal manual";
 * satu-satunya aksi tulis akuntan adalah membalikkan (reverse) jurnal yang
 * keliru — sama seperti satu-satunya action tulis di JurnalResource admin.
 *
 * Gate: middleware('auth') + permission `view_ledger` (baca) — role akuntan
 * sudah punya view_ledger & manage_ledger (RolesAndPermissionsSeeder).
 * Aksi balik jurnal digerbang `manage_ledger` (sama dgn JurnalResource).
 */
class AkuntanController extends Controller
{
    public function __construct(private readonly JurnalService $jurnalService) {}

    private function nav(): array
    {
        $base = '/akuntan';
        $items = [
            ['key' => 'beranda', 'label' => 'Beranda', 'href' => '/staf', 'route' => 'beranda', 'icon' => 'home'],
            ['key' => 'jurnal', 'label' => 'Jurnal', 'href' => "{$base}/jurnal", 'route' => 'jurnal', 'icon' => 'book-open'],
            ['key' => 'akun', 'label' => 'Akun', 'href' => "{$base}/akun", 'route' => 'akun', 'icon' => 'bank'],
        ];

        return collect($items)
            ->map(fn (array $i): array => [
                'key' => $i['key'],
                'label' => $i['label'],
                'href' => $i['href'],
                'icon' => $i['icon'],
                'active' => request()->routeIs("tenant.akuntan.{$i['route']}"),
            ])
            ->all();
    }

    private function can(): array
    {
        return ['balik_jurnal' => (bool) Auth::user()?->can('manage_ledger')];
    }

    public function beranda(BukuBesarService $bukuBesar): Response
    {
        $tenantId = tenant('id');

        $jurnalBulanIni = Jurnal::where('tenant_id', $tenantId)
            ->where('status', 'posted')
            ->whereMonth('tanggal', now()->month)
            ->whereYear('tanggal', now()->year)
            ->count();

        $neraca = $bukuBesar->neracaSaldo();
        $totalDebit = $neraca->sum('debit');
        $totalKredit = $neraca->sum('kredit');

        return Inertia::render('Akuntan/Beranda', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'userName' => Auth::user()?->name ?? 'Akuntan',
            'stats' => [
                ['key' => 'jurnal', 'label' => 'Jurnal Bulan Ini', 'value' => (string) $jurnalBulanIni, 'hint' => 'posted', 'icon' => 'book-open', 'tone' => 'emerald'],
                ['key' => 'akun', 'label' => 'Akun Aktif', 'value' => (string) Akun::where('tenant_id', $tenantId)->where('is_active', true)->count(), 'hint' => 'di COA', 'icon' => 'bank', 'tone' => 'teal'],
            ],
            'neracaRingkas' => [
                'total_debit' => round((float) $totalDebit, 2),
                'total_kredit' => round((float) $totalKredit, 2),
                'seimbang' => abs($totalDebit - $totalKredit) < 0.01,
            ],
        ]);
    }

    public function jurnal(Request $request): Response
    {
        $tenantId = tenant('id');

        $query = Jurnal::where('tenant_id', $tenantId)
            ->with(['baris.akun:id,kode,nama'])
            ->withSum('baris as total_debit', 'debit')
            ->orderByDesc('tanggal')
            ->orderByDesc('id');

        if ($request->filled('dari')) {
            $query->where('tanggal', '>=', $request->input('dari'));
        }
        if ($request->filled('sampai')) {
            $query->where('tanggal', '<=', $request->input('sampai'));
        }

        $jurnal = $query->paginate(20)->through(fn (Jurnal $j): array => [
            'id' => $j->id,
            'nomor' => $j->nomor,
            'tanggal' => optional($j->tanggal)->translatedFormat('d M Y') ?? '-',
            'deskripsi' => $j->deskripsi,
            'tipe_sumber' => $j->tipe_sumber,
            'status' => $j->status,
            'total' => (float) $j->total_debit,
            'baris' => $j->baris->map(fn ($b) => [
                'akun' => $b->akun ? "{$b->akun->kode} — {$b->akun->nama}" : '-',
                'debit' => (float) $b->debit,
                'kredit' => (float) $b->kredit,
                'memo' => $b->memo,
            ]),
        ]);

        return Inertia::render('Akuntan/Jurnal', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'jurnal' => $jurnal->items(),
            'meta' => [
                'current_page' => $jurnal->currentPage(),
                'last_page' => $jurnal->lastPage(),
                'total' => $jurnal->total(),
            ],
        ]);
    }

    /**
     * Balikkan jurnal keliru — satu-satunya jalur "koreksi" (jurnal tak
     * pernah diedit langsung). Reuse JurnalService::reverseJurnal (sudah
     * dipakai JurnalResource admin, math/idempotensi tak diubah).
     */
    public function balikJurnal(Request $request, Jurnal $jurnal): RedirectResponse
    {
        if ($jurnal->tenant_id !== tenant('id')) {
            abort(404);
        }

        $data = $request->validate([
            'alasan' => ['nullable', 'string', 'max:500'],
        ]);

        $this->jurnalService->reverseJurnal($jurnal, $data['alasan'] ?? null, Auth::id());

        return back()->with('success', 'Jurnal dibalikkan.');
    }

    public function akun(): Response
    {
        $tenantId = tenant('id');

        $akun = Akun::where('tenant_id', $tenantId)
            ->orderBy('kode')
            ->get()
            ->map(fn (Akun $a): array => [
                'id' => $a->id,
                'kode' => $a->kode,
                'nama' => $a->nama,
                'tipe' => $a->tipe,
                'saldo_normal' => $a->saldo_normal,
                'is_kas_bank' => $a->is_kas_bank,
                'is_postable' => $a->is_postable,
                'is_active' => $a->is_active,
            ]);

        return Inertia::render('Akuntan/Akun', [
            'navigation' => $this->nav(),
            'can' => $this->can(),
            'akun' => $akun,
        ]);
    }
}
