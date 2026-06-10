<?php

namespace Modules\AI\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Modules\AI\Jobs\ProsesPesanWaJob;
use Modules\AI\Models\AiConversation;
use Modules\AI\Models\AiMessage;
use Modules\AI\Services\Privacy\PiiRedactor;

/**
 * T-A050 — webhook WA inbound (adaptasi K6: pilot WAHA+n8n, BUKAN BSP resmi).
 *
 * KONTRAK ACK <5 DETIK (Blueprint §4.2/§7.3): handler ini HANYA
 * validasi token → resolusi tenant → dedup → simpan pesan TERREDAKSI →
 * 200 → dispatch ProsesPesanWaJob (queue ai). DILARANG memanggil LLM /
 * HTTP keluar apa pun di jalur sinkron ini.
 *
 * KEAMANAN (Aturan Emas #10 — tanpa auth baru): header X-Webhook-Token
 * dibandingkan hash_equals dengan config ai.wa.webhook_token (env).
 * Token kosong/belum dikonfigurasi = webhook mati total (401).
 *
 * PRIVASI: nomor WA tidak pernah polos di DB/log — hash sha256+APP_KEY
 * untuk lookup, Crypt untuk kebutuhan balasan. Isi pesan disimpan SUDAH
 * lewat PiiRedactor::redactPaksa; teks asli diteruskan ke job TERENKRIPSI.
 *
 * Format payload WAHA (engine WEBJS, diteruskan n8n apa adanya, opsional
 * ditambah field 'tenant'):
 *   { event: "message", session: "default",
 *     payload: { id, from: "628xx@c.us", body, fromMe } }
 */
class WaWebhookController extends Controller
{
    public function __construct(private readonly PiiRedactor $redactor)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        // 1. Token rahasia — hash_equals anti timing attack.
        $tokenConfig = (string) config('ai.wa.webhook_token', '');
        $tokenHeader = (string) $request->header('X-Webhook-Token', '');

        if ($tokenConfig === '' || ! hash_equals($tokenConfig, $tokenHeader)) {
            return response()->json(['status' => 'ditolak'], 401);
        }

        // 2. Hanya event 'message' dari lawan bicara yang diproses.
        if ($request->input('event') !== 'message') {
            return response()->json(['status' => 'diabaikan', 'alasan' => 'bukan_event_message']);
        }

        $payload = $request->input('payload');
        if (! is_array($payload)) {
            return response()->json(['status' => 'ditolak', 'alasan' => 'payload_invalid'], 422);
        }

        if ((bool) ($payload['fromMe'] ?? false)) {
            return response()->json(['status' => 'diabaikan', 'alasan' => 'pesan_sendiri']);
        }

        $from = isset($payload['from']) && is_string($payload['from']) ? trim($payload['from']) : '';
        $body = isset($payload['body']) && is_string($payload['body']) ? $payload['body'] : '';
        $waMessageId = isset($payload['id']) && is_string($payload['id']) ? $payload['id'] : null;

        if ($from === '' || trim($body) === '') {
            return response()->json(['status' => 'diabaikan', 'alasan' => 'tanpa_pengirim_atau_isi']);
        }

        // 3. Resolusi tenant: payload 'tenant' > peta session > tenant pilot.
        $tenantId = $this->resolveTenant($request);
        if ($tenantId === null) {
            return response()->json(['status' => 'ditolak', 'alasan' => 'tenant_tidak_dikenal'], 422);
        }

        if (! tenancy()->initialized || (string) tenant()?->getTenantKey() !== $tenantId) {
            try {
                tenancy()->initialize($tenantId);
            } catch (\Throwable) {
                return response()->json(['status' => 'ditolak', 'alasan' => 'tenant_tidak_dikenal'], 422);
            }
        }

        // 4. Dedup webhook ganda (n8n/WAHA bisa retry) — wa_message_id UNIQUE.
        if ($waMessageId !== null && AiMessage::query()->where('wa_message_id', $waMessageId)->exists()) {
            return response()->json(['status' => 'duplikat']);
        }

        // 5. Simpan percakapan + pesan masuk (TERREDAKSI) dalam satu transaksi.
        $conversation = null;
        $message = null;

        try {
            DB::transaction(function () use ($from, $body, $waMessageId, &$conversation, &$message): void {
                $conversation = AiConversation::query()->firstOrCreate(
                    ['kanal' => 'wa', 'nomor_wa_hash' => AiConversation::hashNomor($from)],
                    [
                        'nomor_wa_terenkripsi' => Crypt::encryptString($from),
                        'status' => AiConversation::STATUS_AKTIF,
                    ],
                );
                $conversation->update(['terakhir_aktif' => now()]);

                $message = AiMessage::create([
                    'conversation_id' => $conversation->id,
                    'arah' => AiMessage::ARAH_MASUK,
                    'isi_terredaksi' => $this->redactor->redactPaksa($body)->text,
                    'wa_message_id' => $waMessageId,
                    'meta' => ['session' => (string) request()->input('session', '')],
                ]);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Balapan dua webhook identik — yang kalah dianggap duplikat.
            return response()->json(['status' => 'duplikat']);
        }

        // 6. Ack segera; pemrosesan (opt-out / balasan) asinkron di queue ai.
        //    Teks asli hanya menyeberang proses TERENKRIPSI (tak polos di
        //    payload queue) — pola TODO(T-A005-persist).
        ProsesPesanWaJob::dispatch($tenantId, $conversation->id, $message->id, Crypt::encryptString($body));

        return response()->json(['status' => 'diterima']);
    }

    /** payload 'tenant' (dari workflow n8n) > peta session→tenant > default pilot. */
    private function resolveTenant(Request $request): ?string
    {
        $eksplisit = $request->input('tenant');
        if (is_string($eksplisit) && trim($eksplisit) !== '') {
            return trim($eksplisit);
        }

        $session = $request->input('session');
        if (is_string($session) && $session !== '') {
            $map = (array) config('ai.wa.session_tenant_map', []);
            if (isset($map[$session]) && is_string($map[$session]) && $map[$session] !== '') {
                return $map[$session];
            }
        }

        $default = config('ai.wa.tenant_default');

        return is_string($default) && trim($default) !== '' ? trim($default) : null;
    }
}
