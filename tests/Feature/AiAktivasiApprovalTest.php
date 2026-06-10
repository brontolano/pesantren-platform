<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\ModuleRegistrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Modules\AI\Filament\Pages\PengaturanAi;
use Modules\AI\Filament\Pages\PersetujuanAi;
use Modules\AI\Models\AiApproval;
use Modules\AI\Models\AiAuditLog;
use Modules\AI\Services\Gateway\AiGateway;
use Modules\AI\Services\Gateway\AiGenerateRequest;
use Modules\AI\Services\Settings\AiSettingProvisioner;
use Modules\AI\Services\Settings\AiTenantSettingDefaults;
use Modules\AI\Services\Settings\TenantSettingService;
use Modules\Notifikasi\Models\LogNotifikasi;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * T-A012 — alur persetujuan kyai & aktivasi modul AI:
 *  (a) notifikasi pengajuan ke pemegang izin setujui — terkirim SEKALI,
 *      hanya untuk pengajuan sensitif (menunggu keputusan);
 *  (b) badge navigasi halaman persetujuan = jumlah pengajuan menunggu;
 *  (c) tautan ringkasan bertanda tangan: BACA-SAJA tanpa login, kedaluwarsa/
 *      tanpa tanda tangan = 403, tersedia tombol salin di halaman Wizard;
 *  (d) hash ringkasan yang disetujui == hash payload yang berjalan;
 *  (e) modul `ai` nonaktif untuk tenant → gateway menolak santun TANPA HTTP
 *      (gerbang a0 PolicyEngine — pelengkap gating halaman T-A011).
 */
class AiAktivasiApprovalTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Render halaman Wizard (puluhan komponen Filament) butuh ruang
        // lebih dari 128M bawaan php-cli (pola AiWizardPengaturanTest).
        ini_set('memory_limit', '512M');

        $this->ensurePpdbTestingSchemaReady([
            'ai_tenant_setting', 'ai_approval', 'ai_audit_log',
            'ai_model_config', 'ai_credit_ledger', 'log_notifikasi',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        // Baseline gating modul: registry kosong = modul tampil (pola
        // ModuleService); test gerbang modul menyemai registry sendiri.
        DB::table('module_registry')->delete();
        Cache::flush();

        Filament::setCurrentPanel(Filament::getPanel('tenant'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        URL::forceRootUrl(null);

        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Pendukung
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

    private function buatUser(string $email, array $permissions = []): User
    {
        $user = User::create([
            'name' => 'Uji '.$email,
            'email' => $email,
            'password' => 'password',
        ]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    private function admin(): User
    {
        return $this->buatUser('admin-aktivasi@test.local', ['ai.setting.kelola', 'ai.setting.lihat']);
    }

    private function kyai(): User
    {
        return $this->buatUser('kyai-aktivasi@test.local', ['ai.setting.setujui', 'ai.setting.lihat']);
    }

    /** Ajukan perubahan SENSITIF → approval 'menunggu'. */
    private function ajukanSensitif(User $admin): AiApproval
    {
        $service = app(TenantSettingService::class);

        $payload = AiTenantSettingDefaults::konservatif();
        $payload['level_fatwa'] = 'informatif'; // SENSITIF

        $draf = $service->simpanDraf($payload, $admin);
        $service->ajukan($draf->versi, $admin);

        return AiApproval::query()
            ->where('ai_tenant_setting_id', $draf->id)
            ->firstOrFail();
    }

    /** Tautan signed dengan host domain tenant (konteks CLI test). */
    private function tautanSigned(Tenant $tenant, AiApproval $approval, int $menit = 60): string
    {
        URL::forceRootUrl('http://'.$tenant->id.'.localhost');

        $url = URL::temporarySignedRoute(
            'ai.ringkasan.signed',
            now()->addMinutes($menit),
            ['approval' => $approval->getKey()],
        );

        URL::forceRootUrl(null);

        return $url;
    }

    // ------------------------------------------------------------------
    // (a) Notifikasi pengajuan
    // ------------------------------------------------------------------

    public function test_notifikasi_pengajuan_terkirim_sekali_ke_pemegang_izin_setujui(): void
    {
        $this->siapkanTenant('aiakn');
        $admin = $this->admin();
        $kyai = $this->kyai();

        $this->ajukanSensitif($admin);

        $notif = LogNotifikasi::query()
            ->where('jenis', 'ai_pengajuan_setting')
            ->get();

        $this->assertCount(1, $notif, 'Notifikasi pengajuan wajib terkirim tepat SEKALI');
        $this->assertSame('user:'.$kyai->getKey(), $notif->first()->target);
        $this->assertSame('database', $notif->first()->channel);
        $this->assertStringContainsString('menunggu persetujuan', (string) $notif->first()->payload['judul']);

        // Pengaju (hanya ai.setting.kelola) TIDAK menerima notifikasi.
        $this->assertSame(
            0,
            LogNotifikasi::query()
                ->where('jenis', 'ai_pengajuan_setting')
                ->where('target', 'user:'.$admin->getKey())
                ->count(),
        );
    }

    public function test_pengajuan_non_sensitif_tidak_mengirim_notifikasi(): void
    {
        $this->siapkanTenant('aiakx');
        $admin = $this->admin();
        $this->kyai();

        $service = app(TenantSettingService::class);
        $payload = AiTenantSettingDefaults::konservatif();
        $payload['ambang_alert_persen'] = 70; // NON-sensitif → langsung aktif

        $draf = $service->simpanDraf($payload, $admin);
        $service->ajukan($draf->versi, $admin);

        $this->assertSame(
            0,
            LogNotifikasi::query()->where('jenis', 'ai_pengajuan_setting')->count(),
            'Pengajuan non-sensitif langsung aktif — tidak ada yang perlu diberi tahu',
        );
    }

    // ------------------------------------------------------------------
    // (b) Badge navigasi
    // ------------------------------------------------------------------

    public function test_badge_navigasi_persetujuan_menunjukkan_jumlah_menunggu(): void
    {
        $this->siapkanTenant('aiakb');
        $admin = $this->admin();

        $this->assertNull(PersetujuanAi::getNavigationBadge(), 'Tanpa pengajuan = tanpa badge');

        $approval = $this->ajukanSensitif($admin);
        $this->assertSame('1', PersetujuanAi::getNavigationBadge());

        // Setelah diputuskan, badge kembali kosong.
        app(TenantSettingService::class)->putuskan($approval->getKey(), $this->kyai(), true);
        $this->assertNull(PersetujuanAi::getNavigationBadge());
    }

    // ------------------------------------------------------------------
    // (c) Tautan ringkasan bertanda tangan
    // ------------------------------------------------------------------

    public function test_tautan_signed_menampilkan_ringkasan_baca_saja_tanpa_login(): void
    {
        $tenant = $this->siapkanTenant('aiaks');
        $approval = $this->ajukanSensitif($this->admin());

        $url = $this->tautanSigned($tenant, $approval);

        $respons = $this->get($url); // TANPA actingAs — tautan publik baca-saja

        $respons->assertOk();
        $respons->assertSee('Ringkasan Kebijakan Asisten AI');
        $respons->assertSee('TIDAK AKAN');
        $respons->assertSee('Menunggu keputusan');
        $respons->assertSee('Masuk ke panel untuk memberi keputusan');
        // Tidak ada mekanisme persetujuan apa pun di halaman publik.
        $respons->assertDontSee('wire:click');
    }

    public function test_tautan_kedaluwarsa_ditolak_403(): void
    {
        $tenant = $this->siapkanTenant('aiakd');
        $approval = $this->ajukanSensitif($this->admin());

        $url = $this->tautanSigned($tenant, $approval, menit: 10);

        Carbon::setTestNow(now()->addMinutes(11));

        $this->get($url)->assertForbidden();
    }

    public function test_tautan_tanpa_tanda_tangan_ditolak_403(): void
    {
        $tenant = $this->siapkanTenant('aiakt');
        $approval = $this->ajukanSensitif($this->admin());

        $polos = 'http://'.$tenant->id.'.localhost/ai/ringkasan-kebijakan/'.$approval->getKey();

        $this->get($polos)->assertForbidden();
    }

    public function test_halaman_wizard_menyediakan_tombol_salin_tautan_saat_ada_pengajuan(): void
    {
        $this->siapkanTenant('aiakw');
        $admin = $this->admin();
        $this->actingAs($admin);

        // Tanpa pengajuan menunggu → tidak ada blok salin tautan.
        Livewire::test(PengaturanAi::class)
            ->assertDontSee('Salin tautan untuk Kyai');

        $this->ajukanSensitif($admin);

        $halaman = Livewire::test(PengaturanAi::class);
        $halaman->assertSee('Salin tautan untuk Kyai');

        /** @var PengaturanAi $instance */
        $instance = $halaman->instance();
        $tautan = $instance->tautanRingkasanKyai();

        $this->assertNotNull($tautan);
        $this->assertStringContainsString('/ai/ringkasan-kebijakan/', $tautan);
        $this->assertStringContainsString('signature=', $tautan);
        $this->assertStringContainsString('expires=', $tautan);
        $this->assertTrue(Route::has('ai.ringkasan.signed'));
    }

    // ------------------------------------------------------------------
    // (d) Hash ringkasan disetujui == hash payload berjalan
    // ------------------------------------------------------------------

    public function test_hash_ringkasan_disetujui_sama_dengan_hash_payload_berjalan(): void
    {
        $this->siapkanTenant('aiakh');
        $approval = $this->ajukanSensitif($this->admin());

        $service = app(TenantSettingService::class);
        $service->putuskan($approval->getKey(), $this->kyai(), true);

        $aktif = $service->aktif();
        $this->assertSame(2, $aktif->versi, 'Versi yang disetujui kini berjalan');
        $this->assertSame(
            $approval->refresh()->ringkasan_hash,
            TenantSettingService::hashRingkasan((array) $aktif->payload),
            'Sidik jari kebijakan yang DISETUJUI kyai wajib identik dengan payload yang BERJALAN',
        );
    }

    // ------------------------------------------------------------------
    // (e) Modul nonaktif → gateway menolak santun
    // ------------------------------------------------------------------

    public function test_modul_ai_nonaktif_per_tenant_gateway_menolak_tanpa_http(): void
    {
        // Registry terisi: modul `ai` berstatus development → hanya tenant
        // demo. Tenant runtime acak BUKAN demo → gateway wajib menolak,
        // meski pengaturan AI tenant sudah lengkap & aktif.
        $this->siapkanTenant('aiakm');
        $this->seed(ModuleRegistrySeeder::class);

        Http::fake();

        $result = app(AiGateway::class)->generate(new AiGenerateRequest(
            prompt: 'Assalamualaikum, mohon info jadwal.',
            agent: 'uji',
            tier: 'ekonomis',
            channel: 'web',
        ));

        $this->assertTrue($result->ditolakPolicy);
        $this->assertFalse($result->degraded, 'Penolakan modul BUKAN degradasi teknis');
        $this->assertSame((string) config('ai.guardrails.pesan.modul_nonaktif'), $result->text);
        Http::assertNothingSent();

        $audit = AiAuditLog::query()->where('status', AiAuditLog::STATUS_DITOLAK_POLICY)->first();
        $this->assertNotNull($audit);
        $this->assertSame('modul_nonaktif', $audit->keputusan_kebijakan['policy']['kategori']);
    }

    public function test_halaman_ringkasan_publik_404_saat_modul_nonaktif(): void
    {
        $tenant = $this->siapkanTenant('aiakp');
        $approval = $this->ajukanSensitif($this->admin());
        $this->seed(ModuleRegistrySeeder::class); // modul ai development, tenant bukan demo

        $url = $this->tautanSigned($tenant, $approval);

        $this->get($url)->assertNotFound();
    }
}
