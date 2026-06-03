<?php

namespace Modules\Notifikasi\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Modules\Notifikasi\Models\TemplateNotifikasi;

class WaGatewayService
{
    public function send(string $target, TemplateNotifikasi $template, string $message, array $context = []): array
    {
        $payload = [
            'tenant_id' => tenant()?->getTenantKey(),
            'template_code' => $template->kode_template,
            'session' => config('notifikasi.waha_session'),
            'channel' => 'wa',
            'target' => $target,
            'message' => $message,
            'context' => $context,
        ];

        if ($url = config('notifikasi.n8n_webhook_url')) {
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
