<?php

namespace Tests\Feature;

use App\Filament\Resources\Pos\PosKategoriResource;
use App\Models\TenantModule;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * POS-5 — RBAC: gating PosKategoriResource (operate_pos) + memastikan resource
 * (form/table) LOAD tanpa fatal lewat canAccess.
 */
class PosRbacTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePpdbTestingDatabase();

        if (! Schema::connection('pgsql')->hasTable('pos_kategori')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        $this->truncatePpdbTables(['domains', 'tenants', 'users']);

        $tenant = $this->createRuntimeTenant('posrbac', 'Pesantren POS RBAC');

        // Pastikan modul keuangan TAMPAK (konteks central, sebelum init).
        TenantModule::firstOrCreate(
            ['tenant_id' => $tenant->getKey(), 'module_name' => 'keuangan'],
            ['is_active' => true],
        );

        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        (new RolesAndPermissionsSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function userDenganRole(string $role): User
    {
        $user = User::withoutGlobalScopes()->create([
            'name' => ucfirst($role),
            'email' => $role.'@'.tenant('id').'.localhost',
            'password' => 'password',
            'tenant_id' => tenant('id'),
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }

    public function test_kasir_bisa_akses_konfigurasi_pos(): void
    {
        $this->actingAs($this->userDenganRole('kasir'));

        $this->assertTrue(PosKategoriResource::canAccess(), 'Kasir (operate_pos) boleh akses Kategori POS.');
        $this->assertTrue(auth()->user()->can('operate_pos'));
    }

    public function test_guru_tidak_bisa_akses_pos(): void
    {
        $this->actingAs($this->userDenganRole('guru'));

        $this->assertFalse(PosKategoriResource::canAccess(), 'Guru tidak boleh akses Kategori POS.');
        $this->assertFalse(auth()->user()->can('operate_pos'));
    }
}
