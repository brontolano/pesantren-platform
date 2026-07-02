<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Services\PpdbDecisionNotificationService;
use RuntimeException;
use Tests\TestCase;

class PpdbDecisionNotificationServiceTest extends TestCase
{
    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'pgsql');
        Config::set('database.connections.pgsql.host', '127.0.0.1');
        Config::set('database.connections.pgsql.port', '5432');
        Config::set('database.connections.pgsql.database', 'pesantren_platform_test');
        Config::set('database.connections.pgsql.username', 'postgres');
        Config::set('database.connections.pgsql.password', 'secret');

        Artisan::call('migrate:fresh', ['--force' => true]);

        $this->tenantA = Tenant::create([
            'id' => 'ppdb-notif-a',
            'name' => 'Pesantren A',
            'is_active' => true,
        ]);
        $this->tenantA->domains()->create(['domain' => 'ppdb-notif-a']);

        $this->tenantB = Tenant::create([
            'id' => 'ppdb-notif-b',
            'name' => 'Pesantren B',
            'is_active' => true,
        ]);
        $this->tenantB->domains()->create(['domain' => 'ppdb-notif-b']);

        Http::fake([
            '*' => Http::response(['status' => 'sent'], 200),
        ]);

        config()->set('notifikasi.n8n_webhook_url', 'https://example.test/webhook/notifikasi');
        config()->set('app.url', 'https://ppdb.asy-syifaa.test');
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    public function test_notify_decision_sends_wa_and_records_log_for_lulus(): void
    {
        tenancy()->initialize($this->tenantA);

        $candidate = $this->makeCandidate([
            'nomor_pendaftaran' => 'PPDB-2026-201',
            'nama' => 'Abdullah',
            'nama_ayah' => 'Bapak Abdullah',
            'no_hp_wali' => '628111111111',
            'status_pendaftaran' => 'lulus',
            'catatan_keputusan' => 'Silakan registrasi ulang pekan ini.',
        ]);

        $broadcast = app(PpdbDecisionNotificationService::class)->notifyDecision($candidate);

        $this->assertSame('sent', $broadcast->status);
        $this->assertSame('custom', $broadcast->target_group);
        $this->assertSame('ppdb-keputusan-lulus', $broadcast->template->kode_template);
        $this->assertDatabaseHas('log_notifikasi', [
            'broadcast_id' => $broadcast->id,
            'status' => 'sent',
            'target' => '628111111111',
        ]);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $data['template_code'] === 'ppdb-keputusan-lulus'
                && $data['target'] === '628111111111'
                && str_contains($data['message'], 'Abdullah')
                && str_contains($data['message'], 'registrasi ulang')
                && str_contains($data['message'], '/ppdb/tracking/');
        });
    }

    public function test_notify_decision_uses_status_specific_template_for_ditolak(): void
    {
        tenancy()->initialize($this->tenantA);

        $candidate = $this->makeCandidate([
            'nomor_pendaftaran' => 'PPDB-2026-202',
            'nama' => 'Aisyah',
            'nama_ibu' => 'Ibu Aisyah',
            'no_hp_wali' => '628122222222',
            'status_pendaftaran' => 'ditolak',
            'catatan_keputusan' => 'Kuota sudah penuh.',
        ]);

        $broadcast = app(PpdbDecisionNotificationService::class)->notifyDecision($candidate);

        $this->assertSame('ppdb-keputusan-ditolak', $broadcast->template->kode_template);
        $this->assertDatabaseHas('template_notifikasi', [
            'tenant_id' => $this->tenantA->id,
            'kode_template' => 'ppdb-keputusan-ditolak',
            'channel' => 'wa',
        ]);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $data['template_code'] === 'ppdb-keputusan-ditolak'
                && str_contains($data['message'], 'BELUM LULUS')
                && str_contains($data['message'], 'Kuota sudah penuh.');
        });
    }

    public function test_notify_decision_requires_guardian_phone_number(): void
    {
        tenancy()->initialize($this->tenantA);

        $candidate = $this->makeCandidate([
            'nomor_pendaftaran' => 'PPDB-2026-203',
            'nama' => 'Hasan',
            'status_pendaftaran' => 'cadangan',
            'no_hp_wali' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nomor WhatsApp wali wajib ada');

        app(PpdbDecisionNotificationService::class)->notifyDecision($candidate);
    }

    public function test_notify_decision_is_isolated_per_tenant(): void
    {
        tenancy()->initialize($this->tenantA);
        $candidate = $this->makeCandidate([
            'nomor_pendaftaran' => 'PPDB-2026-204',
            'nama' => 'Zainab',
            'no_hp_wali' => '628133333333',
            'status_pendaftaran' => 'lulus',
        ]);
        tenancy()->end();

        tenancy()->initialize($this->tenantB);

        $this->expectException(ModelNotFoundException::class);

        app(PpdbDecisionNotificationService::class)->notifyDecision($candidate->id);
    }

    public function test_notify_decision_requires_active_tenant_context(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant context belum aktif');

        app(PpdbDecisionNotificationService::class)->notifyDecision(1);
    }

    private function makeCandidate(array $attributes): CalonSantri
    {
        return CalonSantri::create(array_merge([
            'nomor_pendaftaran' => 'PPDB-2026-999',
            'nama' => 'Calon',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status_pendaftaran' => 'baru',
        ], $attributes));
    }
}
