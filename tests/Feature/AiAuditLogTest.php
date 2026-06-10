<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\AI\Models\AiAuditLog;
use Modules\AI\Services\Gateway\AiGateway;
use Modules\AI\Services\Gateway\AiGenerateRequest;
use Modules\AI\Services\Settings\AiSettingProvisioner;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * T-A003 — ai_audit_log (tabel TENANT pertama modul AI):
 *  (a) generate() menulis baris audit lengkap di DB tenant yang benar;
 *  (b) UPDATE/DELETE DITOLAK trigger PostgreSQL (test keamanan #1, append-only);
 *  (c) isolasi tenant: audit tenant A tidak terbaca dari tenant B;
 *  (d) kegagalan tulis audit TIDAK menggagalkan generate();
 *  (e) migrasi ber-rollback (down() bersih).
 */
class AiAuditLogTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['ai_model_config', 'ai_audit_log']);
        $this->truncatePpdbTables(['domains', 'tenants']);
        DB::table('ai_model_config')->truncate();
        // Baseline gating modul T-A012: registry kosong = modul tampil
        // (pola ModuleService, sama dengan AiWizardPengaturanTest).
        DB::table('module_registry')->delete();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    private function fakeOllamaSukses(): void
    {
        Http::fake([
            '*/api/generate' => Http::response([
                'response' => 'Waalaikumussalam warahmatullah.',
                'prompt_eval_count' => 12,
                'eval_count' => 7,
            ]),
        ]);
    }

    /**
     * T-A020: gerbang aktivasi PolicyEngine butuh setting AI aktif —
     * paritas dengan provisioning produksi (TenantProvisioner).
     */
    private function provisionSettingAi($tenant): void
    {
        app(AiSettingProvisioner::class)
            ->provision((string) $tenant->id);
    }

    private function request(): AiGenerateRequest
    {
        return new AiGenerateRequest(
            prompt: 'Assalamualaikum',
            agent: 'uji-audit',
            tier: 'ekonomis',
            channel: 'web',
            userId: null,
            idempotencyKey: 'audit-'.str()->random(12),
            ringkasanKonteks: 'Konteks uji terredaksi.',
        );
    }

    public function test_generate_menulis_baris_audit_lengkap_di_db_tenant(): void
    {
        $this->fakeOllamaSukses();

        $tenant = $this->createRuntimeTenant('audita', 'Pesantren Audit A');
        tenancy()->initialize($tenant);
        $this->provisionSettingAi($tenant);

        $request = $this->request();
        $result = app(AiGateway::class)->generate($request);

        $this->assertFalse($result->degraded);

        $log = AiAuditLog::query()->where('idempotency_key', $request->idempotencyKey)->first();

        $this->assertNotNull($log, 'generate() wajib menulis 1 baris ai_audit_log di DB tenant');
        $this->assertSame('uji-audit', $log->agent_key);
        $this->assertSame('web', $log->channel);
        $this->assertSame('ollama', $log->provider);
        $this->assertSame(config('ai.router.defaults.ekonomis.model'), $log->model);
        $this->assertSame('ekonomis', $log->tier);
        $this->assertSame(AiAuditLog::STATUS_SUKSES, $log->status);
        $this->assertFalse($log->degraded);
        $this->assertSame(12, $log->token_masuk);
        $this->assertSame(7, $log->token_keluar);
        $this->assertSame('Konteks uji terredaksi.', $log->ringkasan_konteks);
        $this->assertSame($tenant->id, $log->tenant_id);
        $this->assertNotNull($log->created_at);

        // Breakdown latensi per langkah pipeline (StepTimer).
        $this->assertIsArray($log->latency);
        $this->assertArrayHasKey('router_ms', $log->latency);
        $this->assertArrayHasKey('provider_ms', $log->latency);
        $this->assertArrayHasKey('total_ms', $log->latency);

        // Pastikan benar-benar di DB TENANT (koneksi tenant), 1 baris saja.
        $this->assertSame(
            1,
            (int) DB::connection('tenant')->table('ai_audit_log')->count(),
        );
    }

    public function test_degradasi_juga_tercatat_dengan_status_degradasi(): void
    {
        Http::fake(fn () => Http::response('mati', 500));

        $tenant = $this->createRuntimeTenant('auditd', 'Pesantren Audit Degradasi');
        tenancy()->initialize($tenant);
        $this->provisionSettingAi($tenant);

        $request = $this->request();
        $result = app(AiGateway::class)->generate($request);

        $this->assertTrue($result->degraded);

        $log = AiAuditLog::query()->where('idempotency_key', $request->idempotencyKey)->first();

        $this->assertNotNull($log, 'Degradasi pun wajib meninggalkan jejak audit');
        $this->assertSame(AiAuditLog::STATUS_DEGRADASI, $log->status);
        $this->assertTrue($log->degraded);
        $this->assertSame('null', $log->provider);
    }

    public function test_update_dan_delete_pada_ai_audit_log_ditolak_trigger(): void
    {
        $this->fakeOllamaSukses();

        $tenant = $this->createRuntimeTenant('auditt', 'Pesantren Audit Trigger');
        tenancy()->initialize($tenant);
        $this->provisionSettingAi($tenant);

        app(AiGateway::class)->generate($this->request());
        $id = AiAuditLog::query()->value('id');
        $this->assertNotNull($id);

        // UPDATE ditolak di level DB.
        try {
            DB::connection('tenant')->table('ai_audit_log')
                ->where('id', $id)
                ->update(['agent_key' => 'dipalsukan']);
            $this->fail('UPDATE pada ai_audit_log seharusnya ditolak trigger append-only');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        // DELETE ditolak di level DB.
        try {
            DB::connection('tenant')->table('ai_audit_log')->where('id', $id)->delete();
            $this->fail('DELETE pada ai_audit_log seharusnya ditolak trigger append-only');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        // Baris asli tetap utuh.
        $this->assertSame(
            'uji-audit',
            DB::connection('tenant')->table('ai_audit_log')->where('id', $id)->value('agent_key'),
        );
    }

    public function test_isolasi_tenant_audit_a_tidak_terbaca_dari_tenant_b(): void
    {
        $this->fakeOllamaSukses();

        $tenantA = $this->createRuntimeTenant('audisa', 'Pesantren Isolasi A');
        $tenantB = $this->createRuntimeTenant('audisb', 'Pesantren Isolasi B');

        tenancy()->initialize($tenantA);
        $this->provisionSettingAi($tenantA);
        $request = $this->request();
        app(AiGateway::class)->generate($request);
        $this->assertSame(1, AiAuditLog::query()->count());
        tenancy()->end();

        tenancy()->initialize($tenantB);
        $this->assertSame(0, AiAuditLog::query()->count(), 'Audit tenant A bocor terbaca dari tenant B');
        $this->assertNull(
            AiAuditLog::query()->where('idempotency_key', $request->idempotencyKey)->first(),
        );
        tenancy()->end();
    }

    public function test_kegagalan_tulis_audit_tidak_menggagalkan_generate(): void
    {
        $this->fakeOllamaSukses();

        $tenant = $this->createRuntimeTenant('auditf', 'Pesantren Audit Gagal');
        tenancy()->initialize($tenant);
        $this->provisionSettingAi($tenant);

        // Sabotase: hilangkan tabel audit di DB tenant → penulisan audit pasti gagal.
        Schema::connection('tenant')->drop('ai_audit_log');

        Log::spy();

        $result = app(AiGateway::class)->generate($this->request());

        $this->assertFalse($result->degraded, 'Permintaan utama wajib tetap sukses walau audit gagal ditulis');
        $this->assertSame('Waalaikumussalam warahmatullah.', $result->text);

        // Kegagalan audit tercatat keras di log Laravel.
        Log::shouldHaveReceived('error')
            ->withArgs(fn ($message) => str_contains((string) $message, 'GAGAL menulis ai_audit_log'))
            ->atLeast()->once();
    }

    public function test_migrasi_ai_audit_log_dapat_dirollback(): void
    {
        $migration = require base_path(
            'Modules/AI/database/migrations/2026_06_10_200000_create_ai_audit_log_table.php',
        );

        $this->assertTrue(Schema::hasTable('ai_audit_log'));

        $migration->down();
        $this->assertFalse(Schema::hasTable('ai_audit_log'), 'down() wajib membersihkan tabel + trigger');

        $migration->up();
        $this->assertTrue(Schema::hasTable('ai_audit_log'), 'up() ulang pasca-rollback wajib jalan (trigger tidak duplikat)');
    }

    public function test_ai_health_dengan_flag_tenant_menampilkan_ringkasan_audit(): void
    {
        $this->fakeOllamaSukses();

        $tenant = $this->createRuntimeTenant('audith', 'Pesantren Audit Health');
        tenancy()->initialize($tenant);
        $this->provisionSettingAi($tenant);
        app(AiGateway::class)->generate($this->request());
        tenancy()->end();

        Artisan::call('ai:health', ['--skip-generate' => true, '--tenant' => $tenant->id]);
        $output = Artisan::output();

        $this->assertStringContainsString("Audit '{$tenant->id}'", $output);
        $this->assertStringContainsString('1 panggilan hari ini', $output);
        $this->assertStringContainsString('sukses: 1', $output);
    }
}
