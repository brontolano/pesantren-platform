<?php

namespace Modules\AI\Console;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Modules\AI\Models\AiAuditLog;
use Modules\AI\Models\AiTenantBudget;
use Modules\AI\Services\Gateway\AiGateway;
use Modules\AI\Services\Gateway\AiGenerateRequest;
use Modules\AI\Services\Metering\BudgetGuard;
use Modules\AI\Services\Prompts\PromptRegistry;
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
        {--generate-timeout=30 : Timeout (detik) untuk uji latensi generate}
        {--tenant= : Tampilkan juga ringkasan ai_audit_log hari ini untuk tenant ini (T-A003)}';

    protected $description = 'Cek kesehatan gateway AI: provider, model, latensi, antrian queue ai, circuit breaker, ringkasan audit tenant';

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
            // T-A004: pakai agen 'uji-internal' bila terdaftar — uji generate
            // ikut melewati prompt registry dan mencatat prompt_version_id.
            $agentUji = app(PromptRegistry::class)
                ->agentTerdaftar('uji-internal') ? 'uji-internal' : 'health-check';

            $result = $gateway->generate(new AiGenerateRequest(
                prompt: 'Balas dengan satu kata: siap.',
                agent: $agentUji,
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

        // 5. Ringkasan audit hari ini DI TENANT KONTEKS — hanya bila --tenant=
        // diberikan; tanpa flag = perilaku lama (central-only, bagian ini lewat).
        if (($tenantId = (string) $this->option('tenant')) !== '') {
            $rows[] = $this->tenantAuditRow($tenantId);
            $rows[] = $this->tenantBudgetRow($tenantId);
        }

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

    /**
     * T-A002: status anggaran kredit AI tenant (tabel CENTRAL ai_tenant_budget).
     * Toleran — kegagalan baca tidak menggugurkan laporan.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function tenantBudgetRow(string $tenantId): array
    {
        $label = "Anggaran '{$tenantId}'";

        try {
            $status = app(BudgetGuard::class)->cek($tenantId);
            $budget = AiTenantBudget::query()->where('tenant_id', $tenantId)->first();

            if ($budget === null) {
                return [$label, 'BELUM ADA', 'Baris ai_tenant_budget belum diprovision (jalankan tenant:reprovision).'];
            }

            if ($status->killSwitch) {
                return [$label, 'KILL-SWITCH', sprintf(
                    'Anggaran bulan ini habis (terpakai %s%%) — panggilan AI dijawab pesan top-up.',
                    number_format($status->persenTerpakai, 1, ',', '.'),
                )];
            }

            $detail = $budget->anggaran_kredit_bulanan === null
                ? sprintf('Tanpa batas anggaran — terpakai %s kredit periode ini.', $budget->terpakai_periode)
                : sprintf(
                    'Terpakai %s dari %s kredit (%s%%), alert di %d%%.',
                    $budget->terpakai_periode,
                    $budget->anggaran_kredit_bulanan,
                    number_format($status->persenTerpakai, 1, ',', '.'),
                    $budget->ambang_alert_persen,
                );

            return [$label, 'OK', $detail];
        } catch (Throwable $e) {
            return [$label, 'TAK TERJANGKAU', $e->getMessage()];
        }
    }

    /**
     * T-A003: ringkasan ai_audit_log HARI INI di DB tenant terkait.
     * Toleran — tenant tak ada / DB tak terjangkau tidak menggugurkan laporan.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function tenantAuditRow(string $tenantId): array
    {
        $label = "Audit '{$tenantId}'";

        try {
            $tenant = Tenant::query()->find($tenantId);

            if ($tenant === null) {
                return [$label, 'TIDAK DITEMUKAN', "Tenant '{$tenantId}' tidak terdaftar."];
            }

            tenancy()->initialize($tenant);

            try {
                $today = AiAuditLog::query()->whereDate('created_at', now()->toDateString());

                $total = (clone $today)->count();
                $perStatus = (clone $today)
                    ->selectRaw('status, count(*) as jumlah')
                    ->groupBy('status')
                    ->pluck('jumlah', 'status');

                $detail = $total === 0
                    ? 'Belum ada panggilan AI hari ini.'
                    : sprintf(
                        '%d panggilan hari ini (%s)',
                        $total,
                        $perStatus->map(fn ($n, $s) => "{$s}: {$n}")->implode(', '),
                    );

                return [$label, 'OK', $detail];
            } finally {
                tenancy()->end();
            }
        } catch (Throwable $e) {
            return [$label, 'TAK TERJANGKAU', $e->getMessage()];
        }
    }
}
