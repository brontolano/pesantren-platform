<?php

namespace Modules\AI\Http\Controllers;

use App\Services\ModuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AI\Services\Escalation\EscalationService;

/**
 * T-A090 — penerima POST komponen <x-ai::tombol-tanya-ustadz>.
 *
 * Route ber-middleware 'auth' (Hukum #2: memakai login eksisting, tanpa
 * jalur identitas baru) + konteks tenant wajib (tenancy diinisialisasi
 * middleware web global di domain tenant — pola RingkasanKebijakanController).
 *
 * Idempotensi per (user, hash pertanyaan) hidup di
 * EscalationService::buatManual — klik ganda aman.
 */
class TanyaUstadzController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless(tenancy()->initialized, 404);

        // Gerbang modul (selaras gating halaman T-A011 / T-A012).
        abort_unless(
            app(ModuleService::class)->isVisibleForTenant('ai', tenant()),
            404,
        );

        $data = $request->validate([
            'pertanyaan' => ['required', 'string', 'max:2000'],
        ]);

        $eskalasi = app(EscalationService::class)->buatManual(
            $request->user(),
            $data['pertanyaan'],
        );

        $pesan = 'Pertanyaan Anda telah diteruskan ke antrean Tanya Ustadz '
            .'dan akan dijawab langsung oleh pihak pesantren. Jazakumullahu khairan.';

        if ($request->wantsJson()) {
            return response()->json([
                'pesan' => $pesan,
                'eskalasi_id' => $eskalasi->getKey(),
            ]);
        }

        return back()->with('ai_tanya_ustadz', $pesan);
    }
}
