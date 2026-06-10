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
use Modules\AI\Services\Wa\WaBalasanService;

/**
 * T-A050 — pemrosesan asinkron pesan WA masuk (queue 'ai', worker-ai).
 *
 * Webhook sudah meng-ack <5 detik; job ini yang memutuskan balasan:
 *  1. opt-out (STOP/BERHENTI) → set flag + konfirmasi santun SEKALI, tanpa LLM;
 *  2. opt-in (MULAI)          → buka flag + konfirmasi;
 *  3. opt_out aktif           → DIAM total (penghormatan seketika);
 *  4. mode_uji (T-A050)       → balasan template statis TANPA LLM.
 * Intent nyata (klasifikasi + jawaban LLM) datang di T-A051 — SENGAJA tidak
 * dibangun di sini; saat T-A051 cabang (4) diganti pipeline AiGateway.
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

        // 4. T-A050 mode uji: balasan statis tanpa LLM. Di luar mode uji
        //    belum ada pipeline intent (T-A051) → diam, tercatat saja.
        if ((bool) config('ai.wa.mode_uji', true)) {
            $balasan->balas($conversation, (string) config('ai.wa.balasan_mode_uji'), ['jenis' => 'mode_uji']);
        }
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
