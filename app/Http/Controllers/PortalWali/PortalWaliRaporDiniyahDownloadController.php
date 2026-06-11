<?php

namespace App\Http\Controllers\PortalWali;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalWali\Concerns\ResolvesPortalWaliContext;
use App\Models\AccessWali;
use Illuminate\Http\Request;
use Modules\Akademik\Models\RaporDiniyah;
use Symfony\Component\HttpFoundation\Response;

/**
 * WP-10 — Unduh/cetak Rapor Diniyah dari Portal Wali. Wali HANYA boleh
 * melihat rapor anaknya sendiri: rapor anak orang lain → 403 (bukan 404,
 * agar gagal otorisasi terbedakan dari rapor tak ada). Blok yang tercetak
 * mengikuti Setting tenant `akademik.rapor_diniyah_blok`.
 */
class PortalWaliRaporDiniyahDownloadController extends Controller
{
    use ResolvesPortalWaliContext;

    public function __invoke(Request $request): Response
    {
        $wali = $this->currentWali($request);

        /** @var RaporDiniyah $rapor */
        $rapor = RaporDiniyah::query()->findOrFail($request->route('raporDiniyah'));

        $anakIds = AccessWali::query()
            ->where('wali_id', $wali->getKey())
            ->pluck('santri_id');

        abort_unless($anakIds->contains($rapor->santri_id), 403);

        $rapor->load(['santri.kelas', 'periodeAkademik']);

        return response()->view('exports.rapor-diniyah-print', [
            'tenantName' => tenant('name') ?? config('app.name'),
            'rapor' => $rapor,
            'periode' => $rapor->periodeAkademik,
            'blokAktif' => RaporDiniyah::blokAktif(),
            'printedAt' => now(),
        ]);
    }
}
