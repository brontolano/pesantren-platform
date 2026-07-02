<?php

namespace Modules\AI\Http\Controllers;

use App\Services\ModuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AI\Services\Gateway\AiGateway;
use Modules\AI\Services\Gateway\AiGenerateRequest;

/**
 * Muara internal widget chat "Khadim Digital" (plugin alkoumi/filament-n8n-chat).
 *
 * KRUSIAL (Aturan Emas #4): widget @n8n/chat MENEMBAK endpoint ini — BUKAN
 * webhook n8n / LLM eksternal langsung. Seluruh alur chat BERMUARA ke
 * AiGateway platform (audit + budget + policy + redaksi PII + guardrails),
 * sehingga tidak ada traffic LLM tak-teraudit yang keluar. Pola gating &
 * konteks tenant meniru TanyaUstadzController:
 *  - route ber-middleware 'auth' (Hukum #2: login eksisting, tanpa identitas baru);
 *  - konteks tenant WAJIB (tenancy datang dari middleware web global di domain
 *    tenant);
 *  - gerbang modul 'ai' (selaras halaman & controller AI lain).
 *
 * Protokol @n8n/chat: request { action, chatInput, sessionId }, response
 * { output }. Kita hormati bentuk itu supaya widget berjalan apa adanya —
 * tanpa fork/patch plugin.
 *
 * CATATAN: fitur ini DINONAKTIFKAN secara default (flag ai.n8n_chat.enabled =
 * false); widget baru tampil bila operator menyalakan flag + modul 'ai' aktif.
 * Endpoint tetap fail-close (404) bila modul tak aktif walau ada yang
 * memanggil langsung.
 */
class KhadimChatController extends Controller
{
    public function __invoke(Request $request, AiGateway $gateway): JsonResponse
    {
        abort_unless(tenancy()->initialized, 404);

        // Gerbang modul (selaras gating halaman T-A011 / TanyaUstadzController).
        abort_unless(
            app(ModuleService::class)->isVisibleForTenant('ai', tenant()),
            404,
        );

        // Fitur di belakang flag — default OFF (lihat config ai.n8n_chat).
        abort_unless((bool) config('ai.n8n_chat.enabled', false), 404);

        // @n8n/chat mengirim 'chatInput'; toleran juga terhadap 'pertanyaan'.
        $data = $request->validate([
            'chatInput' => ['sometimes', 'string', 'max:2000'],
            'pertanyaan' => ['sometimes', 'string', 'max:2000'],
        ]);

        $pesan = trim((string) ($data['chatInput'] ?? $data['pertanyaan'] ?? ''));

        if ($pesan === '') {
            return response()->json([
                'output' => 'Silakan tuliskan pertanyaan Anda.',
            ]);
        }

        // SATU-SATUNYA pintu LLM (AiGateway): audit + budget + policy + PII +
        // guardrails berlaku di dalam. Agen 'wali_chat' = agen chat free-form
        // yang sudah terpetakan di guardrails (kanal 'chat' = eksternal →
        // fail-closed bila fitur belum diizinkan tenant).
        $result = $gateway->generate(new AiGenerateRequest(
            prompt: $pesan,
            agent: (string) config('ai.n8n_chat.agent', 'wali_chat'),
            tier: (string) config('ai.n8n_chat.tier', 'ekonomis'),
            channel: 'chat',
            userId: $request->user()?->getAuthIdentifier(),
        ));

        return response()->json([
            'output' => $result->text,
        ]);
    }
}
