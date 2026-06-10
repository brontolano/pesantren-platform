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
}
