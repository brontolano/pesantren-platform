<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Modules\Notifikasi\Mail\GenericNotificationMail;
use Modules\Notifikasi\Models\BroadcastWa;
use Modules\Notifikasi\Models\LogNotifikasi;
use Modules\Notifikasi\Models\TemplateNotifikasi;
use Modules\Notifikasi\Services\NotifDispatcher;
use Modules\Notifikasi\Support\NotifikasiSettings;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F7 — Notification Hooks (NotifDispatcher). Menguji resolusi event→template,
 * dispatch per channel (email/WA), fire-and-forget (tak melempar), dan isolasi.
 */
class NotifDispatcherTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'template_notifikasi', 'broadcast_wa', 'log_notifikasi',
        ]);

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(string $prefix): void
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
    }

    private function template(string $kode, string $channel): TemplateNotifikasi
    {
        return TemplateNotifikasi::create([
            'kode_template' => $kode,
            'judul' => 'Halo {nama}',
            'isi' => 'Pesan untuk {nama} terkait '.$kode,
            'variable_dinamis' => ['nama'],
            'channel' => $channel,
            'is_active' => true,
        ]);
    }

    public function test_resolve_template_dari_registry(): void
    {
        $this->boot('ntf1');
        $t = $this->template('keputusan_ppdb', 'email');

        $svc = app(NotifDispatcher::class);
        $this->assertSame($t->id, $svc->resolveTemplate('keputusan_ppdb')?->id);
        $this->assertNull($svc->resolveTemplate('event_tak_dikenal'));
        $this->assertContains('approval_chasing', $svc->eventTerdaftar());
        tenancy()->end();
    }

    public function test_kirim_email_channel(): void
    {
        $this->boot('ntf2');
        Mail::fake();
        $this->template('keputusan_ppdb', 'email');

        $hasil = app(NotifDispatcher::class)->kirim('keputusan_ppdb', [
            'email' => ['a@contoh.test', 'b@contoh.test'],
            'data' => ['nama' => 'Andi'],
        ]);

        $this->assertSame('email', $hasil['channel']);
        $this->assertSame(2, $hasil['terkirim']);
        $this->assertSame(0, $hasil['gagal']);
        Mail::assertSent(GenericNotificationMail::class, 2);
        tenancy()->end();
    }

    public function test_kirim_wa_channel_terkirim(): void
    {
        $this->boot('ntf3');
        // Endpoint dikonfigurasi → gateway POST nyata (di-fake 'ok') → benar terkirim.
        config(['notifikasi.n8n_webhook_url' => 'https://example.test/webhook']);
        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);
        $this->template('approval_chasing', 'wa');

        $hasil = app(NotifDispatcher::class)->kirim('approval_chasing', [
            'wa' => ['6281234567890'],
            'data' => ['nama' => 'Bendahara'],
        ]);

        $this->assertSame('wa', $hasil['channel']);
        $this->assertSame(1, $hasil['terkirim']);
        $this->assertSame(0, $hasil['gagal']);
        $this->assertSame(1, BroadcastWa::query()->count());
        // Kosakata kanonik: status broadcast = 'sent' (bukan 'selesai').
        $this->assertSame('sent', BroadcastWa::query()->value('status'));
        $this->assertTrue(LogNotifikasi::query()->where('status', 'sent')->exists());
        tenancy()->end();
    }

    public function test_wa_tanpa_endpoint_tidak_dihitung_terkirim(): void
    {
        $this->boot('ntf3b');
        // Tanpa webhook/waha → gateway 'simulated' → JUJUR: tak dihitung terkirim,
        // log status 'simulated' (bukan 'sent'), broadcast 'partial_failed'.
        config(['notifikasi.n8n_webhook_url' => null, 'notifikasi.waha_base_url' => null]);
        $this->template('approval_chasing', 'wa');

        $hasil = app(NotifDispatcher::class)->kirim('approval_chasing', [
            'wa' => ['6281234567890'],
            'data' => ['nama' => 'X'],
        ]);

        $this->assertSame(0, $hasil['terkirim']);
        $this->assertSame(1, $hasil['gagal']);
        $this->assertFalse(LogNotifikasi::query()->where('status', 'sent')->exists());
        $this->assertTrue(LogNotifikasi::query()->where('status', 'simulated')->exists());
        $this->assertSame('partial_failed', BroadcastWa::query()->value('status'));
        tenancy()->end();
    }

    public function test_template_tidak_aktif_fire_and_forget(): void
    {
        $this->boot('ntf4');
        // Tak ada template → tak melempar, kembalikan alasan.
        $hasil = app(NotifDispatcher::class)->kirim('tagihan_jatuh_tempo', ['wa' => ['628999']]);
        $this->assertSame(0, $hasil['terkirim']);
        $this->assertSame('template_tidak_aktif', $hasil['alasan']);
        tenancy()->end();
    }

    public function test_tanpa_penerima(): void
    {
        $this->boot('ntf5');
        $this->template('stok_expiry', 'wa');
        $hasil = app(NotifDispatcher::class)->kirim('stok_expiry', ['data' => ['nama' => 'X']]);
        $this->assertSame('tanpa_penerima', $hasil['alasan']);
        tenancy()->end();
    }

    public function test_isolasi_tenant(): void
    {
        $this->boot('ntfA');
        $this->template('keputusan_ppdb', 'email');
        $this->assertNotNull(app(NotifDispatcher::class)->resolveTemplate('keputusan_ppdb'));
        tenancy()->end();

        $this->boot('ntfB');
        $this->assertNull(app(NotifDispatcher::class)->resolveTemplate('keputusan_ppdb'));
        tenancy()->end();
    }

    public function test_webhook_url_per_tenant_menimpa_config_global(): void
    {
        $this->boot('wh1');
        config(['notifikasi.n8n_webhook_url' => 'http://n8n:5678/webhook/global']);

        // Tanpa setting → fallback config global.
        $this->assertSame('http://n8n:5678/webhook/global', NotifikasiSettings::webhookUrl());

        // Dengan setting tenant → override.
        Setting::updateOrCreate(
            ['key' => NotifikasiSettings::KEY_WA_WEBHOOK],
            ['value' => ['url' => 'http://n8n:5678/webhook/tenant-khusus']]
        );
        $this->assertSame('http://n8n:5678/webhook/tenant-khusus', NotifikasiSettings::webhookUrl());

        // Setting kosong → kembali fallback.
        Setting::updateOrCreate(
            ['key' => NotifikasiSettings::KEY_WA_WEBHOOK],
            ['value' => ['url' => '  ']]
        );
        $this->assertSame('http://n8n:5678/webhook/global', NotifikasiSettings::webhookUrl());
        tenancy()->end();
    }
}
