<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckModuleActive;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\ModuleRegistrySeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Fase 0 — middleware gerbang rute modul (alias 'module') kini selaras
 * ModuleService: modul INTI selalu lolos (tak lagi keliru 403 saat default
 * modul minimal), released hanya bila aktif, pemilik platform bypass.
 */
class CheckModuleActiveTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions',
            'model_has_roles', 'model_has_permissions', 'module_registry', 'tenant_modules',
        ]);
        $this->truncatePpdbTables(['tenant_modules', 'module_registry', 'domains', 'tenants']);

        // ModuleService & TenantModule membaca tabel central → samakan ke pgsql (DB test).
        Config::set('tenancy.database.central_connection', 'pgsql');

        $this->tenant = $this->createRuntimeTenant('mw', 'Pesantren Middleware');
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

    /** Jalankan middleware; true bila next() tercapai (lolos), false bila 403. */
    private function lolos(string $module, ?User $user = null): bool
    {
        $req = Request::create('http://mw.localhost/x', 'GET');
        if ($user !== null) {
            $req->setUserResolver(fn () => $user);
        }

        try {
            (new CheckModuleActive)->handle($req, fn () => response('ok', 200), $module);

            return true;
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());

            return false;
        }
    }

    public function test_modul_inti_selalu_lolos_tanpa_baris_aktivasi(): void
    {
        // keuangan = core → lolos walau tak ada baris tenant_modules.
        $this->assertTrue($this->lolos('keuangan'));
    }

    public function test_modul_released_nonaktif_ditolak(): void
    {
        $this->assertFalse($this->lolos('payroll'));
    }

    public function test_modul_released_aktif_lolos(): void
    {
        DB::connection('pgsql')->table('tenant_modules')->insert([
            'tenant_id' => $this->tenant->id, 'module_name' => 'payroll', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertTrue($this->lolos('payroll'));
    }

    public function test_pemilik_platform_lolos_walau_modul_nonaktif(): void
    {
        $platform = \Mockery::mock(User::class)->makePartial();
        $platform->shouldReceive('isPlatformSuperAdmin')->andReturn(true);

        $this->assertTrue($this->lolos('payroll', $platform));
    }
}
