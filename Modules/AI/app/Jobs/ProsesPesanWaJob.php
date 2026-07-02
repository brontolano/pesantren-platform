<?php

namespace Modules\AI\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Modules\AI\Models\AiConversation;
use Modules\AI\Models\AiMessage;
use Modules\AI\Services\Settings\TenantSettingService;
use Modules\AI\Services\Wa\WaBalasanService;
use Modules\AI\Services\Wa\WaChatService;
use Modules\AI\Services\Wa\WaHandoffService;

/**
 * T-A050/T-A051 — pemrosesan asinkron pesan WA masuk (queue 'ai', worker-ai).
 *
 * Webhook sudah meng-ack <5 detik; job ini yang memutuskan balasan:
 *  1. opt-out (STOP/BERHENTI) → set flag + konfirmasi santun SEKALI, tanpa LLM;
 *  2. opt-in (MULAI)          → buka flag + konfirmasi;
 *  3. opt_out aktif           → DIAM total (penghormatan seketika);
 *  3b. status handoff (T-A052)→ bot DIAM (pesan tersimpan + notifikasi admin
 *      Kotak Masuk WA); 'ditutup' dibuka kembali oleh pesan baru;
 *  4. mode_uji=true (T-A050)  → balasan template statis TANPA LLM (gap-135:
 *     flag dari SETTING tenant `mode_uji_wa`, fallback env `ai.wa.mode_uji`);
 *  5. mode_uji=false (T-A051) → pipeline WaChatService:
 *     verifikasi wali → intent rule-based → tool/template → free-form RAG.
 *
 * PRIVASI: teks asli menyeberang proses TERENKRIPSI (Crypt) di payload job —
 * tidak pernah polos di Redis/DB/log. Outbound HANYA via WaBalasanService
 * (→ WaGatewayService Modules/Notifikasi, Aturan Emas #5).
 */
class ProsesPesanWaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $conversationId,
        public readonly int $messageId,
        public readonly string $isiTerenkripsi,
    ) {
        $this->onQueue('ai');
    }

    public function handle(WaBalasanService $balasan): void
    {
        // Pola IngestDocumentJob: pastikan konteks tenant yang BENAR.
        if (! tenancy()->initialized || (string) tenant()?->getTenantKey() !== $this->tenantId) {
            tenancy()->initialize($this->tenantId);
        }

        $conversation = AiConversation::query()->find($this->conversationId);
        if ($conversation === null) {
            return; // sudah dibersihkan retensi — tidak ada yang diproses.
        }

        // Teks asli HANYA in-memory di sini.
        $isi = Crypt::decryptString($this->isiTerenkripsi);
        $kata = strtoupper(trim($isi));

        // 1. Opt-out: hormati seketika, konfirmasi santun sekali (tanpa LLM).
        if (in_array($kata, (array) config('ai.wa.kata_opt_out', ['STOP', 'BERHENTI']), true)) {
            $conversation->update(['opt_out' => true]);
            $balasan->balas($conversation, (string) config('ai.wa.balasan_opt_out'), ['jenis' => 'konfirmasi_opt_out']);

            return;
        }

        // 2. Opt-in kembali.
        if (in_array($kata, (array) config('ai.wa.kata_opt_in', ['MULAI']), true)) {
            if ($conversation->opt_out) {
                $conversation->update(['opt_out' => false]);
                $balasan->balas($conversation, (string) config('ai.wa.balasan_opt_in'), ['jenis' => 'konfirmasi_opt_in']);
            }

            return;
        }

        // 3. Sudah opt-out → DIAM total.
        if ($conversation->opt_out) {
            return;
        }

        // 3b. T-A052 — status handoff: bot DIAM total (admin yang melayani
        //     lewat Kotak Masuk WA). Pesan masuk SUDAH tersimpan oleh
        //     webhook; di sini cukup beri tahu admin ada pesan menunggu.
        //     STOP/MULAI tetap dihormati (sudah ditangani di atas).
        if ($conversation->status === AiConversation::STATUS_HANDOFF) {
            app(WaHandoffService::class)->notifikasiPesanMasuk($conversation);

            return;
        }

        // 3c. Percakapan 'ditutup' hidup kembali saat wali menyapa lagi —
        //     status Kotak Masuk tetap bermakna tanpa menghalangi layanan.
        if ($conversation->status === AiConversation::STATUS_DITUTUP) {
            $conversation->update(['status' => AiConversation::STATUS_AKTIF]);
        }

        // 4. T-A050 mode uji: balasan statis tanpa LLM.
        if ($this->modeUjiAktif()) {
            $balasan->balas($conversation, (string) config('ai.wa.balasan_mode_uji'), ['jenis' => 'mode_uji']);

            return;
        }

        // 5. T-A051 — pipeline chatbot wali: verifikasi → intent →
        //    tool/template → free-form RAG (idempoten per wa_message_id).
        $masuk = AiMessage::query()->find($this->messageId);

        if ($masuk === null) {
            return; // pesan sudah dibersihkan retensi — tidak ada yang dibalas.
        }

        app(WaChatService::class)->proses($conversation, $masuk, $isi);
    }

    /**
     * gap-135 — sumber flag mode_uji WA:
     *   1) SETTING tenant (ai_tenant_setting.payload['mode_uji_wa']) bila terisi;
     *   2) FALLBACK env lama config('ai.wa.mode_uji') bila setting belum punya
     *      kunci itu (tenant lama yang dibuat sebelum toggle ada — kompat-mundur,
     *      jalur env TIDAK dihapus).
     */
    private function modeUjiAktif(): bool
    {
        // Best-effort baca setting tenant; kegagalan apa pun (tabel belum
        // termigrasi, dll.) jatuh aman ke env lama — pemrosesan pesan WA tidak
        // boleh gagal hanya karena sumber flag.
        try {
            $payload = (array) (app(TenantSettingService::class)->aktif()?->payload ?? []);

            if (array_key_exists('mode_uji_wa', $payload) && $payload['mode_uji_wa'] !== null) {
                return (bool) $payload['mode_uji_wa'];
            }
        } catch (\Throwable $e) {
            Log::warning('ProsesPesanWaJob: gagal baca mode_uji_wa dari setting tenant — fallback env.', [
                'tenant' => $this->tenantId,
                'message' => $e->getMessage(),
            ]);
        }

        return (bool) config('ai.wa.mode_uji', true);
    }

    public function failed(?\Throwable $e): void
    {
        Log::warning('ProsesPesanWaJob gagal permanen.', [
            'tenant' => $this->tenantId,
            'conversation_id' => $this->conversationId,
            'error' => $e?->getMessage(),
        ]);
    }
}
