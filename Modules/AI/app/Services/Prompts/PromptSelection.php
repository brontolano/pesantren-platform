<?php

namespace Modules\AI\Services\Prompts;

/**
 * Hasil resolusi prompt registry untuk satu (agen, tenant) — T-A004.
 */
final readonly class PromptSelection
{
    /**
     * @param  string|null  $systemPrompt  null = tanpa system prompt (gateway tetap jalan).
     * @param  int|null  $promptVersionId  Dicatat di ai_audit_log.prompt_version_id.
     * @param  bool  $enabled  false = feature flag tenant mematikan agen ini.
     * @param  string  $source  Asal keputusan: override-pin|aktif|fallback|disabled.
     */
    public function __construct(
        public ?string $systemPrompt,
        public ?int $promptVersionId,
        public bool $enabled = true,
        public string $source = 'fallback',
    ) {}

    /**
     * Bentuk cache = ARRAY PRIMITIF (lihat ModelSelection::toArray — objek
     * dari cache Redis menjadi __PHP_Incomplete_Class).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'systemPrompt' => $this->systemPrompt,
            'promptVersionId' => $this->promptVersionId,
            'enabled' => $this->enabled,
            'source' => $this->source,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            systemPrompt: isset($data['systemPrompt']) ? (string) $data['systemPrompt'] : null,
            promptVersionId: isset($data['promptVersionId']) ? (int) $data['promptVersionId'] : null,
            enabled: (bool) ($data['enabled'] ?? true),
            source: (string) ($data['source'] ?? 'fallback'),
        );
    }
}
