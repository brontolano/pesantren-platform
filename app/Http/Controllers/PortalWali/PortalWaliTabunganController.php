<?php

namespace App\Http\Controllers\PortalWali;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalWali\Concerns\ResolvesPortalWaliContext;
use App\Http\Requests\PortalWali\BayarDariSaldoRequest;
use App\Http\Requests\PortalWali\StoreTopupRequest;
use App\Models\Setting;
use App\Services\PortalWali\PortalWaliService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Keuangan\Models\Tabungan;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\BayarDariSaldoService;
use Modules\Keuangan\Services\TabunganService;
use Modules\Keuangan\Services\TopupService;

class PortalWaliTabunganController extends Controller
{
    use ResolvesPortalWaliContext;

    /**
     * GET — halaman tabungan: saldo, mutasi, tagihan belum lunas (untuk bayar-dari-saldo).
     */
    public function show(Request $request, PortalWaliService $portalWaliService): Response
    {
        $wali = $this->currentWali($request);
        $child = $this->accessibleSantri($request, $portalWaliService, (string) $request->route('santri'));

        $dashboard = $portalWaliService->getDashboardPayload($wali);
        $financial = $portalWaliService->getFinancialPayload($child);

        $tabungan = Tabungan::query()
            ->where('santri_id', $child->id)
            ->first();
        $batasDefault = Setting::getTenantValue('kantin.batas_belanja_default');

        return Inertia::render('PortalWali/Tabungan', [
            // Batas belanja kantin harian anak (2b): null = ikut default
            // pesantren; 0 = blokir belanja.
            'batasBelanja' => $tabungan?->batas_belanja_harian !== null
                ? (float) $tabungan->batas_belanja_harian
                : null,
            'batasBelanjaDefault' => is_numeric($batasDefault) ? (float) $batasDefault : null,
            'wali' => [
                'id' => $wali->id,
                'name' => $wali->name,
                'email' => $wali->email,
            ],
            'children' => $dashboard['children'],
            'navigation' => $this->navigationItems($dashboard['children'], $child->id),
            'child' => [
                'id' => $child->id,
                'nama' => $child->nama,
                'kelas' => $child->kelas?->nama_kelas,
            ],
            'savings' => $financial['savings'],
            'bills' => collect($financial['bills'])
                ->whereIn('status', ['belum_bayar', 'sebagian'])
                ->values(),
        ]);
    }

    /**
     * POST — ajukan setor saldo (topup) → status 'menunggu', verifikasi bendahara.
     */
    public function topup(
        StoreTopupRequest $request,
        PortalWaliService $portalWaliService,
        TopupService $topupService,
        TabunganService $tabunganService,
    ): RedirectResponse {
        $wali = $this->currentWali($request);
        $child = $this->accessibleSantri($request, $portalWaliService, (string) $request->route('santri'));

        $validated = $request->validated();
        $tabungan = $tabunganService->getOrCreateTabungan($child->id);
        $path = $request->file('bukti')->store('bukti-topup', 'public');

        $topupService->ajukan($tabungan, [
            'nominal' => (float) $validated['nominal'],
            'tanggal_transfer' => $validated['tanggal_transfer'],
            'nama_pengirim' => $validated['nama_pengirim'],
            'bukti' => $path,
            'created_by' => $wali->id,
        ]);

        return redirect()
            ->route('tenant.portal-wali.tabungan.show', [
                'tenant' => tenant('id') ?? $request->route('tenant'),
                'santri' => $child->id,
            ])
            ->with('success', 'Permintaan setor saldo terkirim. Menunggu verifikasi bendahara.');
    }

    /**
     * POST — wali atur batas belanja kantin harian anak (2b, policy-first).
     * null/kosong = ikut default pesantren; 0 = blokir belanja kantin.
     */
    public function setBatasBelanja(
        Request $request,
        PortalWaliService $portalWaliService,
        TabunganService $tabunganService,
    ): RedirectResponse {
        $this->currentWali($request);
        $child = $this->accessibleSantri($request, $portalWaliService, (string) $request->route('santri'));

        $validated = $request->validate([
            'batas' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
        ]);

        $tabungan = $tabunganService->getOrCreateTabungan($child->id);
        $tabungan->update([
            'batas_belanja_harian' => ($validated['batas'] ?? null) === null
                ? null
                : round((float) $validated['batas'], 2),
        ]);

        return redirect()
            ->route('tenant.portal-wali.tabungan.show', [
                'tenant' => tenant('id') ?? $request->route('tenant'),
                'santri' => $child->id,
            ])
            ->with('success', 'Batas belanja kantin harian diperbarui.');
    }

    /**
     * POST — bayar tagihan dari saldo tabungan (atomik via BayarDariSaldoService).
     */
    public function bayarDariSaldo(
        BayarDariSaldoRequest $request,
        PortalWaliService $portalWaliService,
        BayarDariSaldoService $bayarDariSaldoService,
    ): RedirectResponse {
        $wali = $this->currentWali($request);
        $child = $this->accessibleSantri($request, $portalWaliService, (string) $request->route('santri'));

        $tagihan = Tagihan::query()
            ->where('santri_id', $child->id)
            ->findOrFail($request->route('tagihan'));

        $bayarDariSaldoService->bayar(
            $child->id,
            $tagihan,
            (float) $request->validated()['nominal'],
            $wali->id,
        );

        return redirect()
            ->route('tenant.portal-wali.tabungan.show', [
                'tenant' => tenant('id') ?? $request->route('tenant'),
                'santri' => $child->id,
            ])
            ->with('success', 'Tagihan dibayar dari saldo tabungan.');
    }
}
