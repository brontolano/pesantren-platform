<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Modules\Ledger\Database\Seeders\LedgerSeeder;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Services\JurnalService;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F4 — Portal PWA Akuntan (role `akuntan` sebelumnya 0% PWA — tak ada
 * controller/route apapun, harus buka Filament admin utk segala hal).
 * - 403 tanpa permission view_ledger
 * - 200 dengan permission (role akuntan)
 * - Jurnal TIDAK bisa dibuat manual (Pagar #2 — konsisten dgn JurnalResource
 *   admin yang canCreate()-nya selalu false), hanya bisa dibalik.
 */
class AkuntanModulTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions',
            'akun', 'sumber_dana', 'periode_akuntansi', 'jurnal', 'jurnal_baris',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('f4akuntan', 'Pesantren F4 Akuntan');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);
        (new LedgerSeeder)->run();
        tenancy()->end();
    }

    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    public function test_akses_ditolak_403_tanpa_permission_view_ledger(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Guru Biasa', 'email' => 'guru-nonakuntan@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('guru');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $this->actingAs($user)->get("http://{$host}/akuntan", $this->headers($host))->assertStatus(403);
    }

    public function test_akses_diterima_200_dengan_role_akuntan(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Akuntan Pesantren', 'email' => 'akuntan@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('akuntan');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/akuntan", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Akuntan/Beranda'));
    }

    public function test_halaman_jurnal_menampilkan_data_nyata(): void
    {
        tenancy()->initialize($this->tenant);

        $bebanGaji = Akun::where('kode', '5-2000')->firstOrFail();
        $kas = Akun::kasBank()->where('is_postable', true)->firstOrFail();

        app(JurnalService::class)->postJurnal([
            'tanggal' => now(),
            'deskripsi' => 'Uji Jurnal Akuntan',
            'tipe_sumber' => 'manual-test',
            'idempotency_key' => 'uji-akuntan-001',
            'baris' => [
                ['akun_id' => $bebanGaji->id, 'debit' => 100000, 'kredit' => 0],
                ['akun_id' => $kas->id, 'debit' => 0, 'kredit' => 100000],
            ],
        ]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Akuntan Jurnal', 'email' => 'akuntan-jurnal@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('akuntan');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/akuntan/jurnal", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Akuntan/Jurnal')
            ->where('jurnal.0.deskripsi', 'Uji Jurnal Akuntan')
        );
        $this->assertEqualsWithDelta(100000, $response->viewData('page')['props']['jurnal'][0]['total'], 0.01);
    }

    public function test_akuntan_bisa_membalikkan_jurnal_keliru(): void
    {
        tenancy()->initialize($this->tenant);

        $bebanGaji = Akun::where('kode', '5-2000')->firstOrFail();
        $kas = Akun::kasBank()->where('is_postable', true)->firstOrFail();

        $jurnal = app(JurnalService::class)->postJurnal([
            'tanggal' => now(),
            'deskripsi' => 'Jurnal Salah Akun',
            'tipe_sumber' => 'manual-test',
            'idempotency_key' => 'uji-akuntan-002',
            'baris' => [
                ['akun_id' => $bebanGaji->id, 'debit' => 50000, 'kredit' => 0],
                ['akun_id' => $kas->id, 'debit' => 0, 'kredit' => 50000],
            ],
        ]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Akuntan Balik', 'email' => 'akuntan-balik@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('akuntan');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->post("http://{$host}/akuntan/jurnal/{$jurnal->id}/balik", [
            'alasan' => 'Salah akun beban',
        ], $this->headers($host));

        $response->assertRedirect();

        tenancy()->initialize($this->tenant);
        $this->assertSame('reversed', $jurnal->fresh()->status);
        $this->assertSame(2, Jurnal::count()); // jurnal asal + jurnal pembalik.
        tenancy()->end();
    }

    public function test_halaman_akun_menampilkan_coa_nyata(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Akuntan COA', 'email' => 'akuntan-coa@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('akuntan');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/akuntan/akun", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Akuntan/Akun'));

        $akun = $response->viewData('page')['props']['akun'];
        $this->assertGreaterThan(0, count($akun));
        $this->assertTrue(collect($akun)->contains('kode', '5-2000'));
    }
}
