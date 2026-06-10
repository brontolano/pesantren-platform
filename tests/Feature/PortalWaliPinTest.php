<?php

namespace Tests\Feature;

use App\Models\AccessWali;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/** Wali mengatur PIN login App Santri anaknya (kepemilikan ditegakkan). */
class PortalWaliPinTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->configurePpdbTestingDatabase();
        if (! Schema::connection('pgsql')->hasTable('access_wali')) {
            \Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);
        }
        $this->truncatePpdbTables(['access_wali', 'santri', 'model_has_roles', 'roles', 'permissions', 'role_has_permissions', 'users', 'domains', 'tenants']);
    }

    /** @return array{0:string,1:User,2:Santri,3:Santri} */
    private function boot(string $prefix): array
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        $host = $tenant->domains->first()->domain;
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        $this->seed(RolesAndPermissionsSeeder::class);

        $wali = User::create(['tenant_id' => $tenant->id, 'name' => 'Wali', 'email' => $prefix.'-wali@t.local', 'password' => 'password']);
        $wali->assignRole('wali');

        $anak = Santri::create(['tenant_id' => $tenant->id, 'nis' => $prefix.'-A1', 'nama' => 'Anak Sendiri',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif']);
        AccessWali::create(['tenant_id' => $tenant->id, 'wali_id' => $wali->id, 'santri_id' => $anak->id,
            'relationship_type' => 'ayah', 'is_primary' => true]);

        $anakLain = Santri::create(['tenant_id' => $tenant->id, 'nis' => $prefix.'-A2', 'nama' => 'Anak Orang Lain',
            'tanggal_lahir' => '2012-02-01', 'jenis_kelamin' => 'P', 'status' => 'aktif']);

        tenancy()->end();

        return [$host, $wali, $anak, $anakLain];
    }

    public function test_wali_set_pin_anak_sendiri_dan_santri_bisa_login(): void
    {
        [$host, $wali, $anak] = $this->boot('pin1');
        $server = ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];

        $this->actingAs($wali)
            ->post("http://{$host}/wali/santri/{$anak->id}/atur-pin", ['pin' => '135790'], $server)
            ->assertRedirect();

        tenancy()->initialize(\App\Models\Tenant::query()->where('id', 'like', 'pin1%')->first());
        $userId = $anak->fresh()->user_id;
        $this->assertNotNull($userId, 'Akun santri dibuat saat set PIN.');
        $this->assertTrue(Hash::check('135790', User::withoutGlobalScopes()->find($userId)->password));
        tenancy()->end();
    }

    public function test_wali_tidak_bisa_set_pin_anak_orang_lain(): void
    {
        [$host, $wali, , $anakLain] = $this->boot('pin2');
        $server = ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];

        $res = $this->actingAs($wali)
            ->post("http://{$host}/wali/santri/{$anakLain->id}/atur-pin", ['pin' => '111111'], $server);
        $this->assertContains($res->status(), [403, 404], 'Set PIN anak orang lain harus ditolak.');

        tenancy()->initialize(\App\Models\Tenant::query()->where('id', 'like', 'pin2%')->first());
        $this->assertNull($anakLain->fresh()->user_id, 'Akun anak orang lain TIDAK terbuat.');
        tenancy()->end();
    }

    public function test_pin_wajib_6_digit(): void
    {
        [$host, $wali, $anak] = $this->boot('pin3');
        $server = ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];

        $this->actingAs($wali)
            ->from("http://{$host}/wali/santri/{$anak->id}")
            ->post("http://{$host}/wali/santri/{$anak->id}/atur-pin", ['pin' => '12ab'], $server)
            ->assertSessionHasErrors('pin');
    }
}
