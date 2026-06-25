<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Kesantrian\Models\Kelas;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Cetak (print-friendly) 4 dokumen: jadwal per kelas, daftar nilai (legger),
 * penempatan asrama per blok, stok obat. Memastikan rute + controller + view
 * merender < 400 untuk user berizin (data kosong pun aman — view @empty).
 */
class CetakControllerTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions',
            'kelas', 'jadwal_pelajaran', 'pelajaran', 'periode_akademik', 'nilai', 'santri',
            'blok_asrama', 'kamar_asrama', 'penempatan_asrama', 'obat',
            'halaqah', 'tahfidz_hafalan', 'employee',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    public function test_empat_cetakan_merender_untuk_user_berizin(): void
    {
        $tenant = $this->createRuntimeTenant('cetak', 'Pesantren Cetak');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Admin', 'email' => 'a@cetak.test', 'password' => 'x12345678',
        ]);
        $user->assignRole('super_admin');
        Auth::login($user);

        $kelas = Kelas::create(['tenant_id' => $tenant->id, 'nama_kelas' => 'VII-A', 'tingkat' => '7']);

        $host = $tenant->id.'.localhost';
        $kernel = app(Kernel::class);
        $paths = [
            'admin/cetak/stok-obat',
            'admin/cetak/penempatan-blok',
            'admin/cetak/jadwal-kelas?kelas_id='.$kelas->id,
            'admin/cetak/nilai-legger?kelas_id='.$kelas->id,
            'admin/cetak/halaqah',
            'admin/cetak/hafalan',
            'admin/cetak/pegawai',
        ];

        foreach ($paths as $path) {
            $req = Request::create("http://{$host}/{$path}", 'GET');
            $req->headers->set('Host', $host);
            $status = $kernel->handle($req)->getStatusCode();
            $this->assertLessThan(400, $status, "Cetak /{$path} mengembalikan {$status}.");
        }

        tenancy()->end();
    }
}
