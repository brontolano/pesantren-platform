<?php

namespace App\Console\Commands;

use App\Jobs\RecordPlatformWorkerHeartbeat;
use App\Models\PlatformRuntimeHeartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class PlatformHeartbeatCommand extends Command
{
    protected $signature = 'platform:heartbeat';

    protected $description = 'Catat heartbeat scheduler dan kirim ping singkat ke worker platform';

    public function handle(): int
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        PlatformRuntimeHeartbeat::query()->updateOrCreate(
            ['service_key' => 'scheduler'],
            ['last_seen_at' => now(), 'detail' => 'Penjadwal Laravel menjalankan heartbeat'],
        );

        $connections = [
            ['service' => 'worker-default', 'connection' => (string) config('queue.default'), 'queue' => 'default', 'detail' => 'Worker antrean umum'],
            ['service' => 'worker-broadcast', 'connection' => 'redis', 'queue' => 'broadcast', 'detail' => 'Worker siaran WhatsApp'],
            ['service' => 'worker-ai', 'connection' => 'redis-ai', 'queue' => 'ai', 'detail' => 'Worker modul AI'],
        ];

        $seen = [];
        foreach ($connections as $worker) {
            if (isset($seen[$worker['service']])) {
                continue;
            }
            $seen[$worker['service']] = true;

            $driver = (string) config("queue.connections.{$worker['connection']}.driver", $worker['connection']);
            if ($driver === 'sync' || $driver === 'null') {
                continue;
            }

            try {
                RecordPlatformWorkerHeartbeat::dispatch($worker['service'], $worker['detail'])
                    ->onConnection($worker['connection'])
                    ->onQueue($worker['queue']);
            } catch (Throwable $exception) {
                Log::warning('Heartbeat worker Pesantren.online gagal dikirim.', [
                    'service' => $worker['service'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->info('Heartbeat scheduler dicatat; ping worker dikirim ke antrean yang dikonfigurasi.');

        return self::SUCCESS;
    }
}
