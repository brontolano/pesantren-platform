<?php

namespace Tests\Feature;

use App\Filament\Resources\Kesehatan\Obat\Pages\ListObat;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Verifikasi tombol "Unduh Template" (trait HasImportAction) benar-benar
 * menghasilkan file CSV. Diuji pada satu importer (Obat) sebagai wakil pola.
 */
class ImportTemplateDownloadTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'obat',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('tmpl', 'Pesantren Template');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('tenant'));

        $admin = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Admin', 'email' => 'a@tmpl.test', 'password' => 'x12345678',
        ]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    public function test_unduh_template_obat_menghasilkan_csv(): void
    {
        Livewire::test(ListObat::class)
            ->callAction('importObatTemplate')
            ->assertFileDownloaded('template-import-obat.xlsx');
    }

    public function test_modal_import_dengan_seksi_pemetaan_mount_tanpa_error(): void
    {
        // Mount modal import → membangun schema termasuk Section pemetaan kolom
        // (closure visible/options dieksekusi) — memastikan tak ada error konstruksi.
        Livewire::test(ListObat::class)
            ->mountAction('importObat')
            ->assertActionMounted('importObat');
    }
}
