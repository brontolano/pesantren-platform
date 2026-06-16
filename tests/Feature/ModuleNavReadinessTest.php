<?php

namespace Tests\Feature;

use App\Filament\Concerns\ModuleGate;
use App\Models\Tenant;
use App\Services\ModuleService;
use Database\Seeders\ModuleRegistrySeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Fase 1 — disclosure bertahap: navigasi hanya menampilkan modul released yang
 * SIAP (configured_at terisi / langkah wajib tuntas). Modul dipasang-tapi-belum
 * siap TIDAK muncul di nav (hidup di Pusat Modul). + siklus hidup moduleStatus.
 */
class ModuleNavReadinessTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions',
            'model_has_roles', 'model_has_permissions', 'module_registry', 'tenant_modules', 'settings',
        ]);
        $this->truncatePpdbTables(['tenant_modules', 'module_registry', 'domains', 'tenants']);

        Config::set('tenancy.database.central_connection', 'pgsql');

        $this->tenant = $this->createRuntimeTenant('nav', 'Pesantren Nav');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        $this->seed(ModuleRegistrySeeder::class);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    private function gate(): object
    {
        return new class
        {
            use ModuleGate;

            public function vis(string $key): bool
            {
                return static::moduleVisible($key);
            }
        };
    }

    private function aktifkan(string $module, bool $configured = false): void
    {
        DB::connection('pgsql')->table('tenant_modules')->updateOrInsert(
            ['tenant_id' => $this->tenant->id, 'module_name' => $module],
            ['is_active' => true, 'installed_at' => now(),
                'configured_at' => $configured ? now() : null,
                'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function test_status_core_dan_available_dan_locked(): void
    {
        $svc = app(ModuleService::class);

        $this->assertSame(ModuleService::STATUS_CORE, $svc->moduleStatus('kesantrian', $this->tenant));
        $this->assertSame(ModuleService::STATUS_AVAILABLE, $svc->moduleStatus('payroll', $this->tenant));
        // file_storage = development → terkunci untuk tenant non-dev.
        $this->assertSame(ModuleService::STATUS_LOCKED, $svc->moduleStatus('file_storage', $this->tenant));
    }

    public function test_status_installed_saat_dipasang_belum_siap(): void
    {
        $this->aktifkan('payroll'); // configured_at null; tanpa data pegawai → belum siap
        $svc = app(ModuleService::class);

        $this->assertSame(ModuleService::STATUS_INSTALLED, $svc->moduleStatus('payroll', $this->tenant));
    }

    public function test_status_ready_saat_ditandai_siap(): void
    {
        $this->aktifkan('payroll', configured: true);
        $svc = app(ModuleService::class);

        $this->assertSame(ModuleService::STATUS_READY, $svc->moduleStatus('payroll', $this->tenant));
    }

    public function test_nav_sembunyikan_modul_dipasang_belum_siap(): void
    {
        $this->aktifkan('payroll'); // aktif, belum siap
        $this->assertFalse($this->gate()->vis('payroll'), 'released aktif-tapi-belum-siap tak boleh di nav');
    }

    public function test_nav_tampilkan_modul_siap(): void
    {
        $this->aktifkan('payroll', configured: true);
        $this->assertTrue($this->gate()->vis('payroll'));
    }

    public function test_nav_inti_selalu_tampil(): void
    {
        $this->assertTrue($this->gate()->vis('kesantrian'));
    }

    public function test_nav_released_belum_dipasang_tersembunyi(): void
    {
        // Belum ada baris tenant_modules → tak tersedia → tersembunyi dari nav.
        $this->assertFalse($this->gate()->vis('payroll'));
    }
}
