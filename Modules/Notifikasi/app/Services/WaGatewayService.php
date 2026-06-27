<?php

namespace Modules\Notifikasi\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Notifikasi\Models\TemplateNotifikasi;
use Modules\Notifikasi\Support\NotifikasiSettings;

class WaGatewayService
{
    public function send(string $target, TemplateNotifikasi $template, string $message, array $context = []): array
    {
        // Hormati toggle di Pengaturan Notifikasi: bila WA dimatikan, tak ada pesan keluar.
        if (! NotifikasiSettings::waEnabled()) {
            return [
                'status' => 'disabled',
                'message' => 'Notifikasi WhatsApp dinonaktifkan di Pengaturan Notifikasi.',
            ];
        }

        // Tambahkan tanda tangan/footer (jika diatur) ke akhir pesan.
        if ($signature = NotifikasiSettings::signature()) {
            $message = rtrim($message)."\n\n".$signature;
        }

        $payload = [
            'tenant_id' => tenant()?->getTenantKey(),
            'template_code' => $template->kode_template,
            'session' => config('notifikasi.waha_session'),
            'channel' => 'wa',
            'target' => $target,
            'message' => $message,
            'context' => $context,
        ];

        // Webhook per-tenant (Pengaturan Notifikasi) > config/env global.
        if ($url = NotifikasiSettings::webhookUrl()) {
            $response = $this->client()->post($url, $payload)->throw();

            return $this->interpret($response->json() ?: [], $target);
        }

        if ($wahaBaseUrl = config('notifikasi.waha_base_url')) {
            $response = $this->client()
                ->baseUrl(rtrim($wahaBaseUrl, '/'))
                ->post('/api/sendText', [
                    'session' => config('notifikasi.waha_session'),
                    'chatId' => $target,
                    'text' => $message,
                ])
                ->throw();

            return $this->interpret($response->json() ?: [], $target);
        }

        return [
            'status' => 'simulated',
            'message' => 'WA endpoint belum dikonfigurasi',
            'payload' => $payload,
        ];
    }

    /**
     * Kirim media (gambar/dokumen) sebagai lampiran WhatsApp via pipa n8n→WAHA
     * (atau WAHA langsung). $file: ['data' => base64, 'mimetype' => ..., 'filename' => ...]
     * atau ['url' => ...]. Footer tetap ditambahkan ke caption bila diatur.
     */
    public function sendFile(string $target, array $file, string $caption = '', array $context = []): array
    {
        if (! NotifikasiSettings::waEnabled()) {
            return [
                'status' => 'disabled',
                'message' => 'Notifikasi WhatsApp dinonaktifkan di Pengaturan Notifikasi.',
            ];
        }

        if ($signature = NotifikasiSettings::signature()) {
            $caption = trim($caption) === '' ? $signature : rtrim($caption)."\n\n".$signature;
        }

        $payload = [
            'tenant_id' => tenant()?->getTenantKey(),
            'template_code' => 'attachment',
            'session' => config('notifikasi.waha_session'),
            'channel' => 'wa',
            'target' => $target,
            'message' => $caption,
            'file' => $file,
            'context' => $context,
        ];

        // Webhook per-tenant (Pengaturan Notifikasi) > config/env global.
        if ($url = NotifikasiSettings::webhookUrl()) {
            $response = $this->client(30)->post($url, $payload)->throw();

            return $this->interpret($response->json() ?: [], $target);
        }

        if ($wahaBaseUrl = config('notifikasi.waha_base_url')) {
            $isImage = str_starts_with((string) ($file['mimetype'] ?? ''), 'image/');
            $response = $this->client(30)
                ->baseUrl(rtrim($wahaBaseUrl, '/'))
                ->post($isImage ? '/api/sendImage' : '/api/sendFile', [
                    'session' => config('notifikasi.waha_session'),
                    'chatId' => $target,
                    'file' => $file,
                    'caption' => $caption,
                ])
                ->throw();

            return $this->interpret($response->json() ?: [], $target);
        }

        return [
            'status' => 'simulated',
            'message' => 'WA endpoint belum dikonfigurasi',
            'payload' => $payload,
        ];
    }

    /**
     * Tafsirkan body respons gateway. TITIK BUTA yang ditutup di sini: pipa
     * n8n→WAHA bisa membalas HTTP 200 padahal WAHA GAGAL kirim (node "Send WAHA"
     * menelan error) → body memuat `sent: false`. Tanpa pemeriksaan ini, status
     * 2xx saja membuat pesan dianggap TERKIRIM (rasa-aman palsu). Maka: bila
     * `sent === false`, catat warning (agar terlihat/ter-alarm) DAN tandai
     * status 'failed' supaya pemanggil (WaNotificationService dll) menghitungnya
     * sebagai TIDAK terkirim.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function interpret(array $body, string $target): array
    {
        if (($body['sent'] ?? null) === false) {
            Log::warning('wa.gateway.not_sent', [
                'target' => $target,
                'tenant_id' => tenant()?->getTenantKey(),
                'gateway_status' => $body['gateway_status'] ?? null,
                'reason' => $body['reason'] ?? null,
            ]);

            return array_merge($body, ['status' => 'failed']);
        }

        return $body ?: ['status' => 'ok'];
    }

    protected function client(int $timeout = 15): PendingRequest
    {
        return Http::acceptJson()->timeout($timeout);
    }
}
