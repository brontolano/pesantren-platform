<?php

namespace Tests\Feature;

use Modules\Ledger\Filament\Resources\AkunResource;
use Modules\Ledger\Filament\Resources\JurnalResource;
use Modules\Ledger\Filament\Resources\SumberDanaResource;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class LedgerAksesTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePpdbTestingDatabase();

        if (! Schema::connection('pgsql')->hasTable('kebijakan_kehalalan')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function userDenganRole(string $role): User
    {
        $user = User::withoutGlobalScopes()->create([
            'name' => ucfirst($role),
            'email' => $role . '@' . tenant('id') . '.localhost',
            'password' => 'password',
            'tenant_id' => tenant('id'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_bendahara_bisa_akses_ledger_guru_tidak(): void
    {
        $tenant = $this->createRuntimeTenant('akses', 'Pesantren Akses');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        (new RolesAndPermissionsSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $bendahara = $this->userDenganRole('bendahara');
        $guru = $this->userDenganRole('guru');

        // Bendahara (punya view_ledger/manage_ledger) → boleh.
        $this->actingAs($bendahara);
        $this->assertTrue(SumberDanaResource::canAccess(), 'Bendahara harus bisa akses Sumber Dana.');
        $this->assertTrue(AkunResource::canAccess(), 'Bendahara harus bisa akses COA.');
        $this->assertTrue(JurnalResource::canAccess(), 'Bendahara harus bisa akses Jurnal.');
        $this->assertTrue(AkunResource::canCreate(), 'Bendahara (manage_ledger) boleh buat akun.');

        // Guru (tanpa permission ledger) → ditolak.
        $this->actingAs($guru);
        $this->assertFalse(SumberDanaResource::canAccess(), 'Guru tidak boleh akses Sumber Dana.');
        $this->assertFalse(AkunResource::canAccess(), 'Guru tidak boleh akses COA.');
        $this->assertFalse(JurnalResource::canCreate(), 'Jurnal immutable — tak ada create untuk siapa pun.');
    }
}
