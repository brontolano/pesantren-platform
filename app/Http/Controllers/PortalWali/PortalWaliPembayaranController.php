<?php

namespace App\Http\Controllers\PortalWali;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalWali\Concerns\ResolvesPortalWaliContext;
use App\Http\Requests\PortalWali\StorePortalWaliPembayaranRequest;
use App\Services\PortalWali\PortalWaliService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Keuangan\Models\MetodePembayaran;
use Modules\Keuangan\Models\Tagihan;
use Modules\Keuangan\Services\KeuanganNotifikasiService;
use Modules\Keuangan\Services\PembayaranService;

class PortalWaliPembayaranController extends Controller
{
    use ResolvesPortalWaliContext;

    /**
     * GET — halaman detail tagihan + form unggah bukti bayar.
     */
    public function create(Request $request, PortalWaliService $portalWaliService): Response
    {
        $wali = $this->currentWali($request);
        $child = $this->accessibleSantri($request, $portalWaliService, (string) $request->route('santri'));

        $tagihan = Tagihan::query()
            ->where('santri_id', $child->id)
            ->findOrFail($request->route('tagihan'));

        $dashboard = $portalWaliService->getDashboardPayload($wali);

        return Inertia::render('PortalWali/UploadBukti', [
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
            'bill' => [
                'id' => $tagihan->id,
                'jenisTagihan' => $tagihan->jenis_tagihan,
                'keterangan' => $tagihan->keterangan,
                'nominalAkhir' => (float) $tagihan->nominal_akhir,
                'totalDibayar' => (float) $tagihan->total_dibayar,
                'sisaTagihan' => (float) $tagihan->sisa_tagihan,
                'status' => $tagihan->status,
                'jatuhTempo' => optional($tagihan->jatuh_tempo)?->format('Y-m-d'),
                // #332: rincian komponen syahriah (transparansi untuk wali).
                'komponen' => PortalWaliService::komponenPayload($tagihan),
            ],
            'metodePembayaran' => MetodePembayaran::query()
                ->where('is_active', true)
                ->orderBy('urutan')
                ->get()
                ->map(fn (MetodePembayaran $m): array => [
                    'id' => $m->id,
                    'tipe' => $m->tipe,
                    'nama' => $m->nama,
                    'nomor' => $m->nomor,
                    'atasNama' => $m->atas_nama,
                    'instruksi' => $m->instruksi,
                    'gambarUrl' => self::tenantAssetUrl($m->gambar),
                ])
                ->values(),
        ]);
    }

    /**
     * URL gambar metode pembayaran (file tenant disk 'public') via route
     * /_tenant-storage. Storage::url() menghasilkan /storage/... yang 404 di
     * Docker + stancl/tenancy (audit #7). Pola sama dgn TenantBranding::logoUrl.
     */
    private static function tenantAssetUrl(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return url('/_tenant-storage/' . ltrim($path, '/'));
    }

    /**
     * POST — submit bukti bayar → Pembayaran is_verified = false (menunggu verifikasi).
     */
    public function store(
        StorePortalWaliPembayaranRequest $request,
        PortalWaliService $portalWaliService,
        PembayaranService $pembayaranService,
    ): RedirectResponse {
        $wali = $this->currentWali($request);
        $child = $this->accessibleSantri($request, $portalWaliService, (string) $request->route('santri'));

        $tagihan = Tagihan::query()
            ->where('santri_id', $child->id)
            ->findOrFail($request->route('tagihan'));

        $validated = $request->validated();
        $path = $request->file('bukti_pembayaran')->store('bukti-pembayaran', 'public');

        $pembayaranService->inputPembayaran($tagihan, [
            'nominal' => (float) $validated['nominal'],
            'metode' => $validated['metode'],
            'tanggal_pembayaran' => $validated['tanggal_pembayaran'],
            'nomor_bukti' => $validated['nomor_bukti'] ?? null,
            'bukti_pembayaran' => $path,
            'catatan' => $validated['catatan'] ?? null,
            'created_by' => $wali->id,
        ]);

        // Notif WA admin: ada bukti baru di antrean verifikasi (fire-and-forget).
        app(KeuanganNotifikasiService::class)
            ->buktiMasuk('Bukti pembayaran tagihan', $child->nama, (float) $validated['nominal']);

        return redirect()
            ->route('tenant.portal-wali.pembayaran.index', ['tenant' => tenant('id') ?? $request->route('tenant')])
            ->with('success', 'Bukti pembayaran terkirim. Menunggu verifikasi bendahara.');
    }

    /**
     * GET — "Pembayaran Saya": status seluruh pembayaran lintas anak.
     */
    public function index(Request $request, PortalWaliService $portalWaliService): Response
    {
        $wali = $this->currentWali($request);
        $dashboard = $portalWaliService->getDashboardPayload($wali);

        $payments = collect($dashboard['children'])
            ->flatMap(function (array $child) use ($portalWaliService, $wali) {
                $santri = $portalWaliService->findAccessibleSantri($wali, $child['id']);

                return collect($portalWaliService->getFinancialPayload($santri)['payments'])
                    ->map(fn (array $payment): array => array_merge($payment, [
                        'santriNama' => $child['nama'],
                        'santriId' => $child['id'],
                    ]));
            })
            ->sortByDesc('tanggalPembayaran')
            ->values();

        return Inertia::render('PortalWali/PembayaranSaya', [
            'wali' => [
                'id' => $wali->id,
                'name' => $wali->name,
                'email' => $wali->email,
            ],
            'children' => $dashboard['children'],
            'navigation' => $this->navigationItems($dashboard['children']),
            'payments' => $payments,
        ]);
    }
}
