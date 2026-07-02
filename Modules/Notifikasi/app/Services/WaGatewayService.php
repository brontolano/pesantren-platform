<?php

namespace Modules\Notifikasi\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
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

            return $response->json() ?: ['status' => 'ok'];
        }

        if ($wahaBaseUrl = config('notifikasi.waha_base_url')) {
            $response = $this->wahaClient()
                ->baseUrl(rtrim($wahaBaseUrl, '/'))
                ->post('/api/sendText', [
                    'session' => config('notifikasi.waha_session'),
                    'chatId' => $target,
                    'text' => $message,
                ])
                ->throw();

            return $response->json() ?: ['status' => 'ok'];
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

            return $response->json() ?: ['status' => 'ok'];
        }

        if ($wahaBaseUrl = config('notifikasi.waha_base_url')) {
            $isImage = str_starts_with((string) ($file['mimetype'] ?? ''), 'image/');
            $response = $this->wahaClient(30)
                ->baseUrl(rtrim($wahaBaseUrl, '/'))
                ->post($isImage ? '/api/sendImage' : '/api/sendFile', [
                    'session' => config('notifikasi.waha_session'),
                    'chatId' => $target,
                    'file' => $file,
                    'caption' => $caption,
                ])
                ->throw();

            return $response->json() ?: ['status' => 'ok'];
        }

        return [
            'status' => 'simulated',
            'message' => 'WA endpoint belum dikonfigurasi',
            'payload' => $payload,
        ];
    }

    protected function client(int $timeout = 15): PendingRequest
    {
        return Http::acceptJson()->timeout($timeout);
    }

    /**
     * Klien untuk WAHA langsung — menambahkan header X-Api-Key bila WAHA
     * dijalankan dengan auth (config notifikasi.waha_api_key / Setting per-tenant).
     */
    protected function wahaClient(int $timeout = 15): PendingRequest
    {
        $client = $this->client($timeout);

        if ($apiKey = NotifikasiSettings::wahaApiKey()) {
            $client = $client->withHeaders(['X-Api-Key' => $apiKey]);
        }

        return $client;
    }
}
