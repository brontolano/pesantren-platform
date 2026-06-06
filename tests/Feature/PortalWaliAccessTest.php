<?php

namespace Tests\Feature;

use App\Models\AccessWali;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PortalWaliAccessTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePpdbTestingDatabase();

        if (! Schema::connection('pgsql')->hasTable('access_wali')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        $this->truncatePpdbTables([
            'access_wali',
            'rapor',
            'nilai',
            'jadwal_pelajaran',
            'pelajaran',
            'periode_akademik',
            'penempatan_kelas',
            'status_santri',
            'alumni',
            'santri',
            'kelas',
            'settings',
            'role_has_permissions',
            'model_has_roles',
            'model_has_permissions',
            'roles',
            'permissions',
            'users',
            'domains',
            'tenants',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        tenancy()->end();
    }

    public function test_non_wali_user_is_forbidden_from_portal_wali_area(): void
    {
        $tenant = $this->createRuntimeTenant('wali-access-admin', 'Wali Access Admin');
        $host = $tenant->domains->first()->domain;

        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Tenant',
            'email' => 'admin-wali@test.local',
            'password' => 'password',
        ]);
        $admin->assignRole('admin_pesantren');
        tenancy()->end();

        $this->actingAs($admin)
            ->get("http://{$host}/wali", [
                'HTTP_HOST' => $host,
                'SERVER_NAME' => $host,
            ])
            ->assertForbidden();
    }

    public function test_wali_cannot_open_financial_page_for_other_child_in_same_tenant(): void
    {
        $tenant = $this->createRuntimeTenant('wali-foreign-child', 'Wali Foreign Child');
        $host = $tenant->domains->first()->domain;

        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        $this->seed(RolesAndPermissionsSeeder::class);

        $kelas = Kelas::create([
            'tenant_id' => $tenant->id,
            'nama_kelas' => 'VIII-A',
            'tingkat' => '8',
            'capacity' => 30,
            'is_active' => true,
        ]);
        $wali = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Wali Utama',
            'email' => 'wali-utama@test.local',
            'password' => 'password',
        ]);
        $wali->assignRole('wali');

        $santriMilik = Santri::create([
            'tenant_id' => $tenant->id,
            'nis' => 'WALI101',
            'nama' => 'Anak Milik',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'kelas_id' => $kelas->id,
        ]);
        AccessWali::create([
            'tenant_id' => $tenant->id,
            'wali_id' => $wali->id,
            'santri_id' => $santriMilik->id,
            'relationship_type' => 'ayah',
            'is_primary' => true,
        ]);

        $santriLain = Santri::create([
            'tenant_id' => $tenant->id,
            'nis' => 'WALI102',
            'nama' => 'Anak Orang Lain',
            'tanggal_lahir' => '2012-02-01',
            'jenis_kelamin' => 'P',
            'status' => 'aktif',
            'kelas_id' => $kelas->id,
        ]);
        tenancy()->end();

        $this->actingAs($wali)
            ->get("http://{$host}/wali/santri/{$santriLain->id}/keuangan", [
                'HTTP_HOST' => $host,
                'SERVER_NAME' => $host,
            ])
            ->assertNotFound();
    }

    public function test_access_wali_relation_only_returns_children_in_same_tenant(): void
    {
        $tenantA = $this->createRuntimeTenant('wali-a', 'Wali A');
        $tenantB = $this->createRuntimeTenant('wali-b', 'Wali B');

        tenancy()->initialize($tenantA);
        $this->seedTenantRow($tenantA);
        $this->seed(RolesAndPermissionsSeeder::class);
        $kelasA = Kelas::create([
            'tenant_id' => $tenantA->id,
            'nama_kelas' => 'VII-A',
            'tingkat' => '7',
            'capacity' => 30,
            'is_active' => true,
        ]);
        $waliA = User::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Wali Tenant A',
            'email' => 'wali-a@test.local',
            'password' => 'password',
        ]);
        $waliA->assignRole('wali');
        $santriA = Santri::create([
            'tenant_id' => $tenantA->id,
            'nis' => 'WALI001',
            'nama' => 'Anak Tenant A',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'kelas_id' => $kelasA->id,
        ]);
        AccessWali::create([
            'tenant_id' => $tenantA->id,
            'wali_id' => $waliA->id,
            'santri_id' => $santriA->id,
            'relationship_type' => 'ayah',
            'is_primary' => true,
        ]);
        tenancy()->end();

        tenancy()->initialize($tenantB);
        $this->seedTenantRow($tenantB);
        $this->seed(RolesAndPermissionsSeeder::class);
        $kelasB = Kelas::create([
            'tenant_id' => $tenantB->id,
            'nama_kelas' => 'VII-B',
            'tingkat' => '7',
            'capacity' => 30,
            'is_active' => true,
        ]);
        $waliB = User::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Wali Tenant B',
            'email' => 'wali-b@test.local',
            'password' => 'password',
        ]);
        $waliB->assignRole('wali');
        $santriB = Santri::create([
            'tenant_id' => $tenantB->id,
            'nis' => 'WALI002',
            'nama' => 'Anak Tenant B',
            'tanggal_lahir' => '2012-02-01',
            'jenis_kelamin' => 'P',
            'status' => 'aktif',
            'kelas_id' => $kelasB->id,
        ]);
        AccessWali::create([
            'tenant_id' => $tenantB->id,
            'wali_id' => $waliB->id,
            'santri_id' => $santriB->id,
            'relationship_type' => 'ibu',
            'is_primary' => true,
        ]);

        $this->assertCount(1, $waliB->accessibleSantri()->get());
        $this->assertSame($santriB->id, $waliB->accessibleSantri()->first()->id);
        $this->assertCount(1, $santriB->waliPortal()->get());
        $this->assertSame($waliB->id, $santriB->waliPortal()->first()->id);
    }
}
