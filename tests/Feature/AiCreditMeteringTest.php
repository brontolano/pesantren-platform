<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\AI\Models\AiAuditLog;
use Modules\AI\Models\AiCreditLedger;
use Modules\AI\Models\AiTenantBudget;
use Modules\AI\Models\AiUsageEvent;
use Modules\AI\Services\Gateway\AiGateway;
use Modules\AI\Services\Gateway\AiGenerateRequest;
use Modules\AI\Services\Metering\AiBudgetProvisioner;
use Modules\AI\Services\Metering\BudgetGuard;
use Modules\AI\Services\Metering\CreditMeter;
use Modules\AI\Services\Settings\AiSettingProvisioner;
use Modules\Notifikasi\Models\LogNotifikasi;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * T-A002 — metering kredit, anggaran & kill-switch per tenant (TUGAS UANG —
 * verifikasi adversarial, pola modul Keuangan):
 *  (a) hitung kredit per tier (degradasi = 0);
 *  (b) catat() konsisten ledger central + ai_usage_event tenant + terpakai;
 *  (c) idempotensi: kunci sama 2× → 1 baris, saldo TIDAK dobel;
 *  (d) kontinuitas saldo berurutan + locking FOR UPDATE benar-benar terpasang;
 *  (e) alert 80% terkirim SEKALI per periode;
 *  (f) kill-switch 100% → generate() balas santun TANPA HTTP + tetap termeter;
 *  (g) rollover bulan reset terpakai + kill-switch;
 *  (h) append-only: UPDATE/DELETE ledger & usage_event ditolak trigger;
 *  (i) isolasi tenant ai_usage_event;
 *  (j) provisioning idempoten (tenant:reprovision aman).
 */
class AiCreditMeteringTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'ai_model_config', 'ai_audit_log', 'ai_credit_ledger', 'ai_tenant_budget', 'ai_usage_event',
        ]);
        // TRUNCATE CASCADE tenants ikut membersihkan ai_credit_ledger,
        // ai_tenant_budget, log_notifikasi (FK cascade) — trigger append-only
        // tidak menghalangi TRUNCATE (hanya UPDATE/DELETE per baris).
        $this->truncatePpdbTables(['domains', 'tenants']);
        DB::table('ai_model_config')->truncate();
        // Baseline gating modul T-A012: registry kosong = modul tampil.
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

    private function meter(): CreditMeter
    {
        return app(CreditMeter::class);
    }

    /** @return array<string, mixed> */
    private function dataPemakaian(array $override = []): array
    {
        return array_merge([
            'agent_key' => 'uji-meter',
            'channel' => 'web',
            'provider' => 'ollama',
            'model' => 'qwen2.5:3b-instruct',
            'tier' => 'ekonomis',
            'token_masuk' => 100,
            'token_keluar' => 50,
            'kredit' => 1.5,
            'degraded' => false,
        ], $override);
    }

    private function fakeOllamaSukses(): void
    {
        Http::fake([
            '*/api/generate' => Http::response([
                'response' => 'Baik, dengan senang hati.',
                'prompt_eval_count' => 12,
                'eval_count' => 7,
            ]),
        ]);
    }

    private function tenantSiapMeter(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren Meter '.$prefix);
        app(AiBudgetProvisioner::class)->provision($tenant->id);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant); // FK tenants lokal utk log_notifikasi
        // T-A020: gerbang aktivasi PolicyEngine butuh setting aktif (paritas
        // dengan provisioning produksi — TenantProvisioner memanggil ini juga).
        app(AiSettingProvisioner::class)->provision((string) $tenant->id);

        return $tenant;
    }

    // ── (a) Tarif per tier ───────────────────────────────────────────────

    public function test_hitung_kredit_per_tier_sesuai_tarif_config(): void
    {
        $meter = $this->meter();

        // (masuk × tarif_masuk + keluar × tarif_keluar) / 1000
        $this->assertEqualsWithDelta(0.75, $meter->hitungKredit('ekonomis', 1000, 1000), 0.0001);
        $this->assertEqualsWithDelta(1.5, $meter->hitungKredit('standar', 1000, 1000), 0.0001);
        $this->assertEqualsWithDelta(6.0, $meter->hitungKredit('premium', 1000, 1000), 0.0001);
        $this->assertEqualsWithDelta(0.05, $meter->hitungKredit('embedding', 1000, 0), 0.0001);

        // Token null = 0.
        $this->assertSame(0.0, $meter->hitungKredit('standar', null, null));

        // Degradasi (NullProvider) = 0 kredit, apa pun tokennya.
        $this->assertSame(0.0, $meter->hitungKredit('premium', 5000, 5000, degraded: true));

        // Tier tanpa tarif → jatuh ke tarif standar (tidak menggratiskan).
        $this->assertEqualsWithDelta(1.5, $meter->hitungKredit('tier-misterius', 1000, 1000), 0.0001);
    }

    // ── (b) catat() konsisten ────────────────────────────────────────────

    public function test_catat_menulis_ledger_usage_event_dan_terpakai_konsisten(): void
    {
        $tenant = $this->tenantSiapMeter('metcat');

        $ledger = $this->meter()->catat($tenant->id, $this->dataPemakaian(), 'kunci-konsisten-1');

        // Ledger CENTRAL: pemakaian negatif, saldo turun dari alokasi awal 0.
        $this->assertSame(AiCreditLedger::JENIS_PEMAKAIAN, $ledger->jenis);
        $this->assertEqualsWithDelta(-1.5, (float) $ledger->jumlah_kredit, 0.0001);
        $this->assertEqualsWithDelta(-1.5, (float) $ledger->saldo_setelah, 0.0001);

        // Budget: terpakai_periode bertambah.
        $budget = AiTenantBudget::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertEqualsWithDelta(1.5, (float) $budget->terpakai_periode, 0.0001);

        // Kopi tenant: ai_usage_event di DB tenant.
        $event = AiUsageEvent::query()->where('idempotency_key', 'kunci-konsisten-1')->first();
        $this->assertNotNull($event, 'catat() wajib menulis kopi ai_usage_event di DB tenant');
        $this->assertSame('uji-meter', $event->agent_key);
        $this->assertEqualsWithDelta(1.5, (float) $event->kredit, 0.0001);
        $this->assertFalse($event->degraded);
    }

    // ── (c) Idempotensi — adversarial uang ───────────────────────────────

    public function test_catat_dua_kali_kunci_sama_satu_baris_saldo_tidak_dobel(): void
    {
        $tenant = $this->tenantSiapMeter('metidm');

        $pertama = $this->meter()->catat($tenant->id, $this->dataPemakaian(), 'kunci-dobel');
        $kedua = $this->meter()->catat($tenant->id, $this->dataPemakaian(), 'kunci-dobel');

        $this->assertSame($pertama->id, $kedua->id, 'Duplikat kunci wajib mengembalikan baris ledger lama');

        $this->assertSame(1, AiCreditLedger::query()
            ->where('tenant_id', $tenant->id)
            ->where('jenis', AiCreditLedger::JENIS_PEMAKAIAN)
            ->count());

        // Saldo & terpakai TIDAK terpotong dobel.
        $this->assertEqualsWithDelta(-1.5, (float) $kedua->saldo_setelah, 0.0001);
        $budget = AiTenantBudget::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertEqualsWithDelta(1.5, (float) $budget->terpakai_periode, 0.0001);

        // Kopi tenant juga tunggal.
        $this->assertSame(1, AiUsageEvent::query()->where('idempotency_key', 'kunci-dobel')->count());
    }

    // ── (d) Kontinuitas saldo + locking FOR UPDATE ───────────────────────

    public function test_catat_berurutan_saldo_kontinu_dan_memakai_for_update(): void
    {
        $tenant = $this->tenantSiapMeter('metrac');

        $central = DB::connection((string) config('tenancy.database.central_connection'));
        $central->enableQueryLog();

        $saldoSebelum = 0.0;
        foreach ([1.5, 0.25, 3.0, 0.0001, 7.7] as $i => $kredit) {
            $ledger = $this->meter()->catat(
                $tenant->id,
                $this->dataPemakaian(['kredit' => $kredit]),
                "kunci-seri-{$i}",
            );

            // Kontinuitas: saldo_setelah = saldo sebelumnya - kredit, tanpa lubang.
            $this->assertEqualsWithDelta(
                round($saldoSebelum - $kredit, 4),
                (float) $ledger->saldo_setelah,
                0.0001,
                "Saldo terputus pada entri ke-{$i}",
            );
            $saldoSebelum = (float) $ledger->saldo_setelah;
        }

        // Locking FOR UPDATE benar-benar terpasang pada baris budget —
        // serialisasi race dua catat() paralel terjadi di level DB.
        $adaForUpdate = collect($central->getQueryLog())
            ->contains(fn (array $q) => str_contains(strtolower($q['query']), 'for update'));
        $central->disableQueryLog();

        $this->assertTrue($adaForUpdate, 'catat() wajib memakai SELECT ... FOR UPDATE pada ai_tenant_budget');

        $budget = AiTenantBudget::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertEqualsWithDelta(12.4501, (float) $budget->terpakai_periode, 0.0001);
    }

    // ── (e) Alert 80% sekali per periode ─────────────────────────────────

    public function test_alert_ambang_terkirim_sekali_per_periode(): void
    {
        $tenant = $this->tenantSiapMeter('metalr');
        AiTenantBudget::query()->where('tenant_id', $tenant->id)
            ->update(['anggaran_kredit_bulanan' => 10]);

        // 8.5 dari 10 = 85% ≥ ambang 80 → alert pertama.
        $this->meter()->catat($tenant->id, $this->dataPemakaian(['kredit' => 8.5]), 'kunci-alert-1');

        $alerts = LogNotifikasi::query()->where('jenis', 'ai_anggaran')->get();
        $this->assertCount(1, $alerts, 'Alert ambang wajib terkirim sebagai notifikasi database admin tenant');
        $this->assertSame('database', $alerts[0]->channel);
        $this->assertSame('admin_tenant', $alerts[0]->target);

        $budget = AiTenantBudget::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertNotNull($budget->alert_terkirim_pada);

        // Pemakaian berikutnya masih di atas ambang → TIDAK ada alert kedua.
        $this->meter()->catat($tenant->id, $this->dataPemakaian(['kredit' => 0.5]), 'kunci-alert-2');
        $this->assertSame(1, LogNotifikasi::query()->where('jenis', 'ai_anggaran')->count());
    }

    // ── (f) Kill-switch 100% ─────────────────────────────────────────────

    public function test_kill_switch_aktif_generate_balas_santun_tanpa_http_dan_tetap_termeter(): void
    {
        $tenant = $this->tenantSiapMeter('metkil');
        AiTenantBudget::query()->where('tenant_id', $tenant->id)
            ->update(['anggaran_kredit_bulanan' => 5]);

        // Lewati 100% → kill-switch menyala otomatis.
        $this->meter()->catat($tenant->id, $this->dataPemakaian(['kredit' => 6]), 'kunci-kill-1');
        $budget = AiTenantBudget::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertTrue($budget->kill_switch_aktif);

        // generate() berikutnya: NOL panggilan HTTP, jawaban santun anggaran.
        Http::fake();

        $request = new AiGenerateRequest(
            prompt: 'Assalamualaikum',
            agent: 'uji-kill',
            tier: 'ekonomis',
            channel: 'web',
            idempotencyKey: 'kunci-kill-generate',
        );
        $result = app(AiGateway::class)->generate($request);

        Http::assertNothingSent();
        $this->assertTrue($result->degraded);
        $this->assertSame((string) config('ai.metering.kill_switch_message'), $result->text);
        $this->assertStringContainsString('anggaran AI bulan ini telah habis', $result->text);

        // Pagar #5: penolakan pun TETAP termeter — event 0 kredit tercatat.
        $event = AiUsageEvent::query()->where('idempotency_key', 'degraded-kunci-kill-generate')->first();
        $this->assertNotNull($event, 'Penolakan kill-switch wajib tetap menghasilkan ai_usage_event');
        $this->assertEqualsWithDelta(0.0, (float) $event->kredit, 0.0001);
        $this->assertTrue($event->degraded);

        // Jejak audit T-A003 ikut tercatat.
        $log = AiAuditLog::query()->where('idempotency_key', 'kunci-kill-generate')->first();
        $this->assertNotNull($log);
        $this->assertSame(AiAuditLog::STATUS_DEGRADASI, $log->status);
    }

    // ── (g) Rollover bulan ───────────────────────────────────────────────

    public function test_rollover_bulan_reset_terpakai_dan_kill_switch(): void
    {
        $tenant = $this->tenantSiapMeter('metrol');
        AiTenantBudget::query()->where('tenant_id', $tenant->id)->update([
            'anggaran_kredit_bulanan' => 5,
            'periode_berjalan' => now()->subMonth()->startOfMonth()->toDateString(),
            'terpakai_periode' => 99,
            'kill_switch_aktif' => true,
            'alert_terkirim_pada' => now()->subMonth(),
        ]);

        // Pre-check pun sudah meloloskan (rollover virtual di BudgetGuard).
        $status = app(BudgetGuard::class)->cek($tenant->id);
        $this->assertTrue($status->boleh, 'Bulan baru: kill-switch periode lalu tidak boleh memblokir');

        // Pemakaian pertama bulan baru → reset definitif tertulis.
        $this->meter()->catat($tenant->id, $this->dataPemakaian(['kredit' => 1]), 'kunci-rollover');

        $budget = AiTenantBudget::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertFalse($budget->kill_switch_aktif);
        $this->assertNull($budget->alert_terkirim_pada);
        $this->assertEqualsWithDelta(1.0, (float) $budget->terpakai_periode, 0.0001);
        $this->assertSame(
            now()->startOfMonth()->toDateString(),
            $budget->periode_berjalan->toDateString(),
        );
    }

    // ── (h) Append-only — trigger DB ─────────────────────────────────────

    public function test_update_dan_delete_ledger_dan_usage_event_ditolak_trigger(): void
    {
        $tenant = $this->tenantSiapMeter('metapp');
        $this->meter()->catat($tenant->id, $this->dataPemakaian(), 'kunci-append');

        $central = (string) config('tenancy.database.central_connection');
        $ledgerId = DB::connection($central)->table('ai_credit_ledger')
            ->where('idempotency_key', 'kunci-append')->value('id');

        foreach ([
            fn () => DB::connection($central)->table('ai_credit_ledger')->where('id', $ledgerId)->update(['keterangan' => 'dipalsukan']),
            fn () => DB::connection($central)->table('ai_credit_ledger')->where('id', $ledgerId)->delete(),
            fn () => DB::connection('tenant')->table('ai_usage_event')->where('idempotency_key', 'kunci-append')->update(['kredit' => 0]),
            fn () => DB::connection('tenant')->table('ai_usage_event')->where('idempotency_key', 'kunci-append')->delete(),
        ] as $serangan) {
            try {
                $serangan();
                $this->fail('UPDATE/DELETE pada tabel append-only seharusnya ditolak trigger');
            } catch (QueryException $e) {
                $this->assertStringContainsString('append-only', $e->getMessage());
            }
        }
    }

    // ── (i) Isolasi tenant ai_usage_event ────────────────────────────────

    public function test_isolasi_usage_event_tenant_a_tidak_terbaca_dari_tenant_b(): void
    {
        $tenantA = $this->createRuntimeTenant('metisa', 'Pesantren Isolasi Meter A');
        $tenantB = $this->createRuntimeTenant('metisb', 'Pesantren Isolasi Meter B');

        tenancy()->initialize($tenantA);
        $this->meter()->catat($tenantA->id, $this->dataPemakaian(), 'kunci-isolasi');
        $this->assertSame(1, AiUsageEvent::query()->count());
        tenancy()->end();

        tenancy()->initialize($tenantB);
        $this->assertSame(0, AiUsageEvent::query()->count(), 'Usage event tenant A bocor terbaca dari tenant B');
        tenancy()->end();
    }

    // ── Degradasi tetap termeter ─────────────────────────────────────────

    public function test_degradasi_nol_kredit_tapi_tetap_tercatat(): void
    {
        Http::fake(fn () => Http::response('mati', 500));

        $tenant = $this->tenantSiapMeter('metdeg');

        $request = new AiGenerateRequest(
            prompt: 'Assalamualaikum',
            agent: 'uji-degradasi',
            tier: 'ekonomis',
            idempotencyKey: 'kunci-degradasi',
        );
        $result = app(AiGateway::class)->generate($request);
        $this->assertTrue($result->degraded);

        $event = AiUsageEvent::query()->where('idempotency_key', 'degraded-kunci-degradasi')->first();
        $this->assertNotNull($event, 'Degradasi wajib tetap termeter (pagar #5)');
        $this->assertEqualsWithDelta(0.0, (float) $event->kredit, 0.0001);
        $this->assertTrue($event->degraded);

        $ledger = AiCreditLedger::query()->where('idempotency_key', 'degraded-kunci-degradasi')->first();
        $this->assertNotNull($ledger);
        $this->assertEqualsWithDelta(0.0, (float) $ledger->jumlah_kredit, 0.0001);
    }

    // ── generate() sukses memotong kredit + tercermin di audit ──────────

    public function test_generate_sukses_memotong_kredit_dan_tercermin_di_audit(): void
    {
        $this->fakeOllamaSukses(); // 12 token masuk, 7 keluar

        $tenant = $this->tenantSiapMeter('metgen');

        $request = new AiGenerateRequest(
            prompt: 'Assalamualaikum',
            agent: 'uji-generate',
            tier: 'ekonomis',
            idempotencyKey: 'kunci-generate-sukses',
        );
        app(AiGateway::class)->generate($request);

        // ekonomis: (12×0.25 + 7×0.5)/1000 = 0.0065
        $ledger = AiCreditLedger::query()->where('idempotency_key', 'kunci-generate-sukses')->firstOrFail();
        $this->assertEqualsWithDelta(-0.0065, (float) $ledger->jumlah_kredit, 0.00001);

        $log = AiAuditLog::query()->where('idempotency_key', 'kunci-generate-sukses')->firstOrFail();
        $this->assertEqualsWithDelta(0.0065, (float) $log->biaya_kredit, 0.00001);
    }

    // ── Regresi review adversarial #1: dedup per-outcome ─────────────────

    public function test_retry_kunci_sama_setelah_kill_switch_tetap_tertagih(): void
    {
        $tenant = $this->tenantSiapMeter('metrk1');
        AiTenantBudget::query()->where('tenant_id', $tenant->id)
            ->update(['anggaran_kredit_bulanan' => 5]);
        $this->meter()->catat($tenant->id, $this->dataPemakaian(['kredit' => 6]), 'kunci-kill-awal');

        // 1) Request kunci K saat kill-switch aktif → termeter 0 di namespace
        //    degraded- (jalur ini tidak memanggil HTTP sama sekali).
        $this->fakeOllamaSukses();
        $request = new AiGenerateRequest(
            prompt: 'Assalamualaikum',
            agent: 'uji-retry',
            tier: 'ekonomis',
            idempotencyKey: 'kunci-retry-K',
        );
        $hasil1 = app(AiGateway::class)->generate($request);
        $this->assertTrue($hasil1->degraded);
        Http::assertNothingSent();

        // 2) Admin top-up / anggaran dilonggarkan + kill-switch padam.
        AiTenantBudget::query()->where('tenant_id', $tenant->id)->update([
            'anggaran_kredit_bulanan' => null,
            'kill_switch_aktif' => false,
            'terpakai_periode' => 0,
        ]);

        // 3) Retry sah kunci K yang sama → provider nyata terpanggil dan WAJIB tertagih
        //    (sebelum fix: ter-dedup terhadap baris 0 kredit → token gratis).
        $hasil2 = app(AiGateway::class)->generate($request);
        $this->assertFalse($hasil2->degraded);

        $tertagih = AiCreditLedger::query()->where('idempotency_key', 'kunci-retry-K')->first();
        $this->assertNotNull($tertagih, 'Pemakaian nyata pasca-retry wajib punya baris ledger sendiri');
        $this->assertLessThan(0.0, (float) $tertagih->jumlah_kredit, 'Pemakaian nyata wajib memotong kredit');

        $gratis = AiCreditLedger::query()->where('idempotency_key', 'degraded-kunci-retry-K')->first();
        $this->assertNotNull($gratis, 'Jejak penolakan kill-switch tetap ada di namespace degraded-');
    }

    // ── Regresi review adversarial #2: reprovision tidak mereset saldo ───

    public function test_reprovision_tenant_dengan_riwayat_tidak_mereset_rantai_saldo(): void
    {
        // Tenant pra-alokasi: punya riwayat pemakaian TANPA baris alokasi awal
        // (lockBudgetRow auto-membuat budget; skenario tenant lama pra-T-A002).
        $tenant = $this->createRuntimeTenant('metrp2', 'Pesantren Reprovision Saldo');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        $this->meter()->catat($tenant->id, $this->dataPemakaian(['kredit' => 1.5]), 'kunci-riwayat-1');

        app(AiBudgetProvisioner::class)->provision($tenant->id);

        $this->assertSame(
            0,
            AiCreditLedger::query()->where('tenant_id', $tenant->id)
                ->where('jenis', AiCreditLedger::JENIS_ALOKASI)->count(),
            'Tenant dengan riwayat TIDAK boleh disisipi alokasi-0 (mereset rantai saldo)',
        );

        // Baris terakhir (id tertinggi) tetap pemakaian; catat() berikutnya
        // melanjutkan rantai dari -1.5, bukan dari 0.
        $lanjut = $this->meter()->catat($tenant->id, $this->dataPemakaian(['kredit' => 0.5]), 'kunci-riwayat-2');
        $this->assertEqualsWithDelta(-2.0, (float) $lanjut->saldo_setelah, 0.0001);
    }

    // ── (j) Provisioning idempoten ───────────────────────────────────────

    public function test_provisioning_budget_idempoten(): void
    {
        $tenant = $this->createRuntimeTenant('metprv', 'Pesantren Provision Meter');

        $provisioner = app(AiBudgetProvisioner::class);
        $provisioner->provision($tenant->id);
        $provisioner->provision($tenant->id); // reprovision aman

        $this->assertSame(1, AiTenantBudget::query()->where('tenant_id', $tenant->id)->count());

        $budget = AiTenantBudget::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertNull($budget->anggaran_kredit_bulanan, 'Default tenant baru = tanpa batas anggaran');
        $this->assertSame(80, $budget->ambang_alert_persen);
        $this->assertFalse($budget->kill_switch_aktif);

        $alokasi = AiCreditLedger::query()
            ->where('tenant_id', $tenant->id)
            ->where('jenis', AiCreditLedger::JENIS_ALOKASI)
            ->get();
        $this->assertCount(1, $alokasi, 'Saldo awal alokasi wajib persis 1 baris walau provision 2×');
        $this->assertEqualsWithDelta(0.0, (float) $alokasi[0]->saldo_setelah, 0.0001);
    }

    // ── ai:health --tenant menampilkan baris anggaran ────────────────────

    public function test_ai_health_dengan_flag_tenant_menampilkan_status_anggaran(): void
    {
        $this->fakeOllamaSukses();

        $tenant = $this->tenantSiapMeter('methlt');
        tenancy()->end();

        Artisan::call('ai:health', ['--skip-generate' => true, '--tenant' => $tenant->id]);
        $output = Artisan::output();

        $this->assertStringContainsString("Anggaran '{$tenant->id}'", $output);
        $this->assertStringContainsString('Tanpa batas anggaran', $output);
    }
}
