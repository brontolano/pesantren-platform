<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Modules\Notifikasi\Services\WaGatewayService;
use Tests\TestCase;

/**
 * S5 (rencana red-team 2026-06-27): TITIK BUTA deteksi WAHA.
 *
 * Pipa n8n→WAHA bisa membalas HTTP 200 padahal WAHA GAGAL kirim (node "Send
 * WAHA" menelan error) → body memuat `sent: false`. Sebelum perbaikan,
 * WaGatewayService mengembalikan body apa adanya → WaNotificationService
 * menghitungnya TERKIRIM (rasa-aman palsu, tanpa log/alarm).
 *
 * interpret() kini: bila `sent === false` → status 'failed' + Log::warning
 * agar gagal-senyap menjadi TERLIHAT & ter-alarm.
 */
class WaGatewayBlindSpotTest extends TestCase
{
    private function interpret(array $body, string $target = '628123'): array
    {
        $svc = new WaGatewayService;
        $m = new \ReflectionMethod($svc, 'interpret');
        $m->setAccessible(true);

        return $m->invoke($svc, $body, $target);
    }

    public function test_respons_200_tapi_sent_false_ditandai_gagal_dan_dicatat(): void
    {
        Log::spy();

        $hasil = $this->interpret([
            'sent' => false,
            'gateway_status' => 500,
            'reason' => 'WAHA tidak merespons',
        ]);

        $this->assertSame('failed', $hasil['status'], 'sent:false WAJIB jadi status failed (bukan dianggap terkirim).');

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $event, array $ctx = []) => $event === 'wa.gateway.not_sent'
                && ($ctx['gateway_status'] ?? null) === 500)
            ->once();
    }

    public function test_respons_sent_true_tidak_ditandai_gagal(): void
    {
        $hasil = $this->interpret(['sent' => true, 'id' => 'wamid.abc']);

        $this->assertNotSame('failed', $hasil['status'] ?? null, 'pengiriman sukses tak boleh ditandai gagal.');
    }

    public function test_body_kosong_default_ok(): void
    {
        $this->assertSame('ok', $this->interpret([])['status']);
    }
}
