<?php

namespace Modules\AI\Services\Gateway;

/**
 * Hasil generate dari gateway.
 *
 * degraded=true berarti jawaban berasal dari NullProvider (semua provider
 * nyata gagal/sirkuit terbuka) — pemanggil TIDAK menerima exception, cukup
 * menampilkan teks santun ini dan tidak memotong kredit penuh.
 */
final readonly class AiGenerateResult
{
    /**
     * @param  array<string, mixed>  $raw  Payload mentah provider (untuk audit/debug).
     */
    public function __construct(
        public string $text,
        public string $provider,
        public string $model,
        public bool $degraded = false,
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
        public ?int $latencyMs = null,
        public array $raw = [],
    ) {}
}
