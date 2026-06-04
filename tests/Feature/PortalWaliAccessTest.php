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
    }

    public function test_access_wali_relation_only_returns_children_in_same_tenant(): void
    {
        $tenantA = $this->createTestTenant('wali-a', 'Wali A', 'wali-a');
        $tenantB = $this->createTestTenant('wali-b', 'Wali B', 'wali-b');

        tenancy()->initialize($tenantA);
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
