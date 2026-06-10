<?php

namespace Modules\AI\Services\Wa;

use Illuminate\Support\Facades\Crypt;
use Modules\AI\Models\AiConversation;
use Modules\AI\Models\AiMessage;
use Modules\AI\Services\Privacy\PiiRedactor;
use Modules\Notifikasi\Models\TemplateNotifikasi;
use Modules\Notifikasi\Services\WaGatewayService;

/**
 * T-A050 — satu-satunya pintu balasan WA chatbot.
 *
 * ATURAN EMAS #5: pengiriman fisik HANYA lewat jalur outbound resmi
 * Modules/Notifikasi (WaGatewayService → webhook n8n per-tenant / WAHA).
 * Kelas ini tidak membuka koneksi WA sendiri — ia hanya:
 *  1. resolusi TemplateNotifikasi 'AI_BALASAN_WA' (firstOrCreate, idempoten;
 *     tenant bisa menonaktifkan via panel Notifikasi → balasan diam);
 *  2. dekripsi nomor tujuan dari conversation (in-memory saja);
 *  3. delegasi ke WaGatewayService::send (hormati toggle WA + signature);
 *  4. catat ai_message keluar (isi TERREDAKSI — paranoid walau template
 *     statis, supaya jalur T-A051 nanti tak bisa lupa).
 */
class WaBalasanService
{
    public function __construct(
        private readonly WaGatewayService $gateway,
        private readonly PiiRedactor $redactor,
    ) {
    }

    /**
     * Kirim balasan ke percakapan + catat pesan keluar.
     *
     * @return bool true bila gateway dipanggil (template aktif & WA hidup)
     */
    public function balas(AiConversation $conversation, string $pesan, array $meta = []): bool
    {
        $template = $this->resolveTemplate();
        if (! $template->is_active) {
            return false; // tenant mematikan balasan chatbot — hormati, diam.
        }

        // Nomor hanya hidup in-memory selama panggilan ini.
        $target = Crypt::decryptString($conversation->nomor_wa_terenkripsi);

        $hasil = $this->gateway->send($target, $template, $pesan, ['sumber' => 'ai_khadim_t_a050']);

        AiMessage::create([
            'conversation_id' => $conversation->id,
            'arah' => AiMessage::ARAH_KELUAR,
            'isi_terredaksi' => $this->redactor->redactPaksa($pesan)->text,
            'meta' => $meta + ['gateway_status' => $hasil['status'] ?? null],
        ]);

        return ($hasil['status'] ?? null) !== 'disabled';
    }

    private function resolveTemplate(): TemplateNotifikasi
    {
        return TemplateNotifikasi::query()->firstOrCreate(
            ['kode_template' => (string) config('ai.wa.template_kode', 'AI_BALASAN_WA')],
            [
                'judul' => 'Balasan Asisten Khadim Digital',
                'isi' => '{pesan}',
                'channel' => 'wa',
                'is_active' => true,
            ],
        );
    }
}
