<?php

namespace Modules\AI\Services\Gateway;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Provider Ollama self-hosted (keputusan K3) — container internal VPS,
 * endpoint /api/generate (non-stream) dan /api/embed.
 *
 * Satu-satunya tempat (selain config modul) yang boleh tahu alamat Ollama.
 * Exception koneksi/timeout dibiarkan naik — AiGateway yang memutuskan
 * retry/fallback/circuit breaker.
 */
class OllamaProvider implements LlmProvider
{
    public function name(): string
    {
        return 'ollama';
    }

    public function generate(AiGenerateRequest $request, ModelSelection $selection): AiGenerateResult
    {
        $timeout = $request->timeoutSeconds ?? (int) config('ai.gateway.timeout_chat', 120);
        $start = hrtime(true);

        $response = $this->http($timeout)->post('/api/generate', array_filter([
            'model' => $selection->model,
            'prompt' => $request->prompt,
            'system' => $request->system,
            'stream' => false,
            'options' => array_merge(
                ['num_ctx' => $selection->maxContextTokens],
                $selection->options,
                $request->options,
            ),
        ], static fn ($v) => $v !== null));

        $this->guardResponse($response, '/api/generate');

        $json = (array) $response->json();

        if (! array_key_exists('response', $json) || ! is_string($json['response'])) {
            // HTTP 200 tanpa field 'response' (model masih loading / body tak
            // terduga) = kegagalan provider, bukan "sukses kosong" — biarkan
            // breaker mencatat gagal dan rantai fallback bekerja.
            throw new AiProviderException(
                'Respons generate Ollama tidak memuat field response.',
                provider: $this->name(),
            );
        }

        return new AiGenerateResult(
            text: $json['response'],
            provider: $this->name(),
            model: $selection->model,
            degraded: false,
            promptTokens: isset($json['prompt_eval_count']) ? (int) $json['prompt_eval_count'] : null,
            completionTokens: isset($json['eval_count']) ? (int) $json['eval_count'] : null,
            latencyMs: (int) ((hrtime(true) - $start) / 1_000_000),
            raw: $json,
        );
    }

    public function embed(array $inputs, ModelSelection $selection): array
    {
        $timeout = (int) config('ai.gateway.timeout_embed', 30);

        $response = $this->http($timeout)->post('/api/embed', [
            'model' => $selection->model,
            'input' => array_values($inputs),
        ]);

        $this->guardResponse($response, '/api/embed');

        $embeddings = $response->json('embeddings');

        if (! is_array($embeddings)) {
            throw new AiProviderException(
                'Respons embed Ollama tidak memuat field embeddings.',
                provider: $this->name(),
            );
        }

        return $embeddings;
    }

    public function health(): array
    {
        $start = hrtime(true);

        try {
            $response = $this->http(5)->get('/api/tags');
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'detail' => 'Tidak terjangkau: '.$e->getMessage(),
                'models' => [],
                'latency_ms' => null,
            ];
        }

        if ($response->failed()) {
            return [
                'ok' => false,
                'detail' => 'HTTP '.$response->status(),
                'models' => [],
                'latency_ms' => null,
            ];
        }

        $models = collect((array) $response->json('models', []))
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        return [
            'ok' => true,
            'detail' => 'Terhubung, '.count($models).' model tersedia',
            'models' => $models,
            'latency_ms' => (int) ((hrtime(true) - $start) / 1_000_000),
        ];
    }

    private function http(int $timeout): PendingRequest
    {
        return Http::baseUrl((string) config('ai.ollama.base_url', 'http://ollama:11434'))
            ->connectTimeout((int) config('ai.gateway.connect_timeout', 5))
            ->timeout($timeout)
            ->acceptJson()
            ->asJson();
    }

    private function guardResponse(Response $response, string $endpoint): void
    {
        if ($response->failed()) {
            throw new AiProviderException(
                sprintf(
                    'Ollama %s gagal (HTTP %d): %s',
                    $endpoint,
                    $response->status(),
                    mb_substr((string) $response->body(), 0, 300),
                ),
                provider: $this->name(),
                statusCode: $response->status(),
            );
        }
    }
}
