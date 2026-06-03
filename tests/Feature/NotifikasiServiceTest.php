<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Modules\Kesantrian\Models\Santri;
use Modules\Notifikasi\Jobs\BroadcastWaJob;
use Modules\Notifikasi\Models\BroadcastWa;
use Modules\Notifikasi\Models\TemplateNotifikasi;
use Modules\Notifikasi\Services\BroadcastWaService;
use Modules\Notifikasi\Services\EmailNotificationService;
use Tests\TestCase;

class NotifikasiServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['id' => 'notif-a', 'name' => 'Pesantren Notif']);
        $this->tenant->domains()->create(['domain' => 'notif-a']);
        tenancy()->initialize($this->tenant);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    public function test_wa_broadcast_kirim_ke_target_group(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'sent'], 200),
        ]);

        config()->set('notifikasi.n8n_webhook_url', 'https://example.test/webhook/notifikasi');

        Santri::create([
            'nis' => 'NOT001',
            'nama' => 'Ahmad',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'no_hp_wali' => '628111111111',
            'nama_ayah' => 'Bapak Ahmad',
        ]);

        Santri::create([
            'nis' => 'NOT002',
            'nama' => 'Fatimah',
            'tanggal_lahir' => '2012-02-01',
            'jenis_kelamin' => 'P',
            'status' => 'aktif',
            'no_hp_wali' => '628122222222',
            'nama_ibu' => 'Ibu Fatimah',
        ]);

        $template = TemplateNotifikasi::create([
            'kode_template' => 'tagihan-reminder',
            'judul' => 'Reminder Tagihan',
            'isi' => 'Assalamu alaikum {{nama_wali}}, tagihan {{nama_santri}} sudah jatuh tempo.',
            'channel' => 'wa',
        ]);

        $broadcast = BroadcastWa::create([
            'template_id' => $template->id,
            'target_group' => 'wali',
            'filter_criteria' => [],
            'status' => 'draft',
        ]);

        $result = app(BroadcastWaService::class)->execute($broadcast);

        $this->assertSame(2, $result['total']);
        $this->assertSame(2, $result['sent']);
        $this->assertSame(0, $result['failed']);
        $this->assertDatabaseCount('log_notifikasi', 2);
        $this->assertDatabaseHas('log_notifikasi', [
            'broadcast_id' => $broadcast->id,
            'status' => 'sent',
            'target' => '628111111111',
        ]);

        Http::assertSentCount(2);
    }

    public function test_log_tercatat_sent_dan_failed(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push(['status' => 'sent'], 200)
                ->pushStatus(500),
        ]);

        config()->set('notifikasi.n8n_webhook_url', 'https://example.test/webhook/notifikasi');

        Santri::create([
            'nis' => 'LOG001',
            'nama' => 'Ali',
            'tanggal_lahir' => '2012-03-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'no_hp_wali' => '628133333333',
        ]);

        Santri::create([
            'nis' => 'LOG002',
            'nama' => 'Aisyah',
            'tanggal_lahir' => '2012-04-01',
            'jenis_kelamin' => 'P',
            'status' => 'aktif',
            'no_hp_wali' => '628144444444',
        ]);

        $template = TemplateNotifikasi::create([
            'kode_template' => 'pengumuman',
            'judul' => 'Pengumuman',
            'isi' => 'Pesan untuk {{nama_santri}}',
            'channel' => 'wa',
        ]);

        $broadcast = BroadcastWa::create([
            'template_id' => $template->id,
            'target_group' => 'santri',
            'status' => 'draft',
        ]);

        $result = app(BroadcastWaService::class)->execute($broadcast);

        $this->assertSame(1, $result['sent']);
        $this->assertSame(1, $result['failed']);
        $this->assertDatabaseHas('log_notifikasi', [
            'broadcast_id' => $broadcast->id,
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('log_notifikasi', [
            'broadcast_id' => $broadcast->id,
            'status' => 'failed',
        ]);
    }

    public function test_queue_broadcast_dispatches_job_ke_queue_redis(): void
    {
        Queue::fake();

        $template = TemplateNotifikasi::create([
            'kode_template' => 'queue-reminder',
            'judul' => 'Queue Reminder',
            'isi' => 'Pesan queue',
            'channel' => 'wa',
        ]);

        $broadcast = BroadcastWa::create([
            'template_id' => $template->id,
            'target_group' => 'custom',
            'filter_criteria' => ['targets' => ['628155555555']],
            'status' => 'draft',
        ]);

        app(BroadcastWaService::class)->queue($broadcast);

        Queue::assertPushed(BroadcastWaJob::class);
        $this->assertDatabaseHas('broadcast_wa', [
            'id' => $broadcast->id,
            'status' => 'queued',
        ]);
    }

    public function test_email_notification_send_via_smtp_laravel(): void
    {
        Mail::fake();

        $template = TemplateNotifikasi::create([
            'kode_template' => 'email-reminder',
            'judul' => 'Halo {{nama_user}}',
            'isi' => 'Ini pengingat untuk {{nama_user}}.',
            'channel' => 'email',
        ]);

        app(EmailNotificationService::class)->send($template, 'wali@example.test', [
            'nama_user' => 'Wali Santri',
        ]);

        Mail::assertSentCount(1);
        $this->assertDatabaseHas('log_notifikasi', [
            'template_id' => $template->id,
            'channel' => 'email',
            'target' => 'wali@example.test',
            'status' => 'sent',
        ]);
    }
}
