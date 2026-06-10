<?php

namespace Modules\AI\Services\Rag;

use Modules\AI\Models\AiChunk;
use Modules\AI\Models\AiKnowledgeSource;

/**
 * T-A031 — hasil retrieval hybrid.
 *
 * chunks terurut skor fusi RRF menurun, maksimal top-K.
 * adaSumber=false = korpus kosong / tak ada yang relevan → pemanggil
 * (RagAnswerer) WAJIB jalur "tidak ada rujukan" TANPA LLM.
 */
final readonly class RetrievalResult
{
    /**
     * @param  list<array{chunk: AiChunk, skor: float, sumber: AiKnowledgeSource}>  $chunks
     */
    public function __construct(
        public array $chunks,
        public bool $adaSumber,
    ) {}

    public static function kosong(): self
    {
        return new self([], false);
    }
}
