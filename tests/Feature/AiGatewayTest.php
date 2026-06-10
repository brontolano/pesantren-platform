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
        // Baseline gating modul T-A012: registry kosong = modul tampil.
        DB::table('module_registry')->delete();
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

    public function test_connection_reset_dan_curl_56_tidak_diretry(): void
    {
        // Reset/recv-failure terjadi SETELAH request terkirim — inferensi
        // mungkin masih berjalan di server; retry = beban inferensi dobel.
        foreach ([
            'cURL error 56: Recv failure: Connection reset by peer',
            'Connection reset by peer',
            'Empty reply from server',
        ] as $pesan) {
            $panggilan = 0;
            Http::fake(function () use (&$panggilan, $pesan): void {
                $panggilan++;

                throw new ConnectionException($pesan);
            });

            $result = $this->gateway()->generate($this->request());

            $this->assertTrue($result->degraded);
            $this->assertSame(1, $panggilan, "'{$pesan}' TIDAK boleh di-retry");
        }
    }

    public function test_fallback_lintas_provider_memakai_model_default_provider_fallback(): void
    {
        // Baris config menunjuk provider tak terdaftar (typo/belum diaktifkan)
        // dengan model asing — fallback ke Ollama TIDAK boleh membawa model
        // 'gpt-4o' (404 model not found = polusi metrik breaker Ollama sehat).
        DB::table('ai_model_config')->insert([
            'agent_key' => null,
            'tier' => 'ekonomis',
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'max_context_tokens' => 4096,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $modelTerkirim = null;
        Http::fake(function ($request) use (&$modelTerkirim) {
            $modelTerkirim = $request['model'] ?? null;

            return Http::response(['response' => 'Siap.']);
        });

        $result = $this->gateway()->generate($this->request());

        $this->assertFalse($result->degraded);
        $this->assertSame('ollama', $result->provider);
        $this->assertSame(
            config('ai.router.defaults.ekonomis.model'),
            $modelTerkirim,
            'Fallback lintas-provider wajib memakai model default milik provider fallback, bukan model provider asal',
        );
        $this->assertSame(config('ai.router.defaults.ekonomis.model'), $result->model);
    }

    public function test_fallback_provider_tanpa_model_default_dilewati_tanpa_panggilan_http(): void
    {
        // Router memilih provider tak terdaftar DAN default tier pun milik
        // provider lain → Ollama tidak punya model yang diketahui untuk tier
        // ini → dilewati, langsung degradasi tanpa panggilan HTTP.
        DB::table('ai_model_config')->insert([
            'agent_key' => null,
            'tier' => 'ekonomis',
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'max_context_tokens' => 4096,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        config()->set('ai.router.defaults.ekonomis.provider', 'openai');

        Http::fake(['*' => Http::response(['response' => 'tak boleh terpanggil'])]);

        $result = $this->gateway()->generate($this->request());

        $this->assertTrue($result->degraded);
        Http::assertNothingSent();
    }

    public function test_error_4xx_tidak_membuka_circuit_breaker(): void
    {
        config()->set('ai.gateway.circuit_breaker.failure_threshold', 1);

        Http::fake(['*/api/generate' => Http::response(['error' => 'model not found'], 404)]);

        $gateway = $this->gateway();

        $this->assertTrue($gateway->generate($this->request())->degraded);
        $this->assertSame(
            'closed',
            $gateway->circuitStates()['ollama'],
            'HTTP 4xx = salah konfigurasi, bukan provider sakit — tidak boleh membuka sirkuit',
        );

        // Panggilan berikutnya tetap menyentuh HTTP (sirkuit tidak terbuka).
        $gateway->generate($this->request());
        Http::assertSentCount(2);
    }

    public function test_respons_200_tanpa_field_response_dianggap_gagal(): void
    {
        Http::fake(['*/api/generate' => Http::response(['done' => true])]);

        $result = $this->gateway()->generate($this->request());

        $this->assertTrue($result->degraded, "200 tanpa field 'response' bukan sukses kosong");
    }

    public function test_state_circuit_breaker_global_lintas_penukaran_cache_manager_tenancy(): void
    {
        // CacheTenancyBootstrapper menukar singleton 'cache' dengan manager
        // bertag per-tenant. State breaker harus SAMA sebelum & sesudah
        // penukaran (Ollama-nya satu untuk semua tenant) — simulasi: kegagalan
        // pertama di manager A, kedua di manager B, sirkuit tetap terbuka.
        config()->set('ai.gateway.circuit_breaker.failure_threshold', 2);
        config()->set('ai.gateway.retry_connection_max', 0);

        Http::fake(function (): void {
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $gateway = $this->gateway();
        $gateway->generate($this->request()); // kegagalan #1 (manager awal)

        // Simulasi bootstrapper tenancy: tukar manager cache di container.
        \Illuminate\Support\Facades\Facade::clearResolvedInstance('cache');
        $this->app->instance('cache', new \Illuminate\Cache\CacheManager($this->app));

        $gateway->generate($this->request()); // kegagalan #2 (manager "tenant")

        $this->assertSame(
            'open',
            $gateway->circuitStates()['ollama'],
            'Penghitung kegagalan breaker wajib global — tidak terfragmentasi oleh penukaran cache manager tenancy',
        );
    }
}
