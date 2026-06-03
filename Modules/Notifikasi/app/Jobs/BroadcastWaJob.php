<?php

namespace Modules\Notifikasi\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Notifikasi\Models\BroadcastWa;
use Modules\Notifikasi\Services\BroadcastWaService;

class BroadcastWaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $broadcastId,
        public readonly ?string $tenantId,
    ) {
        $this->onQueue('notifikasi');
    }

    public function handle(BroadcastWaService $service): void
    {
        if ($this->tenantId) {
            tenancy()->initialize($this->tenantId);
        }

        $broadcast = BroadcastWa::query()->with('template')->findOrFail($this->broadcastId);
        $service->execute($broadcast);

        if (tenancy()->initialized) {
            tenancy()->end();
        }
    }
}
