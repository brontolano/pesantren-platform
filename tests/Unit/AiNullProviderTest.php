<?php

namespace Tests\Unit;

use Modules\AI\Services\Gateway\AiGenerateRequest;
use Modules\AI\Services\Gateway\ModelSelection;
use Modules\AI\Services\Gateway\NullProvider;
use Tests\TestCase;

/** Unit NullProvider (T-A001): degradasi anggun selalu sukses. */
class AiNullProviderTest extends TestCase
{
    public function test_generate_selalu_sukses_dengan_pesan_santun_dan_degraded_true(): void
    {
        $provider = new NullProvider;

        $result = $provider->generate(
            new AiGenerateRequest(prompt: 'apa saja'),
            new ModelSelection(provider: 'null', model: 'null'),
        );

        $this->assertTrue($result->degraded);
        $this->assertSame('null', $result->provider);
        $this->assertSame(config('ai.gateway.degraded_message'), $result->text);
        $this->assertStringContainsString('sibuk', $result->text);
    }

    public function test_embed_mengembalikan_kosong(): void
    {
        $provider = new NullProvider;

        $this->assertSame([], $provider->embed(['teks'], new ModelSelection(provider: 'null', model: 'null')));
    }

    public function test_health_selalu_ok(): void
    {
        $this->assertTrue((new NullProvider)->health()['ok']);
    }
}
