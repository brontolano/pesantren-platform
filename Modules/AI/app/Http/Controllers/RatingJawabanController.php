<?php

namespace Modules\AI\Http\Controllers;

use App\Services\ModuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AI\Services\Research\ResearchTelemetry;

/**
 * T-A090 — penerima POST komponen <x-ai::rating-jawaban> (bintang 1–5).
 *
 * Meneruskan ke ResearchTelemetry::rekamRating (T-A013) yang sudah NO-OP
 * SENYAP bila tenant tidak opt-in riset. Respons SELALU terima kasih yang
 * sama — status opt-in tenant tidak bocor lewat perilaku UI.
 *
 * Rentang rating divalidasi DI SINI (422 santun) supaya input liar tidak
 * berujung InvalidArgumentException (500) dari service.
 */
class RatingJawabanController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless(tenancy()->initialized, 404);

        abort_unless(
            app(ModuleService::class)->isVisibleForTenant('ai', tenant()),
            404,
        );

        $data = $request->validate([
            'agent' => ['required', 'string', 'max:64'],
            'rating' => ['required', 'integer', 'between:1,5'],
        ]);

        app(ResearchTelemetry::class)->rekamRating(
            (string) tenant()->getKey(),
            $data['agent'],
            (int) $data['rating'],
        );

        $pesan = 'Terima kasih atas penilaian Anda.';

        if ($request->wantsJson()) {
            return response()->json(['pesan' => $pesan]);
        }

        return back()->with('ai_rating_jawaban', $pesan);
    }
}
