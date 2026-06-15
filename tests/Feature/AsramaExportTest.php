<?php

namespace Tests\Feature;

use App\Filament\Resources\Asrama\PenempatanAsrama\Pages\ListPenempatanAsrama;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Asrama\Models\BlokAsrama;
use Modules\Asrama\Models\KamarAsrama;
use Modules\Asrama\Models\PenempatanAsrama;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Export round-trip relasional (Asrama): memvalidasi rantai relasi
 * penempatan→santri & penempatan→kamar→blok dieksekusi tanpa error & terunduh.
 * Mewakili importer relasional lain (Jadwal/Nilai/Hafalan) yang pola export-nya sama.
 */
class AsramaExportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions',
            'santri', 'blok_asrama', 'kamar_asrama', 'penempatan_asrama',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('xasr', 'Pesantren Asrama Export');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('tenant'));

        $admin = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Admin', 'email' => 'a@xasr.test', 'password' => 'x12345678',
        ]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $santri = Santri::create([
            'tenant_id' => $this->tenant->id, 'nis' => '001', 'nama' => 'Ahmad',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'agama' => 'Islam', 'status' => 'aktif',
        ]);
        $blok = BlokAsrama::create(['tenant_id' => $this->tenant->id, 'nama_blok' => 'Blok A', 'gender' => 'putra']);
        $kamar = KamarAsrama::create(['tenant_id' => $this->tenant->id, 'blok_id' => $blok->id, 'nomor_kamar' => 'A-1', 'kapasitas' => 8]);
        PenempatanAsrama::create([
            'tenant_id' => $this->tenant->id, 'santri_id' => $santri->id, 'kamar_id' => $kamar->id,
            'tanggal_masuk' => '2026-07-14', 'status' => 'aktif', 'is_current' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    public function test_export_asrama_relasional_menghasilkan_csv(): void
    {
        Livewire::test(ListPenempatanAsrama::class)
            ->callAction('importAsramaExport')
            ->assertFileDownloaded('export-asrama.csv');
    }
}
