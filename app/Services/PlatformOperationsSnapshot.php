<?php

namespace App\Services;

use App\Models\PlatformRuntimeHeartbeat;
use App\Models\PlatformSupportTicket;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PlatformOperationsSnapshot
{
    /** @return array<string, mixed> */
    public function capture(): array
    {
        $central = (string) config('tenancy.database.central_connection', config('database.default'));
        $database = ['label' => 'Database pusat', 'status' => 'ok', 'detail' => 'Koneksi dan query berhasil'];
        try {
            DB::connection($central)->select('select 1');
        } catch (Throwable) {
            $database = ['label' => 'Database pusat', 'status' => 'error', 'detail' => 'Koneksi database pusat gagal'];
        }

        $queueConfig = (string) config('queue.default');
        $queueDriver = (string) config("queue.connections.{$queueConfig}.driver", $queueConfig);
        $queue = ['label' => 'Antrean pekerjaan', 'status' => 'ok', 'detail' => 'Pengandar '.$queueDriver.' siap', 'pending' => null];
        try {
            $queue['pending'] = (int) Queue::connection($queueConfig)->size();
            $queue['detail'] = $queueDriver === 'sync'
                ? 'Mode sinkron; pekerjaan dijalankan langsung'
                : number_format($queue['pending'], 0, ',', '.').' pekerjaan menunggu';
        } catch (Throwable) {
            $queue['status'] = 'error';
            $queue['detail'] = 'Antrean tidak dapat dihubungi; periksa konfigurasi worker';
        }

        $failedJobs = null;
        try {
            if (Schema::connection($central)->hasTable('failed_jobs')) {
                $failedJobs = DB::connection($central)->table('failed_jobs')->count();
                if ($failedJobs > 0 && $queue['status'] === 'ok') {
                    $queue['status'] = 'warning';
                    $queue['detail'] .= '; '.number_format($failedJobs, 0, ',', '.').' pekerjaan gagal perlu ditinjau';
                }
            }
        } catch (Throwable) {
            $failedJobs = null;
        }

        $support = ['available' => false, 'open' => null, 'urgent' => null];
        try {
            if (Schema::connection($central)->hasTable('platform_support_tickets')) {
                $pending = PlatformSupportTicket::query()->whereIn('status', ['open', 'waiting']);
                $support = [
                    'available' => true,
                    'open' => (clone $pending)->count(),
                    'urgent' => (clone $pending)->whereIn('priority', ['high', 'urgent'])->count(),
                ];
            }
        } catch (Throwable) {
            // Indikator ketersediaan tetap false bila tabel/DB komunikasi belum siap.
        }

        $tenants = ['available' => true, 'total' => null, 'enabled' => null, 'disabled' => null];
        try {
            // One aggregate round-trip is enough for all three dashboard totals.
            $summary = Tenant::query()
                ->selectRaw('COUNT(*) AS total')
                ->selectRaw('COALESCE(SUM(CASE WHEN is_active = ? THEN 1 ELSE 0 END), 0) AS enabled', [true])
                ->selectRaw('COALESCE(SUM(CASE WHEN is_active = ? THEN 0 ELSE 1 END), 0) AS disabled', [true])
                ->first();

            $tenants['total'] = (int) $summary->total;
            $tenants['enabled'] = (int) $summary->enabled;
            $tenants['disabled'] = (int) $summary->disabled;
        } catch (Throwable) {
            $tenants['available'] = false;
        }

        $runtime = [];
        $services = [
            ['key' => 'scheduler', 'label' => 'Scheduler Laravel', 'detail' => 'Pekerjaan terjadwal dan heartbeat aplikasi'],
        ];
        if ($queueDriver !== 'sync' && $queueDriver !== 'null') {
            $services[] = ['key' => 'worker-default', 'label' => 'Worker antrean umum', 'detail' => 'Antrean default dan notifikasi'];
            if (config('queue.connections.redis.driver') === 'redis') {
                $services[] = ['key' => 'worker-broadcast', 'label' => 'Worker siaran', 'detail' => 'Antrean broadcast WhatsApp'];
            }
            if (config('queue.connections.redis-ai.driver') === 'redis') {
                $services[] = ['key' => 'worker-ai', 'label' => 'Worker modul AI', 'detail' => 'Antrean pekerjaan AI'];
            }
        }
        foreach ($services as $service) {
            try {
                $heartbeat = PlatformRuntimeHeartbeat::query()->find($service['key']);
                $ageSeconds = $heartbeat?->last_seen_at?->diffInSeconds(now());
                $status = match (true) {
                    $heartbeat === null => 'missing',
                    $ageSeconds > 180 => 'stale',
                    default => 'ok',
                };
                $runtime[] = [
                    'label' => $service['label'],
                    'status' => $status,
                    'detail' => $heartbeat
                        ? ($status === 'ok' ? ($heartbeat->detail ?: $service['detail']) : 'Heartbeat terakhir '.($ageSeconds === null ? 'tidak diketahui' : floor($ageSeconds / 60).' menit lalu'))
                        : 'Belum ada heartbeat; periksa scheduler, worker, dan migrasi runtime.',
                    'last_seen_at' => $heartbeat?->last_seen_at,
                ];
            } catch (Throwable) {
                $runtime[] = [
                    'label' => $service['label'],
                    'status' => 'unknown',
                    'detail' => 'Tabel heartbeat belum tersedia; jalankan migrasi pusat.',
                    'last_seen_at' => null,
                ];
            }
        }

        return [
            'checked_at' => now(),
            'checks' => [$database, $queue],
            'runtime' => $runtime,
            'failed_jobs' => $failedJobs,
            'support' => $support,
            'tenants' => $tenants,
        ];
    }
}
