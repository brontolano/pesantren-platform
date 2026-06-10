<?php

namespace Modules\AI\Services\Gateway;

/**
 * Kontrak provider LLM (K4: struktur siap multi-provider).
 *
 * Menambah provider baru = satu kelas implementasi ini + satu baris di
 * config('ai.gateway.providers') + baris ai_model_config. TANPA refactor.
 *
 * Implementasi BOLEH melempar exception (ConnectionException, AiProviderException);
 * penanganan retry/fallback/circuit breaker adalah urusan AiGateway, bukan provider.
 */
interface LlmProvider
{
    /** Nama kunci provider (harus sama dengan kunci di config & ai_model_config). */
    public function name(): string;

    public function generate(AiGenerateRequest $request, ModelSelection $selection): AiGenerateResult;

    /**
     * @param  list<string>  $inputs
     * @return list<list<float>> Satu vektor embedding per input, urutan terjaga.
     */
    public function embed(array $inputs, ModelSelection $selection): array;

    /**
     * Pemeriksaan kesehatan ringan (tanpa inferensi berat).
     *
     * @return array{ok: bool, detail: string, models: list<string>, latency_ms: int|null}
     */
    public function health(): array;
}
