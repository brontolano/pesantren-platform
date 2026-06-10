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
        /** Tier asal keputusan — dipakai gateway untuk memilih model default
         *  milik provider fallback saat fallback lintas-provider. */
        public string $tier = '',
    ) {}

    /**
     * Bentuk cache = ARRAY PRIMITIF, bukan objek: store produksi (Redis)
     * membatasi allowed_classes saat unserialize sehingga objek dari cache
     * menjadi __PHP_Incomplete_Class (temuan smoke produksi T-A031).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'maxContextTokens' => $this->maxContextTokens,
            'options' => $this->options,
            'source' => $this->source,
            'tier' => $this->tier,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            provider: (string) ($data['provider'] ?? 'null'),
            model: (string) ($data['model'] ?? 'null'),
            maxContextTokens: (int) ($data['maxContextTokens'] ?? 4096),
            options: (array) ($data['options'] ?? []),
            source: (string) ($data['source'] ?? 'default'),
            tier: (string) ($data['tier'] ?? ''),
        );
    }
}
