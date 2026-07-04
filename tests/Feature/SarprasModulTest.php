<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Modules\Pembiayaan\Models\AreaPiket;
use Modules\Pembiayaan\Models\CeklisPiket;
use Modules\Pembiayaan\Models\LaporanKerusakan;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * F4 — Portal PWA Sarpras (fitur baru, role `pengelola_sarpras` sebelumnya
 * tak punya PWA sama sekali).
 * - 403 tanpa permission manage_sarpras
 * - 200 dengan permission (role pengelola_sarpras)
 * - Ubah status laporan & ceklis piket benar-benar menulis ke DB nyata.
 */
class SarprasModulTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Tenant $tenant;

    private LaporanKerusakan $laporan;

    private AreaPiket $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions',
            'laporan_kerusakan', 'area_piket', 'ceklis_piket',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);

        $this->tenant = $this->createRuntimeTenant('f4sarpras', 'Pesantren F4 Sarpras');
        tenancy()->initialize($this->tenant);
        $this->seedTenantRow($this->tenant);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        $this->laporan = LaporanKerusakan::create([
            'tenant_id' => $this->tenant->id,
            'lokasi' => 'Kamar Mandi Asrama A',
            'deskripsi' => 'Keran bocor',
            'status' => 'masuk',
        ]);

        $this->area = AreaPiket::create([
            'tenant_id' => $this->tenant->id,
            'nama' => 'Area Dapur',
            'kode_qr' => 'AREA-DAPUR-01',
            'aktif' => true,
        ]);

        tenancy()->end();
    }

    private function headers(string $host): array
    {
        return ['HTTP_HOST' => $host, 'SERVER_NAME' => $host];
    }

    public function test_akses_ditolak_403_tanpa_permission_manage_sarpras(): void
    {
        tenancy()->initialize($this->tenant);
        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Guru Biasa', 'email' => 'guru-sarpras@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('guru');
        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/sarpras", $this->headers($host));

        $response->assertForbidden();
    }

    public function test_akses_diterima_200_dengan_role_pengelola_sarpras(): void
    {
        tenancy()->initialize($this->tenant);
        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Petugas Sarpras', 'email' => 'sarpras@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('pengelola_sarpras');
        $host = $this->tenant->id.'.localhost';
        tenancy()->end();

        $response = $this->actingAs($user)->get("http://{$host}/sarpras", $this->headers($host));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Sarpras/Beranda'));
    }

    public function test_post_ubah_status_laporan_kerusakan_ke_database_nyata(): void
    {
        tenancy()->initialize($this->tenant);
        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Petugas Sarpras Ubah', 'email' => 'sarpras-ubah@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('pengelola_sarpras');
        $host = $this->tenant->id.'.localhost';
        $laporanId = $this->laporan->id;
        tenancy()->end();

        $response = $this->actingAs($user)->post("http://{$host}/sarpras/kerusakan/{$laporanId}/status", [
            'status' => 'dikerjakan',
            'catatan' => 'Sedang diperbaiki tukang',
        ], $this->headers($host));

        $response->assertRedirect(route('tenant.sarpras.kerusakan'));

        tenancy()->initialize($this->tenant);

        $this->assertSame('dikerjakan', LaporanKerusakan::find($laporanId)->status);

        tenancy()->end();
    }

    public function test_post_scan_piket_insert_ceklis_ke_database_nyata(): void
    {
        tenancy()->initialize($this->tenant);
        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Petugas Sarpras Piket', 'email' => 'sarpras-piket@f4.test', 'password' => bcrypt('password'),
        ]);
        $user->assignRole('pengelola_sarpras');
        $host = $this->tenant->id.'.localhost';
        $kodeQr = $this->area->kode_qr;

        $this->assertDatabaseCount('ceklis_piket', 0, 'tenant');

        tenancy()->end();

        $response = $this->actingAs($user)->post("http://{$host}/sarpras/piket/scan", [
            'kode_qr' => $kodeQr,
            'status' => 'bersih',
        ], $this->headers($host));

        $response->assertRedirect(route('tenant.sarpras.piket'));

        tenancy()->initialize($this->tenant);

        $this->assertSame(1, CeklisPiket::count());
        $ceklis = CeklisPiket::first();
        $this->assertSame($this->area->id, $ceklis->area_id);
        $this->assertSame('bersih', $ceklis->status);

        tenancy()->end();
    }
}
