<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Kegiatan\Http\Controllers\PwaGuruIzinController;
use Modules\Kegiatan\Models\IzinSantri;
use Modules\Kegiatan\Services\IzinSantriService;
use Modules\Kesantrian\Models\Santri;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class Wp26PwaApiTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private Santri $santri;

    private User $wali;

    private User $musyrif;

    private IzinSantriService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurePpdbTestingDatabase();
        if (! Schema::connection('pgsql')->hasTable('izin_santri')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    private function boot(): void
    {
        $tenant = $this->createRuntimeTenant('wp26api', 'Pesantren WP26 API');
        tenancy()->initialize($tenant);
        $this->seedTenantRow($tenant);

        (new RolesAndPermissionsSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->service = app(IzinSantriService::class);

        $this->wali = User::withoutGlobalScopes()->create([
            'name' => 'Wali Santri',
            'email' => 'wali@'.tenant('id').'.localhost',
            'password' => 'password',
            'tenant_id' => tenant('id'),
        ]);

        $this->musyrif = User::withoutGlobalScopes()->create([
            'name' => 'Musyrif',
            'email' => 'musyrif@'.tenant('id').'.localhost',
            'password' => 'password',
            'tenant_id' => tenant('id'),
        ]);

        // Grant permission ke musyrif
        if (Permission::where('name', 'akses_pwa_guru')->exists()) {
            $this->musyrif->givePermissionTo('akses_pwa_guru');
        } else {
            $perm = Permission::create(['name' => 'akses_pwa_guru', 'guard_name' => 'web']);
            $this->musyrif->givePermissionTo($perm);
        }

        $this->santri = Santri::create([
            'tenant_id' => tenant('id'),
            'nama' => 'Ahmad Santri',
            'nis' => '2024001',
            'tanggal_lahir' => '2010-01-15',
            'jenis_kelamin' => 'L',
        ]);

        // Set default settings
        Setting::create([
            'tenant_id' => tenant('id'),
            'key' => 'kesantrian.maks_izin_bulan',
            'value' => 2,
        ]);

        Setting::create([
            'tenant_id' => tenant('id'),
            'key' => 'kesantrian.approval_h_minimum',
            'value' => 3,
        ]);
    }

    private function req(string $method, array $data, ?User $user): Request
    {
        $request = Request::create('/x', $method, $data);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    /**
     * Test: Service ajukan izin valid
     */
    public function test_pwa_wali_izin_ajukan(): void
    {
        $this->boot();

        $tglMulai = now()->addDays(5)->format('Y-m-d');
        $tglSelesai = now()->addDays(7)->format('Y-m-d');

        $izin = $this->service->ajukan([
            'santri_id' => $this->santri->id,
            'jenis_izin' => 'pulang',
            'tanggal_mulai' => $tglMulai,
            'tanggal_selesai' => $tglSelesai,
            'alasan' => 'Pulang ke rumah',
            'client_ref' => 'pwa-test-1',
        ]);

        $this->assertEquals('diajukan', $izin->status);
        $this->assertNotNull($izin->id);
    }

    /**
     * Test: Service list izin
     */
    public function test_pwa_wali_izin_list(): void
    {
        $this->boot();

        $tglMulai = now()->addDays(5)->format('Y-m-d');
        $tglSelesai = now()->addDays(7)->format('Y-m-d');

        $izin = $this->service->ajukan([
            'santri_id' => $this->santri->id,
            'jenis_izin' => 'pulang',
            'tanggal_mulai' => $tglMulai,
            'tanggal_selesai' => $tglSelesai,
            'alasan' => 'Pulang ke rumah',
        ]);

        $list = IzinSantri::where('santri_id', $this->santri->id)
            ->whereIn('status', ['diajukan', 'disetujui', 'ditolak'])
            ->orderByDesc('created_at')
            ->get();

        $this->assertCount(1, $list);
        $this->assertEquals($izin->id, $list[0]->id);
        $this->assertEquals('pulang', $list[0]->jenis_izin);
    }

    /**
     * Test: Service tandai kembali
     */
    public function test_pwa_wali_tandai_kembali(): void
    {
        $this->boot();

        $tglMulai = now()->addDays(5)->format('Y-m-d');
        $tglSelesai = now()->addDays(7)->format('Y-m-d');

        $izin = $this->service->ajukan([
            'santri_id' => $this->santri->id,
            'jenis_izin' => 'pulang',
            'tanggal_mulai' => $tglMulai,
            'tanggal_selesai' => $tglSelesai,
            'alasan' => 'Pulang ke rumah',
        ]);

        $izin = $this->service->setujui($izin, $this->musyrif);
        $izin = $this->service->tandaiKembali($izin);

        $this->assertTrue($izin->sudah_kembali);
    }

    /**
     * Test: GET /pwa/guru/izin/antrean
     */
    public function test_pwa_guru_antrean(): void
    {
        $this->boot();

        $tglMulai = now()->addDays(5)->format('Y-m-d');
        $tglSelesai = now()->addDays(7)->format('Y-m-d');

        $izin1 = $this->service->ajukan([
            'santri_id' => $this->santri->id,
            'jenis_izin' => 'pulang',
            'tanggal_mulai' => $tglMulai,
            'tanggal_selesai' => $tglSelesai,
            'alasan' => 'Izin 1',
        ]);

        $controller = new PwaGuruIzinController;
        $response = $controller->antrean(
            $this->req('GET', [], $this->musyrif)
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertEquals($izin1->id, $data[0]['id']);
        $this->assertEquals('pulang', $data[0]['jenis_izin']);
    }

    /**
     * Test: POST /pwa/guru/izin/{izin_id}/setujui
     */
    public function test_pwa_guru_setujui(): void
    {
        $this->boot();

        $tglMulai = now()->addDays(5)->format('Y-m-d');
        $tglSelesai = now()->addDays(7)->format('Y-m-d');

        $izin = $this->service->ajukan([
            'santri_id' => $this->santri->id,
            'jenis_izin' => 'pulang',
            'tanggal_mulai' => $tglMulai,
            'tanggal_selesai' => $tglSelesai,
            'alasan' => 'Pulang ke rumah',
        ]);

        $controller = new PwaGuruIzinController;
        $response = $controller->setujui(
            $this->req('POST', [], $this->musyrif),
            $izin->id,
            $this->service
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('disetujui', $data['status']);
        $this->assertEquals($this->musyrif->name, $data['disetujui_oleh']);
    }

    /**
     * Test: POST /pwa/guru/izin/{izin_id}/tolak
     */
    public function test_pwa_guru_tolak(): void
    {
        $this->boot();

        $tglMulai = now()->addDays(5)->format('Y-m-d');
        $tglSelesai = now()->addDays(7)->format('Y-m-d');

        $izin = $this->service->ajukan([
            'santri_id' => $this->santri->id,
            'jenis_izin' => 'pulang',
            'tanggal_mulai' => $tglMulai,
            'tanggal_selesai' => $tglSelesai,
            'alasan' => 'Pulang ke rumah',
        ]);

        $controller = new PwaGuruIzinController;
        $response = $controller->tolak(
            $this->req('POST', ['catatan' => 'Ada kegiatan penting'], $this->musyrif),
            $izin->id,
            $this->service
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('ditolak', $data['status']);

        $izin = $izin->refresh();
        $this->assertEquals('Ada kegiatan penting', $izin->catatan_pengurus);
    }
}
