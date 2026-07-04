<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\SantriAccountService;
use Modules\Keuangan\Models\Tabungan;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F4 — Portal PWA POS Kantin (menggantikan PosKantinDemoController).
 * - 403 tanpa permission operate_pos
 * - 200 dengan permission (role kasir)
 * - POST belanja benar-benar memotong saldo tabungan santri via KantinService
 *   (PIN wajib benar — reuse logika yang sama dgn KasirKantin panel admin).
 */
class PosKantinModulTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    private Santri $santri;

    private string $pin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions',
            'santri', 'tabungan', 'tabungan_transaksi',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('f4pos', 'Pesantren F4 POS');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        $this->santri = Santri::create([
            'tenant_id' => $this->tenant->id, 'nis' => 'F4POS001', 'nama' => 'Santri Kantin Uji',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);
        $this->pin = '135790';
        app(SantriAccountService::class)->setPin($this->santri, $this->pin);

        Tabungan::create(['tenant_id' => $this->tenant->id, 'santri_id' => $this->santri->id, 'saldo' => 100000]);

        tenancy()->end();
    }

    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    public function test_akses_ditolak_403_tanpa_permission_operate_pos(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Guru Biasa', 'email' => 'guru-nonpos@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('guru');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/pos-kantin", $this->headers($host));

        $response->assertStatus(403);
    }

    public function test_akses_diterima_200_dengan_role_kasir(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Kasir Kantin', 'email' => 'kasir@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('kasir');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/pos-kantin", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('PosKantin/Beranda'));
    }

    public function test_belanja_pin_salah_ditolak_saldo_tidak_berubah(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Kasir Uji Salah', 'email' => 'kasir-salah@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('kasir');

        $host = $this->tenant->id.'.localhost';
        $santriId = $this->santri->id;
        tenancy()->end();

        $response = $this->actingAs($user)->post("http://{$host}/pos-kantin/kasir/belanja", [
            'santri_id' => $santriId,
            'nominal' => 5000,
            'pin' => '000000',
        ], $this->headers($host));

        $response->assertSessionHasErrors('pin');

        tenancy()->initialize($this->tenant);
        $this->assertSame(100000.0, (float) Tabungan::where('santri_id', $santriId)->value('saldo'));
        tenancy()->end();
    }

    public function test_belanja_pin_benar_memotong_saldo_nyata(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Kasir Uji Benar', 'email' => 'kasir-benar@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('kasir');

        $host = $this->tenant->id.'.localhost';
        $santriId = $this->santri->id;
        $pin = $this->pin;
        tenancy()->end();

        $response = $this->actingAs($user)->post("http://{$host}/pos-kantin/kasir/belanja", [
            'santri_id' => $santriId,
            'nominal' => 7500,
            'pin' => $pin,
            'keterangan' => 'Roti + Susu',
        ], $this->headers($host));

        $response->assertRedirect(route('tenant.pos-kantin.kasir'));

        tenancy()->initialize($this->tenant);
        $this->assertSame(92500.0, (float) Tabungan::where('santri_id', $santriId)->value('saldo'));
        tenancy()->end();
    }
}
