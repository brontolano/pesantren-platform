<?php

namespace Modules\AI\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Modules\AI\Services\Gateway\AiGateway;
use Modules\AI\Services\Gateway\AiGenerateRequest;
use Throwable;

/**
 * ai:health — pemeriksaan kesehatan gateway LLM.
 *
 * Memeriksa: provider terjangkau + daftar model, latensi generate singkat,
 * kedalaman antrian queue 'ai' (Redis), dan status circuit breaker.
 * Exit code != 0 bila tidak ada provider nyata yang sehat (NullProvider-only).
 */
class AiHealthCommand extends Command
{
    protected $signature = 'ai:health
        {--skip-generate : Lewati uji latensi generate (hanya cek konektivitas)}
        {--generate-timeout=30 : Timeout (detik) untuk uji latensi generate}';

    protected $description = 'Cek kesehatan gateway AI: provider, model, latensi, antrian queue ai, circuit breaker';

    public function handle(AiGateway $gateway): int
    {
        $rows = [];
        $healthy = false;

        // 1. Kesehatan tiap provider nyata (konektivitas + daftar model).
        foreach ($gateway->providerHealth() as $name => $health) {
            $healthy = $healthy || $health['ok'];

            $rows[] = [
                "Provider {$name}",
                $health['ok'] ? 'OK' : 'GAGAL',
                $health['detail'],
            ];

            if ($health['models'] !== []) {
                $rows[] = [
                    "Model {$name}",
                    'OK',
                    implode(', ', $health['models']),
                ];
            }
        }

        // 2. Status circuit breaker per provider (toleran bila cache store
        // tidak terjangkau dari CLI lokal — jangan gugurkan seluruh laporan).
        try {
            foreach ($gateway->circuitStates() as $name => $state) {
                $rows[] = [
                    "Sirkuit {$name}",
                    $state === 'closed' ? 'OK' : strtoupper($state),
                    $state === 'open'
                        ? 'Terbuka — permintaan langsung jatuh ke fallback'
                        : ($state === 'half_open' ? 'Masa percobaan pemulihan' : 'Normal'),
                ];
            }
        } catch (Throwable $e) {
            $rows[] = ['Sirkuit', 'TAK TERJANGKAU', 'Cache store tidak dapat diakses: '.$e->getMessage()];
        }

        // 3. Latensi generate singkat melalui gateway (pintu resmi satu-satunya).
        if ($healthy && ! $this->option('skip-generate')) {
            $result = $gateway->generate(new AiGenerateRequest(
                prompt: 'Balas dengan satu kata: siap.',
                agent: 'health-check',
                tier: 'ekonomis',
                options: ['num_predict' => 8],
                timeoutSeconds: (int) $this->option('generate-timeout'),
            ));

            if ($result->degraded) {
                $healthy = false;
                $rows[] = ['Uji generate', 'GAGAL', 'Jawaban degradasi (provider nyata tidak merespons)'];
            } else {
                $rows[] = [
                    'Uji generate',
                    'OK',
                    sprintf('%s/%s — %d ms', $result->provider, $result->model, $result->latencyMs ?? 0),
                ];
            }
        }

        // 4. Kedalaman antrian queue 'ai' di Redis.
        $rows[] = $this->queueDepthRow();

        $this->table(['Komponen', 'Status', 'Detail'], $rows);

        if (! $healthy) {
            $this->error('Gateway AI TIDAK sehat: tidak ada provider nyata yang merespons (mode NullProvider-only).');

            return self::FAILURE;
        }

        $this->info('Gateway AI sehat.');

        return self::SUCCESS;
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function queueDepthRow(): array
    {
        $queue = (string) config('ai.queue.name', 'ai');
        $queueConnection = (string) config('ai.queue.connection', 'redis-ai');

        if (! is_array(config("queue.connections.{$queueConnection}"))) {
            // Jangan diam-diam jatuh ke koneksi Redis default — itu melaporkan
            // kedalaman antrian dari Redis DB yang salah sebagai "OK".
            return [
                "Antrian '{$queue}'",
                'TAK TERKONFIGURASI',
                "Koneksi queue '{$queueConnection}' tidak ada di config/queue.php",
            ];
        }

        $connection = (string) config("queue.connections.{$queueConnection}.connection", 'default');

        try {
            $redis = Redis::connection($connection);
            $pending = (int) $redis->llen("queues:{$queue}");
            $delayed = (int) $redis->zcard("queues:{$queue}:delayed");
            $reserved = (int) $redis->zcard("queues:{$queue}:reserved");

            return [
                "Antrian '{$queue}'",
                'OK',
                sprintf('%d menunggu, %d tertunda, %d diproses', $pending, $delayed, $reserved),
            ];
        } catch (Throwable $e) {
            return ["Antrian '{$queue}'", 'TAK TERJANGKAU', $e->getMessage()];
        }
    }
}
