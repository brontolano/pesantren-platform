<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesehatan\Models\KunjunganUks;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F4 — Portal UKS data nyata (menggantikan UksDemoController).
 * - 403 tanpa permission view_kesehatan
 * - 200 dengan permission (role pengelola_uks)
 * - POST kunjungan/catat benar-benar insert ke kunjungan_uks
 */
class UksModulTest extends TestCase
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
            'santri', 'kunjungan_uks', 'obat',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('f4uks', 'Pesantren F4 UKS');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        $this->santri = Santri::create([
            'tenant_id' => $this->tenant->id,
            'nis' => '2026001',
            'nama' => 'Santri Uji UKS',
            'tanggal_lahir' => '2010-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);

        tenancy()->end();
    }

    public function test_akses_ditolak_403_tanpa_permission_view_kesehatan(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Guru Biasa',
            'email' => 'guru-nonuks@f4.test',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('guru');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/uks", ['HTTP_HOST' => $host, 'SERVER_NAME' => $host]);

        $response->assertStatus(403);
    }

    public function test_akses_diterima_200_dengan_role_pengelola_uks(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Petugas UKS',
            'email' => 'petugas-uks@f4.test',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('pengelola_uks');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/uks", ['HTTP_HOST' => $host, 'SERVER_NAME' => $host]);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Uks/Beranda'));
    }

    public function test_post_kunjungan_catat_insert_ke_database(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Petugas UKS Catat',
            'email' => 'petugas-catat@f4.test',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('pengelola_uks');

        $santriId = $this->santri->id;
        $this->assertSame(0, KunjunganUks::count());

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->post("http://{$host}/uks/kunjungan/catat", [
            'santri_id' => $santriId,
            'keluhan' => 'Demam sejak semalam',
            'tindakan' => 'Paracetamol, kompres',
            'status' => 'pulang',
        ], ['HTTP_HOST' => $host, 'SERVER_NAME' => $host]);

        tenancy()->initialize($this->tenant);
        $response->assertRedirect(route('tenant.uks.kunjungan'));

        $this->assertSame(1, KunjunganUks::count());
        $kunjungan = KunjunganUks::first();
        $this->assertSame($santriId, $kunjungan->santri_id);
        $this->assertSame('Demam sejak semalam', $kunjungan->keluhan);
        $this->assertSame('pulang', $kunjungan->status);
        $this->assertSame($user->id, $kunjungan->pencatat_id);

        tenancy()->end();
    }
}
