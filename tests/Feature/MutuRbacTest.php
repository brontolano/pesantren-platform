<?php

namespace Tests\Feature;

use App\Filament\Resources\Mutu\DokumenMutuResource;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * RBAC modul Mutu (#27) — gating resource + permission approve_mutu (segregasi).
 * Sekaligus memastikan DokumenMutuResource (dengan aksi approval baru) LOAD
 * tanpa fatal (canAccess static method dieksekusi).
 */
class MutuRbacTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePpdbTestingDatabase();

        if (! Schema::connection('pgsql')->hasTable('dokumen_mutu_versi')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        $this->truncatePpdbTables(['domains', 'tenants', 'users']);

        $tenant = $this->createRuntimeTenant('muturbac', 'Pesantren Mutu RBAC');

        // Pastikan modul 'mutu' TAMPAK (deterministik terlepas dari ModuleRegistry
        // yang mungkin di-seed test lain) — aktivasi di konteks CENTRAL sebelum init.
        \App\Models\TenantModule::firstOrCreate(
            ['tenant_id' => $tenant->getKey(), 'module_name' => 'mutu'],
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

    public function test_kepala_keuangan_bisa_akses_dan_menyetujui_mutu(): void
    {
        $this->actingAs($this->userDenganRole('kepala_keuangan'));

        $this->assertTrue(DokumenMutuResource::canAccess(), 'kepala_keuangan (manage_mutu) boleh akses Dosir Mutu.');
        $this->assertTrue(auth()->user()->can('approve_mutu'), 'kepala_keuangan boleh menyetujui dokumen mutu.');
    }

    public function test_peran_tanpa_mutu_tidak_bisa_akses_maupun_approve(): void
    {
        $this->actingAs($this->userDenganRole('guru'));

        $this->assertFalse(DokumenMutuResource::canAccess(), 'Guru tidak boleh akses Dosir Mutu.');
        $this->assertFalse(auth()->user()->can('approve_mutu'), 'Guru tidak boleh menyetujui dokumen mutu.');
    }
}
