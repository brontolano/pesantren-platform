<?php

namespace App\Http\Controllers\PortalWali;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalWali\Concerns\ResolvesPortalWaliContext;
use App\Http\Requests\PortalWali\StoreTopupCheckoutRequest;
use App\Services\PortalWali\PortalWaliService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\TabunganTopup;
use Modules\Keuangan\Services\PaymentMethodCatalog;
use Modules\Keuangan\Services\TabunganService;
use Modules\Keuangan\Services\TopupService;

/**
 * Top Up Tabungan gaya checkout marketplace (DEMO — simulasi pembayaran).
 *
 * Alur:
 *   1. checkout()    GET   — pilih nominal + metode.
 *   2. store()       POST  — buat TabunganTopup status 'menunggu', simpan metode.
 *   3. instruksi()   GET   — tampilkan detail bayar (VA/QRIS/retail/e-wallet/kartu).
 *   4. konfirmasi()  POST  — DEMO: setujui topup → saldo NAIK (idempotent).
 *   5. sukses()      GET   — ringkasan + saldo terbaru.
 *
 * Otorisasi: per-anak via accessibleSantri(); topup divalidasi milik tabungan anak.
 * Metode disimpan di kolom existing 'nama_pengirim' sbg "Top Up via {Nama} [{CODE}]"
 * (TANPA migration baru). Kolom 'bukti' & 'tanggal_transfer' (NOT NULL) diisi
 * nilai DEMO karena tidak ada upload bukti pada alur checkout ini.
 */
class PortalWaliTopupController extends Controller
{
    use ResolvesPortalWaliContext;

    /** Preset nominal cepat (rupiah). */
    private const PRESETS = [25000, 50000, 100000, 200000, 500000, 1000000];

    public function __construct(
        private readonly PaymentMethodCatalog $catalog = new PaymentMethodCatalog(),
    ) {
    }

    /**
     * GET — halaman checkout: pilih nominal + metode pembayaran.
     */
    public function checkout(Request $request, PortalWaliService $portalWaliService, TabunganService $tabunganService): Response
    {
        $wali = $this->currentWali($request);
        $child = $this->accessibleSantri($request, $portalWaliService, (string) $request->route('santri'));

        $dashboard = $portalWaliService->getDashboardPayload($wali);
        $financial = $portalWaliService->getFinancialPayload($child);

        return Inertia::render('PortalWali/Topup/Checkout', [
            'wali' => $this->waliProps($wali),
            'children' => $dashboard['children'],
            'navigation' => $this->navigationItems($dashboard['children'], $child->id),
            'child' => $this->childProps($child),
            'savings' => $financial['savings'],
            'paymentMethods' => $this->catalog->grouped(),
            'presets' => self::PRESETS,
            'nominalMin' => StoreTopupCheckoutRequest::NOMINAL_MIN,
            'nominalMax' => StoreTopupCheckoutRequest::NOMINAL_MAX,
        ]);
    }

    /**
     * POST — buat pengajuan topup status 'menunggu' lalu redirect ke instruksi.
     */
    public function store(
        StoreTopupCheckoutRequest $request,
        PortalWaliService $portalWaliService,
        TopupService $topupService,
        TabunganService $tabunganService,
    ): RedirectResponse {
        $wali = $this->currentWali($request);
        $child = $this->accessibleSantri($request, $portalWaliService, (string) $request->route('santri'));

        $validated = $request->validated();
        $method = $this->catalog->find($validated['metode']);

        $tabungan = $tabunganService->getOrCreateTabungan($child->id);

        $topup = $topupService->ajukan($tabungan, [
            'nominal' => (float) $validated['nominal'],
            'tanggal_transfer' => now()->toDateString(),
            // Metode disimpan recoverable: "Top Up via {Nama} [{CODE}]".
            'nama_pengirim' => "Top Up via {$method['name']} [{$method['code']}]",
            'bukti' => 'demo-checkout',
            'created_by' => $wali->id,
        ]);

        return redirect()->route('tenant.portal-wali.topup.instruksi', [
            'tenant' => tenant('id') ?? $request->route('tenant'),
            'santri' => $child->id,
            'topup' => $topup->id,
        ]);
    }

