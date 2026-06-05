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

    // tries=1: broadcast mengirim ke banyak target dalam 1 job; retry = kirim ulang DUPLIKAT.
    public int $tries = 1;

    public function __construct(
        public readonly int $broadcastId,
        public readonly ?string $tenantId,
    ) {
        // Queue terpisah dari 'notifikasi' (OTP) agar throttle 10 dtk/pesan tak men-starve OTP.
        $this->onQueue('broadcast');
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
