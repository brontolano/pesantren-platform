<?php

namespace Tests\Feature;

use App\Filament\Resources\Santri\Pages\ListSantri;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Regresi untuk keluhan owner "kenapa di santri tidak ada import?".
 *
 * Empty-state SantriResource menjanjikan "Mulai dengan mengimpor data dari
 * Excel" tetapi tombolnya hilang setelah refactor PR #206 (trait dipasang ke 21
 * resource lain, Santri terlewat). Tes ini memastikan aksi Import + Unduh
 * Template benar-benar terpasang di halaman List Santri dan modal/template-nya
 * terbangun tanpa error — bukan hanya trait-nya yang benar secara terpisah.
 */
class SantriImportUiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'santri', 'kelas',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('santriui', 'Pesantren Import Santri');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('tenant'));

        $admin = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Admin', 'email' => 'a@santriui.test', 'password' => 'x12345678',
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

    public function test_aksi_import_santri_terpasang_di_halaman_list(): void
    {
        Livewire::test(ListSantri::class)
            ->assertActionExists('importSantri')
            ->assertActionExists('importSantriTemplate');
    }

    public function test_unduh_template_santri_menghasilkan_xlsx(): void
    {
        Livewire::test(ListSantri::class)
            ->callAction('importSantriTemplate')
            ->assertFileDownloaded('template-import-santri.xlsx');
    }

    public function test_modal_import_santri_mount_tanpa_error(): void
    {
        // Mount modal import → membangun schema termasuk Section pemetaan kolom
        // (closure visible/options dieksekusi) — memastikan tak ada error konstruksi.
        Livewire::test(ListSantri::class)
            ->mountAction('importSantri')
            ->assertActionMounted('importSantri');
    }
}
