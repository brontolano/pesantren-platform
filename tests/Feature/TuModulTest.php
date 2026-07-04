<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Models\Surat;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F4 — Portal PWA Tata Usaha (menggantikan TuDemoController).
 * - 403 tanpa permission manage_santri
 * - 200 dengan permission (role tu)
 * - POST catat-surat benar-benar insert ke tabel surat (via SuratService,
 *   penomoran otomatis race-safe — sudah dipakai admin panel SuratResource).
 */
class TuModulTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions',
            'santri', 'kelas', 'surat',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('f4tu', 'Pesantren F4 TU');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);
        tenancy()->end();
    }

    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    public function test_akses_ditolak_403_tanpa_permission_manage_santri(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Guru Biasa', 'email' => 'guru-nontu@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('guru');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/tu", $this->headers($host));

        $response->assertStatus(403);
    }

    public function test_akses_diterima_200_dengan_role_tu(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Staf TU', 'email' => 'tu@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('tu');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/tu", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Tu/Beranda'));
    }

    public function test_halaman_santri_menampilkan_data_nyata(): void
    {
        tenancy()->initialize($this->tenant);

        Santri::create([
            'tenant_id' => $this->tenant->id, 'nis' => '2026001', 'nama' => 'Santri Uji TU',
            'tanggal_lahir' => '2010-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Staf TU Santri', 'email' => 'tu-santri@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('tu');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/tu/santri", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Tu/Santri')
            ->where('santri.0.nama', 'Santri Uji TU')
        );
    }

    public function test_post_catat_surat_insert_ke_database_dengan_nomor_otomatis(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Staf TU Catat', 'email' => 'tu-catat@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('tu');

        $host = $this->tenant->id.'.localhost';

        $this->assertDatabaseCount('surat', 0, 'tenant');

        tenancy()->end();

        $response = $this->actingAs($user)->post("http://{$host}/tu/catat-surat", [
            'jenis' => 'keluar',
            'tanggal_surat' => now()->toDateString(),
            'pihak' => 'Kemenag Kab. Sumedang',
            'perihal' => 'Undangan Rapat Koordinasi',
            'keterangan' => 'Undangan',
        ], $this->headers($host));

        $response->assertRedirect(route('tenant.tu.surat'));

        tenancy()->initialize($this->tenant);

        $this->assertSame(1, Surat::count());
        $surat = Surat::first();
        $this->assertSame('keluar', $surat->jenis);
        $this->assertSame('Undangan Rapat Koordinasi', $surat->perihal);
        $this->assertNotEmpty($surat->nomor_surat);

        tenancy()->end();
    }
}
