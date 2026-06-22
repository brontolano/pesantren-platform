<?php

namespace Tests\Feature;

use Modules\Tahfidz\Filament\Resources\HafalanTahfidzResource;
use Modules\Tahfidz\Filament\Resources\PenilaianTahfidzResource;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\Kesantrian\Models\Santri;
use Modules\Tahfidz\Models\HafalanTahfidz;
use Modules\Tahfidz\Models\PenilaianTahfidz;
use Modules\Tahfidz\Services\TahfidzService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

class TahfidzServiceTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    private TahfidzService $service;
    private Tenant $tenantA;
    private Tenant $tenantB;
    private Santri $santriA;
    private Santri $santriB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePpdbTestingSchemaReady(['tahfidz_hafalan', 'tahfidz_penilaian', 'permissions']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (! Role::query()->where('name', 'admin_pesantren')->exists()) {
            $this->seed(RolesAndPermissionsSeeder::class);
        }

        $this->service = new TahfidzService();
        $suffix = Str::lower(Str::random(8));

        $this->tenantA = $this->createTestTenant("pesantren-a-{$suffix}", 'Pesantren A', "pesantren-a-{$suffix}.localhost");
        $this->tenantB = $this->createTestTenant("pesantren-b-{$suffix}", 'Pesantren B', "pesantren-b-{$suffix}.localhost");

        tenancy()->initialize($this->tenantA);
        $this->santriA = Santri::create([
            'tenant_id' => $this->tenantA->id,
            'nis' => 'TA' . strtoupper($suffix) . '01',
            'nama' => 'Ahmad Fulan',
            'tanggal_lahir' => '2012-01-01',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        $this->santriB = Santri::create([
            'tenant_id' => $this->tenantB->id,
            'nis' => 'TB' . strtoupper($suffix) . '01',
            'nama' => 'Budi Santoso',
            'tanggal_lahir' => '2012-02-02',
            'jenis_kelamin' => 'L',
            'status' => 'aktif',
        ]);
        tenancy()->end();
    }

    public function test_create_hafalan_untuk_santri(): void
    {
        tenancy()->initialize($this->tenantA);

        $hafalan = $this->service->createHafalan($this->santriA, [
            'surah'          => 'Al-Baqarah',
            'ayat_awal'      => 1,
            'ayat_akhir'     => 10,
            'tanggal_target' => now()->addDays(30)->toDateString(),
            'status'         => 'berjalan',
        ]);

        $this->assertInstanceOf(HafalanTahfidz::class, $hafalan);
        $this->assertEquals('Al-Baqarah', $hafalan->surah);
        $this->assertEquals('berjalan', $hafalan->status);
        $this->assertEquals($this->tenantA->id, $hafalan->tenant_id);

        tenancy()->end();
    }

    public function test_selesaikan_hafalan(): void
    {
        tenancy()->initialize($this->tenantA);

        $hafalan = $this->service->createHafalan($this->santriA, [
            'surah'  => 'Al-Fatihah',
            'status' => 'berjalan',
        ]);

        $selesai = $this->service->selesaikanHafalan($hafalan);

        $this->assertEquals('selesai', $selesai->status);
        $this->assertNotNull($selesai->tanggal_selesai);

        tenancy()->end();
    }

    public function test_add_penilaian_hafalan(): void
    {
        tenancy()->initialize($this->tenantA);

        $hafalan = $this->service->createHafalan($this->santriA, [
            'surah'  => 'Al-Ikhlas',
            'status' => 'berjalan',
        ]);

        $penilaian = $this->service->addPenilaian($hafalan, [
            'jenis'  => 'murajaah',
            'nilai'  => 90.5,
            'tanggal'=> now()->toDateString(),
        ]);

        $this->assertInstanceOf(PenilaianTahfidz::class, $penilaian);
        $this->assertEquals(90.5, (float) $penilaian->nilai);
        $this->assertEquals('murajaah', $penilaian->jenis);

        tenancy()->end();
    }

    public function test_rata_rata_nilai_dihitung_benar(): void
    {
        tenancy()->initialize($this->tenantA);

        $hafalan = $this->service->createHafalan($this->santriA, ['surah' => 'An-Nas', 'status' => 'berjalan']);

        $this->service->addPenilaian($hafalan, ['jenis' => 'murajaah', 'nilai' => 80, 'tanggal' => now()->toDateString()]);
        $this->service->addPenilaian($hafalan, ['jenis' => 'ziyadah',  'nilai' => 90, 'tanggal' => now()->toDateString()]);

        $avg = $this->service->getRataRataNilai($this->santriA);

        $this->assertEquals(85.0, $avg);

        tenancy()->end();
    }

    public function test_hafalan_tenant_a_tidak_terlihat_di_tenant_b(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->service->createHafalan($this->santriA, ['surah' => 'Al-Falaq', 'status' => 'berjalan']);
        tenancy()->end();

        tenancy()->initialize($this->tenantB);
        $hafalanB = HafalanTahfidz::all();
        $this->assertCount(0, $hafalanB);
        tenancy()->end();
    }

    public function test_ringkasan_hafalan_akurat(): void
    {
        tenancy()->initialize($this->tenantA);

        $h1 = $this->service->createHafalan($this->santriA, ['surah' => 'Al-Fatihah', 'status' => 'berjalan']);
        $this->service->selesaikanHafalan($h1);
        $this->service->createHafalan($this->santriA, ['surah' => 'Al-Baqarah', 'status' => 'direncanakan']);

        $ringkasan = $this->service->getRingkasanHafalan($this->santriA);

        $this->assertEquals(2, $ringkasan['total']);
        $this->assertEquals(1, $ringkasan['selesai']);
        $this->assertEquals(1, $ringkasan['direncanakan']);

        tenancy()->end();
    }

    public function test_resource_dan_route_tahfidz_terdaftar_untuk_admin_pesantren(): void
    {
        tenancy()->initialize($this->tenantA);

        $admin = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Admin Tahfidz',
            'email' => 'admin-tahfidz-' . Str::lower(Str::random(6)) . '@test.local',
            'password' => 'password',
        ]);
        $admin->assignRole('admin_pesantren');

        $this->actingAs($admin);

        $this->assertTrue(HafalanTahfidzResource::canAccess());
        $this->assertTrue(PenilaianTahfidzResource::canAccess());
        $this->assertTrue(Route::has('filament.admin.resources.tahfidz.hafalan-tahfidzs.index'));
        $this->assertTrue(Route::has('filament.admin.resources.tahfidz.penilaian-tahfidzs.index'));

        tenancy()->end();
    }
}
