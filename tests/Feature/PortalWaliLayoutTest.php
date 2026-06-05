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
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tabungan;
use Modules\Keuangan\Models\Tagihan;
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
        tenancy()->end();
    }

    public function test_wali_dashboard_uses_inertia_mobile_layout_payload(): void
    {
        $tenant = $this->createRuntimeTenant('portal-wali-layout', 'Portal Wali Layout');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        $this->seed(RolesAndPermissionsSeeder::class);

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

        $tagihan = Tagihan::create([
            'tenant_id' => $tenant->id,
            'santri_id' => $santri->id,
            'jenis_tagihan' => 'SPP',
            'keterangan' => 'SPP Juli 2026',
            'nominal' => 450000,
            'diskon' => 0,
            'nominal_akhir' => 450000,
            'jatuh_tempo' => '2026-07-10',
            'status' => 'belum_bayar',
            'created_by' => $wali->id,
        ]);

        Pembayaran::create([
            'tenant_id' => $tenant->id,
            'tagihan_id' => $tagihan->id,
            'santri_id' => $santri->id,
            'nominal' => 100000,
            'metode' => 'transfer',
            'tanggal_pembayaran' => '2026-07-02',
            'is_verified' => false,
            'created_by' => $wali->id,
        ]);

        Tabungan::create([
            'tenant_id' => $tenant->id,
            'santri_id' => $santri->id,
            'saldo' => 80000,
        ]);

        tenancy()->end();

        $response = $this
            ->actingAs($wali)
            ->get("http://{$tenant->id}.localhost/wali");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('PortalWali/Dashboard')
            ->where('wali.name', 'Wali Layout')
            ->where('summary.childrenCount', 1)
            ->where('summary.activeChildrenCount', 1)
            ->where('summary.totalOutstanding', 450000)
            ->where('summary.totalSavings', 80000)
            ->where('summary.pendingTransactions', 1)
            ->has('navigation', 5)
            ->where('navigation.0.label', 'Beranda')
            ->where('navigation.1.label', 'Tagihan')
            ->where('navigation.2.label', 'Tabungan')
            ->where('navigation.3.label', 'Anak')
            ->where('navigation.4.label', 'Akun')
            ->has('children', 1)
            ->where('children.0.nama', 'Santri Layout')
            ->where('children.0.kelas.nama', 'VIII-B')
            ->where('children.0.hubungan', 'ayah')
            ->where('children.0.isPrimary', true)
            ->where('children.0.finance.totalOutstanding', 450000)
            ->where('children.0.finance.savingsBalance', 80000)
            ->where('children.0.finance.pendingPaymentsCount', 1)
        );
    }
}
