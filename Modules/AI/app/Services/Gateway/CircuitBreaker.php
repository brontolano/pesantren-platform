<?php

namespace Modules\AI\Services\Gateway;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Circuit breaker sederhana berbasis Cache, per kunci (per provider).
 *
 * Status:
 *  - closed    : normal, permintaan diizinkan.
 *  - open      : >= N kegagalan beruntun dalam jendela M detik → permintaan
 *                DILEWATI (langsung fallback) selama cooldown.
 *  - half_open : cooldown habis tapi penghitung gagal masih tinggi → satu
 *                gelombang percobaan diizinkan; sukses = pulih (closed),
 *                gagal = sirkuit terbuka lagi.
 */
class CircuitBreaker
{
    private const PREFIX = 'ai:cb:';

    public function __construct(
        private readonly int $failureThreshold = 5,
        private readonly int $windowSeconds = 300,
        private readonly int $cooldownSeconds = 60,
        private readonly ?string $store = null,
    ) {}

    public static function fromConfig(): self
    {
        $cfg = (array) config('ai.gateway.circuit_breaker', []);

        return new self(
            failureThreshold: (int) ($cfg['failure_threshold'] ?? 5),
            windowSeconds: (int) ($cfg['window_seconds'] ?? 300),
            cooldownSeconds: (int) ($cfg['cooldown_seconds'] ?? 60),
            store: $cfg['store'] ?? null,
        );
    }

    /** Permintaan boleh diteruskan? (closed atau half_open) */
    public function allowRequest(string $key): bool
    {
        return ! $this->isOpen($key);
    }

    public function isOpen(string $key): bool
    {
        return (bool) $this->cache()->get($this->openKey($key), false);
    }

    public function state(string $key): string
    {
        if ($this->isOpen($key)) {
            return 'open';
        }

        if ((int) $this->cache()->get($this->failureKey($key), 0) >= $this->failureThreshold) {
            return 'half_open';
        }

        return 'closed';
    }

    public function recordSuccess(string $key): void
    {
        $this->cache()->forget($this->failureKey($key));
        $this->cache()->forget($this->openKey($key));
    }

    public function recordFailure(string $key): void
    {
        $cache = $this->cache();
        $failureKey = $this->failureKey($key);

        // add() menetapkan TTL jendela hanya saat kunci belum ada — kegagalan
        // beruntun dihitung dalam jendela M detik sejak kegagalan pertama.
        $cache->add($failureKey, 0, $this->windowSeconds);
        $failures = (int) $cache->increment($failureKey);

        if ($failures >= $this->failureThreshold) {
            // Buka sirkuit selama cooldown; setelah kadaluarsa otomatis half-open.
            $cache->put($this->openKey($key), true, $this->cooldownSeconds);
        }
    }

    public function reset(string $key): void
    {
        $this->recordSuccess($key);
    }

    private function failureKey(string $key): string
    {
        return self::PREFIX.$key.':failures';
    }

    private function openKey(string $key): string
    {
        return self::PREFIX.$key.':open';
    }

    private function cache(): Repository
    {
        return Cache::store($this->store);
    }
}
