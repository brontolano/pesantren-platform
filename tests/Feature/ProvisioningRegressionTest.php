<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\TenantModule;
use App\Models\User;
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

    public function test_provision_mengaktifkan_modul_released_non_core(): void
    {
        $id = 'regmod-'.str()->lower(str()->random(5));
        $tenant = $this->createProvisionedTenant($id, 'Pesantren Regresi Modul', $id.'.localhost');

        $aktif = TenantModule::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->pluck('module_name')
            ->all();

        // Released non-core → aktif.
        $this->assertContains('ppdb', $aktif);
        $this->assertContains('akademik', $aktif);
        $this->assertContains('mutu', $aktif);

        // Core → tak perlu baris (selalu aktif lewat is_core).
        $this->assertNotContains('keuangan', $aktif);
        $this->assertNotContains('cms', $aktif);
        $this->assertNotContains('core', $aktif);

        // Development → hanya demo, tak diaktifkan untuk tenant biasa.
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
                ->where('email', 'admin@' . $tenant->id . '.localhost')
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
        app(\App\Services\TenantProvisioner::class)->provision($tenant);

        $tenant->run(function (): void {
            $this->assertSame('#ff0000', Setting::getTenantStringValue('branding_primary_color'));
        });
    }
}
