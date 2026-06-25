<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\AI\Models\AiApproval;
use Modules\AI\Models\AiTenantSetting;
use Modules\AI\Services\Settings\AiSettingProvisioner;
use Modules\AI\Services\Settings\AiTenantSettingDefaults;
use Modules\AI\Services\Settings\TenantSettingService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * T-A010 — ai_tenant_setting versioned + ai_approval + RBAC:
 *  (a) default konservatif benar + provisioning idempoten;
 *  (b) validasi schema menolak nilai liar;
 *  (c) alur sensitif: pending_approval, versi lama tetap dipakai, setuju/tolak;
 *  (d) non-sensitif langsung aktif tanpa approval;
 *  (e) RBAC kelola vs setujui;
 *  (f) isolasi tenant;
 *  (g) constraint maksimal satu versi aktif.
 */
class AiTenantSettingTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['ai_tenant_setting', 'ai_approval']);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    protected function tearDown(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    /** Inisialisasi tenant runtime + seed roles/permissions + provisioning setting AI. */
    private function siapkanTenant(string $prefix): Tenant
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);

        // Baris tenant LOKAL — target FK users.tenant_id di DB tenant.
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
        return $this->buatUser('admin-ai@test.local', ['ai.setting.kelola', 'ai.setting.lihat']);
    }

    private function kyai(): User
    {
        return $this->buatUser('kyai-ai@test.local', ['ai.setting.setujui', 'ai.setting.lihat']);
    }

    // ------------------------------------------------------------------
    // (a) Default konservatif + provisioning idempoten
    // ------------------------------------------------------------------

    public function test_provisioning_menyemai_default_konservatif_versi_1_aktif(): void
    {
        $this->siapkanTenant('aiset');

        $aktif = app(TenantSettingService::class)->aktif();

        $this->assertNotNull($aktif);
        $this->assertSame(1, $aktif->versi);
        $this->assertSame(AiTenantSetting::STATUS_AKTIF, $aktif->status);

        $p = $aktif->payload;
        $this->assertSame('edukatif_only', $p['level_fatwa']);
        $this->assertSame('tanpa_akses', $p['mode_akses_santri']);
        $this->assertFalse($p['fitur_per_peran']['santri']['tutor'], 'Fitur santri wajib OFF');
        $this->assertTrue($p['scope_data']['tagihan']);
        $this->assertTrue($p['scope_data']['jadwal']);
        $this->assertFalse($p['scope_data']['nilai']);
        $this->assertFalse($p['scope_data']['kehadiran']);
        $this->assertFalse($p['scope_data']['kesehatan']);
        $this->assertFalse($p['scope_data']['keuangan']);
        $this->assertFalse($p['optin_riset']);
        $this->assertSame(90, $p['retensi_hari']);
        $this->assertSame(30, $p['retensi_hari_santri']);
        $this->assertSame('id', $p['bahasa']);
        $this->assertSame('formal_santun', $p['gaya']);
        // Konten keagamaan DILARANG dikarang — menunggu seed tinjauan ustadz (GATE-3).
        $this->assertSame([], $p['whitelist_kitab']);
        $this->assertSame([], $p['blacklist_topik']);
    }

    public function test_provisioning_idempoten_dua_kali_tetap_satu_versi_aktif(): void
    {
        $tenant = $this->siapkanTenant('aiidem');

        app(AiSettingProvisioner::class)->provision((string) $tenant->id);

        $this->assertSame(1, AiTenantSetting::query()->count());
        $this->assertSame(
            1,
            AiTenantSetting::query()->where('status', AiTenantSetting::STATUS_AKTIF)->count(),
        );
    }

    // ------------------------------------------------------------------
    // (b) Validasi schema
    // ------------------------------------------------------------------

    public function test_simpan_draf_menolak_level_fatwa_liar(): void
    {
        $this->siapkanTenant('aival');
        $admin = $this->admin();

        $payload = AiTenantSettingDefaults::konservatif();
        $payload['level_fatwa'] = 'fatwa_final'; // TIDAK ADA mode fatwa final (PRD §6.3)

        $this->expectException(ValidationException::class);
        app(TenantSettingService::class)->simpanDraf($payload, $admin);
    }

    public function test_simpan_draf_menolak_scope_data_tak_dikenal(): void
    {
        $this->siapkanTenant('aivsc');
        $admin = $this->admin();

        $payload = AiTenantSettingDefaults::konservatif();
        $payload['scope_data']['rahasia_negara'] = true;

        $this->expectException(ValidationException::class);
        app(TenantSettingService::class)->simpanDraf($payload, $admin);
    }

    public function test_simpan_draf_menolak_field_asing(): void
    {
        $this->siapkanTenant('aivfa');
        $admin = $this->admin();

        $payload = AiTenantSettingDefaults::konservatif();
        $payload['mode_dewa'] = true;

        $this->expectException(ValidationException::class);
        app(TenantSettingService::class)->simpanDraf($payload, $admin);
    }

    // ------------------------------------------------------------------
    // (c) Alur field sensitif — wajib persetujuan kyai
    // ------------------------------------------------------------------

    public function test_perubahan_sensitif_menunggu_approval_dan_versi_lama_tetap_dipakai(): void
    {
        $this->siapkanTenant('aisen');
        $admin = $this->admin();
        $kyai = $this->kyai();
        $service = app(TenantSettingService::class);

        $payload = AiTenantSettingDefaults::konservatif();
        $payload['level_fatwa'] = 'informatif'; // SENSITIF

        $draf = $service->simpanDraf($payload, $admin);
        $this->assertSame(AiTenantSetting::STATUS_DRAFT, $draf->status);
        $this->assertSame(2, $draf->versi);
        $this->assertSame($admin->id, $draf->dibuat_oleh);

        $diajukan = $service->ajukan($draf->versi, $admin);
        $this->assertSame(AiTenantSetting::STATUS_PENDING_APPROVAL, $diajukan->status);

        $approval = AiApproval::query()->where('ai_tenant_setting_id', $draf->id)->first();
        $this->assertNotNull($approval, 'Perubahan sensitif wajib membuat baris ai_approval');
        $this->assertSame(AiApproval::STATUS_MENUNGGU, $approval->status);
        // Hash dihitung atas payload hasil normalisasi jsonb (DB round-trip).
        $this->assertSame(
            TenantSettingService::hashRingkasan($diajukan->payload),
            $approval->ringkasan_hash,
        );

        // Versi LAMA tetap dipakai sampai kyai menyetujui.
        $aktif = $service->aktif();
        $this->assertSame(1, $aktif->versi);
        $this->assertSame('edukatif_only', $aktif->payload['level_fatwa']);

        // Kyai menyetujui → versi baru aktif, lama diarsipkan.
        $service->putuskan($approval->id, $kyai, true);

        $aktifBaru = $service->aktif();
        $this->assertSame(2, $aktifBaru->versi);
        $this->assertSame('informatif', $aktifBaru->payload['level_fatwa']);
        $this->assertSame(
            AiTenantSetting::STATUS_ARSIP,
            AiTenantSetting::query()->where('versi', 1)->value('status'),
        );

        $approval->refresh();
        $this->assertSame(AiApproval::STATUS_DISETUJUI, $approval->status);
        $this->assertSame($kyai->id, $approval->disetujui_oleh);
        $this->assertNotNull($approval->decided_at);
    }

    public function test_approval_ditolak_mengembalikan_versi_ke_draft(): void
    {
        $this->siapkanTenant('aitol');
        $admin = $this->admin();
        $kyai = $this->kyai();
        $service = app(TenantSettingService::class);

        $payload = AiTenantSettingDefaults::konservatif();
        $payload['mode_akses_santri'] = 'lab_terjadwal'; // SENSITIF

        $draf = $service->simpanDraf($payload, $admin);
        $service->ajukan($draf->versi, $admin);

        $approval = AiApproval::query()->where('ai_tenant_setting_id', $draf->id)->firstOrFail();
        $service->putuskan($approval->id, $kyai, false, 'Belum siap secara adab & pengawasan.');

        $draf->refresh();
        $this->assertSame(AiTenantSetting::STATUS_DRAFT, $draf->status);

        $approval->refresh();
        $this->assertSame(AiApproval::STATUS_DITOLAK, $approval->status);
        $this->assertSame('Belum siap secara adab & pengawasan.', $approval->alasan);

        // Versi 1 tetap aktif.
        $this->assertSame(1, $service->aktif()->versi);
    }

    // ------------------------------------------------------------------
    // (d) Non-sensitif → langsung aktif tanpa approval
    // ------------------------------------------------------------------

    public function test_perubahan_non_sensitif_langsung_aktif_tanpa_approval(): void
    {
        $this->siapkanTenant('ainon');
        $admin = $this->admin();
        $service = app(TenantSettingService::class);

        $payload = AiTenantSettingDefaults::konservatif();
        $payload['jam_layanan'] = ['mulai' => '07:00', 'selesai' => '21:00'];
        $payload['ambang_alert_persen'] = 70;

        $draf = $service->simpanDraf($payload, $admin);
        $hasil = $service->ajukan($draf->versi, $admin);

        $this->assertSame(AiTenantSetting::STATUS_AKTIF, $hasil->status);
        $this->assertSame(0, AiApproval::query()->count(), 'Non-sensitif tidak boleh membuat approval');
        $this->assertSame(2, $service->aktif()->versi);
        $this->assertSame('07:00', $service->aktif()->payload['jam_layanan']['mulai']);
        $this->assertSame(
            AiTenantSetting::STATUS_ARSIP,
            AiTenantSetting::query()->where('versi', 1)->value('status'),
        );
    }

    // ------------------------------------------------------------------
    // (e) RBAC
    // ------------------------------------------------------------------

    public function test_user_tanpa_permission_kelola_ditolak_simpan_draf(): void
    {
        $this->siapkanTenant('airbk');
        $tanpaIzin = $this->buatUser('tanpa-izin@test.local');

        $this->expectException(AuthorizationException::class);
        app(TenantSettingService::class)->simpanDraf(AiTenantSettingDefaults::konservatif(), $tanpaIzin);
    }

    public function test_user_tanpa_permission_setujui_ditolak_memutuskan(): void
    {
        $this->siapkanTenant('airbs');
        $admin = $this->admin();
        $service = app(TenantSettingService::class);

        $payload = AiTenantSettingDefaults::konservatif();
        $payload['level_fatwa'] = 'informatif';
        $draf = $service->simpanDraf($payload, $admin);
        $service->ajukan($draf->versi, $admin);
        $approval = AiApproval::query()->firstOrFail();

        // Admin pengelola TIDAK boleh menyetujui kebijakannya sendiri.
        $this->expectException(AuthorizationException::class);
        $service->putuskan($approval->id, $admin, true);
    }

    // ------------------------------------------------------------------
    // (f) Isolasi tenant
    // ------------------------------------------------------------------

    public function test_isolasi_setting_tenant_a_tidak_terbaca_dari_tenant_b(): void
    {
        $tenantA = $this->createRuntimeTenant('aisoa', 'Pesantren Isolasi A');
        $tenantB = $this->createRuntimeTenant('aisob', 'Pesantren Isolasi B');

        tenancy()->initialize($tenantA);
        app(AiSettingProvisioner::class)->provision((string) $tenantA->id);
        $this->assertSame(1, AiTenantSetting::query()->count());
        tenancy()->end();

        tenancy()->initialize($tenantB);
        $this->assertSame(0, AiTenantSetting::query()->count(), 'Setting tenant A bocor ke tenant B');
        $this->assertNull(app(TenantSettingService::class)->aktif());
        tenancy()->end();
    }

    // ------------------------------------------------------------------
    // (g) Constraint: maksimal SATU versi aktif (level DB)
    // ------------------------------------------------------------------

    public function test_db_menolak_dua_versi_aktif_sekaligus(): void
    {
        $this->siapkanTenant('aiuni');

        $this->expectException(QueryException::class);

        DB::connection('tenant')->table('ai_tenant_setting')->insert([
            'versi' => 99,
            'payload' => json_encode(AiTenantSettingDefaults::konservatif()),
            'status' => AiTenantSetting::STATUS_AKTIF,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
