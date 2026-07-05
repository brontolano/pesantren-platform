<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Regresi audit RBAC independen 2026-07-05: rute /admin/keuangan/pwa/* hanya
 * digate `auth` (tanpa `can:`), dan controller-nya tidak abort_unless — dari
 * kode saja terlihat user ber-role apa pun (termasuk santri tanpa izin
 * keuangan) bisa melihat shell halaman + data referensi keuangan (COA/sumber
 * dana). Verifikasi HTTP menunjukkan mekanisme lain (redirect ke /admin lalu
 * halaman 403 bertema) SUDAH menahannya di praktik — tapi abort_unless
 * eksplisit tetap ditambahkan sebagai defense-in-depth & agar intent kode
 * jelas, mengikuti pola setorKeliling() yang sudah benar.
 */
class KeuanganPwaAuthorizationTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions',
            'akun', 'sumber_dana', 'divisi_unit',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('kpwaauth', 'Pesantren KPWA Auth');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);
        tenancy()->end();
    }

    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    private function bootUser(string $email, string $role): array
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'User '.$email, 'email' => $email, 'password' => bcrypt('password'),
        ]);
        $user->assignRole($role);

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        return [$user, $host];
    }

    public function test_santri_tidak_bisa_akses_catat_belanja(): void
    {
        [$user, $host] = $this->bootUser('santri1@kpwa.test', 'santri');

        $this->actingAs($user)->followingRedirects()
            ->get("http://{$host}/admin/keuangan/pwa/catat-belanja", $this->headers($host))
            ->assertStatus(403);
    }

    public function test_santri_tidak_bisa_akses_dasbor_pimpinan(): void
    {
        [$user, $host] = $this->bootUser('santri2@kpwa.test', 'santri');

        $this->actingAs($user)->followingRedirects()
            ->get("http://{$host}/admin/keuangan/pwa/dasbor", $this->headers($host))
            ->assertStatus(403);
    }

    public function test_santri_tidak_bisa_akses_slip_saya(): void
    {
        [$user, $host] = $this->bootUser('santri3@kpwa.test', 'santri');

        $this->actingAs($user)->followingRedirects()
            ->get("http://{$host}/admin/keuangan/pwa/slip-saya", $this->headers($host))
            ->assertStatus(403);
    }

    public function test_bendahara_tetap_bisa_akses_catat_belanja(): void
    {
        [$user, $host] = $this->bootUser('bendahara1@kpwa.test', 'bendahara_pusat');

        $this->actingAs($user)->get("http://{$host}/admin/keuangan/pwa/catat-belanja", $this->headers($host))->assertStatus(200);
    }

    public function test_santri_tanpa_izin_apapun_tidak_bisa_akses_beranda(): void
    {
        [$user, $host] = $this->bootUser('santri4@kpwa.test', 'santri');

        $this->actingAs($user)->followingRedirects()
            ->get("http://{$host}/admin/keuangan/pwa/", $this->headers($host))
            ->assertStatus(403);
    }
}
