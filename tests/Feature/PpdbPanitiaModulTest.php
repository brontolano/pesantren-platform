<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\CalonSantriDokumen;
use Modules\PPDB\Models\HasilUjian;
use Modules\PPDB\Models\JadwalUjian;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F4 — Portal PWA PPDB Panitia (menggantikan PpdbPanitiaDemoController).
 * - 403 tanpa permission manage_ppdb
 * - 200 dengan permission (role panitia_ppdb)
 * - Verifikasi dokumen & keputusan seleksi benar-benar menulis ke DB nyata
 *   (via CalonSantriDokumen dan PpdbAdmissionDecisionService — sudah ada).
 */
class PpdbPanitiaModulTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    private CalonSantri $calon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions',
            'calon_santri', 'calon_santri_dokumen', 'hasil_ujian', 'jadwal_ujian',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('f4ppdb', 'Pesantren F4 PPDB');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        $this->calon = CalonSantri::create([
            'tenant_id' => $this->tenant->id,
            'nomor_pendaftaran' => 'PPDB-TEST-001',
            'nama' => 'Calon Uji PPDB',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'asal_sekolah' => 'SMP Uji',
            'program_diminati' => 'Tahfizh',
            'gelombang' => 1,
            'status_pendaftaran' => 'menunggu_verifikasi',
        ]);

        HasilUjian::create([
            'tenant_id' => $this->tenant->id,
            'calon_santri_id' => $this->calon->id,
            'kategori_ujian' => 'Tes Tulis',
            'nilai' => 85,
        ]);

        tenancy()->end();
    }

    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    public function test_akses_ditolak_403_tanpa_permission_manage_ppdb(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Guru Biasa', 'email' => 'guru-nonppdb@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('guru');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/ppdb-panitia", $this->headers($host));

        $response->assertStatus(403);
    }

    public function test_akses_diterima_200_dengan_role_panitia_ppdb(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Panitia PPDB', 'email' => 'panitia@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('panitia_ppdb');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/ppdb-panitia", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('PpdbPanitia/Beranda'));
    }

    public function test_halaman_pendaftar_menampilkan_data_nyata(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Panitia Pendaftar', 'email' => 'panitia-p@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('panitia_ppdb');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/ppdb-panitia/pendaftar", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('PpdbPanitia/Pendaftar')
            ->where('pendaftar.0.nama', 'Calon Uji PPDB')
        );
    }

    public function test_verifikasi_dokumen_update_status_ke_database(): void
    {
        tenancy()->initialize($this->tenant);

        $dokumen = CalonSantriDokumen::create([
            'tenant_id' => $this->tenant->id,
            'calon_santri_id' => $this->calon->id,
            'jenis_dokumen' => 'akta_kelahiran',
            'file_url' => 'dummy/akta.pdf',
            'status' => CalonSantriDokumen::STATUS_PENDING,
        ]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Panitia Verif', 'email' => 'panitia-v@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('panitia_ppdb');

        $host = $this->tenant->id.'.localhost';
        $dokumenId = $dokumen->id;
        tenancy()->end();

        $response = $this->actingAs($user)->post("http://{$host}/ppdb-panitia/verifikasi/dokumen/{$dokumenId}", [
            'status' => 'verified',
            'catatan_verifikasi' => 'Sesuai',
        ], $this->headers($host));

        $response->assertRedirect();

        tenancy()->initialize($this->tenant);
        $this->assertSame('verified', CalonSantriDokumen::find($dokumenId)->status);
        tenancy()->end();
    }

    public function test_keputusan_lulus_insert_status_dan_tanggal_keputusan(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Panitia Keputusan', 'email' => 'panitia-k@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('panitia_ppdb');

        $host = $this->tenant->id.'.localhost';
        $calonId = $this->calon->id;
        tenancy()->end();

        $response = $this->actingAs($user)->post("http://{$host}/ppdb-panitia/keputusan/{$calonId}", [
            'status_pendaftaran' => 'lulus',
            'catatan_keputusan' => 'Nilai memenuhi syarat',
        ], $this->headers($host));

        $response->assertRedirect(route('tenant.ppdb-panitia.hasil'));

        tenancy()->initialize($this->tenant);
        $fresh = CalonSantri::find($calonId);
        $this->assertSame('lulus', $fresh->status_pendaftaran);
        $this->assertNotNull($fresh->tanggal_keputusan);
        tenancy()->end();
    }

    public function test_catat_nilai_ujian_tersimpan_dan_idempoten_per_kategori(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Panitia Nilai', 'email' => 'panitia-nilai@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('panitia_ppdb');

        $host = $this->tenant->id.'.localhost';
        $calonId = $this->calon->id;
        tenancy()->end();

        $this->actingAs($user)->post("http://{$host}/ppdb-panitia/nilai-ujian/{$calonId}", [
            'kategori_ujian' => 'Wawancara', 'nilai' => 90,
        ], $this->headers($host))->assertRedirect();

        // Kirim ulang kategori sama dengan nilai berbeda — harus update, bukan duplikat.
        $this->actingAs($user)->post("http://{$host}/ppdb-panitia/nilai-ujian/{$calonId}", [
            'kategori_ujian' => 'Wawancara', 'nilai' => 95,
        ], $this->headers($host))->assertRedirect();

        tenancy()->initialize($this->tenant);
        $this->assertSame(1, HasilUjian::where('calon_santri_id', $calonId)->where('kategori_ujian', 'Wawancara')->count());
        $this->assertEquals(95, HasilUjian::where('calon_santri_id', $calonId)->where('kategori_ujian', 'Wawancara')->first()->nilai);
        tenancy()->end();
    }

    public function test_buat_jadwal_ujian_tersimpan(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Panitia Jadwal', 'email' => 'panitia-jadwal@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('panitia_ppdb');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->post("http://{$host}/ppdb-panitia/jadwal-ujian", [
            'kategori_ujian' => 'Tes Baca Quran', 'tanggal' => now()->addDays(7)->toDateString(),
            'waktu_mulai' => '08:00', 'ruang' => 'Aula',
        ], $this->headers($host));
        $response->assertRedirect();

        tenancy()->initialize($this->tenant);
        $this->assertSame(1, JadwalUjian::where('kategori_ujian', 'Tes Baca Quran')->count());
        tenancy()->end();
    }

    public function test_halaman_jadwal_ujian_200_dengan_role_panitia(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Panitia Lihat Jadwal', 'email' => 'panitia-lj@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('panitia_ppdb');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/ppdb-panitia/jadwal-ujian", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('PpdbPanitia/JadwalUjian'));
    }
}
