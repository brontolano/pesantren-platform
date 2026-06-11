<?php

namespace Tests\Feature;

use App\Models\AccessWali;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Modules\AI\Filament\Pages\KotakMasukWa;
use Modules\AI\Models\AiApproval;
use Modules\AI\Models\AiConversation;
use Modules\AI\Models\AiMessage;
use Modules\AI\Models\AiTenantSetting;
use Modules\AI\Services\Gateway\AiGenerateResult;
use Modules\AI\Services\Rag\RagAnswerer;
use Modules\AI\Services\Settings\AiSettingProvisioner;
use Modules\AI\Services\Settings\AiTenantSettingDefaults;
use Modules\AI\Services\Settings\TenantSettingService;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Tagihan;
use Modules\Notifikasi\Models\LogNotifikasi;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * T-A052 — handoff manusia & Kotak Masuk WA (PRD §8.2):
 *  (a) wali terverifikasi minta manusia (keyword config) → status handoff +
 *      konfirmasi santun + LogNotifikasi pemegang ai.chat.kelola;
 *  (b) 2× jawaban free-form gagal beruntun (degraded / tanpa-rujukan) →
 *      tawaran handoff otomatis + status handoff; jawaban sukses me-reset;
 *  (c) saat handoff bot DIAM total (pesan tersimpan, NOL balasan otomatis,
 *      NOL HTTP Ollama) + notifikasi pesan masuk; STOP (opt-out) tetap jalan;
 *  (d) balasan manual admin dari halaman → terkirim via jalur outbound resmi
 *      (WaGatewayService) + tercatat ai_message keluar meta {manual, oleh};
 *  (e) Kembalikan ke Bot → aktif + reset gagal_beruntun → alur normal lagi;
 *  (f) RBAC halaman Kotak Masuk (ai.chat.kelola; admin_pesantren/tu via role);
 *  (g) privasi: nomor penuh TIDAK PERNAH dirender — hanya 4 digit terakhir;
 *  (h) isolasi tenant.
 */
class AiWaHandoffInboxTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private const TOKEN = 'token-rahasia-uji';

    private const NOMOR_JID = '628111222333@c.us';

    private const NO_HP_WALI = '08111222333';

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'ai_conversation', 'ai_message', 'ai_tenant_setting', 'ai_approval',
            'ai_policy', 'ai_policy_rule', 'ai_escalation', 'log_notifikasi',
            'santri', 'access_wali', 'tagihan', 'pembayaran',
        ]);

        if (! Schema::connection('pgsql')->hasColumn('ai_conversation', 'gagal_beruntun')) {
            Artisan::call('migrate', ['--force' => true]);
        }

        $this->truncatePpdbTables([
            'domains', 'tenants', 'santri', 'access_wali', 'tagihan', 'pembayaran',
        ]);
        // Baseline gating modul T-A012: registry kosong = modul tampil.
        DB::table('module_registry')->delete();
        Cache::flush();

        Config::set('ai.wa.webhook_token', self::TOKEN);
        Config::set('ai.wa.mode_uji', false);
        Config::set('notifikasi.waha_base_url', 'http://waha.test:3000');
        Config::set('notifikasi.n8n_webhook_url', null);
        Config::set('notifikasi.waha_session', 'default');

        Filament::setCurrentPanel(Filament::getPanel('tenant'));
    }

    protected function tearDown(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Pendukung (pola AiWaIntentWaliTest)
    // ------------------------------------------------------------------

    private function siapkanTenant(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);

        $this->seedTenantRow($tenant);
        $this->seed(RolesAndPermissionsSeeder::class);
        app(AiSettingProvisioner::class)->provision((string) $tenant->id);

        return $tenant;
    }

    private function buatUser(string $email, array $roles = [], array $permissions = []): User
    {
        $user = User::create([
            'name' => 'Uji '.$email,
            'email' => $email,
            'password' => 'password',
        ]);

        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    /** Aktifkan setting dengan override (alur approval kyai bila sensitif). */
    private function terapkanSetting(array $overrides): void
    {
        $admin = $this->buatUser('admin-'.uniqid().'@test.local', [], ['ai.setting.kelola']);
        $kyai = $this->buatUser('kyai-'.uniqid().'@test.local', [], ['ai.setting.setujui']);
        $service = app(TenantSettingService::class);

        $payload = array_replace(AiTenantSettingDefaults::konservatif(), $overrides);

        $draf = $service->simpanDraf($payload, $admin);
        $hasil = $service->ajukan($draf->versi, $admin);

        if ($hasil->status === AiTenantSetting::STATUS_PENDING_APPROVAL) {
            $approval = AiApproval::query()
                ->where('ai_tenant_setting_id', $draf->id)
                ->firstOrFail();
            $service->putuskan($approval->id, $kyai, true);
        }
    }

    private function aktifkanChatbotWali(): void
    {
        $fitur = AiTenantSettingDefaults::konservatif()['fitur_per_peran'];
        $fitur['wali']['chatbot'] = true;

        $this->terapkanSetting(['fitur_per_peran' => $fitur]);
    }

    /** @return array{0: User, 1: Santri} */
    private function buatWaliDenganAnak(
        string $nama = 'Ahmad Fauzi',
        string $tanggalLahir = '2014-03-05',
    ): array {
        $wali = User::query()->where('email', 'wali-wa@test.local')->first()
            ?? $this->buatUser('wali-wa@test.local', ['wali']);

        $santri = Santri::create([
            'nis' => 'WA'.str_pad((string) (Santri::query()->count() + 1), 3, '0', STR_PAD_LEFT),
            'nama' => $nama,
            'tanggal_lahir' => $tanggalLahir,
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'no_hp_wali' => self::NO_HP_WALI,
            'wali_user_id' => $wali->id,
        ]);

        AccessWali::create([
            'wali_id' => $wali->id,
            'santri_id' => $santri->id,
            'relationship_type' => 'ayah',
            'is_primary' => true,
        ]);

        return [$wali, $santri];
    }

    private function buatTagihan(Santri $s, float $nominal): Tagihan
    {
        return Tagihan::create([
            'santri_id' => $s->id,
            'jenis_tagihan' => 'spp',
            'nominal' => $nominal,
            'nominal_akhir' => $nominal,
            'status' => 'belum_bayar',
            'jatuh_tempo' => '2026-07-01',
        ]);
    }

    private function kirim(Tenant $tenant, string $id, string $body, string $from = self::NOMOR_JID)
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        return $this->postJson('/api/ai/webhook/wa', [
            'event' => 'message',
            'session' => 'default',
            'tenant' => (string) $tenant->id,
            'payload' => ['id' => $id, 'from' => $from, 'body' => $body, 'fromMe' => false],
        ], ['X-Webhook-Token' => self::TOKEN]);
    }

    /** Teks balasan terakhir yang terkirim ke WAHA (Http fake). */
    private function balasanTerakhir(): string
    {
        $recorded = Http::recorded(
            fn ($request) => str_contains($request->url(), '/api/sendText'),
        );

        $this->assertNotEmpty($recorded, 'Tidak ada balasan WA yang terkirim.');

        return (string) $recorded[count($recorded) - 1][0]['text'];
    }

    private function fakeWaha(): void
    {
        Http::fake(['http://waha.test:3000/*' => Http::response(['status' => 'ok'])]);
        Http::preventStrayRequests(); // HTTP tak ter-fake (mis. Ollama) = GAGAL
    }

    private function verifikasiSampaiSukses(Tenant $tenant): void
    {
        $this->kirim($tenant, 'ver-'.uniqid(), 'assalamualaikum, mau tanya kabar ananda')->assertOk();
        $this->kirim($tenant, 'ver-'.uniqid(), '05-03-2014')->assertOk();
    }

    /** Hasil RAG degradasi provider (NullProvider). */
    private function hasilDegraded(): AiGenerateResult
    {
        return new AiGenerateResult(
            text: (string) config('ai.gateway.degraded_message'),
            provider: 'null',
            model: 'null',
            degraded: true,
        );
    }

    /** Hasil RAG tanpa-rujukan (bukan degradasi provider, provider 'null'). */
    private function hasilTanpaRujukan(): AiGenerateResult
    {
        return new AiGenerateResult(
            text: (string) config('ai.rag.pesan.tanpa_rujukan'),
            provider: 'null',
            model: 'null',
            degraded: false,
        );
    }

    private function hasilSukses(): AiGenerateResult
    {
        return new AiGenerateResult(
            text: 'Visi pesantren adalah mencetak generasi qurani. (uji)',
            provider: 'ollama',
            model: 'uji',
        );
    }

    // ------------------------------------------------------------------
    // (a) Wali minta manusia → handoff + konfirmasi + notifikasi admin
    // ------------------------------------------------------------------

    public function test_wali_minta_manusia_handoff_konfirmasi_dan_notifikasi_admin(): void
    {
        $this->fakeWaha();
        $tenant = $this->siapkanTenant('hoffa');
        $this->aktifkanChatbotWali();
        $this->buatWaliDenganAnak();
        $petugas = $this->buatUser('tu-inbox@test.local', [], ['ai.chat.kelola']);
        $this->verifikasiSampaiSukses($tenant);

        $this->kirim($tenant, 'a-1', 'tolong saya mau bicara dengan admin langsung')->assertOk();

        // Konfirmasi santun terkirim ke wali.
        $this->assertStringContainsString('kami teruskan kepada admin pesantren', $this->balasanTerakhir());

        tenancy()->initialize($tenant);
        $conv = AiConversation::query()->sole();
        $this->assertSame(AiConversation::STATUS_HANDOFF, $conv->status);

        // Notifikasi ke pemegang ai.chat.kelola — TANPA nomor penuh.
        $notif = LogNotifikasi::query()->where('jenis', 'ai_wa_handoff')->sole();
        $this->assertSame('user:'.$petugas->id, $notif->target);
        $this->assertSame('diminta_wali', $notif->payload['pemicu'] ?? null);
        $this->assertSame($conv->id, (int) ($notif->payload['conversation_id'] ?? 0));
        $this->assertStringNotContainsString('628111222333', json_encode($notif->payload));
        $this->assertStringContainsString('2333', (string) ($notif->payload['nomor_tersamar'] ?? ''));
    }

    // ------------------------------------------------------------------
    // (b) 2× jawaban gagal beruntun → tawaran handoff otomatis
    // ------------------------------------------------------------------

    public function test_dua_jawaban_gagal_beruntun_menawarkan_handoff_otomatis(): void
    {
        $this->fakeWaha();
        $this->mock(RagAnswerer::class, function ($mock): void {
            $mock->shouldReceive('jawab')
                ->twice()
                ->andReturn($this->hasilDegraded(), $this->hasilTanpaRujukan());
        });

        $tenant = $this->siapkanTenant('hoffb');
        $this->aktifkanChatbotWali();
        $this->buatWaliDenganAnak();
        $this->verifikasiSampaiSukses($tenant);

        // Gagal #1 (degraded): masih dijawab biasa, TANPA tawaran handoff.
        $this->kirim($tenant, 'b-1', 'apa visi misi pesantren ini')->assertOk();
        $this->assertStringNotContainsString('menunggu balasan admin', $this->balasanTerakhir());

        tenancy()->initialize($tenant);
        $conv = AiConversation::query()->sole();
        $this->assertSame(AiConversation::STATUS_AKTIF, $conv->status);
        $this->assertSame(1, $conv->gagal_beruntun);

        // Gagal #2 (tanpa-rujukan): tawaran handoff + status handoff.
        $this->kirim($tenant, 'b-2', 'bagaimana sejarah berdirinya pesantren ini')->assertOk();
        $this->assertStringContainsString('menunggu balasan admin', $this->balasanTerakhir());

        tenancy()->initialize($tenant);
        $conv->refresh();
        $this->assertSame(AiConversation::STATUS_HANDOFF, $conv->status);
        $this->assertSame(2, $conv->gagal_beruntun);
        $this->assertSame(
            'gagal_beruntun',
            LogNotifikasi::query()->where('jenis', 'ai_wa_handoff')->sole()->payload['pemicu'] ?? null,
        );
    }

    public function test_jawaban_sukses_mereset_hitungan_gagal_beruntun(): void
    {
        $this->fakeWaha();
        $this->mock(RagAnswerer::class, function ($mock): void {
            $mock->shouldReceive('jawab')
                ->twice()
                ->andReturn($this->hasilDegraded(), $this->hasilSukses());
        });

        $tenant = $this->siapkanTenant('hoffc');
        $this->aktifkanChatbotWali();
        $this->buatWaliDenganAnak();
        $this->verifikasiSampaiSukses($tenant);

        $this->kirim($tenant, 'c-1', 'apa visi misi pesantren ini')->assertOk();

        tenancy()->initialize($tenant);
        $this->assertSame(1, AiConversation::query()->sole()->gagal_beruntun);

        // Jawaban sukses memutus rentetan — hitungan kembali 0, tetap aktif.
        $this->kirim($tenant, 'c-2', 'bagaimana program unggulan pesantren ini')->assertOk();
        $this->assertStringContainsString('Visi pesantren', $this->balasanTerakhir());

        tenancy()->initialize($tenant);
        $conv = AiConversation::query()->sole();
        $this->assertSame(AiConversation::STATUS_AKTIF, $conv->status);
        $this->assertSame(0, $conv->gagal_beruntun);
    }

    // ------------------------------------------------------------------
    // (c) Saat handoff: bot diam total, STOP tetap jalan
    // ------------------------------------------------------------------

    public function test_saat_handoff_bot_diam_pesan_tersimpan_dan_stop_tetap_jalan(): void
    {
        $this->fakeWaha();
        // RAG/LLM tidak boleh tersentuh sama sekali selama handoff.
        $this->mock(RagAnswerer::class);

        $tenant = $this->siapkanTenant('hoffd');
        $this->aktifkanChatbotWali();
        $this->buatWaliDenganAnak();
        $this->buatUser('tu-inbox@test.local', [], ['ai.chat.kelola']);
        $this->verifikasiSampaiSukses($tenant);
        $this->kirim($tenant, 'd-1', 'minta tolong sambungkan ke operator')->assertOk();

        tenancy()->initialize($tenant);
        $this->assertSame(AiConversation::STATUS_HANDOFF, AiConversation::query()->sole()->status);
        $jumlahHttp = count(Http::recorded());
        $jumlahKeluar = AiMessage::query()->where('arah', AiMessage::ARAH_KELUAR)->count();
        $jumlahMasuk = AiMessage::query()->where('arah', AiMessage::ARAH_MASUK)->count();

        // Pesan baru saat handoff: tersimpan, NOL balasan otomatis, NOL HTTP
        // (Ollama tak ter-fake — panggilan apa pun menggagalkan test).
        $this->kirim($tenant, 'd-2', 'halo, apakah sudah dibaca?')->assertOk();

        $this->assertCount($jumlahHttp, Http::recorded(), 'Bot wajib DIAM saat handoff');

        tenancy()->initialize($tenant);
        $this->assertSame($jumlahMasuk + 1, AiMessage::query()->where('arah', AiMessage::ARAH_MASUK)->count(), 'Pesan masuk wajib tetap tersimpan');
        $this->assertSame($jumlahKeluar, AiMessage::query()->where('arah', AiMessage::ARAH_KELUAR)->count(), 'NOL balasan otomatis saat handoff');

        // Admin tetap diberi tahu ada pesan menunggu.
        $this->assertSame(1, LogNotifikasi::query()->where('jenis', 'ai_wa_handoff_pesan')->count());

        // STOP (opt-out) TETAP dihormati saat handoff: flag + konfirmasi.
        $this->kirim($tenant, 'd-3', 'STOP')->assertOk();
        $this->assertStringContainsString('MULAI', $this->balasanTerakhir());

        tenancy()->initialize($tenant);
        $this->assertTrue(AiConversation::query()->sole()->opt_out, 'STOP wajib set opt_out walau handoff');
    }

    // ------------------------------------------------------------------
    // (d) Balasan manual admin → outbound resmi + tercatat
    // ------------------------------------------------------------------

    public function test_balasan_manual_admin_terkirim_via_outbound_dan_tercatat(): void
    {
        $this->fakeWaha();
        $tenant = $this->siapkanTenant('hoffe');
        $this->aktifkanChatbotWali();
        $this->buatWaliDenganAnak();
        $this->verifikasiSampaiSukses($tenant);
        $this->kirim($tenant, 'e-1', 'saya ingin bicara dengan admin')->assertOk();

        tenancy()->initialize($tenant);
        $conv = AiConversation::query()->sole();
        $admin = $this->buatUser('admin-inbox@test.local', [], ['ai.chat.kelola']);
        $this->actingAs($admin);

        Livewire::test(KotakMasukWa::class)
            ->set("balasTeks.{$conv->id}", "Wa'alaikumussalam, baik Bapak/Ibu, ada yang bisa kami bantu?")
            ->call('kirimBalasan', $conv->id);

        // Terkirim via jalur outbound resmi (WaGatewayService → WAHA).
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/api/sendText')
                && $request['chatId'] === self::NOMOR_JID
                && str_contains((string) $request['text'], 'ada yang bisa kami bantu');
        });

        // Tercatat ai_message keluar terredaksi bermeta {manual, oleh}.
        $keluar = AiMessage::query()
            ->where('arah', AiMessage::ARAH_KELUAR)
            ->orderByDesc('id')
            ->first();
        $this->assertSame('manual', $keluar->meta['jenis'] ?? null);
        $this->assertSame($admin->id, (int) ($keluar->meta['oleh'] ?? 0));
        $this->assertStringContainsString('ada yang bisa kami bantu', $keluar->isi_terredaksi);
    }

    public function test_balasan_manual_kosong_ditolak_tanpa_kirim(): void
    {
        $this->fakeWaha();
        $tenant = $this->siapkanTenant('hofff');
        $this->aktifkanChatbotWali();
        $this->buatWaliDenganAnak();
        $this->verifikasiSampaiSukses($tenant);

        tenancy()->initialize($tenant);
        $conv = AiConversation::query()->sole();
        $this->actingAs($this->buatUser('admin-inbox@test.local', [], ['ai.chat.kelola']));

        $jumlahHttp = count(Http::recorded());

        Livewire::test(KotakMasukWa::class)
            ->set("balasTeks.{$conv->id}", '   ')
            ->call('kirimBalasan', $conv->id);

        $this->assertCount($jumlahHttp, Http::recorded(), 'Balasan kosong tidak boleh terkirim');
    }

    // ------------------------------------------------------------------
    // (e) Ambil alih + Kembalikan ke Bot → alur normal jalan lagi
    // ------------------------------------------------------------------

    public function test_ambil_alih_lalu_kembalikan_ke_bot_alur_normal_jalan_lagi(): void
    {
        $this->fakeWaha();
        $tenant = $this->siapkanTenant('hoffg');
        $this->aktifkanChatbotWali();
        [, $anak] = $this->buatWaliDenganAnak();
        $this->buatTagihan($anak, 150000);
        $this->verifikasiSampaiSukses($tenant);

        tenancy()->initialize($tenant);
        $conv = AiConversation::query()->sole();
        // Simulasikan rentetan gagal sebelumnya — wajib ikut ter-reset.
        $conv->update(['gagal_beruntun' => 1]);
        $admin = $this->buatUser('admin-inbox@test.local', [], ['ai.chat.kelola']);
        $this->actingAs($admin);

        // Ambil Alih manual dari inbox → handoff + notifikasi.
        Livewire::test(KotakMasukWa::class)->call('ambilAlih', $conv->id);

        $conv->refresh();
        $this->assertSame(AiConversation::STATUS_HANDOFF, $conv->status);
        $this->assertSame(
            'ambil_alih_admin',
            LogNotifikasi::query()->where('jenis', 'ai_wa_handoff')->sole()->payload['pemicu'] ?? null,
        );

        // Saat handoff: pertanyaan tagihan pun DIDIAMKAN (tool tak jalan).
        $jumlahHttp = count(Http::recorded());
        $this->kirim($tenant, 'g-1', 'tagihan')->assertOk();
        $this->assertCount($jumlahHttp, Http::recorded());

        // Kembalikan ke Bot → aktif + reset gagal_beruntun.
        tenancy()->initialize($tenant);
        $this->actingAs($admin);
        Livewire::test(KotakMasukWa::class)->call('kembalikanKeBot', $conv->id);

        $conv->refresh();
        $this->assertSame(AiConversation::STATUS_AKTIF, $conv->status);
        $this->assertSame(0, $conv->gagal_beruntun);

        // Alur normal jalan lagi: intent tagihan dijawab tool (nominal nyata).
        $this->kirim($tenant, 'g-2', 'tagihan')->assertOk();
        $this->assertStringContainsString('Rp 150.000', $this->balasanTerakhir());
    }

    // ------------------------------------------------------------------
    // (f) RBAC halaman
    // ------------------------------------------------------------------

    public function test_rbac_halaman_kotak_masuk_wa(): void
    {
        $this->siapkanTenant('hoffh');

        $this->assertTrue(Route::has('filament.tenant.pages.kotak-masuk-wa'));

        // Tanpa izin: tertutup.
        $this->actingAs($this->buatUser('polos@test.local'));
        $this->assertFalse(KotakMasukWa::canAccess());

        // Pemegang ai.chat.kelola langsung: terbuka + halaman dapat dirender.
        $this->actingAs($this->buatUser('petugas@test.local', [], ['ai.chat.kelola']));
        $this->assertTrue(KotakMasukWa::canAccess());
        Livewire::test(KotakMasukWa::class)->assertOk();

        // Role admin_pesantren & tu mewarisi ai.chat.kelola dari seeder.
        $this->actingAs($this->buatUser('adminp@test.local', ['admin_pesantren']));
        $this->assertTrue(KotakMasukWa::canAccess());

        $this->actingAs($this->buatUser('tu@test.local', ['tu']));
        $this->assertTrue(KotakMasukWa::canAccess());

        // Asatidz (punya ai.eskalasi.kelola) TIDAK otomatis pegang inbox WA.
        $this->actingAs($this->buatUser('asatidz@test.local', ['asatidz']));
        $this->assertFalse(KotakMasukWa::canAccess());
    }

    // ------------------------------------------------------------------
    // (g) Privasi: nomor tersamar di render
    // ------------------------------------------------------------------

    public function test_nomor_tersamar_di_render_tanpa_nomor_penuh(): void
    {
        $this->fakeWaha();
        $tenant = $this->siapkanTenant('hoffi');
        $this->aktifkanChatbotWali();
        $this->buatWaliDenganAnak();
        $this->verifikasiSampaiSukses($tenant);

        tenancy()->initialize($tenant);
        $conv = AiConversation::query()->sole();
        $this->actingAs($this->buatUser('admin-inbox@test.local', [], ['ai.chat.kelola']));

        // Daftar + thread terbuka: hanya 4 digit terakhir, nama wali tampil,
        // nomor penuh TIDAK PERNAH menyentuh halaman.
        Livewire::test(KotakMasukWa::class)
            ->assertSee('••••2333')
            ->assertSee('Uji wali-wa@test.local')
            ->assertDontSee('628111222333')
            ->call('pilih', $conv->id)
            ->assertSee('••••2333')
            ->assertDontSee('628111222333')
            ->assertDontSee(self::NO_HP_WALI);
    }

    // ------------------------------------------------------------------
    // (h) Isolasi tenant
    // ------------------------------------------------------------------

    public function test_isolasi_tenant_kotak_masuk(): void
    {
        $this->fakeWaha();
        $tenantA = $this->siapkanTenant('hoffj');
        $this->aktifkanChatbotWali();
        $this->buatWaliDenganAnak();
        $this->verifikasiSampaiSukses($tenantA);

        tenancy()->initialize($tenantA);
        $this->assertSame(1, AiConversation::query()->count());
        tenancy()->end();

        // Tenant B: kotak masuk kosong — percakapan A tidak bocor.
        $this->siapkanTenant('hoffk');
        $this->actingAs($this->buatUser('admin-b@test.local', [], ['ai.chat.kelola']));

        $this->assertSame(0, AiConversation::query()->count());

        Livewire::test(KotakMasukWa::class)
            ->assertSee('Belum ada percakapan')
            ->assertDontSee('••••2333');
    }
}
