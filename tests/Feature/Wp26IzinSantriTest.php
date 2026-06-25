<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Kegiatan\Models\IzinSantri;
use Modules\Kegiatan\Services\IzinSantriService;
use Modules\Kesantrian\Models\Santri;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class Wp26IzinSantriTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private IzinSantriService $service;

    private Santri $santri;

    private User $wali;

    private User $musyrif;

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
        $tenant = $this->createRuntimeTenant('wp26', 'Pesantren WP26');
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

        $this->santri = Santri::create([
            'tenant_id' => tenant('id'),
            'nama' => 'Ahmad Santri',
            'nis' => '2024001',
            'tanggal_lahir' => '2010-01-15',
            'jenis_kelamin' => 'L',
        ]);

        // Set default settings (store integer directly)
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

    /**
     * Test: ajukan izin dengan data valid → status diajukan
     */
    public function test_ajukan_izin_valid(): void
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
            'client_ref' => 'test-ref-1',
        ]);

        $this->assertDatabaseHas('izin_santri', [
            'id' => $izin->id,
            'santri_id' => $this->santri->id,
            'jenis_izin' => 'pulang',
            'status' => 'diajukan',
            'client_ref' => 'test-ref-1',
        ]);

        $this->assertEquals('diajukan', $izin->status);
    }

    /**
     * Test: ajukan izin 2x dengan client_ref sama → return existing (idempoten)
     */
    public function test_ajukan_izin_idempoten_client_ref(): void
    {
        $this->boot();

        $tglMulai = now()->addDays(5)->format('Y-m-d');
        $tglSelesai = now()->addDays(7)->format('Y-m-d');
        $ref = 'idempoten-test-1';

        $izin1 = $this->service->ajukan([
            'santri_id' => $this->santri->id,
            'jenis_izin' => 'pulang',
            'tanggal_mulai' => $tglMulai,
            'tanggal_selesai' => $tglSelesai,
            'alasan' => 'Pulang ke rumah',
            'client_ref' => $ref,
        ]);

        $izin2 = $this->service->ajukan([
            'santri_id' => $this->santri->id,
            'jenis_izin' => 'pulang',
            'tanggal_mulai' => $tglMulai,
            'tanggal_selesai' => $tglSelesai,
            'alasan' => 'Pulang ke rumah',
            'client_ref' => $ref,
        ]);

        $this->assertEquals($izin1->id, $izin2->id);
        $this->assertCount(1, IzinSantri::where('client_ref', $ref)->get());
    }

    /**
     * Test: ajukan izin H < minimum → ditolak
     */
    public function test_ajukan_izin_h_kurang_dari_minimum(): void
    {
        $this->boot();

        $tglMulai = now()->addDay()->format('Y-m-d');  // H-1, minimum H-3
        $tglSelesai = now()->addDays(2)->format('Y-m-d');

        $this->expectExceptionMessage('Izin harus diajukan minimal H-3');

        $this->service->ajukan([
            'santri_id' => $this->santri->id,
            'jenis_izin' => 'pulang',
            'tanggal_mulai' => $tglMulai,
            'tanggal_selesai' => $tglSelesai,
            'alasan' => 'Terlalu dekat',
        ]);
    }

    /**
     * Test: ajukan izin dengan tanggal overlap → ditolak
     */
    public function test_ajukan_izin_overlap_ditolak(): void
    {
        $this->boot();

        $tglMulai1 = now()->addDays(5)->format('Y-m-d');
        $tglSelesai1 = now()->addDays(7)->format('Y-m-d');

        $izin1 = $this->service->ajukan([
            'santri_id' => $this->santri->id,
            'jenis_izin' => 'pulang',
            'tanggal_mulai' => $tglMulai1,
            'tanggal_selesai' => $tglSelesai1,
            'alasan' => 'Izin pertama',
        ]);

        $this->assertEquals('diajukan', $izin1->status);

        $tglMulai2 = now()->addDays(6)->format('Y-m-d');  // Overlap dengan izin1
        $tglSelesai2 = now()->addDays(8)->format('Y-m-d');

        $this->expectExceptionMessage('tumpang-tindih');

        $this->service->ajukan([
            'santri_id' => $this->santri->id,
            'jenis_izin' => 'sakit',
            'tanggal_mulai' => $tglMulai2,
            'tanggal_selesai' => $tglSelesai2,
            'alasan' => 'Izin kedua',
        ]);
    }

    /**
     * Test: kuota maks_izin_bulan habis → ajukan ditolak
     */
    public function test_ajukan_izin_kuota_habis(): void
    {
        $this->boot();

        $hariIni = now();
        $bulan = $hariIni->format('Y-m');

        Setting::where('key', 'kesantrian.maks_izin_bulan')->update([
            'value' => 2,
        ]);

        // Ajukan izin pertama
        $tglMulai1 = $hariIni->copy()->addDays(5)->format('Y-m-d');
        $tglSelesai1 = $hariIni->copy()->addDays(6)->format('Y-m-d');

        $izin1 = $this->service->ajukan([
            'santri_id' => $this->santri->id,
            'jenis_izin' => 'pulang',
            'tanggal_mulai' => $tglMulai1,
            'tanggal_selesai' => $tglSelesai1,
            'alasan' => 'Izin ke-1',
        ]);

        $this->assertEquals('diajukan', $izin1->status);

        // Ajukan izin kedua
        $tglMulai2 = $hariIni->copy()->addDays(10)->format('Y-m-d');
        $tglSelesai2 = $hariIni->copy()->addDays(11)->format('Y-m-d');

        $izin2 = $this->service->ajukan([
            'santri_id' => $this->santri->id,
            'jenis_izin' => 'sakit',
            'tanggal_mulai' => $tglMulai2,
            'tanggal_selesai' => $tglSelesai2,
            'alasan' => 'Izin ke-2',
        ]);

        $this->assertEquals('diajukan', $izin2->status);

        // Ajukan izin ketiga → kuota habis
        $tglMulai3 = $hariIni->copy()->addDays(15)->format('Y-m-d');
        $tglSelesai3 = $hariIni->copy()->addDays(16)->format('Y-m-d');

        $this->expectExceptionMessage('Kuota izin bulan ini (2) sudah habis');

        $this->service->ajukan([
            'santri_id' => $this->santri->id,
            'jenis_izin' => 'keluar_kota',
            'tanggal_mulai' => $tglMulai3,
            'tanggal_selesai' => $tglSelesai3,
            'alasan' => 'Izin ke-3',
        ]);
    }

    /**
     * Test: setujui izin diajukan → status berubah disetujui
     */
    public function test_setujui_izin(): void
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

        $this->assertEquals('diajukan', $izin->status);

        $izinSetujui = $this->service->setujui($izin, $this->musyrif);

        $this->assertEquals('disetujui', $izinSetujui->status);
        $this->assertEquals($this->musyrif->id, $izinSetujui->disetujui_oleh);
    }

    /**
     * Test: tolak izin dengan catatan
     */
    public function test_tolak_izin(): void
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

        $izinTolak = $this->service->tolak($izin, 'Ada kegiatan rutin', $this->musyrif);

        $this->assertEquals('ditolak', $izinTolak->status);
        $this->assertEquals('Ada kegiatan rutin', $izinTolak->catatan_pengurus);
    }

    /**
     * Test: tandai kembali izin yang disetujui
     */
    public function test_tandai_kembali(): void
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

        $izinKembali = $this->service->tandaiKembali($izin);

        $this->assertTrue($izinKembali->sudah_kembali);
        $this->assertNotNull($izinKembali->tanggal_kembali_aktual);
    }

    /**
     * Test: policy 2 tenant beda → setting terpisah
     */
    public function test_multiple_tenants_independent_settings(): void
    {
        // Tenant 1
        $tenant1 = $this->createRuntimeTenant('wp26-t1', 'Pesantren 1');
        tenancy()->initialize($tenant1);
        $this->seedTenantRow($tenant1);

        Setting::create([
            'tenant_id' => tenant('id'),
            'key' => 'kesantrian.maks_izin_bulan',
            'value' => 1,
        ]);

        Setting::create([
            'tenant_id' => tenant('id'),
            'key' => 'kesantrian.approval_h_minimum',
            'value' => 2,
        ]);

        $val1 = Setting::getTenantValue('kesantrian.maks_izin_bulan', 2);
        $this->assertEquals(1, $val1);

        // Tenant 2
        $tenant2 = $this->createRuntimeTenant('wp26-t2', 'Pesantren 2');
        tenancy()->initialize($tenant2);
        $this->seedTenantRow($tenant2);

        Setting::create([
            'tenant_id' => tenant('id'),
            'key' => 'kesantrian.maks_izin_bulan',
            'value' => 5,
        ]);

        $val2 = Setting::getTenantValue('kesantrian.maks_izin_bulan', 2);
        $this->assertEquals(5, $val2);
    }
}
