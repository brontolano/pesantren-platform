<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Modules\Kegiatan\Models\Kegiatan;
use Modules\Kegiatan\Models\PresensiKegiatan;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F4 — Portal PWA Pengabdian (menggantikan PengabdianDemoController).
 *
 * Gate: middleware('auth') SAJA — bukan permission staf (lihat docblock
 * PengabdianController). Maka pengujian akses adalah:
 *  - Guest (belum login) DITOLAK (redirect ke login, bukan 200).
 *  - User login TANPA data Santri tertaut tetap 200 (santri belum tertaut =
 *    layar kosong yang aman, bukan error) — konsisten dgn pola santri-app.
 *  - User login DENGAN data Santri + PresensiKegiatan nyata → 200 dan data
 *    kegiatan yang sungguhan (bukan dummy) muncul di response Inertia.
 */
class PengabdianModulTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions',
            'santri', 'kegiatan', 'presensi_kegiatan',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('f4pengabdian', 'Pesantren F4 Pengabdian');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);
        tenancy()->end();
    }

    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    public function test_guest_ditolak_dan_diarahkan_ke_login(): void
    {
        tenancy()->initialize($this->tenant);
        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->get("http://{$host}/pengabdian", $this->headers($host));

        $response->assertRedirect();
        $this->assertNotEquals(200, $response->getStatusCode());
    }

    public function test_user_login_tanpa_santri_tertaut_tetap_200_layar_kosong(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Akun Tanpa Santri', 'email' => 'tanpa-santri@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('santri');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/pengabdian", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Pengabdian/Beranda')
            ->where('penugasan', null));
    }

    public function test_user_login_dengan_data_kegiatan_nyata_muncul_di_beranda(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Santri Khidmah', 'email' => 'khidmah@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('santri');

        $santri = Santri::create([
            'tenant_id' => $this->tenant->id, 'nis' => 'F4PGB001', 'nama' => 'Santri Khidmah Uji',
            'tanggal_lahir' => '2010-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif',
            'user_id' => $user->id,
        ]);

        $kegiatan = Kegiatan::create([
            'tenant_id' => $this->tenant->id,
            'nama' => 'Khidmah Kebersihan Masjid',
            'jenis' => 'wajib',
            'mulai' => now()->addDay(),
            'selesai' => now()->addDay()->addHours(2),
            'lokasi' => 'Masjid Jami Pusat',
            'status' => 'terjadwal',
        ]);

        PresensiKegiatan::create([
            'tenant_id' => $this->tenant->id,
            'kegiatan_id' => $kegiatan->id,
            'santri_id' => $santri->id,
            'status' => 'hadir',
            'keterangan' => 'Hadir tepat waktu',
        ]);

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/pengabdian", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Pengabdian/Beranda')
            ->where('penugasan.nis', 'F4PGB001')
            ->where('tugasTerdekat.0.judul', 'Khidmah Kebersihan Masjid'));

        // Halaman Presensi & Laporan juga memuat data PresensiKegiatan nyata (bukan dummy).
        $laporan = $this->actingAs($user)->get("http://{$host}/pengabdian/laporan", $this->headers($host));
        $laporan->assertStatus(200);
        $laporan->assertInertia(fn ($page) => $page->component('Pengabdian/Laporan')
            ->where('laporan.0.judul', 'Khidmah Kebersihan Masjid')
            ->where('laporan.0.ringkasan', 'Hadir tepat waktu'));
    }
}
