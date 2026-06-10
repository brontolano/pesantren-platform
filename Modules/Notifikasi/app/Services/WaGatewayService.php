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
            $response = $this->client()
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

    protected function client(): PendingRequest
    {
        return Http::acceptJson()->timeout(15);
    }
}
