<?php

namespace Modules\AI\Services\Gateway;

/**
 * Permintaan generate ke gateway. DTO immutable — dibangun pemanggil
 * (Orchestrator/job), diteruskan apa adanya ke provider terpilih.
 */
final readonly class AiGenerateRequest
{
    /**
     * @param  string  $prompt  Prompt utama (sudah melewati redaksi PII di hulu).
     * @param  string|null  $system  System prompt (dari prompt registry, T-A004).
     * @param  string  $agent  Kunci agen (mis. chatbot_wali, generator_soal).
     * @param  string  $tier  ekonomis|standar|premium — diteruskan ke ModelRouter.
     * @param  array<string, mixed>  $options  Opsi inferensi tambahan (num_predict, temperature, ...).
     * @param  int|null  $timeoutSeconds  Override timeout chat (null = config).
     */
    public function __construct(
        public string $prompt,
        public ?string $system = null,
        public string $agent = 'default',
        public string $tier = 'standar',
        public array $options = [],
        public ?int $timeoutSeconds = null,
    ) {}
}
