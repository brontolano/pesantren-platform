<?php

namespace Tests\Feature;

use App\Models\AccessWali;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * #340 — Privasi foto santri PUTRI di luar panel admin:
 * - Portal Guru pos gerbang: guru bukan peran berhak → avatar inisial, bukan foto asli.
 * - Portal Wali: wali melihat foto ANAK SENDIRI (diizinkan), anak lain tak terjangkau.
 */
class PrivasiFotoPutriPortalTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private const FOTO_PUTRI = 'santri/foto/putri-rahasia.jpg';

    private const FOTO_PUTRA = 'santri/foto/putra-biasa.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePpdbTestingDatabase();

        if (! Schema::connection('pgsql')->hasTable('log_gerbang')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        $this->truncatePpdbTables([
            'log_gerbang',
            'access_wali',
            'santri',
            'settings',
            'role_has_permissions',
            'model_has_roles',
            'model_has_permissions',
            'roles',
            'permissions',
            'users',
            'domains',
            'tenants',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        tenancy()->end();
    }

    private function buatSantri(string $nis, string $jk, string $foto): Santri
    {
        return Santri::create([
            'tenant_id' => tenant('id'),
            'nis' => $nis,
            'nama' => 'Santri '.$nis,
            'tanggal_lahir' => '2011-05-05',
            'jenis_kelamin' => $jk,
            'status' => 'aktif',
            'photo_url' => $foto,
        ]);
    }

    /**
     * Pola Wp29GerbangTest: tenancy tetap terinisialisasi, request via Kernel.
     */
    private function bukaGerbang(User $petugas, string $nis): string
    {
        Auth::login($petugas);

        $host = tenant()->domains()->first()->domain;
        $request = Request::create("http://{$host}/guru/gerbang?nis={$nis}", 'GET');
        $request->headers->set('Host', $host);
        $response = app(Kernel::class)->handle($request);

        $this->assertSame(200, $response->getStatusCode());

        return (string) $response->getContent();
    }

    private function bootGerbang(string $slug): User
    {
        $tenant = $this->createRuntimeTenant($slug, 'Pesantren '.$slug);
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        $petugas = User::withoutGlobalScopes()->create([
            'name' => 'Guru Pos Gerbang',
            'email' => 'satpam@'.tenant('id').'.localhost',
            'password' => 'password',
            'tenant_id' => tenant('id'),
        ]);
        $petugas->givePermissionTo('akses_pwa_guru');

        return $petugas;
    }

    public function test_gerbang_foto_putri_tidak_bocor_ke_guru_tak_berhak(): void
    {
        $petugas = $this->bootGerbang('foto-gerbang-putri');
        $this->buatSantri('GP001', 'P', self::FOTO_PUTRI);

        $html = $this->bukaGerbang($petugas, 'GP001');

        $this->assertStringNotContainsString('putri-rahasia.jpg', $html);
        $this->assertStringContainsString('ui-avatars.com', $html);
    }

    public function test_gerbang_foto_putra_tetap_tampil(): void
    {
        $petugas = $this->bootGerbang('foto-gerbang-putra');
        $this->buatSantri('GP002', 'L', self::FOTO_PUTRA);

        $html = $this->bukaGerbang($petugas, 'GP002');

        $this->assertStringContainsString('putra-biasa.jpg', $html);
    }

    public function test_gerbang_foto_putri_tampil_untuk_kepala(): void
    {
        $petugas = $this->bootGerbang('foto-gerbang-kepala');
        $petugas->assignRole('kepala');
        $this->buatSantri('GP003', 'P', self::FOTO_PUTRI);

        $html = $this->bukaGerbang($petugas, 'GP003');

        $this->assertStringContainsString('putri-rahasia.jpg', $html);
    }

    public function test_wali_tetap_melihat_foto_anak_putri_sendiri(): void
    {
        $tenant = $this->createRuntimeTenant('foto-wali-anak', 'Foto Wali Anak');
        $host = $tenant->domains->first()->domain;

        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        $this->seed(RolesAndPermissionsSeeder::class);

        $putri = $this->buatSantri('WP001', 'P', self::FOTO_PUTRI);

        $wali = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Wali Putri',
            'email' => 'wali-putri@test.local',
            'password' => 'password',
        ]);
        $wali->assignRole('wali');

        AccessWali::create([
            'tenant_id' => $tenant->id,
            'wali_id' => $wali->id,
            'santri_id' => $putri->id,
            'relationship_type' => 'ayah',
            'is_primary' => true,
        ]);
        tenancy()->end();

        $response = $this->actingAs($wali)->get("http://{$host}/wali/santri/{$putri->id}", [
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);

        $response->assertOk();
        // Kebijakan #340: wali melihat foto ANAK SENDIRI = wajar & diizinkan.
        $this->assertStringContainsString('putri-rahasia.jpg', $response->getContent());
    }

    public function test_wali_tidak_bisa_membuka_profil_anak_orang_lain(): void
    {
        $tenant = $this->createRuntimeTenant('foto-wali-asing', 'Foto Wali Asing');
        $host = $tenant->domains->first()->domain;

        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        $this->seed(RolesAndPermissionsSeeder::class);

        $putriLain = $this->buatSantri('WP002', 'P', self::FOTO_PUTRI);

        $wali = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Wali Tanpa Anak Ini',
            'email' => 'wali-asing@test.local',
            'password' => 'password',
        ]);
        $wali->assignRole('wali');
        tenancy()->end();

        $this->actingAs($wali)
            ->get("http://{$host}/wali/santri/{$putriLain->id}", [
                'HTTP_HOST' => $host,
                'SERVER_NAME' => $host,
            ])
            ->assertNotFound();
    }
}
