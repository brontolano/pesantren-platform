<?php

namespace Tests\Feature;

use App\Filament\Pages\SetupPesantren;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\ModuleRegistrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Akademik\Models\Lembaga;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Ledger\Support\FiturKeuangan;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Wizard Setup Pesantren TERPANDU: persist() per langkah menulis ke mesin yang
 * ada (Setting/KebijakanService/Lembaga/PeriodeAkademik/FiturKeuangan), idempoten,
 * lacak progres, tandai 'setup.selesai' hanya bila fondasi WAJIB lengkap.
 *
 * Uji persist() LANGSUNG (bebas quirk hidrasi Wizard). Modul akademik diaktifkan
 * agar langkah Lembaga/Periode wajib (mencerminkan tenant produksi normal).
 */
class SetupPesantrenTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions',
            'model_has_roles', 'model_has_permissions', 'settings',
            'periode_akademik', 'lembaga', 'audit_logs', 'module_registry', 'tenant_modules',
        ]);
        // Tabel pusat (module_registry/tenant_modules) di DB test 'pgsql'; arahkan
        // central_connection ke sana agar ModuleService/ModuleRegistry tak mencari
        // ke DB tenant fisik (yang tak ada di sandbox shared-DB).
        config(['tenancy.database.central_connection' => 'pgsql']);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('setup', 'Pesantren Setup');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ModuleRegistrySeeder::class);
        // Aktifkan modul akademik untuk tenant ini (tabel pusat) → langkah Lembaga/Periode wajib.
        DB::connection('pgsql')->table('tenant_modules')->updateOrInsert(
            ['tenant_id' => $this->tenant->id, 'module_name' => 'akademik'],
            ['is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        );
        Filament::setCurrentPanel(Filament::getPanel('tenant'));
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Admin Setup',
            'email' => 'admin.setup@test.local', 'password' => 'password123',
        ]);
        $user->assignRole('super_admin');

        return $user;
    }

    /** @return array<string,mixed> data fondasi lengkap (wajib + arketipe). */
    private function dataLengkap(array $override = []): array
    {
        return array_merge([
            'nama' => 'Pondok Contoh',
            'preset' => 'salafiyah-kecil',
            'lembaga_nama' => 'Pondok Pusat',
            'lembaga_jenis' => 'pondok',
            'periode_nama' => '2026/2027 Ganjil',
            'tahun_akademik' => '2026/2027',
            'semester' => 'ganjil',
            'tanggal_mulai' => '2026-07-14',
            'tanggal_akhir' => '2026-12-31',
            'k_hari_libur' => ['jumat'],
            'k_penempatan' => 'berjenjang',
            'k_notif_wali' => 'eskalasi',
            'keuangan_jenis' => 'madrasah',
            'keuangan_kehalalan' => true,
        ], $override);
    }

    public function test_persist_fondasi_lengkap_tandai_selesai(): void
    {
        $this->actingAs($this->admin());

        (new SetupPesantren)->persist($this->dataLengkap());

        $this->assertSame('Pondok Contoh', Setting::getTenantStringValue('branding_name'));
        $this->assertNotNull(Setting::getTenantValue('setup.selesai'), 'fondasi wajib lengkap → setup.selesai ditandai');

        // Preset salafiyah-kecil mengatur libur Jumat.
        $this->assertSame(['jumat'], Setting::getTenantValue('akademik.hari_libur_mingguan'));

        // Lembaga + periode aktif terbuat.
        $this->assertSame(1, Lembaga::query()->where('nama', 'Pondok Pusat')->count());
        $periode = PeriodeAkademik::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('is_active', true)->first();
        $this->assertSame('2026/2027', $periode?->tahun_akademik);

        // Keuangan dibaca FiturKeuangan (bentuk nilai cocok).
        $this->assertSame('madrasah', FiturKeuangan::jenisLembaga());
        $this->assertTrue(FiturKeuangan::pemisahanKehalalanAktif());
    }

    public function test_persist_lembaga_idempoten(): void
    {
        $this->actingAs($this->admin());
        $page = new SetupPesantren;

        $page->persist($this->dataLengkap());
        $page->persist($this->dataLengkap());

        $this->assertSame(1, Lembaga::query()->where('nama', 'Pondok Pusat')->count(), 'persist 2× tak menggandakan lembaga');
    }

    public function test_wajib_lengkap_gating_butuh_lembaga_dan_periode(): void
    {
        $this->actingAs($this->admin());

        // Hanya nama (tanpa lembaga/periode) → akademik aktif → BELUM selesai.
        (new SetupPesantren)->persist(['nama' => 'Baru Mulai']);
        $this->assertNull(Setting::getTenantValue('setup.selesai'), 'akademik aktif tapi belum ada lembaga/periode → belum selesai');

        // Lengkapi → selesai.
        (new SetupPesantren)->persist($this->dataLengkap(['nama' => 'Baru Mulai']));
        $this->assertNotNull(Setting::getTenantValue('setup.selesai'));
    }

    public function test_progress_melacak_langkah_selesai(): void
    {
        $this->actingAs($this->admin());

        (new SetupPesantren)->persist($this->dataLengkap());

        $p = SetupPesantren::progress();
        $this->assertTrue($p['selesai']);
        $this->assertSame(100, $p['persen'], 'identitas+lembaga+periode+kebijakan+keuangan semua tercatat');
    }

    public function test_daftar_modul_read_only_aman(): void
    {
        $this->actingAs($this->admin());

        $modul = (new SetupPesantren)->daftarModul();

        $this->assertNotEmpty($modul);
        $this->assertArrayHasKey('label', $modul[0]);
        $this->assertArrayHasKey('aktif', $modul[0]);
    }

    public function test_simpan_lewat_form_livewire_tidak_error(): void
    {
        // Regresi statePath/Wizard: jalur NYATA simpan() → getState() → persist().
        $this->actingAs($this->admin());

        Livewire::test(SetupPesantren::class)
            ->fillForm([
                'nama' => 'Pondok Lewat Form',
                'lembaga_nama' => 'Pondok Pusat',
                'lembaga_jenis' => 'pondok',
                'periode_nama' => '2027/2028 Ganjil',
                'tahun_akademik' => '2027/2028',
                'semester' => 'ganjil',
                'tanggal_mulai' => '2027-07-14',
                'tanggal_akhir' => '2027-12-31',
            ])
            ->call('simpan')
            ->assertHasNoFormErrors();

        $this->assertSame('Pondok Lewat Form', Setting::getTenantStringValue('branding_name'));
        $this->assertNotNull(Setting::getTenantValue('setup.selesai'));
    }

    public function test_halaman_render_authed_tanpa_500(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(SetupPesantren::class)
            ->assertOk();
    }

    public function test_halaman_tergate_untuk_user_tanpa_izin(): void
    {
        $polos = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Polos',
            'email' => 'polos.setup@test.local', 'password' => 'password123',
        ]);
        $this->actingAs($polos);

        $this->assertFalse(SetupPesantren::canAccess());
    }
}
