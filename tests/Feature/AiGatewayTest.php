<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\AI\Services\Gateway\AiGateway;
use Modules\AI\Services\Gateway\AiGenerateRequest;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * AiGateway (T-A001) dengan Http::fake:
 *  - generate sukses via Ollama;
 *  - timeout/error provider → hasil NullProvider degraded=true TANPA exception;
 *  - retry idempoten HANYA pada connection error (assert jumlah panggilan HTTP);
 *  - circuit breaker terbuka → tidak ada panggilan HTTP, langsung degradasi.
 */
class AiGatewayTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['ai_model_config']);
        DB::table('ai_model_config')->truncate();
        Cache::flush();
    }

    private function gateway(): AiGateway
    {
        return app(AiGateway::class);
    }

    private function request(): AiGenerateRequest
    {
        return new AiGenerateRequest(prompt: 'Assalamualaikum', agent: 'uji', tier: 'ekonomis');
    }

    public function test_generate_sukses_via_ollama(): void
    {
        Http::fake([
            '*/api/generate' => Http::response([
                'response' => 'Waalaikumussalam warahmatullah.',
                'prompt_eval_count' => 12,
                'eval_count' => 7,
            ]),
        ]);

        $result = $this->gateway()->generate($this->request());

        $this->assertFalse($result->degraded);
        $this->assertSame('ollama', $result->provider);
        $this->assertSame(config('ai.router.defaults.ekonomis.model'), $result->model);
        $this->assertSame('Waalaikumussalam warahmatullah.', $result->text);
        $this->assertSame(12, $result->promptTokens);
        $this->assertSame(7, $result->completionTokens);

        Http::assertSentCount(1);
    }

    public function test_timeout_jatuh_ke_null_provider_tanpa_exception_dan_tanpa_retry(): void
    {
        $panggilan = 0;
        Http::fake(function () use (&$panggilan): void {
            $panggilan++;

            throw new ConnectionException('cURL error 28: Operation timed out after 120000 milliseconds');
        });

        $result = $this->gateway()->generate($this->request());

        $this->assertTrue($result->degraded, 'Timeout harus berakhir di NullProvider, bukan exception');
        $this->assertSame('null', $result->provider);
        $this->assertStringContainsString('sibuk', $result->text);
        $this->assertSame(1, $panggilan, 'Timeout TIDAK boleh di-retry (beban dobel inferensi)');
    }

    public function test_connection_error_diretry_sekali_lalu_fallback(): void
    {
        $panggilan = 0;
        Http::fake(function () use (&$panggilan): void {
            $panggilan++;

            throw new ConnectionException('cURL error 7: Failed to connect to host port 11434: Connection refused');
        });

        $result = $this->gateway()->generate($this->request());

        $this->assertTrue($result->degraded);
        $this->assertSame(2, $panggilan, 'Connection error harus di-retry tepat 1x');
    }

    public function test_retry_koneksi_yang_berhasil_mengembalikan_jawaban_normal(): void
    {
        $panggilan = 0;
        Http::fake(function () use (&$panggilan) {
            $panggilan++;

            if ($panggilan === 1) {
                throw new ConnectionException('cURL error 7: Connection refused');
            }

            return Http::response(['response' => 'Bismillah.']);
        });

        $result = $this->gateway()->generate($this->request());

        $this->assertFalse($result->degraded);
        $this->assertSame('Bismillah.', $result->text);
        $this->assertSame(2, $panggilan);
    }

    public function test_error_http_500_jatuh_ke_null_provider_tanpa_retry(): void
    {
        Http::fake(['*/api/generate' => Http::response(['error' => 'model meledak'], 500)]);

        $result = $this->gateway()->generate($this->request());

        $this->assertTrue($result->degraded);
        Http::assertSentCount(1);
    }

    public function test_sirkuit_terbuka_langsung_degradasi_tanpa_panggilan_http(): void
    {
        config()->set('ai.gateway.circuit_breaker.failure_threshold', 2);
        config()->set('ai.gateway.retry_connection_max', 0);

        $panggilan = 0;
        Http::fake(function () use (&$panggilan): void {
            $panggilan++;

            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $gateway = $this->gateway();

        // 2 kegagalan = ambang → sirkuit terbuka.
        $gateway->generate($this->request());
        $gateway->generate($this->request());
        $this->assertSame(2, $panggilan);
        $this->assertSame('open', $gateway->circuitStates()['ollama']);

        // Panggilan ke-3: tidak menyentuh HTTP sama sekali.
        $result = $gateway->generate($this->request());
        $this->assertTrue($result->degraded);
        $this->assertSame(2, $panggilan, 'Sirkuit terbuka: tidak boleh ada panggilan HTTP baru');
    }

    public function test_embed_sukses_via_ollama(): void
    {
        Http::fake([
            '*/api/embed' => Http::response(['embeddings' => [[0.1, 0.2], [0.3, 0.4]]]),
        ]);

        $vectors = $this->gateway()->embed(['teks satu', 'teks dua']);

        $this->assertCount(2, $vectors);
        $this->assertSame([0.1, 0.2], $vectors[0]);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/api/embed')
                && $request['model'] === config('ai.router.defaults.embedding.model');
        });
    }

    public function test_embed_gagal_total_mengembalikan_kosong_tanpa_exception(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $this->assertSame([], $this->gateway()->embed('teks'));
    }
}