    /**
     * GET — instruksi pembayaran sesuai metode (VA/QRIS/retail/e-wallet/kartu).
     */
    public function instruksi(Request $request, PortalWaliService $portalWaliService): Response
    {
        $wali = $this->currentWali($request);
        $child = $this->accessibleSantri($request, $portalWaliService, (string) $request->route('santri'));
        $topup = $this->resolveTopup($request, $child);

        $code = $this->extractMethodCode($topup->nama_pengirim);
        $detail = $this->catalog->paymentDetail($topup, $code);

        $dashboard = $portalWaliService->getDashboardPayload($wali);

        return Inertia::render('PortalWali/Topup/Instruksi', [
            'wali' => $this->waliProps($wali),
            'children' => $dashboard['children'],
            'navigation' => $this->navigationItems($dashboard['children'], $child->id),
            'child' => $this->childProps($child),
            'topup' => $this->topupProps($topup),
            'payment' => $detail,
            'alreadyPaid' => $topup->status === 'disetujui',
        ]);
    }

    /**
     * POST — DEMO: konfirmasi "sudah bayar" → setujui topup → saldo NAIK.
     * Idempotent: bila sudah disetujui, langsung redirect ke sukses tanpa dobel.
     */
    public function konfirmasi(
        Request $request,
        PortalWaliService $portalWaliService,
        TopupService $topupService,
    ): RedirectResponse {
        $wali = $this->currentWali($request);
        $child = $this->accessibleSantri($request, $portalWaliService, (string) $request->route('santri'));
        $topup = $this->resolveTopup($request, $child);

        if ($topup->status === 'menunggu') {
            $topupService->setujui($topup, $wali->id);
        }

        return redirect()->route('tenant.portal-wali.topup.sukses', [
            'tenant' => tenant('id') ?? $request->route('tenant'),
            'santri' => $child->id,
            'topup' => $topup->id,
        ]);
    }

    /**
     * GET — halaman sukses: ringkasan topup + saldo terbaru.
     */
    public function sukses(Request $request, PortalWaliService $portalWaliService, TabunganService $tabunganService): Response
    {
        $wali = $this->currentWali($request);
        $child = $this->accessibleSantri($request, $portalWaliService, (string) $request->route('santri'));
        $topup = $this->resolveTopup($request, $child);

        $dashboard = $portalWaliService->getDashboardPayload($wali);
        $saldoBaru = $tabunganService->getSaldo($child->id);

        return Inertia::render('PortalWali/Topup/Sukses', [
            'wali' => $this->waliProps($wali),
            'children' => $dashboard['children'],
            'navigation' => $this->navigationItems($dashboard['children'], $child->id),
            'child' => $this->childProps($child),
            'topup' => $this->topupProps($topup),
            'saldoBaru' => $saldoBaru,
            'tabunganUrl' => route('tenant.portal-wali.tabungan.show', [
                'tenant' => tenant('id') ?? $request->route('tenant'),
                'santri' => $child->id,
            ]),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Ambil topup milik tabungan anak ini, atau 404. Otorisasi per-anak.
     */
    private function resolveTopup(Request $request, Santri $child): TabunganTopup
    {
        $topup = TabunganTopup::query()
            ->with('tabungan')
            ->findOrFail($request->route('topup'));

        abort_unless(
            $topup->tabungan !== null && (int) $topup->tabungan->santri_id === (int) $child->id,
            404,
        );

        return $topup;
    }

    /**
     * Pulihkan kode metode dari 'nama_pengirim' (format "... [CODE]").
     * Fallback 'QRIS' bila tak ditemukan (data lama / manual).
     */
    private function extractMethodCode(?string $namaPengirim): string
    {
        if ($namaPengirim !== null && preg_match('/\[([A-Z]+)\]/', $namaPengirim, $m) === 1) {
            if ($this->catalog->isValid($m[1])) {
                return $m[1];
            }
        }

        return 'QRIS';
    }

    /**
     * @return array{id:int, nama:string, kelas:string|null}
     */
    private function childProps(Santri $child): array
    {
        return [
            'id' => $child->id,
            'nama' => $child->nama,
            'kelas' => $child->kelas?->nama_kelas,
        ];
    }

    /**
     * @return array{id:int, name:string, email:string|null}
     */
    private function waliProps($wali): array
    {
        return [
            'id' => $wali->id,
            'name' => $wali->name,
            'email' => $wali->email,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function topupProps(TabunganTopup $topup): array
    {
        $code = $this->extractMethodCode($topup->nama_pengirim);
        $method = $this->catalog->find($code);

        return [
            'id' => $topup->id,
            'nominal' => (float) $topup->nominal,
            'status' => $topup->status,
            'metodeCode' => $code,
            'metodeName' => $method['name'] ?? $code,
            'metodeCategory' => $method['category'] ?? null,
            'createdAt' => optional($topup->created_at)?->toIso8601String(),
            'verifiedAt' => optional($topup->verified_at)?->toIso8601String(),
        ];
    }
}
