<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Regresi 500 "invalid input syntax for type bigint: asatidz" di resource Users.
 *
 * Bug lama: field roles meng-override ->options(pluck('name','name')) sehingga
 * value opsi = NAMA role, padahal ->relationship('roles','name') me-resolve via
 * ID (kunci pivot model_has_roles.role_id) → query "where roles.id = 'asatidz'".
 * Fix: biarkan relationship() menyetir opsi (value = id). Test memakai peran
 * ber-nama non-numerik ('asatidz') agar bentrok nama-vs-id pasti tampak.
 */
class UserResourceRolesTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions',
            'model_has_roles', 'model_has_permissions',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('users', 'Pesantren Users');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        $this->seed(RolesAndPermissionsSeeder::class);
        Role::findOrCreate('asatidz', 'web'); // peran ber-nama (bukan ID numerik)
        Filament::setCurrentPanel(Filament::getPanel('tenant'));
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Admin Users',
            'email' => 'admin.users@test.local', 'password' => 'password123',
        ]);
        $user->assignRole('super_admin');

        return $user;
    }

    /**
     * GUARD inti: opsi field roles HARUS di-key dgn ID role (bukan nama). Inilah
     * akar 500-nya — kalau opsi di-key nama, UI menyimpan NAMA ke state, lalu
     * relationship resolve "where roles.id = '<nama>'" → bigint error. Test ini
     * MERAH di kode lama (key='asatidz'), HIJAU setelah fix (key=id, 'asatidz'=label).
     */
    public function test_opsi_roles_di_key_dengan_id_bukan_nama(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateUser::class)
            ->assertFormFieldExists('roles', function (\Filament\Forms\Components\Select $field): bool {
                $options = $field->getOptions();

                return ! array_key_exists('asatidz', $options) // nama BUKAN kunci
                    && in_array('asatidz', $options, true);     // nama = label/value
            });
    }

    public function test_buat_user_dengan_peran_terpasang(): void
    {
        $this->actingAs($this->admin());
        $asatidz = Role::findByName('asatidz', 'web');

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Ustadz Baru',
                'email' => 'ustadz.baru@test.local',
                'password' => 'rahasia123',
                'roles' => [$asatidz->getKey()],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::withoutGlobalScopes()->where('email', 'ustadz.baru@test.local')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('asatidz'));
    }
}
