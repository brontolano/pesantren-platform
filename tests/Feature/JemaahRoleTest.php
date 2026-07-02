<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Fondasi 1-aplikasi (Fase 2b/2c): peran publik 'jemaah'.
 *
 * Jemaah = pengunjung login (no-WA + password) yang BOLEH berkomentar di Beranda
 * publik, tetapi TIDAK boleh menyentuh panel/dashboard staf. Tamu (tanpa login)
 * tetap read-only tanpa izin apa pun. Test ini mengunci kontrak izin tsb agar
 * peran jemaah tidak pernah diam-diam mewarisi kewenangan staf.
 */
class JemaahRoleTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady(['users', 'roles', 'permissions', 'domains', 'tenants', 'settings']);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(): void
    {
        $tenant = $this->createRuntimeTenant('jemaah', 'Pesantren Jemaah Test');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        (new RolesAndPermissionsSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function jemaah(): User
    {
        $u = User::withoutGlobalScopes()->create([
            'name' => 'Jemaah Test',
            'email' => 'jemaah@'.tenant('id').'.localhost',
            'password' => 'password',
            'tenant_id' => tenant('id'),
        ]);
        $u->syncRoles(['jemaah']);

        return $u->fresh();
    }

    public function test_izin_komentar_terdaftar(): void
    {
        $this->boot();
        $this->assertDatabaseHas('permissions', ['name' => 'komentar']);
    }

    public function test_jemaah_boleh_komentar(): void
    {
        $this->boot();
        $this->assertTrue($this->jemaah()->can('komentar'), 'jemaah harus boleh berkomentar');
    }

    public function test_jemaah_tidak_punya_akses_staf(): void
    {
        $this->boot();
        $u = $this->jemaah();
        // Peran publik TIDAK boleh mewarisi satu pun kewenangan staf/keuangan.
        foreach ([
            'view_dashboard',
            'view_santri',
            'manage_santri',
            'view_keuangan',
            'manage_keuangan',
            'manage_users',
            'view_akademik',
        ] as $izinStaf) {
            $this->assertFalse(
                $u->can($izinStaf),
                "jemaah TIDAK boleh punya izin staf: {$izinStaf}"
            );
        }
    }
}
