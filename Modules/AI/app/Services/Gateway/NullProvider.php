<?php

namespace Modules\AI\Services\Gateway;

/**
 * Provider degradasi anggun — jaring pengaman terakhir rantai fallback.
 *
 * Selalu "sukses": balasan statis santun + degraded=true, supaya kegagalan
 * LLM tidak pernah menjadi exception bagi pemanggil gateway dan fitur inti
 * platform tidak terdampak (PRD §11).
 */
class NullProvider implements LlmProvider
{
    public function name(): string
    {
        return 'null';
    }

    public function generate(AiGenerateRequest $request, ModelSelection $selection): AiGenerateResult
    {
        return new AiGenerateResult(
            text: (string) config(
                'ai.gateway.degraded_message',
                'Mohon maaf, asisten sedang sibuk. Silakan coba beberapa saat lagi.',
            ),
            provider: $this->name(),
            model: 'null',
            degraded: true,
        );
    }

    public function embed(array $inputs, ModelSelection $selection): array
    {
        // Tidak ada embedding yang bermakna saat degradasi — kembalikan kosong;
        // pemanggil (IngestService dst) wajib memperlakukan [] sebagai gagal-tunda.
        return [];
    }

    public function health(): array
    {
        return [
            'ok' => true,
            'detail' => 'Selalu aktif (mode degradasi)',
            'models' => [],
            'latency_ms' => 0,
        ];
    }
}
