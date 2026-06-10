<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use LogicException;
use Modules\AI\Database\Seeders\AiAgentSeeder;
use Modules\AI\Models\AiAgent;
use Modules\AI\Models\AiAuditLog;
use Modules\AI\Models\AiPromptVersion;
use Modules\AI\Models\AiUsageEvent;
use Modules\AI\Services\Gateway\AiGateway;
use Modules\AI\Services\Gateway\AiGenerateRequest;
use Modules\AI\Services\Metering\AiBudgetProvisioner;
use Modules\AI\Services\Prompts\PromptRegistry;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * T-A004 — Prompt registry ber-versi & feature flag per tenant:
 *  (a) resolve: versi aktif default; agen tak dikenal → fallback tanpa prompt;
 *  (b) override pin versi tenant menang atas versi aktif;
 *  (c) flag disabled per tenant → generate balas santun TANPA HTTP, termeter 0,
 *      audit tercatat; tenant lain TIDAK terdampak (isolasi flag);
 *  (d) ganti versi aktif TANPA deploy: cache ter-invalidasi; rollback pulih;
 *  (e) system prompt diteruskan ke provider + prompt_version_id di audit;
 *  (f) guard sekali-tulis system_prompt + constraint satu 'aktif' per agen;
 *  (g) seeder idempoten; (h) perintah ai:prompt list/aktifkan/rollback.
 */
class AiPromptRegistryTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'ai_model_config', 'ai_audit_log', 'ai_credit_ledger', 'ai_tenant_budget',
            'ai_usage_event', 'ai_agent', 'ai_prompt_version', 'ai_prompt_tenant_override',
        ]);
        // TRUNCATE CASCADE: tenants ikut membersihkan override; ai_agent ikut
        // membersihkan ai_prompt_version (FK cascade).
        $this->truncatePpdbTables(['domains', 'tenants', 'ai_agent']);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    private function registry(): PromptRegistry
    {
        return app(PromptRegistry::class);
    }

    private function tenantSiap(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren Prompt '.$prefix);
        app(AiBudgetProvisioner::class)->provision($tenant->id);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        return $tenant;
    }

    private function fakeOllamaSukses(): void
    {
        Http::fake([
            '*/api/generate' => Http::response([
                'response' => 'Siap.',
                'prompt_eval_count' => 10,
                'eval_count' => 5,
            ]),
        ]);
    }

    private function versiAktif(string $agentKey = 'uji-internal'): AiPromptVersion
    {
        return AiPromptVersion::query()
            ->where('agent_key', $agentKey)
            ->where('status', AiPromptVersion::STATUS_AKTIF)
            ->firstOrFail();
    }

    // ── (a) resolve dasar ────────────────────────────────────────────────

    public function test_resolve_memakai_versi_aktif_dan_fallback_tanpa_prompt(): void
    {
        $this->seed(AiAgentSeeder::class);

        $pilihan = $this->registry()->resolve('uji-internal');
        $this->assertTrue($pilihan->enabled);
        $this->assertSame('aktif', $pilihan->source);
        $this->assertSame($this->versiAktif()->id, $pilihan->promptVersionId);
        $this->assertStringContainsString('asisten internal platform', (string) $pilihan->systemPrompt);

        // Agen tak terdaftar → fallback: tanpa system prompt, gateway tetap jalan.
        $fallback = $this->registry()->resolve('agen-misterius');
        $this->assertTrue($fallback->enabled);
        $this->assertNull($fallback->systemPrompt);
        $this->assertNull($fallback->promptVersionId);
        $this->assertSame('fallback', $fallback->source);
    }

    // ── (b) override pin versi tenant menang ─────────────────────────────

    public function test_override_pin_versi_tenant_menang_atas_versi_aktif(): void
    {
        $this->seed(AiAgentSeeder::class);
        $tenant = $this->tenantSiap('prpin');

        $v2 = $this->registry()->buatVersi('uji-internal', 'Prompt percobaan versi dua.', 'Uji pin.');

        // Pin tenant ke v2 (masih draft) — tenant ini melihat v2, global tetap v1.
        $this->registry()->setOverride($tenant->id, 'uji-internal', promptVersionId: (int) $v2->id);

        $tenantView = $this->registry()->resolve('uji-internal', $tenant->id);
        $this->assertSame((int) $v2->id, $tenantView->promptVersionId);
        $this->assertSame('override-pin', $tenantView->source);
        $this->assertSame('Prompt percobaan versi dua.', $tenantView->systemPrompt);

        $globalView = $this->registry()->resolve('uji-internal');
        $this->assertSame((int) $this->versiAktif()->id, $globalView->promptVersionId);
    }

    // ── (c) flag disabled per tenant ─────────────────────────────────────

    public function test_flag_disabled_generate_balas_santun_tanpa_http_termeter_nol_dan_teraudit(): void
    {
        $this->seed(AiAgentSeeder::class);
        $tenant = $this->tenantSiap('prdis');

        $this->registry()->setOverride($tenant->id, 'uji-internal', enabled: false);

        Http::fake();

        $result = app(AiGateway::class)->generate(new AiGenerateRequest(
            prompt: 'Assalamualaikum',
            agent: 'uji-internal',
            tier: 'ekonomis',
            channel: 'web',
            idempotencyKey: 'kunci-flag-mati',
        ));

        Http::assertNothingSent();
        $this->assertTrue($result->degraded);
        $this->assertSame((string) config('ai.prompts.disabled_message'), $result->text);
        $this->assertStringContainsString('tidak diaktifkan', $result->text);

        // Termeter 0 di namespace degraded- (pagar #5).
        $event = AiUsageEvent::query()->where('idempotency_key', 'degraded-kunci-flag-mati')->first();
        $this->assertNotNull($event, 'Penolakan feature flag wajib tetap termeter 0');
        $this->assertEqualsWithDelta(0.0, (float) $event->kredit, 0.0001);

        // Audit tercatat (status degradasi, tanpa versi prompt).
        $log = AiAuditLog::query()->where('idempotency_key', 'kunci-flag-mati')->first();
        $this->assertNotNull($log);
        $this->assertSame(AiAuditLog::STATUS_DEGRADASI, $log->status);
        $this->assertNull($log->prompt_version_id);
    }

    public function test_isolasi_flag_tenant_lain_tidak_terdampak(): void
    {
        $this->seed(AiAgentSeeder::class);

        $tenantA = $this->createRuntimeTenant('priso-a', 'Pesantren Prompt Isolasi A');
        $tenantB = $this->createRuntimeTenant('priso-b', 'Pesantren Prompt Isolasi B');

        $this->registry()->setOverride($tenantA->id, 'uji-internal', enabled: false);

        $this->assertFalse($this->registry()->resolve('uji-internal', $tenantA->id)->enabled);

        $viewB = $this->registry()->resolve('uji-internal', $tenantB->id);
        $this->assertTrue($viewB->enabled, 'Flag tenant A tidak boleh menular ke tenant B');
        $this->assertSame((int) $this->versiAktif()->id, $viewB->promptVersionId);
    }

    // ── (d) ganti versi aktif tanpa deploy + rollback ────────────────────

    public function test_aktifkan_versi_baru_tanpa_deploy_dan_rollback_memulihkan(): void
    {
        $this->seed(AiAgentSeeder::class);
        $registry = $this->registry();

        $v1 = $this->versiAktif();

        // Panaskan cache dulu — invalidasi harus eksplisit, bukan sekadar TTL.
        $this->assertSame((int) $v1->id, $registry->resolve('uji-internal')->promptVersionId);

        $v2 = $registry->buatVersi('uji-internal', 'Prompt baru versi dua.', 'Perbaikan gaya jawaban.');
        $registry->aktifkanVersi('uji-internal', (int) $v2->versi);

        // Cache ter-invalidasi: resolve langsung melihat v2.
        $segar = $registry->resolve('uji-internal');
        $this->assertSame((int) $v2->id, $segar->promptVersionId);
        $this->assertSame('Prompt baru versi dua.', $segar->systemPrompt);

        // v1 otomatis terarsip; hanya satu aktif.
        $this->assertSame(AiPromptVersion::STATUS_ARSIP, $v1->fresh()->status);
        $this->assertSame(1, AiPromptVersion::query()
            ->where('agent_key', 'uji-internal')
            ->where('status', AiPromptVersion::STATUS_AKTIF)->count());

        // Rollback memulihkan prompt lama.
        $registry->rollbackKeVersi('uji-internal', (int) $v1->versi);
        $pulih = $registry->resolve('uji-internal');
        $this->assertSame((int) $v1->id, $pulih->promptVersionId);
        $this->assertSame(AiPromptVersion::STATUS_ARSIP, $v2->fresh()->status);
    }

    // ── (e) system prompt ke provider + versi di audit ───────────────────

    public function test_generate_meneruskan_system_prompt_dan_mencatat_versi_di_audit(): void
    {
        $this->seed(AiAgentSeeder::class);
        $tenant = $this->tenantSiap('prgen');
        $this->fakeOllamaSukses();

        app(AiGateway::class)->generate(new AiGenerateRequest(
            prompt: 'Assalamualaikum',
            agent: 'uji-internal',
            tier: 'ekonomis',
            idempotencyKey: 'kunci-prompt-gen',
        ));

        $vAktif = $this->versiAktif();

        // System prompt registry sampai ke payload provider (field 'system').
        Http::assertSent(fn ($req) => str_contains($req->url(), '/api/generate')
            && ($req->data()['system'] ?? null) === $vAktif->system_prompt);

        // Jawaban mencatat versi prompt di ai_audit_log.
        $log = AiAuditLog::query()->where('idempotency_key', 'kunci-prompt-gen')->firstOrFail();
        $this->assertSame((int) $vAktif->id, (int) $log->prompt_version_id);
        $this->assertSame(AiAuditLog::STATUS_SUKSES, $log->status);
    }

    // ── (f) guard sekali-tulis + constraint satu aktif ───────────────────

    public function test_mengedit_system_prompt_versi_lama_melempar_exception(): void
    {
        $this->seed(AiAgentSeeder::class);
        $v1 = $this->versiAktif();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('tidak boleh diedit');

        $v1->update(['system_prompt' => 'Isi dipalsukan.']);
    }

    public function test_dua_versi_aktif_sekaligus_ditolak_constraint_db(): void
    {
        $this->seed(AiAgentSeeder::class);
        $registry = $this->registry();
        $v2 = $registry->buatVersi('uji-internal', 'Versi dua.', 'Uji constraint.');

        $central = (string) config('tenancy.database.central_connection');

        $this->expectException(QueryException::class);

        // Lewati service (jalur jahat): paksa v2 'aktif' padahal v1 masih aktif.
        DB::connection($central)->table('ai_prompt_version')
            ->where('id', $v2->id)
            ->update(['status' => AiPromptVersion::STATUS_AKTIF]);
    }

    // ── (g) seeder idempoten ─────────────────────────────────────────────

    public function test_seeder_agen_idempoten_dan_tidak_menimpa_pilihan_operator(): void
    {
        $this->seed(AiAgentSeeder::class);
        $this->seed(AiAgentSeeder::class);

        $this->assertSame(1, AiAgent::query()->where('key', 'uji-internal')->count());
        $this->assertSame(1, AiPromptVersion::query()->where('agent_key', 'uji-internal')->count());
        $this->assertSame(1, $this->versiAktif()->versi);

        // Operator mengaktifkan v2 → seeder ulang TIDAK mengembalikan ke v1.
        $v2 = $this->registry()->buatVersi('uji-internal', 'Versi dua.', 'Uji seeder.');
        $this->registry()->aktifkanVersi('uji-internal', (int) $v2->versi);
        $this->seed(AiAgentSeeder::class);

        $this->assertSame(2, $this->versiAktif()->versi, 'Seeder ulang tidak boleh menimpa versi aktif pilihan operator');
    }

    // ── (h) perintah ai:prompt ───────────────────────────────────────────

    public function test_perintah_ai_prompt_list_aktifkan_dan_rollback(): void
    {
        $this->seed(AiAgentSeeder::class);
        $v2 = $this->registry()->buatVersi('uji-internal', 'Versi dua via CLI.', 'Uji CLI.');

        $this->assertSame(0, Artisan::call('ai:prompt', ['aksi' => 'list']));
        $this->assertStringContainsString('uji-internal', Artisan::output());

        $this->assertSame(0, Artisan::call('ai:prompt', [
            'aksi' => 'aktifkan', '--agent' => 'uji-internal', '--versi' => (string) $v2->versi,
        ]));
        $this->assertStringContainsString('berhasil diaktifkan', Artisan::output());
        $this->assertSame((int) $v2->id, $this->registry()->resolve('uji-internal')->promptVersionId);

        $this->assertSame(0, Artisan::call('ai:prompt', [
            'aksi' => 'rollback', '--agent' => 'uji-internal', '--versi' => '1',
        ]));
        $this->assertStringContainsString('rollback', Artisan::output());
        $this->assertSame(1, $this->versiAktif()->versi);

        // Versi tak ada → gagal santun (exit != 0), bukan exception telanjang.
        $this->assertNotSame(0, Artisan::call('ai:prompt', [
            'aksi' => 'aktifkan', '--agent' => 'uji-internal', '--versi' => '99',
        ]));
        $this->assertStringContainsString('tidak ditemukan', Artisan::output());
    }
}
