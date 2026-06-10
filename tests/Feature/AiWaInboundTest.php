<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\AI\Jobs\ProsesPesanWaJob;
use Modules\AI\Models\AiConversation;
use Modules\AI\Models\AiMessage;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * T-A050 — WhatsApp inbound chatbot (adaptasi K6: pilot WAHA+n8n):
 *  (a) keamanan token webhook (401 token salah/kosong);
 *  (b) fromMe / event non-message diabaikan;
 *  (c) pesan masuk tersimpan TERREDAKSI + nomor tak pernah polos di DB;
 *  (d) dedup wa_message_id (2x post → 1 baris) + job dispatched ke queue ai;
 *  (e) ack cepat: handler webhook TIDAK memanggil HTTP keluar apa pun;
 *  (f) opt-out STOP → flag + konfirmasi via gateway; pesan berikut DIAM;
 *      MULAI → opt-in kembali;
 *  (g) mode_uji → balasan template statis via WaGatewayService (tanpa LLM);
 *  (h) retensi ai:bersihkan-percakapan menghapus pesan tua sesuai default;
 *  (i) isolasi tenant.
 */
class AiWaInboundTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private const TOKEN = 'token-rahasia-uji';

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['ai_conversation', 'ai_message']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        Config::set('ai.wa.webhook_token', self::TOKEN);
        Config::set('ai.wa.mode_uji', true);
        // Outbound resmi WaGatewayService → cabang WAHA langsung (di-fake).
        Config::set('notifikasi.waha_base_url', 'http://waha.test:3000');
        Config::set('notifikasi.n8n_webhook_url', null);
        Config::set('notifikasi.waha_session', 'default');
    }

    protected function tearDown(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    private function siapkanTenant(string $prefix): \App\Models\Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);

        // Baris tenant LOKAL — target FK template_notifikasi.tenant_id dkk.
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        tenancy()->end();

        return $tenant;
    }

    /** @return array payload WAHA WEBJS standar yang diteruskan n8n. */
    private function payloadWa(string $tenantId, array $override = []): array
    {
        return array_replace_recursive([
            'event' => 'message',
            'session' => 'default',
            'tenant' => $tenantId,
            'payload' => [
                'id' => 'false_628111@c.us_ABC123',
                'from' => '628111222333@c.us',
                'body' => 'Assalamualaikum, mau tanya jadwal',
                'fromMe' => false,
            ],
        ], $override);
    }

    private function postWebhook(array $payload, ?string $token = self::TOKEN)
    {
        $headers = $token !== null ? ['X-Webhook-Token' => $token] : [];

        return $this->postJson('/api/ai/webhook/wa', $payload, $headers);
    }

    // ------------------------------------------------------------------
    // (a) Keamanan token
    // ------------------------------------------------------------------

    public function test_token_salah_atau_tanpa_token_ditolak_401(): void
    {
        $tenant = $this->siapkanTenant('wain');

        $this->postWebhook($this->payloadWa($tenant->id), 'token-keliru')->assertStatus(401);
        $this->postWebhook($this->payloadWa($tenant->id), null)->assertStatus(401);
    }

    public function test_token_belum_dikonfigurasi_webhook_mati(): void
    {
        $tenant = $this->siapkanTenant('wain');
        Config::set('ai.wa.webhook_token', null);

        // Bahkan header kosong == config kosong TIDAK boleh lolos.
        $this->postWebhook($this->payloadWa($tenant->id), '')->assertStatus(401);
    }

    // ------------------------------------------------------------------
    // (b) fromMe / event lain diabaikan
    // ------------------------------------------------------------------

    public function test_pesan_from_me_dan_event_non_message_diabaikan(): void
    {
        Queue::fake();
        $tenant = $this->siapkanTenant('wain');

        $this->postWebhook($this->payloadWa($tenant->id, ['payload' => ['fromMe' => true]]))
            ->assertOk()
            ->assertJson(['status' => 'diabaikan']);

        $this->postWebhook($this->payloadWa($tenant->id, ['event' => 'message.ack']))
            ->assertOk()
            ->assertJson(['status' => 'diabaikan']);

        tenancy()->initialize($tenant);
        $this->assertSame(0, AiMessage::query()->count());
        Queue::assertNothingPushed();
    }

    // ------------------------------------------------------------------
    // (c) Tersimpan terredaksi, nomor tidak polos
    // ------------------------------------------------------------------

    public function test_pesan_masuk_tersimpan_terredaksi_dan_nomor_tidak_polos(): void
    {
        Queue::fake();
        $tenant = $this->siapkanTenant('wain');

        $this->postWebhook($this->payloadWa($tenant->id, [
            'payload' => ['body' => 'Tolong hubungi saya di 081234567890 ya'],
        ]))->assertOk()->assertJson(['status' => 'diterima']);

        tenancy()->initialize($tenant);

        $msg = AiMessage::query()->sole();
        $this->assertSame(AiMessage::ARAH_MASUK, $msg->arah);
        $this->assertStringNotContainsString('081234567890', $msg->isi_terredaksi, 'No HP wajib terredaksi');
        $this->assertStringContainsString('[PII:HP:1]', $msg->isi_terredaksi);

        $conv = AiConversation::query()->sole();
        $this->assertSame('wa', $conv->kanal);
        $this->assertFalse($conv->opt_out);
        $this->assertNotNull($conv->terakhir_aktif);
        // Nomor: hanya hash + ciphertext — tak ada kolom yang memuat nomor polos.
        $this->assertSame(AiConversation::hashNomor('628111222333@c.us'), $conv->nomor_wa_hash);
        $this->assertStringNotContainsString('628111222333', $conv->nomor_wa_hash);
        $this->assertStringNotContainsString('628111222333', $conv->nomor_wa_terenkripsi);
        $this->assertSame('628111222333@c.us', Crypt::decryptString($conv->nomor_wa_terenkripsi));
    }

    // ------------------------------------------------------------------
    // (d) Dedup wa_message_id + job dispatched
    // ------------------------------------------------------------------

    public function test_webhook_ganda_dedup_satu_baris_dan_job_dispatched(): void
    {
        Queue::fake();
        $tenant = $this->siapkanTenant('wain');

        $this->postWebhook($this->payloadWa($tenant->id))->assertOk()->assertJson(['status' => 'diterima']);
        $this->postWebhook($this->payloadWa($tenant->id))->assertOk()->assertJson(['status' => 'duplikat']);

        tenancy()->initialize($tenant);
        $this->assertSame(1, AiMessage::query()->where('wa_message_id', 'false_628111@c.us_ABC123')->count());

        Queue::assertPushed(ProsesPesanWaJob::class, 1);
        Queue::assertPushed(ProsesPesanWaJob::class, function (ProsesPesanWaJob $job) use ($tenant): bool {
            return $job->tenantId === (string) $tenant->id
                && $job->queue === 'ai'
                && Crypt::decryptString($job->isiTerenkripsi) === 'Assalamualaikum, mau tanya jadwal';
        });
    }

    // ------------------------------------------------------------------
    // (e) Ack cepat: handler webhook nol HTTP keluar (nol panggilan LLM)
    // ------------------------------------------------------------------

    public function test_handler_webhook_tidak_memanggil_http_keluar_sama_sekali(): void
    {
        Queue::fake();
        Http::fake();
        $tenant = $this->siapkanTenant('wain');

        $this->postWebhook($this->payloadWa($tenant->id))->assertOk();

        Http::assertNothingSent(); // tanpa LLM, tanpa gateway — murni simpan + ack.
    }

    // ------------------------------------------------------------------
    // (f) Opt-out / opt-in
    // ------------------------------------------------------------------

    public function test_stop_set_opt_out_konfirmasi_sekali_lalu_diam(): void
    {
        Http::fake(['http://waha.test:3000/*' => Http::response(['status' => 'ok'])]);
        $tenant = $this->siapkanTenant('wain');

        // QUEUE_CONNECTION=sync — job langsung jalan saat webhook diterima.
        $this->postWebhook($this->payloadWa($tenant->id, [
            'payload' => ['id' => 'msg-stop-1', 'body' => 'STOP'],
        ]))->assertOk();

        tenancy()->initialize($tenant);
        $conv = AiConversation::query()->sole();
        $this->assertTrue($conv->opt_out, 'STOP wajib set opt_out');

        // Konfirmasi opt-out terkirim via gateway resmi (sekali).
        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/api/sendText')
                && str_contains((string) $request['text'], 'MULAI');
        });
        $this->assertSame(1, AiMessage::query()->where('arah', AiMessage::ARAH_KELUAR)->count());

        // Pesan berikutnya: DIAM total — tak ada balasan apa pun.
        tenancy()->end();
        $this->postWebhook($this->payloadWa($tenant->id, [
            'payload' => ['id' => 'msg-stop-2', 'body' => 'Masih ada info jadwal?'],
        ]))->assertOk();

        Http::assertSentCount(1); // tetap 1 — tidak bertambah.
        tenancy()->initialize($tenant);
        $this->assertSame(1, AiMessage::query()->where('arah', AiMessage::ARAH_KELUAR)->count());
    }

    public function test_mulai_membuka_kembali_opt_out(): void
    {
        Http::fake(['http://waha.test:3000/*' => Http::response(['status' => 'ok'])]);
        $tenant = $this->siapkanTenant('wain');

        $this->postWebhook($this->payloadWa($tenant->id, [
            'payload' => ['id' => 'm1', 'body' => 'BERHENTI'],
        ]))->assertOk();
        $this->postWebhook($this->payloadWa($tenant->id, [
            'payload' => ['id' => 'm2', 'body' => 'MULAI'],
        ]))->assertOk();

        tenancy()->initialize($tenant);
        $this->assertFalse(AiConversation::query()->sole()->opt_out);
        Http::assertSentCount(2); // konfirmasi opt-out + konfirmasi opt-in.
    }

    // ------------------------------------------------------------------
    // (g) Mode uji: balasan template statis tanpa LLM
    // ------------------------------------------------------------------

    public function test_mode_uji_membalas_template_statis_via_gateway_tanpa_llm(): void
    {
        Http::fake(['http://waha.test:3000/*' => Http::response(['status' => 'ok'])]);
        $tenant = $this->siapkanTenant('wain');

        $this->postWebhook($this->payloadWa($tenant->id))->assertOk();

        // Satu-satunya HTTP keluar = WaGatewayService → WAHA sendText.
        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'waha.test:3000/api/sendText')
                && $request['chatId'] === '628111222333@c.us'
                && str_contains((string) $request['text'], 'pesan Anda sudah kami terima');
        });

        tenancy()->initialize($tenant);
        $keluar = AiMessage::query()->where('arah', AiMessage::ARAH_KELUAR)->sole();
        $this->assertSame('mode_uji', $keluar->meta['jenis'] ?? null);
    }

    public function test_di_luar_mode_uji_tidak_ada_balasan(): void
    {
        Config::set('ai.wa.mode_uji', false);
        Http::fake();
        $tenant = $this->siapkanTenant('wain');

        $this->postWebhook($this->payloadWa($tenant->id))->assertOk();

        Http::assertNothingSent(); // intent nyata = T-A051, belum ada balasan.
        tenancy()->initialize($tenant);
        $this->assertSame(0, AiMessage::query()->where('arah', AiMessage::ARAH_KELUAR)->count());
        $this->assertSame(1, AiMessage::query()->where('arah', AiMessage::ARAH_MASUK)->count());
    }

    // ------------------------------------------------------------------
    // (h) Retensi
    // ------------------------------------------------------------------

    public function test_retensi_menghapus_pesan_tua_default_90_hari(): void
    {
        Queue::fake();
        $tenant = $this->siapkanTenant('wain');

        $this->postWebhook($this->payloadWa($tenant->id))->assertOk();

        tenancy()->initialize($tenant);
        $conv = AiConversation::query()->sole();

        // Pesan tua (100 hari) — melewati retensi default 90.
        $tua = AiMessage::create([
            'conversation_id' => $conv->id,
            'arah' => AiMessage::ARAH_MASUK,
            'isi_terredaksi' => 'pesan lama',
            'wa_message_id' => 'msg-tua-1',
        ]);
        AiMessage::query()->whereKey($tua->id)->update(['created_at' => now()->subDays(100)]);
        tenancy()->end();

        $this->artisan('ai:bersihkan-percakapan', ['tenant' => $tenant->id])->assertExitCode(0);

        tenancy()->initialize($tenant);
        $this->assertNull(AiMessage::query()->find($tua->id), 'Pesan > retensi wajib terhapus');
        $this->assertSame(1, AiMessage::query()->count(), 'Pesan baru wajib bertahan');
        $this->assertNotNull(AiConversation::query()->find($conv->id), 'Percakapan masih punya pesan — bertahan');
    }

    // ------------------------------------------------------------------
    // (i) Isolasi tenant
    // ------------------------------------------------------------------

    public function test_isolasi_tenant_pesan_hanya_di_tenant_tujuan(): void
    {
        Queue::fake();
        $tenantA = $this->siapkanTenant('waia');
        $tenantB = $this->siapkanTenant('waib');

        $this->postWebhook($this->payloadWa($tenantA->id))->assertOk();

        tenancy()->initialize($tenantA);
        $this->assertSame(1, AiMessage::query()->count());
        tenancy()->end();

        tenancy()->initialize($tenantB);
        $this->assertSame(0, AiMessage::query()->count(), 'Pesan tenant A tak boleh bocor ke tenant B');
        $this->assertSame(0, AiConversation::query()->count());
    }

    public function test_tenant_tidak_dikenal_ditolak(): void
    {
        Queue::fake();
        $this->siapkanTenant('wain');

        Config::set('ai.wa.tenant_default', null);
        $payload = $this->payloadWa('apapun');
        unset($payload['tenant']);

        $this->postWebhook($payload)->assertStatus(422);
        Queue::assertNothingPushed();
    }
}
