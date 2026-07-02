<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\TenantModule;
use App\Models\User;
use App\Services\TenantProvisioner;
use Database\Seeders\ModuleRegistrySeeder;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Guard regresi provisioning end-to-end (TODO #15).
 * Membuktikan provision() menyemai: modul (tenant_modules) + branding (Setting)
 * + roles + admin — sehingga tenant baru lahir "lengkap" tanpa setup manual.
 */
class ProvisioningRegressionTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles',
            'module_registry', 'tenant_modules', 'settings',
        ]);

        // Klasifikasi modul final (keputusan #1) sebelum provisioning.
        $this->seed(ModuleRegistrySeeder::class);
    }

    public function test_provision_default_minimal_tidak_mengaktifkan_released(): void
    {
        // Default BARU (self-installer): tenant baru lahir RAMPING — modul
        // released TIDAK diaktifkan otomatis (dipasang sendiri lewat Pusat Modul).
        $id = 'regmod-'.str()->lower(str()->random(5));
        $tenant = $this->createProvisionedTenant($id, 'Pesantren Regresi Modul', $id.'.localhost');

        $aktif = TenantModule::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->pluck('module_name')
            ->all();

        // Released non-core → TIDAK aktif default (dulu auto-aktif).
        $this->assertNotContains('ppdb', $aktif);
        $this->assertNotContains('akademik', $aktif);
        $this->assertNotContains('mutu', $aktif);

        // Core → tetap tak perlu baris (selalu aktif lewat is_core).
        $this->assertNotContains('keuangan', $aktif);

        // Development → tetap tak diaktifkan untuk tenant biasa.
        $this->assertNotContains('file_storage', $aktif);
    }

    public function test_flag_rollback_mengaktifkan_semua_released_seperti_dulu(): void
    {
        // Jalur rollback: config true → perilaku lama (semua released auto-aktif).
        config(['tenant_modules.default_active_released' => true]);

        $id = 'regflag-'.str()->lower(str()->random(5));
        $tenant = $this->createProvisionedTenant($id, 'Pesantren Regresi Flag', $id.'.localhost');

        $aktif = TenantModule::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->pluck('module_name')
            ->all();

        $this->assertContains('ppdb', $aktif);
        $this->assertContains('akademik', $aktif);
        $this->assertContains('mutu', $aktif);
        $this->assertNotContains('file_storage', $aktif);
    }

    public function test_provision_menyemai_branding_default(): void
    {
        $id = 'regbrand-'.str()->lower(str()->random(5));
        $tenant = $this->createProvisionedTenant($id, 'Pesantren Regresi Branding', $id.'.localhost');

        $tenant->run(function () use ($tenant): void {
            $this->assertSame($tenant->name, Setting::getTenantStringValue('branding_name'));
            $this->assertSame('#14532d', Setting::getTenantStringValue('branding_primary_color'));
            $this->assertSame('#c2410c', Setting::getTenantStringValue('branding_secondary_color'));
            $this->assertSame('default', Setting::getTenantStringValue('branding_font'));
            $this->assertSame('-6.85', Setting::getTenantStringValue('branding_latitude'));
            $this->assertSame('107.49', Setting::getTenantStringValue('branding_longitude'));
        });
    }

    public function test_provision_menyemai_roles_dan_admin(): void
    {
        $id = 'regrole-'.str()->lower(str()->random(5));
        $tenant = $this->createProvisionedTenant($id, 'Pesantren Regresi Role', $id.'.localhost');

        $tenant->run(function () use ($tenant): void {
            $this->assertTrue(
                Role::query()->where('name', 'super_admin')->exists(),
                'Role super_admin wajib ter-seed di DB tenant'
            );

            $admin = User::withoutGlobalScopes()
                ->where('email', 'admin@'.$tenant->id.'.localhost')
                ->first();

            $this->assertNotNull($admin, 'User admin wajib dibuat saat provisioning');
            $this->assertTrue($admin->hasRole('super_admin'));
        });
    }

    public function test_branding_tidak_menimpa_nilai_yang_sudah_ada(): void
    {
        $id = 'regkeep-'.str()->lower(str()->random(5));
        $tenant = $this->createProvisionedTenant($id, 'Pesantren Regresi Keep', $id.'.localhost');

        // Admin mengubah warna primer.
        $tenant->run(function (): void {
            Setting::updateOrCreate(['key' => 'branding_primary_color'], ['value' => ['color' => '#ff0000']]);
        });

        // Reprovision (idempotent) TIDAK boleh mengembalikan ke default.
        app(TenantProvisioner::class)->provision($tenant);

        $tenant->run(function (): void {
            $this->assertSame('#ff0000', Setting::getTenantStringValue('branding_primary_color'));
        });
    }
}
