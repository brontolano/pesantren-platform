<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/** App Santri: login via NIS, salah ditolak, dashboard butuh role santri. */
class AppSantriLoginTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady(['tenants', 'domains', 'users', 'santri', 'roles', 'model_has_roles']);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    /** @return array{0:Tenant,1:Santri,2:User} */
    private function boot(string $prefix): array
    {
        $tenant = $this->createRuntimeTenant($prefix, 'Pesantren '.$prefix);
        tenancy()->initialize($tenant);
        DB::connection('tenant')->table('tenants')->updateOrInsert(
            ['id' => $tenant->id],
            ['name' => $tenant->name, 'is_active' => true, 'data' => json_encode([]),
                'created_at' => now(), 'updated_at' => now()],
        );
        if (! DB::connection('tenant')->table('roles')->where('name', 'santri')->exists()) {
            DB::connection('tenant')->table('roles')->insert([
                'name' => 'santri', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Santri App',
            'email' => 'app-'.$prefix.'@santri.test', 'password' => 'rahasia123',
        ]);
        $user->assignRole('santri');
        $santri = Santri::create(['nama' => 'Santri App', 'nis' => 'NIS-'.$prefix, 'status' => 'aktif',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'user_id' => $user->id]);

        return [$tenant, $santri, $user];
    }

    private function host(Tenant $t): array
    {
        $h = $t->id.'.localhost';

        return [$h, ['HTTP_HOST' => $h, 'SERVER_NAME' => $h]];
    }

    public function test_login_nis_benar_masuk_dashboard(): void
    {
        [$tenant, $santri] = $this->boot('as1');
        tenancy()->end();
        [$h, $server] = $this->host($tenant);

        $res = $this->post("http://{$h}/app/login", [
            'nis' => $santri->nis, 'password' => 'rahasia123',
        ], $server);
        $res->assertRedirect();
        $this->assertAuthenticated();

        $home = $this->call('GET', "http://{$h}/app", [], [], [], $server);
        $home->assertOk();
        $home->assertSee('Santri App');
    }

    public function test_nis_atau_password_salah_ditolak(): void
    {
        [$tenant, $santri] = $this->boot('as2');
        tenancy()->end();
        [$h, $server] = $this->host($tenant);

        $this->post("http://{$h}/app/login", ['nis' => $santri->nis, 'password' => 'salah'], $server)
            ->assertSessionHasErrors('nis');
        $this->assertGuest();

        $this->post("http://{$h}/app/login", ['nis' => 'TIDAK-ADA', 'password' => 'rahasia123'], $server)
            ->assertSessionHasErrors('nis');
        $this->assertGuest();
    }

    public function test_user_non_santri_ditendang_dari_dashboard(): void
    {
        [$tenant] = $this->boot('as3');
        $bukan = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Bukan Santri',
            'email' => 'bukan-as3@santri.test', 'password' => 'rahasia123',
        ]);
        tenancy()->end();
        [$h, $server] = $this->host($tenant);

        $this->actingAs($bukan)
            ->call('GET', "http://{$h}/app", [], [], [], $server)
            ->assertRedirect();
    }
}
