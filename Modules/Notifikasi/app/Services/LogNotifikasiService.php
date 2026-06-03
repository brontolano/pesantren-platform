<?php

namespace Modules\Notifikasi\Services;

use Illuminate\Support\Arr;
use Modules\Notifikasi\Models\BroadcastWa;
use Modules\Notifikasi\Models\LogNotifikasi;
use Modules\Notifikasi\Models\TemplateNotifikasi;

class LogNotifikasiService
{
    public function create(array $attributes): LogNotifikasi
    {
        return LogNotifikasi::create([
            'broadcast_id' => Arr::get($attributes, 'broadcast_id'),
            'template_id' => Arr::get($attributes, 'template_id'),
            'jenis' => Arr::get($attributes, 'jenis', 'broadcast'),
            'channel' => Arr::get($attributes, 'channel', 'wa'),
            'target' => Arr::get($attributes, 'target'),
            'status' => Arr::get($attributes, 'status', 'queued'),
            'error_message' => Arr::get($attributes, 'error_message'),
            'payload' => Arr::get($attributes, 'payload'),
            'created_at' => now(),
        ]);
    }

    public function forSuccess(BroadcastWa $broadcast, string $target, array $payload = []): LogNotifikasi
    {
        return $this->create([
            'broadcast_id' => $broadcast->id,
            'template_id' => $broadcast->template_id,
            'jenis' => 'broadcast',
            'channel' => $broadcast->template->channel,
            'target' => $target,
            'status' => 'sent',
            'payload' => $payload,
        ]);
    }

    public function forFailure(
        BroadcastWa $broadcast,
        string $target,
        string $errorMessage,
        array $payload = []
    ): LogNotifikasi {
        return $this->create([
            'broadcast_id' => $broadcast->id,
            'template_id' => $broadcast->template_id,
            'jenis' => 'broadcast',
            'channel' => $broadcast->template->channel,
            'target' => $target,
            'status' => 'failed',
            'error_message' => $errorMessage,
            'payload' => $payload,
        ]);
    }

    public function forEmail(TemplateNotifikasi $template, string $target, array $payload = []): LogNotifikasi
    {
        return $this->create([
            'template_id' => $template->id,
            'jenis' => 'direct',
            'channel' => 'email',
            'target' => $target,
            'status' => 'sent',
            'payload' => $payload,
        ]);
    }
}
