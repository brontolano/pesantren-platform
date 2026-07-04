<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Modules\Asrama\Models\BlokAsrama;
use Modules\Asrama\Models\KamarAsrama;
use Modules\Asrama\Models\PenempatanAsrama;
use Modules\Kesantrian\Models\JenisPelanggaran;
use Modules\Kesantrian\Models\PelanggaranSantri;
use Modules\Kesantrian\Models\PresensiAsrama;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F4 — Portal PWA Pengasuhan/Asrama (menggantikan PengasuhanDemoController).
 * - 403 tanpa permission manage_asrama
 * - 200 dengan permission (role pengelola_asrama)
 * - Data nyata (kamar/santri binaan/pelanggaran) tampil di halaman
 * - POST presensi & catat pelanggaran benar-benar menulis ke DB nyata via
 *   PresensiAsramaService/DisiplinService (sudah dipakai admin panel).
 */
class PengasuhanModulTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions',
            'santri', 'kelas', 'blok_asrama', 'kamar_asrama', 'penempatan_asrama',
            'presensi_asrama', 'jenis_pelanggaran', 'pelanggaran_santri', 'izin_santri', 'settings',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('f4asrama', 'Pesantren F4 Asrama');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);
        tenancy()->end();
    }

    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    public function test_akses_ditolak_403_tanpa_permission_manage_asrama(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Guru Biasa', 'email' => 'guru-nonasrama@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('guru');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/pengasuhan", $this->headers($host));

        $response->assertStatus(403);
    }

    public function test_akses_diterima_200_dengan_role_pengelola_asrama(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Musyrif Asrama', 'email' => 'musyrif@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('pengelola_asrama');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/pengasuhan", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Pengasuhan/Beranda'));
    }

    public function test_beranda_menampilkan_data_kamar_nyata(): void
    {
        tenancy()->initialize($this->tenant);

        $blok = BlokAsrama::create([
            'tenant_id' => $this->tenant->id, 'nama_blok' => 'Blok Al-Faruq', 'gender' => 'putra',
        ]);
        $kamar = KamarAsrama::create([
            'tenant_id' => $this->tenant->id, 'blok_id' => $blok->id, 'nomor_kamar' => '101',
            'kapasitas' => 10, 'tipe' => 'regular', 'biaya_per_bulan' => 0, 'is_active' => true,
        ]);
        $santri = Santri::create([
            'tenant_id' => $this->tenant->id, 'nis' => '2026101', 'nama' => 'Santri Uji Asrama',
            'tanggal_lahir' => '2011-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);
        PenempatanAsrama::create([
            'tenant_id' => $this->tenant->id, 'santri_id' => $santri->id, 'kamar_id' => $kamar->id,
            'tanggal_masuk' => now()->toDateString(), 'status' => 'aktif', 'is_current' => true,
        ]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Musyrif Kamar', 'email' => 'musyrif-kamar@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('pengelola_asrama');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/pengasuhan", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Pengasuhan/Beranda')
            ->where('ringkasan.santri_binaan', 1)
            ->where('kamar.0.nama', 'Kamar 101')
        );
    }

    public function test_post_presensi_insert_ke_database(): void
    {
        tenancy()->initialize($this->tenant);

        $blok = BlokAsrama::create([
            'tenant_id' => $this->tenant->id, 'nama_blok' => 'Blok Az-Zahra', 'gender' => 'putri',
        ]);
        $kamar = KamarAsrama::create([
            'tenant_id' => $this->tenant->id, 'blok_id' => $blok->id, 'nomor_kamar' => '201',
            'kapasitas' => 10, 'tipe' => 'regular', 'biaya_per_bulan' => 0, 'is_active' => true,
        ]);
        $santri = Santri::create([
            'tenant_id' => $this->tenant->id, 'nis' => '2026201', 'nama' => 'Santri Presensi',
            'tanggal_lahir' => '2011-01-01', 'jenis_kelamin' => 'P', 'status' => 'aktif',
        ]);
        PenempatanAsrama::create([
            'tenant_id' => $this->tenant->id, 'santri_id' => $santri->id, 'kamar_id' => $kamar->id,
            'tanggal_masuk' => now()->toDateString(), 'status' => 'aktif', 'is_current' => true,
        ]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Musyrif Presensi', 'email' => 'musyrif-presensi@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('pengelola_asrama');

        $host = $this->tenant->id.'.localhost';
        $kamarId = $kamar->id;
        $santriId = $santri->id;

        $this->assertDatabaseCount('presensi_asrama', 0, 'tenant');

        tenancy()->end();

        $response = $this->actingAs($user)->post("http://{$host}/pengasuhan/presensi", [
            'kamar_id' => $kamarId,
            'slot' => 'subuh',
            'santri' => [
                ['santri_id' => $santriId, 'status' => 'hadir'],
            ],
        ], $this->headers($host));

        $response->assertRedirect();

        tenancy()->initialize($this->tenant);
        $this->assertSame(1, PresensiAsrama::count());
        $presensi = PresensiAsrama::first();
        $this->assertSame($santriId, $presensi->santri_id);
        $this->assertSame('hadir', $presensi->status);
        tenancy()->end();
    }

    public function test_post_catat_pelanggaran_insert_ke_database(): void
    {
        tenancy()->initialize($this->tenant);

        $santri = Santri::create([
            'tenant_id' => $this->tenant->id, 'nis' => '2026301', 'nama' => 'Santri Disiplin',
            'tanggal_lahir' => '2011-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);
        $jenis = JenisPelanggaran::create([
            'tenant_id' => $this->tenant->id, 'nama' => 'Terlambat shalat berjamaah',
            'kategori' => 'ringan', 'poin' => 10, 'aktif' => true,
        ]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Musyrif Disiplin', 'email' => 'musyrif-disiplin@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('pengelola_asrama');

        $host = $this->tenant->id.'.localhost';
        $santriId = $santri->id;
        $jenisId = $jenis->id;

        $this->assertDatabaseCount('pelanggaran_santri', 0, 'tenant');

        tenancy()->end();

        $response = $this->actingAs($user)->post("http://{$host}/pengasuhan/disiplin/catat", [
            'santri_id' => $santriId,
            'jenis_pelanggaran_id' => $jenisId,
            'kronologi' => 'Tidak mengikuti shalat Subuh berjamaah tanpa keterangan.',
        ], $this->headers($host));

        $response->assertRedirect(route('tenant.pengasuhan.disiplin'));

        tenancy()->initialize($this->tenant);
        $this->assertSame(1, PelanggaranSantri::count());
        $pelanggaran = PelanggaranSantri::first();
        $this->assertSame($santriId, $pelanggaran->santri_id);
        $this->assertSame('dicatat', $pelanggaran->status);
        tenancy()->end();
    }
}
