<?php

namespace Tests\Feature;

use App\Models\AccessWali;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class PortalWaliLayoutTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

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

    public function test_wali_dashboard_uses_inertia_mobile_layout_payload(): void
    {
        $tenant = $this->createTestTenant('portal-wali-layout', 'Portal Wali Layout', 'portal-wali-layout');
        tenancy()->initialize($tenant);

        $kelas = Kelas::create([
            'tenant_id' => $tenant->id,
            'nama_kelas' => 'VIII-B',
            'tingkat' => '8',
            'capacity' => 32,
            'is_active' => true,
        ]);

        $wali = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Wali Layout',
            'email' => 'wali-layout@test.local',
            'password' => 'password',
        ]);
        $wali->assignRole('wali');

        $santri = Santri::create([
            'tenant_id' => $tenant->id,
            'nis' => 'PWL001',
            'nama' => 'Santri Layout',
            'tanggal_lahir' => '2012-03-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
            'kelas_id' => $kelas->id,
        ]);

        AccessWali::create([
            'tenant_id' => $tenant->id,
            'wali_id' => $wali->id,
            'santri_id' => $santri->id,
            'relationship_type' => 'ayah',
            'is_primary' => true,
        ]);

        $response = $this
            ->actingAs($wali)
            ->get('http://portal-wali-layout.localhost/wali');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('PortalWali/Dashboard')
            ->where('wali.name', 'Wali Layout')
            ->where('summary.childrenCount', 1)
            ->where('summary.activeChildrenCount', 1)
            ->has('children', 1)
            ->where('children.0.nama', 'Santri Layout')
            ->where('children.0.kelas.nama', 'VIII-B')
            ->where('children.0.hubungan', 'ayah')
            ->where('children.0.isPrimary', true)
        );
    }
}
