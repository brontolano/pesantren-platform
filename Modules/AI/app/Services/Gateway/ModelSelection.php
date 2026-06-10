<?php

namespace Modules\AI\Services\Gateway;

/**
 * Hasil keputusan ModelRouter: provider + model + batas konteks untuk satu
 * kombinasi (agent, tier). source = 'db' (ai_model_config) atau 'default'
 * (hard-default config bila tabel kosong/belum termigrasi).
 */
final readonly class ModelSelection
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public string $provider,
        public string $model,
        public int $maxContextTokens = 4096,
        public array $options = [],
        public string $source = 'default',
    ) {}
}
