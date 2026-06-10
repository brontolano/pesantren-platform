<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\AI\Database\Seeders\AiModelConfigSeeder;
use Modules\AI\Models\AiModelConfig;
use Modules\AI\Services\Gateway\ModelRouter;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * ModelRouter (T-A001): konfigurasi DB central ai_model_config menang atas
 * hard-default config; cache 60 dtk; tier tak dikenal → exception jelas.
 */
class AiModelRouterTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['ai_model_config']);
        DB::table('ai_model_config')->truncate();
        Cache::flush();
    }

    private function router(): ModelRouter
    {
        return app(ModelRouter::class);
    }

    public function test_tabel_kosong_jatuh_ke_hard_default_config(): void
    {
        $selection = $this->router()->resolve('chatbot_wali', 'ekonomis');

        $this->assertSame('default', $selection->source);
        $this->assertSame(config('ai.router.defaults.ekonomis.provider'), $selection->provider);
        $this->assertSame(config('ai.router.defaults.ekonomis.model'), $selection->model);
    }

    public function test_konfigurasi_db_menang_atas_default_config(): void
    {
        $this->seed(AiModelConfigSeeder::class);

        AiModelConfig::query()->create([
            'agent_key' => 'generator_soal',
            'tier' => 'standar',
            'provider' => 'provider-uji',
            'model' => 'model-khusus-agen',
            'max_context_tokens' => 2048,
        ]);

        // Baris spesifik agen menang atas baris default (agent_key NULL).
        $khusus = $this->router()->resolve('generator_soal', 'standar');
        $this->assertSame('db', $khusus->source);
        $this->assertSame('model-khusus-agen', $khusus->model);
        $this->assertSame(2048, $khusus->maxContextTokens);

        // Agen lain memakai baris default DB hasil seeder.
        $umum = $this->router()->resolve('agen_lain', 'standar');
        $this->assertSame('db', $umum->source);
        $this->assertSame(config('ai.router.defaults.standar.model'), $umum->model);
    }

    public function test_baris_nonaktif_diabaikan(): void
    {
        AiModelConfig::query()->create([
            'agent_key' => null,
            'tier' => 'premium',
            'provider' => 'provider-mati',
            'model' => 'model-mati',
            'is_active' => false,
        ]);

        $selection = $this->router()->resolve('default', 'premium');

        $this->assertSame('default', $selection->source, 'Baris is_active=false tidak boleh terpilih');
    }

    public function test_keputusan_dicache_60_detik(): void
    {
        $this->seed(AiModelConfigSeeder::class);

        $pertama = $this->router()->resolve('default', 'ekonomis');
        $this->assertSame('db', $pertama->source);

        // Ubah DB — keputusan lama masih tersaji dari cache.
        AiModelConfig::query()->whereNull('agent_key')->where('tier', 'ekonomis')
            ->update(['model' => 'model-baru-setelah-cache']);

        $masihCache = $this->router()->resolve('default', 'ekonomis');
        $this->assertSame($pertama->model, $masihCache->model);

        // Setelah cache dihapus → keputusan baru terbaca.
        $this->router()->forget('default', 'ekonomis');
        $segar = $this->router()->resolve('default', 'ekonomis');
        $this->assertSame('model-baru-setelah-cache', $segar->model);
    }

    public function test_tier_tak_dikenal_melempar_exception_jelas(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Tier AI tidak dikenal: 'sultan'");

        $this->router()->resolve('default', 'sultan');
    }

    public function test_resolve_embedding_memakai_tier_embedding(): void
    {
        $selection = $this->router()->resolveEmbedding();

        $this->assertSame(config('ai.router.defaults.embedding.model'), $selection->model);
    }

    public function test_seeder_idempoten_dijalankan_dua_kali(): void
    {
        $this->seed(AiModelConfigSeeder::class);
        $count1 = AiModelConfig::query()->count();

        $this->seed(AiModelConfigSeeder::class);
        $count2 = AiModelConfig::query()->count();

        $this->assertSame($count1, $count2, 'Seeder tak boleh menggandakan baris');
        $this->assertSame(count((array) config('ai.router.defaults')), $count2);
    }
}
