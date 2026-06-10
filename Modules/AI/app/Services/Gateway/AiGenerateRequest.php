<?php

namespace Modules\AI\Services\Gateway;

/**
 * Permintaan generate ke gateway. DTO immutable — dibangun pemanggil
 * (Orchestrator/job), diteruskan apa adanya ke provider terpilih.
 */
final readonly class AiGenerateRequest
{
    /**
     * @param  string  $prompt  Prompt utama (gateway meredaksi PII sebelum provider, T-A005).
     * @param  string|null  $system  System prompt (dari prompt registry, T-A004).
     * @param  string|null  $context  Konten eksternal (dokumen/RAG) — gateway mensanitasi
     *                                & membungkusnya sebagai blok DATA (T-A005), lalu
     *                                menempelkannya ke prompt sebelum provider.
     * @param  string  $agent  Kunci agen (mis. chatbot_wali, generator_soal).
     * @param  string  $tier  ekonomis|standar|premium — diteruskan ke ModelRouter.
     * @param  array<string, mixed>  $options  Opsi inferensi tambahan (num_predict, temperature, ...).
     * @param  int|null  $timeoutSeconds  Override timeout chat (null = config).
     * @param  string  $channel  Kanal asal (web/wa/internal/cli) — kolom audit T-A003.
     * @param  int|null  $userId  Pengguna pemicu (null = sistem/CLI) — kolom audit.
     * @param  string|null  $idempotencyKey  Kunci idempotensi baris audit (unik; null = tanpa dedup).
     * @param  string|null  $ringkasanKonteks  Ringkasan konteks TERREDAKSI untuk audit (jangan kirim PII mentah).
     */
    public function __construct(
        public string $prompt,
        public ?string $system = null,
        public ?string $context = null,
        public string $agent = 'default',
        public string $tier = 'standar',
        public array $options = [],
        public ?int $timeoutSeconds = null,
        public string $channel = 'internal',
        public ?int $userId = null,
        public ?string $idempotencyKey = null,
        public ?string $ringkasanKonteks = null,
    ) {}

    /**
     * Salinan request dengan system prompt terisi (dipakai gateway saat
     * menyuntik hasil PromptRegistry, T-A004) — DTO tetap immutable.
     */
    public function withSystem(?string $system): self
    {
        return $this->salin(system: $system);
    }

    /**
     * Salinan request hasil langkah privasi T-A005 (PiiRedactor +
     * ContentSanitizer di gateway): prompt sudah tergabung konteks-tersanitasi
     * dan terredaksi PII; context dinolkan (sudah menyatu di prompt);
     * ringkasanKonteks ikut terredaksi untuk audit. DTO tetap immutable.
     */
    public function withPiiTerredaksi(string $prompt, ?string $ringkasanKonteks): self
    {
        return $this->salin(
            prompt: $prompt,
            context: null,
            ringkasanKonteks: $ringkasanKonteks,
            pakaiContextBaru: true,
            pakaiRingkasanBaru: true,
        );
    }

    private function salin(
        ?string $prompt = null,
        ?string $system = null,
        ?string $context = null,
        ?string $ringkasanKonteks = null,
        bool $pakaiContextBaru = false,
        bool $pakaiRingkasanBaru = false,
    ): self {
        return new self(
            prompt: $prompt ?? $this->prompt,
            system: $system ?? $this->system,
            context: $pakaiContextBaru ? $context : $this->context,
            agent: $this->agent,
            tier: $this->tier,
            options: $this->options,
            timeoutSeconds: $this->timeoutSeconds,
            channel: $this->channel,
            userId: $this->userId,
            idempotencyKey: $this->idempotencyKey,
            ringkasanKonteks: $pakaiRingkasanBaru ? $ringkasanKonteks : $this->ringkasanKonteks,
        );
    }
}
