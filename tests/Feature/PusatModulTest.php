<?php

namespace Tests\Feature;

use App\Filament\Pages\PusatModul;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\ModuleRegistrySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Fase 1 — Pusat Modul (self-installer panel tenant): render, gerbang izin,
 * pasang modul + prasyarat (tulis CENTRAL), modul instan langsung siap, nonaktif.
 */
class PusatModulTest extends TestCase
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

        $this->tenant = $this->createRuntimeTenant('toko', 'Pesantren Toko');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ModuleRegistrySeeder::class);
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
        $u = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Admin Toko',
            'email' => 'admin@toko.test', 'password' => 'x12345678',
        ]);
        $u->assignRole('super_admin');

        return $u;
    }

    private function aktif(string $module): bool
    {
        return DB::connection('pgsql')->table('tenant_modules')
            ->where('tenant_id', $this->tenant->id)->where('module_name', $module)
            ->where('is_active', true)->exists();
    }

    public function test_gerbang_butuh_manage_modules_dan_staf_tenant(): void
    {
        // User tanpa role → tak punya manage_modules → ditolak.
        $biasa = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Staf', 'email' => 's@toko.test', 'password' => 'x12345678',
        ]);
        $this->actingAs($biasa);
        $this->assertFalse(PusatModul::canAccess());

        $this->actingAs($this->admin());
        $this->assertTrue(PusatModul::canAccess());
    }

    public function test_render_ok(): void
    {
        $this->actingAs($this->admin());
        Livewire::test(PusatModul::class)->assertOk();
    }

    public function test_pasang_modul_dengan_prasyarat_memasang_prasyarat_dulu(): void
    {
        $this->actingAs($this->admin());

        // payroll butuh ledger → keduanya aktif setelah dipasang.
        Livewire::test(PusatModul::class)->call('pasang', 'payroll');

        $this->assertTrue($this->aktif('ledger'), 'prasyarat ledger harus ikut dipasang');
        $this->assertTrue($this->aktif('payroll'));
    }

    public function test_pasang_modul_instan_langsung_siap(): void
    {
        $this->actingAs($this->admin());

        // ppdb tanpa langkah wajib → configured_at terisi (siap) saat dipasang.
        Livewire::test(PusatModul::class)->call('pasang', 'ppdb');

        $row = DB::connection('pgsql')->table('tenant_modules')
            ->where('tenant_id', $this->tenant->id)->where('module_name', 'ppdb')->first();

        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->is_active);
        $this->assertNotNull($row->configured_at, 'modul instan harus langsung ditandai siap');
    }

    public function test_nonaktif_modul(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(PusatModul::class)->call('pasang', 'ppdb');
        $this->assertTrue($this->aktif('ppdb'));

        Livewire::test(PusatModul::class)->call('nonaktif', 'ppdb');
        $this->assertFalse($this->aktif('ppdb'), 'modul harus nonaktif (is_active=false)');
    }
}
