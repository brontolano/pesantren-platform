<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesehatan\Models\SesiKonseling;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F4 — Portal PWA Konselor (fitur baru, role `konselor` sebelumnya tak
 * punya PWA sama sekali).
 * - 403 tanpa permission view_konseling
 * - 200 dengan permission (role konselor)
 * - Catat sesi benar-benar menulis ke DB nyata (SesiKonseling).
 */
class KonselorModulTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    private Santri $santri;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions',
            'santri', 'sesi_konseling',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('f4konselor', 'Pesantren F4 Konselor');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        $this->santri = Santri::create([
            'tenant_id' => $this->tenant->id,
            'nama' => 'Santri Uji Konseling',
            'nis' => 'KNS-001',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);

        tenancy()->end();
    }

    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    public function test_akses_ditolak_403_tanpa_permission_view_konseling(): void
    {
        tenancy()->initialize($this->tenant);
        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Guru Biasa', 'email' => 'guru-konselor@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('guru');
        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/konselor", $this->headers($host));

        $response->assertForbidden();
    }

    public function test_akses_diterima_200_dengan_role_konselor(): void
    {
        tenancy()->initialize($this->tenant);
        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Konselor Uji', 'email' => 'konselor@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('konselor');
        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/konselor", $this->headers($host));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Konselor/Beranda'));
    }

    public function test_post_catat_sesi_insert_ke_database_nyata(): void
    {
        tenancy()->initialize($this->tenant);
        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Konselor Catat', 'email' => 'konselor-catat@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('konselor');
        $host = $this->tenant->id.'.localhost';

        $this->assertDatabaseCount('sesi_konseling', 0, 'tenant');

        tenancy()->end();

        $response = $this->actingAs($user)->post("http://{$host}/konselor/catat", [
            'santri_id' => $this->santri->id,
            'tanggal' => now()->toDateString(),
            'topik' => 'Penyesuaian Asrama',
            'deskripsi' => 'Santri kesulitan adaptasi',
        ], $this->headers($host));

        $response->assertRedirect(route('tenant.konselor.sesi'));

        tenancy()->initialize($this->tenant);

        $this->assertSame(1, SesiKonseling::count());
        $sesi = SesiKonseling::first();
        $this->assertSame($this->santri->id, $sesi->santri_id);
        $this->assertSame('Penyesuaian Asrama', $sesi->topik);
        $this->assertSame('terjadwal', $sesi->status);

        tenancy()->end();
    }
}
