<?php

namespace Tests\Feature;

use Modules\Akademik\Filament\Resources\Pelajaran\Pages\ListPelajaran;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Akademik\Models\Pelajaran;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Export round-trip (trait HasImportAction): tombol Export menstream CSV data
 * unit dalam format yang sama dengan import. Diuji pada Pelajaran sbg wakil pola.
 */
class ImportExportTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'pelajaran',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('xport', 'Pesantren Export');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('tenant'));

        $admin = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Admin', 'email' => 'a@xport.test', 'password' => 'x12345678',
        ]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        Pelajaran::create(['tenant_id' => $this->tenant->id, 'nama' => 'Nahwu', 'kode' => 'NHW', 'sks' => 2]);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    public function test_export_pelajaran_menghasilkan_csv(): void
    {
        Livewire::test(ListPelajaran::class)
            ->callAction('importPelajaranExport')
            ->assertFileDownloaded('export-pelajaran.csv');
    }
}
