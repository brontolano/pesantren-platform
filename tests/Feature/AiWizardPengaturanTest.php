<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ModuleRegistrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Modules\AI\Filament\Pages\PengaturanAi;
use Modules\AI\Filament\Pages\PersetujuanAi;
use Modules\AI\Models\AiApproval;
use Modules\AI\Models\AiTenantSetting;
use Modules\AI\Services\Settings\AiSettingProvisioner;
use Modules\AI\Services\Settings\AiTenantSettingDefaults;
use Modules\AI\Services\Settings\RingkasanKebijakan;
use Modules\AI\Services\Settings\TenantSettingService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * T-A011 — UI Wizard "Wawancara Digital" + halaman persetujuan Kyai:
 *  (a) gerbang akses: permission + modul `ai` (development → tenant demo saja);
 *  (b) autosave draf antar-langkah: SATU versi draf di-update, bukan beranak;
 *  (c) ajukan dari Wizard → pending_approval + approval menunggu;
 *  (d) halaman persetujuan: setujui → versi aktif berganti; tolak → draf;
 *  (e) Ringkasan Kebijakan merender frasa kunci yang benar utk ≥3 kombinasi.
 */
class AiWizardPengaturanTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Render penuh halaman Wizard (6 langkah, puluhan komponen Filament)
        // melewati 128M bawaan php-cli lokal — naikkan khusus suite ini.
        ini_set('memory_limit', '512M');

        $this->ensurePpdbTestingSchemaReady(['ai_tenant_setting', 'ai_approval']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        // DB test persisten lintas-run: registry modul bisa sudah terisi dari
        // suite lain. Kosongkan agar baseline = "modul tak terdaftar → tampil"
        // (pola ModuleService); test gating menyemai ulang registry sendiri.
        \Illuminate\Support\Facades\DB::table('module_registry')->delete();

        // Halaman hanya terdaftar pada panel tenant.
        Filament::setCurrentPanel(Filament::getPanel('tenant'));
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
        $user = $this->buatUser('admin-wizard@test.local', ['ai.setting.kelola', 'ai.setting.lihat']);
        $this->actingAs($user);

        return $user;
    }

    private function kyai(): User
    {
        $user = $this->buatUser('kyai-wizard@test.local', ['ai.setting.setujui', 'ai.setting.lihat']);
        $this->actingAs($user);

        return $user;
    }

    // ------------------------------------------------------------------
    // (a) Gerbang akses
    // ------------------------------------------------------------------

    public function test_user_ber_permission_dapat_mengakses_dan_route_terdaftar(): void
    {
        $this->siapkanTenant('aiwzac');
        $this->admin();

        $this->assertTrue(PengaturanAi::canAccess());
        $this->assertTrue(Route::has('filament.tenant.pages.pengaturan-ai'));
        $this->assertTrue(Route::has('filament.tenant.pages.persetujuan-ai'));

        // Halaman dapat dirender (Livewire mount + form build tanpa error).
        Livewire::test(PengaturanAi::class)->assertOk();
    }

    public function test_user_tanpa_permission_tidak_bisa_akses(): void
    {
        $this->siapkanTenant('aiwzna');
        $this->actingAs($this->buatUser('polos@test.local'));

        $this->assertFalse(PengaturanAi::canAccess());
        $this->assertFalse(PersetujuanAi::canAccess());
    }

    public function test_halaman_persetujuan_hanya_untuk_pemegang_izin_setujui(): void
    {
        $this->siapkanTenant('aiwzpk');

        $this->admin();
        $this->assertFalse(PersetujuanAi::canAccess(), 'Admin (kelola) bukan pemutus');

        $this->kyai();
        $this->assertTrue(PersetujuanAi::canAccess());
    }

    public function test_tenant_tanpa_modul_ai_tidak_bisa_akses(): void
    {
        // Modul `ai` berstatus development → hanya tampil di tenant demo.
        // Runtime tenant acak BUKAN demo, jadi setelah registry diseed,
        // halaman wajib tertutup meski user punya permission.
        $this->siapkanTenant('aiwzmd');
        $this->seed(ModuleRegistrySeeder::class);
        $this->admin();

        $this->assertFalse(PengaturanAi::canAccess());
        $this->assertFalse(PersetujuanAi::canAccess());
    }

    // ------------------------------------------------------------------
    // (b) Autosave draf antar-langkah
    // ------------------------------------------------------------------

    public function test_pindah_langkah_menyimpan_satu_draf_yang_sama(): void
    {
        $this->siapkanTenant('aiwzdr');
        $this->admin();

        $jumlahAwal = AiTenantSetting::query()->count(); // 1 (versi aktif provisioning)

        $halaman = Livewire::test(PengaturanAi::class)
            ->fillForm(['level_fatwa' => 'informatif'])
            ->call('simpanLangkah')
            ->assertHasNoFormErrors();

        $draf = AiTenantSetting::query()
            ->where('status', AiTenantSetting::STATUS_DRAFT)
            ->get();

        $this->assertCount(1, $draf);
        $this->assertSame('informatif', $draf->first()->payload['level_fatwa']);

        // Langkah berikutnya: draf yang SAMA di-update, bukan versi baru.
        $halaman
            ->fillForm(['gaya' => 'hangat', 'blacklist_topik' => ['politik praktis']])
            ->call('simpanLangkah')
            ->assertHasNoFormErrors();

        $this->assertSame($jumlahAwal + 1, AiTenantSetting::query()->count());

        $drafSegar = AiTenantSetting::query()
            ->where('status', AiTenantSetting::STATUS_DRAFT)
            ->firstOrFail();
        $this->assertSame($draf->first()->versi, $drafSegar->versi);
        $this->assertSame('informatif', $drafSegar->payload['level_fatwa']);
        $this->assertSame('hangat', $drafSegar->payload['gaya']);
        $this->assertSame(['politik praktis'], $drafSegar->payload['blacklist_topik']);
        // Field yang tidak diedit form tetap utuh (whitelist menunggu GATE-3).
        $this->assertSame([], $drafSegar->payload['whitelist_kitab']);
    }

    public function test_keluar_di_tengah_lanjut_dari_draf_terakhir(): void
    {
        $this->siapkanTenant('aiwzlj');
        $this->admin();

        Livewire::test(PengaturanAi::class)
            ->fillForm(['level_fatwa' => 'khilafiyah'])
            ->call('simpanLangkah');

        // Sesi baru: mount membaca draf terakhir.
        Livewire::test(PengaturanAi::class)
            ->assertFormSet(['level_fatwa' => 'khilafiyah']);
    }

    // ------------------------------------------------------------------
    // (c) Ajukan → pending_approval + approval menunggu
    // ------------------------------------------------------------------

    public function test_ajukan_perubahan_sensitif_menunggu_persetujuan(): void
    {
        $this->siapkanTenant('aiwzaj');
        $this->admin();

        Livewire::test(PengaturanAi::class)
            ->fillForm(['level_fatwa' => 'informatif']) // SENSITIF
            ->call('ajukan')
            ->assertHasNoFormErrors();

        $pending = AiTenantSetting::query()
            ->where('status', AiTenantSetting::STATUS_PENDING_APPROVAL)
            ->firstOrFail();
        $this->assertSame('informatif', $pending->payload['level_fatwa']);

        $approval = AiApproval::query()
            ->where('ai_tenant_setting_id', $pending->id)
            ->firstOrFail();
        $this->assertSame(AiApproval::STATUS_MENUNGGU, $approval->status);

        // Versi lama tetap aktif sampai kyai memutuskan.
        $this->assertSame(1, app(TenantSettingService::class)->aktif()->versi);
    }

    // ------------------------------------------------------------------
    // (d) Halaman persetujuan: setujui / tolak
    // ------------------------------------------------------------------

    private function ajukanPerubahanSensitif(): AiApproval
    {
        $admin = $this->buatUser('pengaju@test.local', ['ai.setting.kelola']);
        $service = app(TenantSettingService::class);

        $payload = AiTenantSettingDefaults::konservatif();
        $payload['level_fatwa'] = 'informatif';

        $draf = $service->simpanDraf($payload, $admin);
        $service->ajukan($draf->versi, $admin);

        return AiApproval::query()
            ->where('ai_tenant_setting_id', $draf->id)
            ->firstOrFail();
    }

    public function test_kyai_menyetujui_lewat_halaman_versi_aktif_berganti(): void
    {
        $this->siapkanTenant('aiwzok');
        $approval = $this->ajukanPerubahanSensitif();
        $kyai = $this->kyai();

        Livewire::test(PersetujuanAi::class)
            ->call('setujui', $approval->id)
            ->assertOk();

        $approval->refresh();
        $this->assertSame(AiApproval::STATUS_DISETUJUI, $approval->status);
        $this->assertSame($kyai->id, $approval->disetujui_oleh);
        $this->assertNotNull($approval->decided_at);

        $aktif = app(TenantSettingService::class)->aktif();
        $this->assertSame(2, $aktif->versi);
        $this->assertSame('informatif', $aktif->payload['level_fatwa']);
    }

    public function test_kyai_menolak_lewat_halaman_versi_kembali_draf(): void
    {
        $this->siapkanTenant('aiwzno');
        $approval = $this->ajukanPerubahanSensitif();
        $this->kyai();

        Livewire::test(PersetujuanAi::class)
            ->set('alasan.'.$approval->id, 'Mohon dikaji ulang bersama Dewan Ustadz.')
            ->call('tolak', $approval->id)
            ->assertOk();

        $approval->refresh();
        $this->assertSame(AiApproval::STATUS_DITOLAK, $approval->status);
        $this->assertSame('Mohon dikaji ulang bersama Dewan Ustadz.', $approval->alasan);

        $this->assertSame(
            AiTenantSetting::STATUS_DRAFT,
            $approval->setting()->first()->status,
        );
        $this->assertSame(1, app(TenantSettingService::class)->aktif()->versi);
    }

    // ------------------------------------------------------------------
    // (e) Ringkasan Kebijakan — frasa kunci utk ≥3 kombinasi
    // ------------------------------------------------------------------

    public function test_ringkasan_edukatif_konservatif(): void
    {
        $html = app(RingkasanKebijakan::class)->render(AiTenantSettingDefaults::konservatif());

        $this->assertStringContainsString('AKAN', $html);
        $this->assertStringContainsString('TIDAK AKAN', $html);
        $this->assertStringContainsString('tidak akan menjawab pertanyaan hukum agama', $html);
        $this->assertStringContainsString('tidak akan dapat diakses langsung oleh santri', $html);
        $this->assertStringContainsString('membaca data tagihan santri', $html);
        $this->assertStringContainsString('tidak akan membaca data kesehatan santri', $html);
        $this->assertStringContainsString('tidak akan mengirimkan data penggunaan untuk riset produk', $html);
        $this->assertStringContainsString('melayani sepanjang hari (24 jam)', $html);
        $this->assertStringContainsString('setelah 90 hari', $html);
    }

    public function test_ringkasan_informatif_dengan_scope_nilai(): void
    {
        $payload = AiTenantSettingDefaults::konservatif();
        $payload['level_fatwa'] = 'informatif';
        $payload['scope_data']['nilai'] = true;
        $payload['optin_riset'] = true;
        $payload['fitur_per_peran']['asatidz']['generator_soal'] = true;

        $html = app(RingkasanKebijakan::class)->render($payload);

        $this->assertStringContainsString('daftar rujukan yang telah ditinjau Dewan Ustadz', $html);
        $this->assertStringContainsString('tidak akan memberikan kesimpulan hukum atau fatwa', $html);
        $this->assertStringContainsString('membaca data nilai akademik santri', $html);
        $this->assertStringContainsString('membantu Asatidz menyusun draf soal latihan', $html);
        $this->assertStringContainsString('data penggunaan anonim untuk riset', $html);
    }

    public function test_ringkasan_khilafiyah_jam_terbatas_dan_anggaran(): void
    {
        $payload = AiTenantSettingDefaults::konservatif();
        $payload['level_fatwa'] = 'khilafiyah';
        $payload['jam_layanan'] = ['mulai' => '08:00', 'selesai' => '16:00'];
        $payload['anggaran_kredit_bulanan'] = 50000;
        $payload['blacklist_topik'] = ['politik praktis', 'takfir'];
        $payload['eskalasi_tujuan'] = ['penerima' => [1, 2], 'kanal' => 'wa', 'sla_jam' => 24];

        $html = app(RingkasanKebijakan::class)->render($payload);

        $this->assertStringContainsString('memaparkan ragam pendapat', $html);
        $this->assertStringContainsString('tidak akan menetapkan satu pendapat sebagai keputusan hukum', $html);
        $this->assertStringContainsString('pukul 08:00 sampai 16:00', $html);
        $this->assertStringContainsString('tidak akan menjawab di luar jam layanan', $html);
        $this->assertStringContainsString('50.000 kredit', $html);
        $this->assertStringContainsString('2 topik yang masuk daftar larangan', $html);
        $this->assertStringContainsString('2 penerima eskalasi melalui WhatsApp', $html);
    }
}
