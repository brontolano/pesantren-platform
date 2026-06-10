<?php

namespace Modules\AI\Services\Audit;

/**
 * Pengukur latensi per langkah pipeline (T-A003).
 *
 * Dipakai AiGateway sekarang (router_ms, provider_ms, total_ms) dan siap
 * dipakai langkah pipeline lain (PolicyEngine, Retriever, dst — T-A020+):
 *
 *   $timer = new StepTimer();
 *   $hasil = $timer->measure('router', fn () => ...);
 *   $breakdown = $timer->breakdown(); // ['router_ms' => 12, 'total_ms' => 12]
 */
class StepTimer
{
    /** @var array<string, int> durasi per langkah dalam milidetik */
    private array $steps = [];

    private readonly float $startedAt;

    public function __construct()
    {
        $this->startedAt = microtime(true);
    }

    /**
     * Ukur satu langkah bernama. Exception dari $callback tetap dilempar,
     * tapi durasinya tetap tercatat (berguna untuk forensik kegagalan).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function measure(string $step, callable $callback): mixed
    {
        $start = microtime(true);

        try {
            return $callback();
        } finally {
            $this->record($step, (int) round((microtime(true) - $start) * 1000));
        }
    }

    /** Catat durasi langkah secara manual (akumulatif bila nama sama). */
    public function record(string $step, int $milliseconds): void
    {
        $this->steps[$step] = ($this->steps[$step] ?? 0) + max(0, $milliseconds);
    }

    /**
     * Breakdown akhir untuk kolom latency: tiap langkah ber-suffix _ms
     * + total_ms terukur sejak timer dibuat.
     *
     * @return array<string, int>
     */
    public function breakdown(): array
    {
        $result = [];

        foreach ($this->steps as $step => $ms) {
            $result["{$step}_ms"] = $ms;
        }

        $result['total_ms'] = (int) round((microtime(true) - $this->startedAt) * 1000);

        return $result;
    }
}
