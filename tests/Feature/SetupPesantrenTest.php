<?php

namespace Tests\Feature;

use App\Filament\Pages\SetupPesantren;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Akademik\Models\PeriodeAkademik;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Wizard Setup Pesantren (pokok): simpan() menulis identitas (branding_name),
 * menerapkan preset kebijakan, membuat periode akademik aktif, dan menandai
 * setup selesai. Juga: halaman ter-gate ke panel tenant + user berizin.
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
            'periode_akademik', 'audit_logs',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('setup', 'Pesantren Setup');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        $this->seed(RolesAndPermissionsSeeder::class);
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

    public function test_simpan_menulis_identitas_preset_dan_periode_aktif(): void
    {
        $this->actingAs($this->admin());

        // Uji logika inti langsung (persist) — bebas dari quirk hidrasi Wizard di test.
        (new SetupPesantren())->persist([
            'nama' => 'Pondok Contoh',
            'preset' => 'salafiyah-kecil',
            'periode_nama' => '2026/2027 Ganjil',
            'tahun_akademik' => '2026/2027',
            'semester' => 'ganjil',
            'tanggal_mulai' => '2026-07-14',
            'tanggal_akhir' => '2026-12-31',
        ]);

        $this->assertSame('Pondok Contoh', Setting::getTenantStringValue('branding_name'));
        $this->assertNotNull(Setting::getTenantValue('setup.selesai'));

        $periode = PeriodeAkademik::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('is_active', true)->first();
        $this->assertNotNull($periode);
        $this->assertSame('2026/2027', $periode->tahun_akademik);

        // Preset salafiyah-kecil mengatur libur Jumat.
        $this->assertSame(['jumat'], Setting::getTenantValue('akademik.hari_libur_mingguan'));
    }

    public function test_simpan_lewat_form_livewire_tidak_error_dan_tersimpan(): void
    {
        // Regresi: jalur NYATA simpan() → form->getState() → persist(). Test lama
        // memanggil persist() langsung sehingga LUPUT dari bug statePath di Wizard
        // (getState() ter-nest → "Undefined array key nama"). Test ini menjamin
        // bentuk state yang dikirim ke persist() benar.
        $this->actingAs($this->admin());

        Livewire::test(SetupPesantren::class)
            ->fillForm([
                'nama' => 'Pondok Lewat Form',
                'preset' => 'salafiyah-kecil',
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

        $periode = PeriodeAkademik::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('is_active', true)->first();
        $this->assertSame('2027/2028', $periode?->tahun_akademik);
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
