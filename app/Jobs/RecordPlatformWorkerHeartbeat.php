<?php

namespace App\Jobs;

use App\Models\PlatformRuntimeHeartbeat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecordPlatformWorkerHeartbeat implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $serviceKey, public string $detail) {}

    public function handle(): void
    {
        PlatformRuntimeHeartbeat::query()->updateOrCreate(
            ['service_key' => $this->serviceKey],
            ['last_seen_at' => now(), 'detail' => $this->detail],
        );
    }
}
