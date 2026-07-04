<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Modules\Kegiatan\Models\IzinSantri;
use Modules\Kegiatan\Models\KunjunganWali;
use Modules\Kegiatan\Models\LogGerbang;
use Modules\Kesantrian\Models\Santri;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F4 — Portal PWA Pos Keamanan (menggantikan KeamananDemoController).
 * - 403 tanpa permission view_kegiatan
 * - 200 dengan permission (role tu, yang sudah punya view_kegiatan)
 * - Data beranda/gerbang/tamu/log benar-benar dari DB nyata
 *   (Modules\Kegiatan\LogGerbang/KunjunganWali/IzinSantri), bukan dummy.
 * - POST catat gerbang benar-benar insert ke tabel log_gerbang via
 *   GerbangService (sama persis logika PortalGuruGerbangController).
 */
class KeamananModulTest extends TestCase
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
            'santri', 'kelas', 'izin_santri', 'log_gerbang', 'kunjungan_wali', 'pengajuan_jenguk',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('f4keamanan', 'Pesantren F4 Keamanan');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        $this->santri = Santri::create([
            'tenant_id' => $this->tenant->id, 'nis' => 'F4KM001', 'nama' => 'Santri Keamanan Uji',
            'tanggal_lahir' => '2012-01-01', 'jenis_kelamin' => 'L', 'status' => 'aktif',
        ]);

        tenancy()->end();
    }

    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    public function test_akses_ditolak_403_tanpa_permission_view_kegiatan(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Guru Biasa', 'email' => 'guru-nonkeamanan@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('guru');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/keamanan", $this->headers($host));

        $response->assertStatus(403);
    }

    public function test_akses_diterima_200_dengan_permission_view_kegiatan(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Petugas Keamanan', 'email' => 'keamanan@f4.test', 'password' => bcrypt('password'),
        ]);
        // Tidak ada role "keamanan" — role tu SUDAH punya view_kegiatan (scope docblock).
        $user->assignRole('tu');

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/keamanan", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Keamanan/Beranda'));
    }

    public function test_beranda_menghitung_data_nyata_bukan_dummy(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Petugas Keamanan Dua', 'email' => 'keamanan2@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('tu');

        IzinSantri::create([
            'tenant_id' => $this->tenant->id, 'santri_id' => $this->santri->id, 'jenis_izin' => 'pulang',
            'tanggal_mulai' => now()->toDateString(), 'tanggal_selesai' => now()->toDateString(),
            'alasan' => 'Uji coba', 'status' => 'disetujui', 'sudah_kembali' => false,
        ]);

        LogGerbang::create([
            'tenant_id' => $this->tenant->id, 'santri_id' => $this->santri->id, 'arah' => 'keluar',
            'waktu' => now(), 'petugas_id' => $user->id, 'is_tanpa_izin' => false,
        ]);

        KunjunganWali::create([
            'tenant_id' => $this->tenant->id, 'santri_id' => $this->santri->id,
            'nama_pengunjung' => 'Bpk Uji', 'hubungan' => 'ayah',
            'tanggal' => now()->toDateString(), 'jam_datang' => '10:00', 'status' => 'hadir',
        ]);

        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/keamanan", $this->headers($host));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Keamanan/Beranda')
            ->where('stats.0.value', 1) // di_luar
            ->where('stats.1.value', 1) // tamu hadir
            ->where('stats.2.value', 1) // izin aktif
        );
    }

    public function test_catat_gerbang_insert_nyata_ke_log_gerbang(): void
    {
        tenancy()->initialize($this->tenant);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Petugas Keamanan Tiga', 'email' => 'keamanan3@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('tu');

        $host = $this->tenant->id.'.localhost';
        $santriId = $this->santri->id;
        tenancy()->end();

        $response = $this->actingAs($user)->post("http://{$host}/keamanan/gerbang/catat", [
            'santri_id' => $santriId,
            'arah' => 'keluar',
            'catatan' => 'Uji coba',
        ], $this->headers($host));

        $response->assertSessionHasNoErrors();

        tenancy()->initialize($this->tenant);
        $this->assertSame(1, LogGerbang::where('santri_id', $santriId)->where('arah', 'keluar')->count());
        $log = LogGerbang::where('santri_id', $santriId)->first();
        $this->assertTrue((bool) $log->is_tanpa_izin, 'Tanpa izin aktif → log harus is_tanpa_izin true.');
        tenancy()->end();
    }
}
