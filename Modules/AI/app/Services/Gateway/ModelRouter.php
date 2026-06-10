<?php

namespace Modules\AI\Services\Gateway;

use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Modules\AI\Models\AiModelConfig;
use RuntimeException;

/**
 * Router model per (agent, tier).
 *
 * Sumber kebenaran: tabel CENTRAL ai_model_config (cache 60 dtk) —
 * baris spesifik agent_key menang atas baris default (agent_key NULL).
 * Fallback: hard-default config('ai.router.defaults') bila tabel kosong
 * atau belum termigrasi (mis. lingkungan lama) — gateway tak boleh mati
 * hanya karena konfigurasi router belum di-seed.
 */
class ModelRouter
{
    private const CACHE_PREFIX = 'ai:router:';

    public function resolve(string $agent, string $tier): ModelSelection
    {
        $tiers = (array) config('ai.router.tiers', []);

        if (! in_array($tier, $tiers, true)) {
            throw new InvalidArgumentException(sprintf(
                "Tier AI tidak dikenal: '%s'. Tier yang tersedia: %s.",
                $tier,
                implode(', ', $tiers),
            ));
        }

        // CentralCache (bukan facade Cache): keputusan router bersumber tabel
        // CENTRAL — jangan ter-fragmentasi per tenant oleh CacheTenancyBootstrapper.
        return CentralCache::repository()->remember(
            self::CACHE_PREFIX.$agent.':'.$tier,
            (int) config('ai.router.cache_seconds', 60),
            fn (): ModelSelection => $this->lookup($agent, $tier),
        );
    }

    /** Pintasan untuk tier embedding. */
    public function resolveEmbedding(string $agent = 'default'): ModelSelection
    {
        return $this->resolve($agent, 'embedding');
    }

    /** Hapus cache keputusan satu kombinasi (dipakai test/panel operator). */
    public function forget(string $agent, string $tier): void
    {
        CentralCache::repository()->forget(self::CACHE_PREFIX.$agent.':'.$tier);
    }

    private function lookup(string $agent, string $tier): ModelSelection
    {
        $row = null;

        try {
            $row = AiModelConfig::query()
                ->where('is_active', true)
                ->where('tier', $tier)
                ->where(function ($q) use ($agent): void {
                    $q->where('agent_key', $agent)->orWhereNull('agent_key');
                })
                ->orderByRaw('agent_key IS NULL') // baris spesifik agen lebih dulu
                ->first();
        } catch (QueryException) {
            // Tabel belum ada (lingkungan belum migrasi) → pakai default config.
        }

        if ($row !== null) {
            return new ModelSelection(
                provider: $row->provider,
                model: $row->model,
                maxContextTokens: (int) $row->max_context_tokens,
                options: (array) ($row->options ?? []),
                source: 'db',
                tier: $tier,
            );
        }

        $default = config("ai.router.defaults.{$tier}");

        if (! is_array($default) || ! isset($default['provider'], $default['model'])) {
            throw new RuntimeException(
                "Konfigurasi default router AI untuk tier '{$tier}' tidak ditemukan — "
                .'periksa config ai.router.defaults atau seed tabel ai_model_config.',
            );
        }

        return new ModelSelection(
            provider: (string) $default['provider'],
            model: (string) $default['model'],
            maxContextTokens: (int) ($default['max_context_tokens'] ?? 4096),
            options: (array) ($default['options'] ?? []),
            source: 'default',
            tier: $tier,
        );
    }
}
